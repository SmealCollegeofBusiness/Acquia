<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;

/**
 * Base for enqueue eligibility tests.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
abstract class QueueingTestBase extends KernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;

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
   * Client factory.
   *
   * @var \Drupal\acquia_contenthub_server_test\Client\ClientFactoryMock
   */
  protected $clientFactory;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);
    $this->contentHubQueue = $this->container->get('acquia_contenthub_publisher.acquia_contenthub_export_queue');

    $this->createAcquiaContentHubAdminSettings();
    $this->clientFactory = $this->container->get('acquia_contenthub.client.factory');
  }

}
