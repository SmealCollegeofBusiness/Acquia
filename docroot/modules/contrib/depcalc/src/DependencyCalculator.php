<?php

namespace Drupal\depcalc;

use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Calculates all the dependencies of a given entity.
 *
 * This class calculates all the dependencies of any entity. Pass an entity of
 * any sort to the calculateDependencies() method, and this class will recurse
 * through all the existing inter-dependencies that it knows about. New
 * dependency collectors can be add via the
 */
class DependencyCalculator {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The DependencyCalculator constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $dispatcher) {
    $this->dispatcher = $dispatcher;
  }

  /**
   * Calculates the dependencies.
   *
   * @param \Drupal\depcalc\DependentEntityWrapperInterface $wrapper
   *   The dependency wrapper for the entity to calculate dependencies.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   An array of pre-calculated dependencies to prevent recalculation.
   * @param array $dependencies
   *   (optional) An array of dependencies by reference. Internally used.
   *
   * @return array
   *   The list of the dependencies.
   */
  public function calculateDependencies(DependentEntityWrapperInterface $wrapper, DependencyStack $stack, array &$dependencies = []) {
    if (empty($dependencies['module'])) {
      $dependencies['module'] = [];
    }
    // Prevent handling the same entity more than once.
    if (!empty($dependencies[$wrapper->getUuid()])) {
      return $dependencies;
    }
    // Prevent handling the same entity more than once.
    try {
      if ($dependency = $stack->getDependency($wrapper->getUuid())) {
        // Get module dependencies from cache.
        $modules = $dependency->getModuleDependencies();
        if ($modules) {
          $wrapper->addModuleDependencies($modules);
          $dependencies['module'] = $modules;
        }
        // Get list of the dependencies from cache.
        $dependencies = $stack->getDependenciesByUuid(array_keys($dependency->getDependencies()));
        // Add the dependencies from cache to the main wrapper as well as stack.
        $wrapper->addDependencies($stack, ...array_values($dependencies));
        $dependencies[$wrapper->getUuid()] = $dependency;
        return $dependencies;
      }
    }
    catch (\Exception $exception) {
      if ($exception->getMessage() !== sprintf("Missing Dependency requested: %s.", $wrapper->getUuid())) {
        throw $exception;
      }
    }

    // We add the dependency to the stack because we need it there but the dependency data
    // is WRONG, so don't make it permanent in case something breaks before we get to
    // resave it later when all dependencies are correctly calculated.
    $stack->addDependency($wrapper, FALSE);
    $event = new CalculateEntityDependenciesEvent($wrapper, $stack);
    $this->dispatcher->dispatch(DependencyCalculatorEvents::CALCULATE_DEPENDENCIES, $event);
    // Update the stack with the newest $wrapper and the correct dependencies.
    $stack->addDependency($wrapper);

    $modules = $event->getModuleDependencies();
    if ($modules) {
      $wrapper->addModuleDependencies($modules);
    }
    $dependencies = $stack->getDependenciesByUuid(array_keys($event->getDependencies()));
    $wrapper->addDependencies($stack, ...array_values($dependencies));
    $dependencies[$wrapper->getUuid()] = $wrapper;
    // Extract the name of the module providing this entity type.
    $entity_module = $wrapper->getEntity()->getEntityType()->getProvider();
    $entity_module = \Drupal::moduleHandler()->moduleExists($entity_module) ? [$entity_module] : [];
    $dependencies['module'] = $event->getModuleDependencies() + $entity_module;
    return $dependencies;
  }

}
