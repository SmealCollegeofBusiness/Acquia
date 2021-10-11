<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Base for enqueue eligibility tests.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
abstract class QueueingTestBase extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'acquia_contenthub_server_test',
  ];

  /**
   * Acquia ContentHub export queue.
   *
   * @var \Drupal\acquia_contenthub_publisher\ContentHubExportQueue
   */
  protected $contentHubQueue;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);
    $this->contentHubQueue = $this->container->get('acquia_contenthub_publisher.acquia_contenthub_export_queue');
  }

}
