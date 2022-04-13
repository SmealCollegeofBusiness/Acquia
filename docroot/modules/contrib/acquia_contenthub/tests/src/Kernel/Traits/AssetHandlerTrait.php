<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Traits;

/**
 * Load assets and override content.
 *
 * The asset should be placed in the assets directory. The file extension
 * should be json.
 */
trait AssetHandlerTrait {

  /**
   * Returns expected field values for assertions from prebuilt json.
   *
   * @param string $filename
   *   The file to load assets from.
   * @param array $overrides
   *   (Optional) Array of dynamic field values to use as overrides.
   *
   * @return array
   *   Merged expected values.
   */
  public function getCdfArray(string $filename, array $overrides = []): array {
    $data = $this->loadAsset($filename);
    return array_replace_recursive($data, $overrides);
  }

  /**
   * Loads the required json from fixtures.
   *
   * Expected location: ./assets/{filename}
   *
   * @param string $name
   *   Name of json file.
   *
   * @return array
   *   Returns expected values from prebuild json.
   */
  protected function loadAsset(string $name): array {
    $class = new \ReflectionClass(__CLASS__);
    $path = $class->getFileName();
    $data = file_get_contents(dirname($path) . '/assets/' . $name);
    return json_decode($data, TRUE);
  }

}
