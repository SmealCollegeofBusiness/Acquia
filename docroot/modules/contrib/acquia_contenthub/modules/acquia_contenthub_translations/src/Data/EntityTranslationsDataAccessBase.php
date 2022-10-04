<?php

namespace Drupal\acquia_contenthub_translations\Data;

use Drupal\acquia_contenthub_translations\Exceptions\TranslationDataException;
use Drupal\Core\Database\Connection;

/**
 * Base class for translation tracker DAOs.
 */
abstract class EntityTranslationsDataAccessBase implements EntityTranslationsDAOInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $entity_uuid): array {
    $match = $this->database->select(static::tableName(), 't')
      ->fields('t')
      ->condition('entity_uuid', $entity_uuid, '=')
      ->execute()
      ->fetchAllAssoc('entity_uuid');
    return !empty($match) ? current($match) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function insert(array $data): void {
    if (!is_array(current($data))) {
      $data = [$data];
    }
    $fields = static::schema()['fields'];
    unset($fields['id']);
    $fields = array_keys($fields);

    // An arbitrary length, but sufficient.
    $chunks = array_chunk($data, 5000);
    foreach ($chunks as $values) {
      $insert = $this->database->insert(static::tableName());
      $insert->fields($fields);
      foreach ($values as $datum) {
        $insert->values($this->prepareFields($datum));
      }
      $insert->execute();
    }
  }

  /**
   * Prepares the fields and their values for insertion.
   *
   * Required for seamless multi-value insertion.
   *
   * @param array $data
   *   The input data to insert.
   *
   * @return array
   *   The fields and their values.
   */
  abstract protected function prepareFields(array $data): array;

  /**
   * {@inheritdoc}
   */
  public function getAll(): array {
    return $this->database->select(static::tableName(), 't')
      ->fields('t')
      ->orderBy('id')
      ->execute()
      ->fetchAllAssoc('entity_uuid');
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByUuid(string $entity_uuid): void {
    $this->deleteBy('entity_uuid', $entity_uuid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBy(string $field_name, $value, array $conditions = []): void {
    $query = $this->database->delete(static::tableName())
      ->condition($field_name, $value, '=');

    if (!empty($conditions)) {
      if (!is_array(current($conditions))) {
        $conditions = [$conditions];
      }
      foreach ($conditions as $condition) {
        $query->condition($condition['field_name'], $condition['value'], $condition['operator']);
      }
    }

    $query->execute();
  }

  /**
   * Validates fields before update to avoid updating protected fields.
   *
   * @param array $data
   *   The update values.
   * @param array $allowed_fields
   *   The allowed fields.
   *
   * @throws \Drupal\acquia_contenthub_translations\Exceptions\TranslationDataException
   */
  protected function validateUpdateFields(array $data, array $allowed_fields): void {
    $diff = array_diff(array_keys($data), $allowed_fields);
    if (empty($diff)) {
      return;
    }

    throw new TranslationDataException(
      sprintf(
        'invalid field provided: %s, allowed: %s',
        implode(', ', $diff), implode(', ', $allowed_fields)
      )
    );
  }

}
