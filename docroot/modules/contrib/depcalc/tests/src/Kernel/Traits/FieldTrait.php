<?php

namespace Drupal\Tests\depcalc\Kernel\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Helper trait for field storage and field config.
 */
trait FieldTrait {

  /**
   * Creates field storage.
   *
   * @param string $field_name
   *   The name of the field.
   * @param string $entity_type
   *   The entity type.
   * @param string $field_type
   *   The field type.
   * @param array $settings
   *   Array of settings for field storage.
   * @param int $cardinality
   *   Value for cardinality.
   *
   * @return \Drupal\field\Entity\FieldStorageConfig
   *   Creates and returns field storage configuration.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createFieldStorage(string $field_name, string $entity_type, string $field_type, array $settings = [], int $cardinality = 1): FieldStorageConfig {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $field_type,
      'cardinality' => $cardinality,
      'settings' => $settings,
    ]);
    $field_storage->save();

    return $field_storage;
  }

  /**
   * Creates field configurations.
   *
   * @param \Drupal\field\Entity\FieldStorageConfig $field_storage
   *   The field storage configuration.
   * @param string $bundle
   *   The bundle name.
   *
   * @return \Drupal\field\Entity\FieldConfig
   *   Creates and returns field configurations.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createFieldConfig(FieldStorageConfig $field_storage, string $bundle): FieldConfig {
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
    ]);
    $field->save();

    return $field;
  }

}
