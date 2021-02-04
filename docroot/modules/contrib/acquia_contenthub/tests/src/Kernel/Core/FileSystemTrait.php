<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Core;

/**
 * A trait for file system components.
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\Core
 */
trait FileSystemTrait {

  /**
   * File system component.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Adjust file system for test.
   */
  protected function fileSystemSetUp() {
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $this->fileSystem = \Drupal::service('file_system');
    if (version_compare(\Drupal::VERSION, '8.8.0', '<')) {
      $this->fileSystem->mkdir('public://2018-12');
    }
    $this->fileSystem->mkdir('public://2020-03');
  }

}
