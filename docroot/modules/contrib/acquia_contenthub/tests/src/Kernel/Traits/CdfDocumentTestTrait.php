<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Traits;

use Drupal\Component\Uuid\Php;

/**
 * Helper trait for tests.
 */
trait CdfDocumentTestTrait {

  /**
   * Generates the given number of random dependencies.
   *
   * @param int $number_of_deps
   *   The number of dependencies.
   *
   * @return array
   *   The dependencies.
   */
  protected function generateRandomDependencies(int $number_of_deps): array {
    $deps = [];
    $uuid_generator = new Php();
    for ($i = 0; $i < $number_of_deps; $i++) {
      $uuid = $uuid_generator->generate();
      $deps[$uuid] = sha1($uuid);
    }
    return $deps;
  }

}
