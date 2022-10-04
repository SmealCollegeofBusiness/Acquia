<?php

namespace Drupal\acquia_contenthub_translations\Data;

/**
 * Represents the entity translations tracker table.
 */
final class EntityTranslationsTracker extends EntityTranslationsDataAccessBase {

  /**
   * The table name.
   */
  public const TABLE = 'acquia_contenthub_entity_translations_tracking';

  /**
   * {@inheritdoc}
   */
  protected function prepareFields(array $data): array {
    $time = time();
    return [
      'entity_uuid' => $data['entity_uuid'],
      'entity_type' => $data['entity_type'],
      'original_default_language' => $data['original_default_language'],
      'default_language' => $data['default_language'],
      'created' => $time,
      'changed' => $time,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\acquia_contenthub_translations\Exceptions\TranslationDataException
   */
  public function update(string $entity_uuid, array $data): void {
    $this->validateUpdateFields(
      $data['values'],
      ['default_language']
    );

    $data['values']['changed'] = time();
    $this->database->update(self::TABLE)
      ->condition('entity_uuid', $entity_uuid, '=')
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
      'description' => 'Tracks languages of entities',
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
        'entity_type' => [
          'description' => 'The type of the entity',
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'original_default_language' => [
          'description' => 'The original default language of the entity',
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
        ],
        'default_language' => [
          'description' => 'Current default language of the entity',
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
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
      'primary key' => ['id'],
      'unique keys' => [
        'entity_uuid' => ['entity_uuid'],
      ],
      'collation' => 'utf8_bin',
    ];
  }

}
