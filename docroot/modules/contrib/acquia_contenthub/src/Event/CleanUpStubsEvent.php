<?php

namespace Drupal\acquia_contenthub\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\depcalc\DependencyStack;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event to clean up stubs after import.
 *
 * @package Drupal\acquia_contenthub\Event
 */
class CleanUpStubsEvent extends Event {

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $entity;

  /**
   * The dependency stack.
   *
   * @var \Drupal\depcalc\DependencyStack
   */
  protected $stack;

  /**
   * By default, we do not delete the stubs.
   *
   * @var \Drupal\depcalc\DependencyStack
   * @see \Drupal\acquia_contenthub\EventSubscriber\CleanupStubs\DefaultStubCleanup
   */
  protected $delete = FALSE;

  /**
   * CleanUpStubsEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity interface.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack.
   */
  public function __construct(EntityInterface $entity, DependencyStack $stack) {
    $this->entity = $entity;
    $this->stack = $stack;
  }

  /**
   * Get the entity interface.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity interface for this event.
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Get the dependency stack.
   *
   * @return \Drupal\depcalc\DependencyStack
   *   The dependency stack for this event.
   */
  public function getStack(): DependencyStack {
    return $this->stack;
  }

  /**
   * Set the stub to be deleted.
   */
  public function deleteStub() {
    $this->delete = TRUE;
  }

  /**
   * Get the delete status.
   *
   * @return bool
   *   Delete status for this event.
   *
   * @see \Drupal\acquia_contenthub\StubTracker::cleanUp
   */
  public function doDeleteStub() : bool {
    return $this->delete;
  }

}
