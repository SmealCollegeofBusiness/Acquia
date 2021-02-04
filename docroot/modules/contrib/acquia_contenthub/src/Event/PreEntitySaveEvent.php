<?php

namespace Drupal\acquia_contenthub\Event;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\Core\Entity\EntityInterface;
use Drupal\depcalc\DependencyStack;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event that fires prior to an entity save.
 *
 * @package Drupal\acquia_contenthub\Event
 */
class PreEntitySaveEvent extends Event {

  /**
   * The entity we're about to save.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The dependency stack.
   *
   * @var \Drupal\depcalc\DependencyStack
   */
  protected $stack;

  /**
   * The CDF object.
   *
   * @var \Acquia\ContentHubClient\CDF\CDFObject
   */
  protected $cdf;

  /**
   * PreEntitySaveEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity about to be saved.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   The dependency stack.
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The cdf object.
   */
  public function __construct(EntityInterface $entity, DependencyStack $stack, CDFObject $cdf) {
    $this->entity = $entity;
    $this->stack = $stack;
    $this->cdf = $cdf;
  }

  /**
   * Get the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Entity Interface.
   */
  public function getEntity() : EntityInterface {
    return $this->entity;
  }

  /**
   * Get the dependency stack.
   *
   * @return \Drupal\depcalc\DependencyStack
   *   Dependency Stack.
   */
  public function getStack() : DependencyStack {
    return $this->stack;
  }

  /**
   * Get the CDF object from which this entity was derived.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject
   *   CDF Object.
   */
  public function getCdf() : CDFObject {
    return $this->cdf;
  }

}
