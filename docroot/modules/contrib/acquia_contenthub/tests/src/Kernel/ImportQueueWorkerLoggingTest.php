<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\Syndication\SyndicationStatus;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\WatchdogAssertsTrait;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * Tests logging for import queue.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ImportQueueWorkerLoggingTest extends UnserializationTest {

  use WatchdogAssertsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setUp();

    $this->installSchema('dblog', 'watchdog');
  }

  /**
   * Tests logging within import queue worker.
   *
   * @throws \ReflectionException
   */
  public function testImporQueueWorkerLogging() {
    // Throw error while getting interest list.
    $error_msg = 'Some error from service.';
    $this->contentHubClient->getInterestsByWebhookAndSiteRole(
      Argument::type('string'),
      Argument::type('string')
    )->willThrow(new \Exception($error_msg));
    $this->runImportQueueWorker([self::CLIENT_UUID_1]);

    $this->assertLogMessage(
      'acquia_contenthub_subscriber',
      sprintf(
        'Following error occurred while we were trying to get the interest list: %s',
        $error_msg
      )
    );

    // Mimic entity deletion from publisher.
    $this->contentHubClient->getInterestsByWebhookAndSiteRole(
      Argument::type('string'),
      Argument::type('string')
    )->willReturn([]);
    $this->runImportQueueWorker([self::CLIENT_UUID_1]);

    $message = sprintf(
      'Skipped importing the following missing entities: %s. This occurs when entities are deleted at the Publisher before importing.',
      self::CLIENT_UUID_1
    );

    $this->assertLogMessage('acquia_contenthub_subscriber', $message);

    // Mimic if there are no match between the queued items and interest list.
    $this->contentHubClient->getInterestsByWebhookAndSiteRole(
      Argument::type('string'),
      Argument::type('string')
    )->willReturn([]);
    $this->runImportQueueWorker([]);

    $this->assertLogMessage('acquia_contenthub_subscriber',
      'There are no matching entities in the queues and the site interest list.'
    );

    // Successful addition to interest list.
    $cdf_document = $this->createCdfDocumentFromFixtureFile('view_modes.json');
    $this->contentHubClient->getEntities([self::CLIENT_UUID_1 => self::CLIENT_UUID_1])->willReturn($cdf_document);

    $interest_list = [
      'fefd7eda-4244-4fe4-b9b5-b15b89c61aa8' => [
        'status' => SyndicationStatus::IMPORT_SUCCESSFUL,
        'reason' => 'manual',
        'event_ref' => 'event_uuid',
      ],
    ];
    $this->contentHubClient->getInterestsByWebhookAndSiteRole(
      Argument::type('string'), Argument::type('string'))
      ->willReturn($interest_list);
    $this->contentHubClient->addEntitiesToInterestListBySiteRole(
      Argument::any(), Argument::any(), Argument::type('array'))
      ->willReturn(new Response());
    $this->contentHubClient->updateInterestListBySiteRole(
      Argument::any(), Argument::any(), Argument::type('array'))
      ->willReturn(new Response());
    $this->runImportQueueWorker([self::CLIENT_UUID_1]);

    $this->assertLogMessage('acquia_contenthub_subscriber',
      'The following imported entities have been added to the interest '
    );

    // Failed addition to interest list.
    $this->contentHubClient->getEntities([self::CLIENT_UUID_1 => self::CLIENT_UUID_1])->willReturn($cdf_document);

    $this->contentHubClient->getInterestsByWebhookAndSiteRole(
      Argument::type('string'), Argument::type('string'))
      ->willReturn($interest_list);
    $this->contentHubClient->addEntitiesToInterestListBySiteRole(
      Argument::any(), Argument::any(), Argument::type('array'))
      ->willThrow(new \Exception('error'));
    $this->runImportQueueWorker([self::CLIENT_UUID_1]);

    $this->assertLogMessage('acquia_contenthub_subscriber',
      'Error adding the following entities to the interest list for webhook'
    );
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

}
