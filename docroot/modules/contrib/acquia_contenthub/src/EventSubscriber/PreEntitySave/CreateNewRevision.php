<?php

namespace Drupal\acquia_contenthub\EventSubscriber\PreEntitySave;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\acquia_contenthub\StubTracker;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prepares the entity for a new revision if it is configured to do so.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\PreEntitySave
 */
class CreateNewRevision implements EventSubscriberInterface {

  /**
   * The stub tracker.
   *
   * @var \Drupal\acquia_contenthub\StubTracker
   */
  protected $stubTracker;

  /**
   * CreateNewRevision constructor.
   *
   * @param \Drupal\acquia_contenthub\StubTracker $stub_tracker
   *   The stub tracker.
   */
  public function __construct(StubTracker $stub_tracker) {
    $this->stubTracker = $stub_tracker;
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
    // every time it is saved or if we're saving an entity that
    // has been stubbed.
    $bundle_entity_type = $entity->getEntityType()->getBundleEntityType();
    if (!$bundle_entity_type || $this->stubTracker->hasStub($entity->getEntityTypeId(), $entity->id())) {
      return;
    }
    $bundle = $this->getEntityTypeManager()->getStorage($bundle_entity_type)->load($entity->bundle());
    $should_create_new_revision = $bundle instanceof RevisionableEntityBundleInterface && $bundle->shouldCreateNewRevision();
    if ($entity->getEntityType()->isRevisionable() && $should_create_new_revision) {
      $entity->setNewRevision(TRUE);
    }
  }

  /**
   * Returns uncached entity type manager.
   *
   * Using \Drupal::entityTypeManager() do to caching of the instance in
   * some services. Looks like a core bug.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager(): EntityTypeManagerInterface {
    return \Drupal::entityTypeManager();
  }

}
