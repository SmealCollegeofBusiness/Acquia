<?php

namespace Drupal\depcalc\Event;

use Drupal\depcalc\DependentEntityWrapper;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class FilterDependencyConfigEntityEvent
 */
class FilterDependencyConfigEntityEvent extends Event {

  /**
   * Whether or not to calculate this config entity as a dependency.
   *
   * @var bool
   */
  protected $calculate = TRUE;

  /**
   * The dependent entity wrapper.
   *
   * @var \Drupal\depcalc\DependentEntityWrapper
   */
  protected $wrapper;

  /**
   * FilterDependencyConfigEntityEvent constructor.
   *
   * @param \Drupal\depcalc\DependentEntityWrapper $wrapper
   *   The entity wrapper for calculation.
   */
  public function __construct(DependentEntityWrapper $wrapper) {
    $this->wrapper = $wrapper;
  }

  /**
   * Get the wrapper of the entity we are considering calculating.
   *
   * @return \Drupal\depcalc\DependentEntityWrapper
   *   The entity wrapper for calculation.
   */
  public function getWrapper() {
    return $this->wrapper;
  }

  /**
   * Get the entity we are considering calculating.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity for calculation.
   */
  public function getEntity() {
    return $this->wrapper->getEntity();
  }

  /**
   * Set whether if this config entity should be calculated for dependencies.
   *
   * @param bool $calculate
   *   Whether or not to calculate this entity.
   */
  public function setCalculable(bool $calculate) {
    $this->calculate = $calculate;
  }

  /**
   * Whether this config entity should be dependency calculated.
   *
   * @return bool
   *   Whether or not to calculate this entity.
   */
  public function isCalculable() {
    return $this->calculate;
  }

}
