<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Stubs;

/**
 * Drupal version trait for consistency.
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\Stubs
 */
trait DrupalVersion {

  /**
   * Get the current version of Drupal to identify fixtures for tests.
   *
   * @return string
   *   The Drupal version string.
   */
  protected function getDrupalVersion() {
    $version = implode('.', explode('.', \Drupal::VERSION, -1));
    return "drupal-$version";
  }

}
