<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Database\Database;
use Prophecy\Argument;

class ImportQueueWorkerLoggingTest extends UnserializationTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('dblog', ['watchdog']);
  }

  /**
   * Tests logging within import queue worker.
   *
   * @throws \ReflectionException
   */
  public function testImporQueueWorkerLogging() {
    // Throw error while getting interest list.
    $error_msg = 'Some error from service.';
    $this->contentHubClient->getInterestsByWebhook(Argument::type('string'))->willThrow(new \Exception($error_msg));
    $this->runImportQueueWorker([self::CLIENT_UUID_1]);

    $this->assertLogMessage(
      'acquia_contenthub_subscriber',
      sprintf(
        'Following error occurred while we were trying to get the interest list: %s',
        $error_msg
      )
    );

    // Mimic entity deletion from publisher.
    $this->contentHubClient->getInterestsByWebhook(Argument::type('string'))->willReturn([]);
    $this->runImportQueueWorker([self::CLIENT_UUID_1]);

    $message = sprintf(
      'Skipped importing the following missing entities: %s. This occurs when entities are deleted at the Publisher before importing.',
      self::CLIENT_UUID_1
    );

    $this->assertLogMessage('acquia_contenthub_subscriber', $message);

    // Mimic if there are no match between the queued items and interest list.
    $this->contentHubClient->getInterestsByWebhook(Argument::type('string'))->willReturn([]);
    $this->runImportQueueWorker([]);

    $this->assertLogMessage('acquia_contenthub_subscriber', 'There are no matching entities in the queues and the site interest list.');

    // Successful addition to interest list.
    $cdf_document = $this->createCDFDocumentFromFixture('view_modes.json');
    $this->contentHubClient->getEntities([self::CLIENT_UUID_1 => self::CLIENT_UUID_1])->willReturn($cdf_document);

    $this->contentHubClient->getInterestsByWebhook(Argument::type('string'))->willReturn(['fefd7eda-4244-4fe4-b9b5-b15b89c61aa8']);
    $this->contentHubClient->addEntitiesToInterestList(Argument::any(), Argument::any())->willReturn();
    $this->runImportQueueWorker([self::CLIENT_UUID_1]);

    $this->assertLogMessage('acquia_contenthub_subscriber', 'The following imported entities have been added to the interest ');

    // Failed addition to interest list.
    $this->contentHubClient->getEntities([self::CLIENT_UUID_1 => self::CLIENT_UUID_1])->willReturn($cdf_document);

    $this->contentHubClient->getInterestsByWebhook(Argument::type('string'))->willReturn(['fefd7eda-4244-4fe4-b9b5-b15b89c61aa8']);
    $this->contentHubClient->addEntitiesToInterestList(Argument::any(), Argument::any())->willThrow(new \Exception('error'));
    $this->runImportQueueWorker([self::CLIENT_UUID_1]);

    $this->assertLogMessage('acquia_contenthub_subscriber', 'Error adding the following entities to the interest list for webhook');
  }

  /**
   * Run import queue worker processItem method.
   *
   * @param array $uuids
   *   UUIDs which will be passed for the queue worker.
   *
   * @throws \Exception
   */
  protected function runImportQueueWorker(array $uuids) {
    $item = new \stdClass();
    $item->uuids = implode(', ', $uuids);
    $this->contentHubImportQueueWorker->processItem($item);
  }

  /**
   * Verify a log entry was entered into watchdog table.
   *
   * @param string $type
   *   The channel to which this message belongs.
   * @param string $message
   *   The message to check in the log.
   */
  public function assertLogMessage(string $type, string $message) {
    $count = Database::getConnection()->select('watchdog', 'w')
      ->condition('type', $type)
      ->condition('message', '%' . $message . '%', 'LIKE')
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertTrue($count > 0, new FormattableMarkup('watchdog table contains @count rows for @message', ['@count' => $count, '@message' => new FormattableMarkup($message, [])]));
  }

}
