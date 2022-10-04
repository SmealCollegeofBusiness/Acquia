<?php

namespace Drupal\acquia_contenthub_translations\OperationHandler;

use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Handles translation deletions.
 */
class TranslationDeletionHandler {

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
   * Constructs a new object.
   *
   * @param \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface $manager
   *   The translation manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $channel
   *   The acquia_contenthub_translations logger channel.
   */
  public function __construct(EntityTranslationManagerInterface $manager, LoggerChannelInterface $channel) {
    $this->manager = $manager;
    $this->channel = $channel;
  }

  /**
   * Handles tracked entity deletions.
   *
   * @param \Drupal\Core\Entity\TranslatableInterface $entity
   *   The entity to delete.
   */
  public function deleteTrackedEntity(TranslatableInterface $entity): void {
    if (!$entity->uuid()) {
      return;
    }
    $tracked_entity = $this->manager->getTrackedEntity($entity->uuid());
    if (is_null($tracked_entity)) {
      return;
    }
    $tracked_entity->delete();
  }

  /**
   * Removes all translations of an entity based on the tracked data.
   *
   * @param \Drupal\Core\Entity\TranslatableInterface $entity
   *   The entity to be pruned.
   * @param array $accepted_translations
   *   The accepted language list.
   */
  public function pruneTranslations(TranslatableInterface $entity, array $accepted_translations): void {
    if (!$entity->uuid()) {
      return;
    }
    $tracked_entity = $this->manager->getTrackedEntity($entity->uuid());
    if (is_null($tracked_entity)) {
      return;
    }
    $translations = array_keys($entity->getTranslationLanguages());

    $this->channel->info(sprintf('Pruning entity translations %s | %s, translations: %s, accepted languages: %s',
        $tracked_entity->type(), $tracked_entity->uuid(),
        implode(', ', $translations),
        implode(', ', $accepted_translations))
    );
    $removable = array_diff($translations, $accepted_translations);
    if (empty($removable)) {
      return;
    }

    $removed = [];
    foreach ($removable as $lang) {
      if (!$tracked_entity->isTranslationDeletable($lang)) {
        continue;
      }
      $removed[] = $lang;
      $entity->removeTranslation($lang);
    }

    if (empty($removed)) {
      return;
    }

    $tracked_entity->removeLanguage(...$removed);
    if ($tracked_entity->isChanged()) {
      $tracked_entity->save();
    }
    $this->channel->info(sprintf(
      'The following languages have been removed for %s | %s: %s',
      $tracked_entity->type(), $tracked_entity->uuid(), implode(', ', $removed)
    ));
  }

  /**
   * Handles tracked entity translation deletions.
   *
   * @param \Drupal\Core\Entity\TranslatableInterface $entity
   *   The entity to use for the operation.
   * @param string $langcode
   *   The language to delete.
   */
  public function deleteTranslation(TranslatableInterface $entity, string $langcode): void {
    if (!$entity->uuid()) {
      return;
    }
    $tracked_entity = $this->manager->getTrackedEntity($entity->uuid());
    if (is_null($tracked_entity)) {
      return;
    }
    $tracked_entity->removeLanguage($langcode);
    if (!$tracked_entity->isChanged()) {
      return;
    }
    $tracked_entity->save();
  }

}
