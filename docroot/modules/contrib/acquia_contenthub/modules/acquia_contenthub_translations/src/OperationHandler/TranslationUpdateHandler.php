<?php

namespace Drupal\acquia_contenthub_translations\OperationHandler;

use Drupal\acquia_contenthub_translations\Data\TrackedEntity;
use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface;
use Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf\TrackTranslations;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Handles entity translation updates.
 */
class TranslationUpdateHandler {

  /**
   * The translation manager service.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
   */
  protected $manager;

  /**
   * The acquia_contenthub_translations logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $channel;

  /**
   * Content hub translations config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface $manager
   *   The translation manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $channel
   *   The acquia_contenthub_translations logger channel.
   * @param \Drupal\Core\Config\Config $config
   *   Translations config object.
   */
  public function __construct(EntityTranslationManagerInterface $manager, LoggerChannelInterface $channel, Config $config) {
    $this->manager = $manager;
    $this->channel = $channel;
    $this->config = $config;
  }

  /**
   * Updates a tracked entity.
   *
   * Reverts the translation's field values under certain conditions.
   *
   * @param \Drupal\Core\Entity\TranslatableInterface $entity
   *   The translatable entity.
   *
   * @see \acquia_contenthub_translations_entity_presave()
   */
  public function updateTrackedEntity(TranslatableInterface $entity): void {
    if (!$entity->uuid()) {
      return;
    }
    $tracked_entity = $this->manager->getTrackedEntity($entity->uuid());
    if (!$tracked_entity) {
      return;
    }
    $langs = array_keys($entity->getTranslationLanguages());

    foreach ($langs as $lang) {
      $translation = $entity->getTranslation($lang);

      if ($this->shouldUpdateTranslation($entity, $tracked_entity, $lang)) {
        continue;
      }

      // Revert to the original translation values.
      /** @var \Drupal\Core\Entity\ContentEntityInterface $original */
      $original = $entity->original;
      $orig_translation = $original->getTranslation($lang);
      $fields = $orig_translation->getTranslatableFields();
      foreach ($fields as $field_name => $field) {
        if (!$translation->get($field_name)->hasAffectingChanges($field, $lang)) {
          continue;
        }
        $translation->set($field_name, $field->getValue());
      }
      $this->channel->debug(sprintf(
        'Translation %s of %s | %s has been reverted to original',
        $lang, $entity->bundle(), $entity->uuid(),
      ));

    }
  }

  /**
   * Checks if translation should be updated.
   *
   * @param \Drupal\Core\Entity\TranslatableInterface $entity
   *   The translatable entity.
   * @param \Drupal\acquia_contenthub_translations\Data\TrackedEntity $tracked_entity
   *   The tracked entity.
   * @param string $lang
   *   The language code.
   *
   * @return bool
   *   Returns TRUE if translation is updatable.
   */
  protected function shouldUpdateTranslation(TranslatableInterface $entity, TrackedEntity $tracked_entity, string $lang): bool {
    $allow_override_translation = $this->config->get('override_translation');
    $translation = $entity->getTranslation($lang);
    if (
      // Default translation is updatable as of now.
      (!$translation->hasTranslationChanges() || $translation->isDefaultTranslation())
      ||
      // Locally edited, therefore updatable.
      !TrackTranslations::$isSyndicating
      ||
      // Config to force translation overriding.
      ($allow_override_translation && TrackTranslations::$isSyndicating)
      ||
      // Updatable through syndication.
      ($tracked_entity->isTranslationUpdatable($lang) && TrackTranslations::$isSyndicating)
    ) {
      return TRUE;
    }

    // Revert to the original translation values.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $original */
    $original = $entity->original;
    if (!$original->hasTranslation($lang)) {
      return TRUE;
    }
    return FALSE;
  }

}
