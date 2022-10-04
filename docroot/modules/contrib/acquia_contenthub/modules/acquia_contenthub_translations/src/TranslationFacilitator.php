<?php

namespace Drupal\acquia_contenthub_translations;

use Drupal\acquia_contenthub_translations\Data\TrackedEntity;
use Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf\TrackTranslations;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;

/**
 * Facilitates overriding of translations.
 */
class TranslationFacilitator {

  /**
   * Operation flag for different entity actions.
   */
  public const OPERATION_MAP = [
    'create' => EntityTranslationManagerInterface::NO_DELETION | EntityTranslationManagerInterface::NO_UPDATE,
    'update' => EntityTranslationManagerInterface::NO_UPDATE,
    'syndicating' => EntityTranslationManagerInterface::NO_ACTION,
  ];

  /**
   * Entity translation manager.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
   */
  protected $entityTranslationManager;

  /**
   * TranslationsOverrideAgent constructor.
   *
   * @param \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface $entity_translation_manager
   *   Entity translation manager.
   */
  public function __construct(EntityTranslationManagerInterface $entity_translation_manager) {
    $this->entityTranslationManager = $entity_translation_manager;
  }

  /**
   * Tracks translation based on global syndication state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param string $entity_operation
   *   Operation for this translation. ('create', 'update' etc.)
   */
  public function trackTranslation(EntityInterface $entity, string $entity_operation): void {
    $uuid = $entity->uuid();
    if (!$uuid || !$entity instanceof TranslatableInterface) {
      return;
    }
    $non_default_languages = array_keys($entity->getTranslationLanguages(FALSE));
    if (empty($non_default_languages)) {
      return;
    }
    $tracked_entity = $this->getTrackedEntity($entity);
    $languages_to_track = [];
    foreach ($non_default_languages as $lang) {
      $entity_operation = TrackTranslations::$isSyndicating ? 'syndicating' : $entity_operation;
      $op_flag = $this->getOperationFlag($entity_operation);
      $original_op = $tracked_entity->getOperation($lang);

      // Higher operation takes precedence.
      if ($original_op >= $op_flag && !TrackTranslations::$isSyndicating) {
        continue;
      }

      if (!$tracked_entity->isTranslationUpdatable($lang) && TrackTranslations::$isSyndicating) {
        continue;
      }

      $languages_to_track[$lang] = $this->getOperationFlag($entity_operation);
    }
    if (empty($languages_to_track)) {
      return;
    }
    $tracked_entity->addLanguages($languages_to_track);
    $tracked_entity->save();
  }

  /**
   * Returns operation flag.
   *
   * @param string $entity_operation
   *   Operation for this entity.('create', 'update').
   *
   * @return int
   *   Operation flag for this translation.
   */
  protected function getOperationFlag(string $entity_operation): int {
    return static::OPERATION_MAP[$entity_operation] ?? -1;
  }

  /**
   * Returns tracked entity if available.
   *
   * Otherwise, tracks it in the entity tracker.
   *
   * @param \Drupal\Core\Entity\TranslatableInterface $entity
   *   Entity object.
   *
   * @return \Drupal\acquia_contenthub_translations\Data\TrackedEntity
   *   Tracked entity.
   */
  protected function getTrackedEntity(TranslatableInterface $entity): TrackedEntity {
    // If it's not tracked then it needs to be tracked
    // with same original and new default language.
    $tracked_entity = $this->entityTranslationManager->getTrackedEntity($entity->uuid());
    if ($tracked_entity) {
      return $tracked_entity;
    }
    // Active langcode for translatable entities.
    $default_language = $entity->language()->getId();
    $translation_languages = array_keys($entity->getTranslationLanguages());
    foreach ($translation_languages as $translation_language) {
      $translation = $entity->getTranslation($translation_language);
      if ($translation->isDefaultTranslation()) {
        $default_language = $translation_language;
        break;
      }
    }
    return $this->entityTranslationManager->trackEntity($entity->uuid(), $entity->getEntityTypeId(), $default_language, $default_language);
  }

}
