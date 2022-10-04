<?php

namespace Drupal\acquia_contenthub_translations\Data;

/**
 * Provides an implementation schema for translation DAOs.
 */
interface EntityTranslationsDAOInterface {

  /**
   * Returns every record from the table.
   *
   * Returned structure:
   * @code
   * [
   *   'f1165aa6-ad63-44b1-b85e-72d3928aa174' => [
   *     'entity_uuid' => 'f1165aa6-ad63-44b1-b85e-72d3928aa174',
   *     'attr' => 'attr_value',
   *     ...
   *   ],
   * ]
   * @endcode
   *
   * @return array
   *   An array of records.
   */
  public function getAll(): array;

  /**
   * Returns a record from the table based on the entity uuid.
   *
   * @param string $entity_uuid
   *   The entity's uuid.
   *
   * @return array
   *   An associative array with keys as entity fields.
   */
  public function get(string $entity_uuid): array;

  /**
   * Inserts a record into the table.
   *
   * @param array $data
   *   The data to insert.
   */
  public function insert(array $data): void;

  /**
   * Updates a record based on the entity uuid.
   *
   * The values should be passed on the value key. This is to allow more
   * flexibility for the implementation.
   * Expected structure:
   * @code
   * [
   *   'values' => ['entity_field' => 'entity_value'],
   *   'field_used_for_additional_condition' => 'field_value',
   * ]
   * @endcode
   *
   * @param string $entity_uuid
   *   The entity's uuid.
   * @param array $data
   *   The update data.
   */
  public function update(string $entity_uuid, array $data): void;

  /**
   * Deletes a / multiple record(s).
   *
   * Based on the match, delete a record / multiple records.
   *
   * Expected condition structure:
   * @code
   * [
   *   'field_name' => 'name',
   *   'value' => 'some_value',
   *   'operator => '=',
   * ]
   * @endcode
   *
   * @param string $field_name
   *   The field name to delete by.
   * @param mixed $value
   *   The value to match for deletion.
   * @param array $conditions
   *   Additional conditions to run the query by.
   */
  public function deleteBy(string $field_name, $value, array $conditions): void;

  /**
   * Deletes a record by entity uuid.
   *
   * @param string $entity_uuid
   *   The entity's uuid.
   */
  public function deleteByUuid(string $entity_uuid): void;

  /**
   * Returns the table schema.
   *
   * @return array
   *   The schema's array.
   */
  public static function schema(): array;

  /**
   * Returns the table name of the current implementation.
   *
   * This method is more favourable than the constants to decrease coupleness.
   *
   * @return string
   *   The table's name.
   */
  public static function tableName(): string;

}
