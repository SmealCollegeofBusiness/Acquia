<?php

namespace Drupal\Tests\depcalc\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;

trait DependencyHelperTrait {

  /**
   * Calculates all the dependencies of a given entity.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * Returns the list of entity dependencies.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The list of UUIDs of dependencies (entities).
   *
   * @throws \Exception
   */
  protected function getEntityDependencies(EntityInterface $entity) {
    $wrapper = $this->getDependentEntityWrapper($entity);

    return array_keys($wrapper->getDependencies());
  }

  /**
   * Returns the list of module dependencies.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return array
   *   The list of UUIDs of entity dependencies.
   *
   * @throws \Exception
   */
  protected function getModuleDependencies(EntityInterface $entity) {
    $wrapper = $this->getDependentEntityWrapper($entity);

    return $wrapper->getModuleDependencies();
  }

  /**
   * Calculate entity dependencies and return the DependentEntityWrapper object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Drupal\depcalc\DependentEntityWrapper
   *   The DependentEntityWrapper object.
   *
   * @throws \Exception
   */
  protected function getDependentEntityWrapper(EntityInterface $entity): DependentEntityWrapper {
    $dependentEntityWrapper = new DependentEntityWrapper($entity);
    $stack = new DependencyStack();
    $this->calculator->calculateDependencies($dependentEntityWrapper, $stack);

    return $stack->getDependency($entity->uuid());
  }

}
