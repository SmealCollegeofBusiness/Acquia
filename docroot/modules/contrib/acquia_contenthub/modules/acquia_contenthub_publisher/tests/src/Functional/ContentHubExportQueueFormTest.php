<?php

namespace Drupal\Tests\acquia_contenthub_publisher\Functional;

use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\Tests\acquia_contenthub\Functional\ContentHubQueueFormTestBase;

/**
 * Tests the Export Queue form.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_publisher\Form\ContentHubExportQueueForm
 *
 * @group acquia_contenthub_publisher
 */
class ContentHubExportQueueFormTest extends ContentHubQueueFormTestBase {

  /**
   * Path to publisher export queue form.
   */
  const QUEUE_FORM_PATH = '/admin/config/services/acquia-contenthub/export-queue';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'acquia_contenthub_publisher',
  ];

  /**
   * Tests form reachability for users with different permissions.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testExportQueuePagePermissions(): void {
    $this->checkFormAccessForUsers(self::QUEUE_FORM_PATH, 'Export Queue');
  }

  /**
   * {@inheritdoc}
   */
  public function queueFormDataProvider(): array {
    return [
      [
        self::QUEUE_FORM_PATH,
        PublisherTracker::EXPORT_TRACKING_TABLE,
        'Export Items',
        'Purged all contenthub export queues.',
      ],
    ];
  }

}
