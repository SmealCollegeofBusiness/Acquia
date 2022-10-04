<?php

namespace Drupal\acquia_contenthub_translations;

use Drupal\acquia_contenthub_translations\Data\TrackedEntity;

/**
 * Represents the abstraction layer of the DAOs.
 */
interface EntityTranslationManagerInterface {

  /**
   * Syndication proceeds as normal, it will be updated or deleted.
   */
  public const NO_ACTION = 0;

  /**
   * The entity will not be deleted but can be updated.
   */
  public const NO_DELETION = 1;

  /**
   * The entity will not be deleted nor updated buy the publisher.
   */
  public const NO_UPDATE = 2;

  /**
   * Tracks a new entity.
   *
   * @param string $uuid
   *   The uuid of the entity.
   * @param string $type
   *   The type of the entity.
   * @param string $original_default_langcode
   *   The original language of the entity.
   * @param string $default_langcode
   *   The locally tracked default language of the entity.
   *
   * @return \Drupal\acquia_contenthub_translations\Data\TrackedEntity
   *   The tracked entity.
   */
  public function trackEntity(string $uuid, string $type, string $original_default_langcode, string $default_langcode): TrackedEntity;

  /**
   * Track multiple entities.
   *
   * @param array $values
   *   The entity values.
   */
  public function trackMultiple(array $values): void;

  /**
   * Tracks translations of an already tracked entity.
   *
   * @param string $uuid
   *   The tracked entity's uuid.
   * @param string $langcode
   *   The langcode to track.
   * @param int $operation
   *   The operations allowed for the tracked langcode.
   */
  public function trackTranslation(string $uuid, string $langcode, int $operation = self::NO_ACTION): void;

  /**
   * Removes translations of an already tracked entity.
   *
   * @param string $uuid
   *   The tracked entity's uuid.
   * @param string $langcode
   *   The langcode to remove.
   */
  public function removeTranslation(string $uuid, string $langcode): void;

  /**
   * Returns a tracked entity.
   *
   * @param string $entity_uuid
   *   The uuid of the entity.
   *
   * @return \Drupal\acquia_contenthub_translations\Data\TrackedEntity|null
   *   The tracked entity or null.
   */
  public function getTrackedEntity(string $entity_uuid): ?TrackedEntity;

  /**
   * Deletes and entity by its uuid.
   *
   * Deletes it from both tables, with all the registered langcodes.
   *
   * @param string $entity_uuid
   *   The entity's uuid.
   */
  public function deleteTrackedEntity(string $entity_uuid): void;

  /**
   * Updates a tracked entity.
   *
   * @param \Drupal\acquia_contenthub_translations\Data\TrackedEntity $entity
   *   The tracked entity to update.
   */
  public function updateTrackedEntity(TrackedEntity $entity): void;

}
