<?php

namespace Drupal\acquia_contenthub_publisher;

use Drupal\acquia_contenthub\AcquiaContentHubStatusMetricsTrait;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;

/**
 * The publisher tracker table class.
 */
class PublisherTracker {

  use AcquiaContentHubStatusMetricsTrait;

  const QUEUED = 'queued';

  const EXPORTED = 'exported';

  const CONFIRMED = 'confirmed';

  /**
   * The name of the tracking table.
   */
  const EXPORT_TRACKING_TABLE = 'acquia_contenthub_publisher_export_tracking';

  /**
   * PublisherTracker constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Get the tracking record for a given uuid.
   *
   * @param string $uuid
   *   The entity uuid.
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   *   Database statement
   */
  public function get($uuid) {
    $query = $this->database->select(self::EXPORT_TRACKING_TABLE, 't')
      ->fields('t', ['entity_uuid']);
    $query->condition('entity_uuid', $uuid);
    return $query->execute()->fetchObject();
  }

  /**
   * Add tracking for an entity in a self::EXPORTED state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to add tracking.
   * @param string $hash
   *   A sha1 hash of the data attribute for change management.
   *
   * @throws \Exception
   */
  public function track(EntityInterface $entity, $hash) {
    $this->insertOrUpdate($entity, self::EXPORTED, $hash);
  }

  /**
   * Add tracking for an entity in a self::QUEUED state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to add tracking.
   *
   * @throws \Exception
   */
  public function queue(EntityInterface $entity) {
    $this->insertOrUpdate($entity, self::QUEUED);
  }

  /**
   * Remove tracking for an entity.
   *
   * @param string $uuid
   *   The uuid for which to remove tracking.
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   *   Database statement
   *
   * @throws \Exception
   */
  public function delete($uuid) {
    $query = $this->database->delete(self::EXPORT_TRACKING_TABLE);
    $query->condition('entity_uuid', $uuid);
    return $query->execute();
  }

  /**
   * Determines if an entity will be inserted or updated with a status.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to add tracking to.
   * @param string $status
   *   The status of the tracking.
   * @param string $hash
   *   A sha1 hash of the data attribute for change management.
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   *   Database statement.
   *
   * @throws \Exception
   */
  protected function insertOrUpdate(EntityInterface $entity, $status, $hash = '') {
    if ($entity instanceof EntityChangedInterface) {
      $modified = date('c', $entity->getChangedTime());
    }
    else {
      $modified = date('c');
    }

    $results = $this->get($entity->uuid());

    // If we've previously tracked this thing, set its created date.
    if ($results) {
      $values = ['modified' => $modified, 'status' => $status];
      if ($hash) {
        $values['hash'] = $hash;
      }
      $query = $this->database->update(self::EXPORT_TRACKING_TABLE)
        ->fields($values);
      $query->condition('entity_uuid', $entity->uuid());
      return $query->execute();
    }
    elseif ($entity instanceof NodeInterface) {
      $created = date('c', $entity->getCreatedTime());
    }
    // Otherwise just mirror the modified date.
    else {
      $created = $modified;
    }
    $values = [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'entity_uuid' => $entity->uuid(),
      'status' => $status,
      'created' => $created,
      'modified' => $modified,
      'hash' => $hash,
    ];
    return $this->database->insert(self::EXPORT_TRACKING_TABLE)
      ->fields($values)
      ->execute();
  }

  /**
   * Set the queue item of a particular record by its uuid.
   *
   * @param string $uuid
   *   The uuid of an entity.
   * @param string $queue_id
   *   The status to set.
   *
   * @throws \Exception
   */
  public function setQueueItemByUuid(string $uuid, string $queue_id) {
    if (!$this->isTracked($uuid)) {
      return;
    }
    $query = $this->database->update(self::EXPORT_TRACKING_TABLE);
    $query->fields(['queue_id' => $queue_id]);
    $query->condition('entity_uuid', $uuid);
    $query->execute();
  }

  /**
   * Checks if a particular entity uuid is tracked.
   *
   * @param string $uuid
   *   The uuid of an entity.
   *
   * @return bool
   *   Whether or not the entity is tracked in the subscriber tables.
   */
  public function isTracked(string $uuid) {
    $query = $this->database->select(self::EXPORT_TRACKING_TABLE, 't');
    $query->fields('t', ['entity_type', 'entity_id']);
    $query->condition('entity_uuid', $uuid);

    return (bool) $query->execute()->fetchObject();
  }

  /**
   * Nullify queue_id when entities loose their queued state.
   *
   * @param string $uuid
   *   The uuid of an entity.
   */
  public function nullifyQueueId($uuid) {
    $query = $this->database->update(self::EXPORT_TRACKING_TABLE);
    $query->fields(['queue_id' => '']);
    $query->condition('entity_uuid', $uuid);
    $query->execute();
  }

}
