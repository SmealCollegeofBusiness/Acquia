<?php

namespace Drupal\Tests\acquia_contenthub_translations\Traits;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Tests\acquia_contenthub\Traits\CommonRandomGenerator;

/**
 * Contains helper functions related CDF generation.
 */
trait CdfGeneratorForTranslationsTrait {

  use CommonRandomGenerator;

  /**
   * Generates a new CDF object.
   *
   * @param array $overrides
   *   The data array to override default values.
   * @param array $deps
   *   The dependency list of the CDF.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject
   *   The instantiated CDF object.
   *
   * @throws \ReflectionException
   */
  protected function generateCdfObject(array $overrides = [], array $deps = []): CDFObject {
    $hashed = [];
    foreach ($deps as $dep) {
      $hashed[$dep] = sha1($dep);
    }

    $time = time();
    $data = [
      'type' => 'drupal8_content_entity',
      'uuid' => $this->generateUuid(),
      'created' => $time,
      'modified' => $time,
      'origin' => $this->generateUuid(),
      'metadata' => [
        'default_language' => 'de',
        'dependencies' => [
          'entity' => $hashed,
        ],
        'languages' => ['de'],
        'translatable' => FALSE,
      ],
      'attributes' => [
        'entity_type' => [
          'type' => 'string',
          'value' => ['und' => 'path_alias'],
        ],
      ],
    ];
    $data = array_replace_recursive($data, $overrides);
    return CDFObject::fromArray($data);
  }

}
