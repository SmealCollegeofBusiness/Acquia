<?php

namespace Drupal\acquia_contenthub_publisher;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Determines whether an entity revision is "published".
 */
class EntityModeratedRevision {

  /**
   * The acquia_contenthub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $achPublisherChannel;

  /**
   * EntityModeratedRevision constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->achPublisherChannel = $logger_factory->get('acquia_contenthub_publisher');
  }

  /**
   * Determines whether an entity revision is "published".
   *
   * The following conditions have to be met for an entity
   * revision to NOT be considered published:
   *   - It does not have a "published" translation.
   *   - It has at least a published translation but the
   *     revision is not the current revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity revision to be checked.
   *
   * @return bool
   *   TRUE if entity is considered "published", FALSE otherwise.
   *
   * @throws \Exception
   */
  public function isPublishedRevision(EntityInterface $entity): bool {
    if (!$this->revisionHasPublishedTranslation($entity)) {
      // This revision has no published translation = not "published".
      return FALSE;
    }

    // If this revision has at least one published translation, then check
    // that this revision is the current revision.
    $revision_col = $entity->getEntityType()->hasKey("revision") ? $entity->getEntityType()->getKey("revision") : NULL;
    if (!$revision_col || !($entity instanceof RevisionableInterface)) {
      // If it does not have a revision column or entity is not
      // "revisionable" then we can assume it to be "published".
      return TRUE;
    }

    // Checking if this is the current revision.
    $table = $entity->getEntityType()->getBaseTable();
    $id_col = $entity->getEntityType()->getKey("id");
    $query = \Drupal::database()->select($table)
      ->fields($table, [$revision_col]);
    $query->condition("$table.$id_col", $entity->id());
    // Verify if the entity revision being saved is the current revision
    // by checking that the entity revision id with the one specified in
    // its base table.
    $revision_id = $query->execute()->fetchField();
    // It is not a current revision, then it is not "published".
    return ($revision_id !== $entity->getRevisionId()) ? FALSE : TRUE;
  }

  /**
   * Checks if the revision transitioning from published to unpublished state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity revision being saved.
   *
   * @return bool
   *   True if the original entity is published,
   *   but the current revision is unpublished.
   */
  public function isTransitionedToUnpublished(EntityInterface $entity): bool {
    $status = $entity->getEntityType()->hasKey("status") ? $entity->getEntityType()->getKey("status") : NULL;
    if (!$status || !($entity instanceof RevisionableInterface)) {
      // If the entity does not have a publishing status then
      // it is considered published.
      return TRUE;
    }

    $definition = $entity->getFieldDefinition($status);
    $property = $definition->getFieldStorageDefinition()->getMainPropertyName();

    if (!$property) {
      $this->achPublisherChannel->warning(
        sprintf(
          'Cannot get status field main property name of entity. (%s, %s)',
          $entity->getEntityTypeId(),
          $entity->uuid()
        ));
      return FALSE;
    }

    // If there is no original then the entity just created.
    if (!isset($entity->original)) {
      return FALSE;
    }

    // Handle multilingual entities here.
    $translation_origin_published = FALSE;
    if ($entity instanceof TranslatableInterface && $entity->isTranslatable()) {
      // Loop through each language on the original
      // entity that has a translation.
      foreach ($entity->original->getTranslationLanguages() as $language) {
        // Load the translated revision. If any of the origin
        // translation is published then we assume it was published.
        $translation = $entity->original->getTranslation($language->getId());
        if ($translation->get($status)->$property) {
          $translation_origin_published = TRUE;
          break;
        }
      }
    }

    $original_is_published = (bool) $entity->original->get($status)->$property || $translation_origin_published;

    // If current status is unpublished and the original is published,
    // then we should export.
    return (bool) $entity->get($status)->$property === FALSE && $original_is_published;
  }

  /**
   * Checks if the revision has at least one published translation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity revision being saved.
   *
   * @return bool
   *   TRUE if this revision has a published translation, FALSE otherwise.
   */
  protected function revisionHasPublishedTranslation(EntityInterface $entity): bool {
    $status = $entity->getEntityType()->hasKey("status") ? $entity->getEntityType()->getKey("status") : NULL;
    if (!$status || !($entity instanceof RevisionableInterface)) {
      // If the entity does not have a publishing status then
      // it is considered published.
      return TRUE;
    }
    $definition = $entity->getFieldDefinition($status);
    $property = $definition->getFieldStorageDefinition()->getMainPropertyName();
    // Ensure we are checking all translations of the revision to be saved.
    if ($entity instanceof TranslatableInterface && $entity->isTranslatable()) {
      // Loop through each language that has a translation.
      foreach ($entity->getTranslationLanguages() as $language) {
        // Load the translated revision.
        $translation = $entity->getTranslation($language->getId());
        $is_published = $translation->get($status)->$property;
        if ($is_published) {
          return TRUE;
        }
      }
    }
    // Entity is not translatable, just return its publishing status.
    return (bool) $entity->get($status)->$property;
  }

}
