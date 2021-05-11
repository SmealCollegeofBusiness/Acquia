<?php

namespace Drupal\acquia_contenthub\EventSubscriber\PreEntitySave;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prepares the eneity for a new revision if it is configured to do so.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\PreEntitySave
 */
class CreateNewRevision implements EventSubscriberInterface {

  /**
   * The Entity Type Manager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * CreateNewRevision constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager Service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PRE_ENTITY_SAVE][] =
      ['onPreEntitySave', 100];
    return $events;
  }

  /**
   * Creates a new revision for revisionable entities being imported.
   *
   * @param \Drupal\acquia_contenthub\Event\PreEntitySaveEvent $event
   *   The pre entity save event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onPreEntitySave(PreEntitySaveEvent $event) {
    $entity = $event->getEntity();
    // Check whether the entity is configured to create a new revision
    // every time it is saved.
    $bundle_entity_type = $entity->getEntityType()->getBundleEntityType();
    if (!$bundle_entity_type) {
      return;
    }
    $bundle = $this->entityTypeManager->getStorage($bundle_entity_type)->load($entity->bundle());
    $should_create_new_revision = $bundle instanceof RevisionableEntityBundleInterface && $bundle->shouldCreateNewRevision();
    if ($entity->getEntityType()->isRevisionable() && $should_create_new_revision) {
      $entity->setNewRevision(TRUE);
    }
  }

}
