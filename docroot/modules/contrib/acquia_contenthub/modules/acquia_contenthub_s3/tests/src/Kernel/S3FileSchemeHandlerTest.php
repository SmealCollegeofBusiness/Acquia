<?php

namespace Drupal\Tests\acquia_contenthub_s3\Kernel;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CreateCdfEntityEvent;

/**
 * Tests the S3FileSchemeHandler.
 *
 * @group acquia_contenthub_s3
 * @coversDefaultClass \Drupal\acquia_contenthub_s3\Plugin\FileSchemeHandler\S3FileSchemeHandler
 *
 * @requires module s3fs
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_s3\Kernel
 */
class S3FileSchemeHandlerTest extends S3FileKernelTestBase {

  use S3FileTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $config_factory = $this->container->get('config.factory');
    $config_factory->getEditable('s3fs.settings')
      ->set('bucket', 'bucket-name')
      ->set('root_folder', 'a-root-folder')
      ->save();
    $config_factory->getEditable('acquia_contenthub.admin_settings')
      ->set('origin', 'aa1c7fd9-cffe-411e-baef-d0a2c67bddd4')
      ->save();
  }

  /**
   * @covers ::addAttributes
   */
  public function testAddAttributes() {
    $file = $this->createFileEntity('test.jpg', 's3');
    $event = new CreateCdfEntityEvent($file, []);
    $this->container->get('event_dispatcher')->dispatch(AcquiaContentHubEvents::CREATE_CDF_OBJECT, $event);

    $cdf = $event->getCdf($file->uuid());
    $this->assertCdfAttribute($cdf, 'ach_s3_bucket', 'bucket-name');
    $this->assertCdfAttribute($cdf, 'ach_s3_source', 'a-root-folder');
  }

}
