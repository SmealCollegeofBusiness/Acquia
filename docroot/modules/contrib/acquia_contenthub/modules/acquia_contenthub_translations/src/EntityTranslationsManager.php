<?php

namespace Drupal\acquia_contenthub_translations;

use Drupal\acquia_contenthub_translations\Data\EntityTranslationsDAOInterface;
use Drupal\acquia_contenthub_translations\Data\TrackedEntity;
use Drupal\Core\Database\Connection;

/**
 * Provides an abstraction layer between the DAOs and upper layer.
 *
 * Additionally, the manager provides a simple caching mechanism as well.
 * The cache is stored in memory.
 */
class EntityTranslationsManager implements EntityTranslationManagerInterface {

  /**
   * The entity translations DAO.
   *
   * @var \Drupal\acquia_contenthub_translations\Data\EntityTranslationsDAOInterface
   */
  protected $translations;

  /**
   * The entity translations tracker DAO.
   *
   * @var \Drupal\acquia_contenthub_translations\Data\EntityTranslationsDAOInterface
   */
  protected $tracker;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Cache for already retrieved items.
   *
   * @var \Drupal\acquia_contenthub_translations\Data\TrackedEntity[]
   */
  private static $cache;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\acquia_contenthub_translations\Data\EntityTranslationsDAOInterface $translations
   *   The entity translations DAO.
   * @param \Drupal\acquia_contenthub_translations\Data\EntityTranslationsDAOInterface $tracker
   *   The entity translations tracker DAO.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTranslationsDAOInterface $translations, EntityTranslationsDAOInterface $tracker, Connection $database) {
    $this->translations = $translations;
    $this->tracker = $tracker;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackedEntity(string $entity_uuid): ?TrackedEntity {
    if (isset(self::$cache[$entity_uuid])) {
      return self::$cache[$entity_uuid];
    }

    $query = $this->database->select($this->tracker::tableName(), 'et')
      ->fields('et')
      ->fields('ett', ['langcode', 'operation_flag'])
      ->condition('et.entity_uuid', $entity_uuid);
    $query->leftJoin($this->translations::tableName(), 'ett', '[ett].[entity_uuid] = [et].[entity_uuid]');
    $rows = $query
      ->condition('et.entity_uuid', $entity_uuid)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($rows)) {
      return NULL;
    }

    $normalized = [];
    $first = current($rows);
    $normalized['uuid'] = $first['entity_uuid'];
    $normalized['type'] = $first['entity_type'];
    $normalized['original_default_language'] = $first['original_default_language'];
    $normalized['default_language'] = $first['default_language'];
    $normalized['created'] = $first['created'];
    $normalized['changed'] = $first['changed'];
    foreach ($rows as $row) {
      if (isset($row['langcode'])) {
        $normalized['languages'][$row['langcode']] = (int) $row['operation_flag'];
      }
    }

    $entity = new TrackedEntity($this, $normalized);
    self::$cache[$entity_uuid] = $entity;

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTrackedEntity(string $entity_uuid): void {
    $this->translations->deleteByUuid($entity_uuid);
    $this->tracker->deleteByUuid($entity_uuid);
  }

  /**
   * {@inheritdoc}
   */
  public function trackEntity(string $uuid, string $type, string $original_default_langcode, string $default_langcode): TrackedEntity {
    $time = time();
    $this->tracker->insert(
      [
        'entity_uuid' => $uuid,
        'entity_type' => $type,
        'original_default_language' => $original_default_langcode,
        'default_language' => $default_langcode,
        'created' => $time,
        'changed' => $time,
      ]
    );
    $values = [
      'uuid' => $uuid,
      'type' => $type,
      'original_default_language' => $original_default_langcode,
      'default_language' => $default_langcode,
      'created' => $time,
      'changed' => $time,
    ];
    $entity = new TrackedEntity($this, $values);
    self::$cache[$uuid] = $entity;
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function trackMultiple(array $values): void {
    $this->tracker->insert($values);
  }

  /**
   * {@inheritdoc}
   */
  public function trackTranslation(string $uuid, string $langcode, int $operation = self::NO_ACTION): void {
    $this->translations->insert([
      'entity_uuid' => $uuid,
      'langcode' => $langcode,
      'operation_flag' => $operation,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function removeTranslation(string $uuid, string $langcode): void {
    $this->translations->deleteBy('entity_uuid', $uuid,
      [['field_name' => 'langcode', 'value' => $langcode, 'operator' => '=']]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function updateTrackedEntity(TrackedEntity $entity): void {
    $changed = $entity->getChangedValues();
    if (empty($changed)) {
      return;
    }

    // Update translations table.
    if (isset($changed['languages'])) {
      $langs = $changed['languages'];
      $original_langs = $entity->languages();
      $removable = array_diff_key($original_langs, $langs);
      foreach (array_keys($removable) as $lang) {
        $this->removeTranslation($entity->uuid(), $lang);
      }

      foreach ($langs as $lang => $operation_flag) {
        if (!isset($original_langs[$lang])) {
          $this->trackTranslation($entity->uuid(), $lang, $operation_flag);
          continue;
        }
        if ($operation_flag === $original_langs[$lang]) {
          continue;
        }
        $this->translations->update($entity->uuid(), [
          'values' => ['operation_flag' => $operation_flag],
          'langcode' => $lang,
        ]);
      }
      // Tracker doesn't accept languages fields therefore needs to be unset.
      unset($changed['languages']);
    }

    $this->tracker->update($entity->uuid(), ['values' => $changed]);

    unset(self::$cache[$entity->uuid()]);
  }

}
