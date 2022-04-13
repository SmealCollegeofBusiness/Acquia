<?php

namespace Drupal\Tests\acquia_contenthub_subscriber\Functional;

use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Tests\acquia_contenthub\Functional\ContentHubQueueFormTestBase;

/**
 * Test the Import Queue form.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_subscriber\Form\ContentHubImportQueueForm
 *
 * @group acquia_contenthub_subscriber
 */
class ContentHubImportQueueFormTest extends ContentHubQueueFormTestBase {

  /**
   * Path to subscriber import queue form.
   */
  const QUEUE_FORM_PATH = '/admin/config/services/acquia-contenthub/import-queue';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'acquia_contenthub_subscriber',
  ];

  /**
   * Tests form reachability for users with different permissions.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testImportQueuePagePermissions(): void {
    $this->checkFormAccessForUsers(self::QUEUE_FORM_PATH, 'Import Queue');
  }

  /**
   * {@inheritdoc}
   *
   * @dataProvider queueFormDataProvider
   */
  public function testQueueFormPurgeData(string $form_path, string $table_name, string $button_label, string $page_title): void {
    $node = $this->drupalCreateNode([
      'title' => 'test title 1',
      'type' => 'test_type',
    ]);
    $queue = \Drupal::queue('acquia_contenthub_subscriber_import');
    $item = new \stdClass();
    $item->type = $node->getEntityTypeId();
    $item->uuid = $node->uuid();
    $queue->createItem($item);
    $this->container->get('acquia_contenthub_subscriber.tracker')
      ->queue($node->uuid());

    parent::testQueueFormPurgeData($form_path, $table_name, $button_label, $page_title);
  }

  /**
   * {@inheritdoc}
   */
  public function queueFormDataProvider(): array {
    return [
      [
        self::QUEUE_FORM_PATH,
        SubscriberTracker::IMPORT_TRACKING_TABLE,
        'Import Items',
        'Successfully purged Content Hub import queue.',
      ],
    ];
  }

}
