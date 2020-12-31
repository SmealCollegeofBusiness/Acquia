<?php

namespace Drupal\Tests\acquia_contenthub_s3\Kernel;

use Drupal\acquia_contenthub_s3\S3FileMap;
use Drupal\KernelTests\KernelTestBase;

/**
 * Provides a base setup for s3 file tests.
 *
 * @package Drupal\Tests\acquia_contenthub_s3\Kernel
 */
abstract class S3FileKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_s3',
    'depcalc',
    'file',
    's3fs',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installSchema('acquia_contenthub_s3', S3FileMap::TABLE_NAME);
    $this->installSchema('s3fs', 's3fs_file');

    // @todo: Can be removed after resolving https://www.drupal.org/project/s3fs/issues/3053014
    $this->container->get('config.factory')->getEditable('s3fs.settings')
      ->set('region', 'us-east-1')->save();
  }

}
