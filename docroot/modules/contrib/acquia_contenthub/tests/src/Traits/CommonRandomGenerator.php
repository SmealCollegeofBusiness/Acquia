<?php

namespace Drupal\Tests\acquia_contenthub\Traits;

use Drupal\Component\Uuid\Php;

/**
 * Contains helper methods for common use cases.
 */
trait CommonRandomGenerator {

  /**
   * Generates a new uuid.
   *
   * @return string
   *   The uuid.
   */
  public function generateUuid(): string {
    $uuid = new Php();
    return $uuid->generate();
  }

}
