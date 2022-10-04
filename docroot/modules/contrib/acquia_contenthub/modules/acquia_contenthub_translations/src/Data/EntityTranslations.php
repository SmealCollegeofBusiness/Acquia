<?php

namespace Drupal\acquia_contenthub_translations\Data;

/**
 * Represents the entity translations table.
 */
final class EntityTranslations extends EntityTranslationsDataAccessBase {

  /**
   * The table name.
   */
  public const TABLE = 'acquia_contenthub_entity_translations';

  /**
   * {@inheritdoc}
   */
  protected function prepareFields(array $data): array {
    $time = time();
    return [
      'entity_uuid' => $data['entity_uuid'],
      'langcode' => $data['langcode'],
      'operation_flag' => $data['operation_flag'],
      'created' => $data['created'] ?? $time,
      'changed' => $data['changed'] ?? $time,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function update(string $entity_uuid, array $data): void {
    $this->validateUpdateFields($data['values'], ['langcode', 'operation_flag']);

    $data['values']['changed'] = time();
    $this->database->update(self::TABLE)
      ->condition('entity_uuid', $entity_uuid, '=')
      ->condition('langcode', $data['langcode'], '=')
      ->fields($data['values'])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function tableName(): string {
    return self::TABLE;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(): array {
    return [
      'description' => 'Tracks entity translations',
      'fields' => [
        'id' => [
          'description' => 'The id of the record',
          'type' => 'serial',
          'not null' => TRUE,
          'unsigned' => TRUE,
        ],
        'entity_uuid' => [
          'description' => 'The uuid of the entity',
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
        ],
        'langcode' => [
          'description' => 'The langcode of the translation',
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
        ],
        'operation_flag' => [
          'description' => 'Defines syndication behaviour',
          'type' => 'int',
          'size' => 'small',
          'not null' => TRUE,
          'default' => 0,
        ],
        'created' => [
          'description' => 'The created date of the record',
          'type' => 'int',
          'not null' => TRUE,
        ],
        'changed' => [
          'description' => 'The changed date of the record',
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
      'foreign_keys' => [
        'entity_uuid' => [
          'table' => EntityTranslationsTracker::TABLE,
          'columns' => ['entity_uuid' => 'entity_uuid'],
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => [
        'uuid_langcode' => ['entity_uuid', 'langcode'],
      ],
      'collation' => 'utf8_bin',
    ];
  }

}
