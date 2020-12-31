<?php

namespace Drupal\acquia_contenthub;

use Drupal\acquia_contenthub\Event\CleanUpStubsEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\depcalc\DependencyStack;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class StubTracker.
 *
 * Tracks entities saved during an import process and compares them against
 * known entities in the stack. Entities in the stack were actually created as
 * part of the final representation of the import, and entities saved outside
 * of that list are considered to be sample values for the purposes of
 * satisfying circular dependencies. These stubs are cleaned up as part of the
 * final step of an import.
 *
 * @package Drupal\acquia_contenthub
 */
class StubTracker {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $manager;

  /**
   * Potential stub entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $stubs = [];

  /**
   * The dependency stack.
   *
   * @var \Drupal\depcalc\DependencyStack
   */
  protected $stack;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * StubTracker constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Event dispatcher.
   */
  public function __construct(EntityTypeManagerInterface $manager, EventDispatcherInterface $dispatcher) {
    $this->manager = $manager;
    $this->dispatcher = $dispatcher;
  }

  /**
   * Sets the dependency stack object to compare potential stubs against.
   *
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack.
   */
  public function setStack(DependencyStack $stack) {
    $this->stack = $stack;
  }

  /**
   * Whether the stub tracker is currently tracking.
   *
   * @return bool
   *   Whether the stub tracker is currently tracking.
   */
  public function isTracking() : bool {
    return (bool) $this->stack;
  }

  /**
   * Adds potential stub entities to the tracker.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A potential stub entity to track.
   */
  public function track(EntityInterface $entity) {
    if ($this->isTracking()) {
      $this->stubs[$entity->getEntityTypeId()][] = $entity->id();
    }
  }

  /**
   * Removes any stub entities created during the import process.
   *
   * This method prevents sample entity data from being permanently saved in
   * the database. Tracking of potential stub entities is compared against the
   * DependencyStack object. Any entity not found in the stack is considered to
   * be a stub and is deleted.
   *
   * The stack and stub properties are reset when this is complete to prevent
   * bleed-through between runs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function cleanUp() {
    if (!$this->isTracking()) {
      return;
    }
    foreach ($this->stubs as $entity_type => $entity_ids) {
      $storage = $this->manager->getStorage($entity_type);
      foreach ($entity_ids as $id) {
        $entity = $storage->load($id);
        if (!$entity) {
          continue;
        }
        $event = new CleanUpStubsEvent($entity, $this->stack);
        $this->dispatcher->dispatch(AcquiaContentHubEvents::CLEANUP_STUBS, $event);
        if ($event->doDeleteStub()) {
          $entity->delete();
        }
      }
    }
    $this->stack = [];
    $this->stubs = [];
  }

}
