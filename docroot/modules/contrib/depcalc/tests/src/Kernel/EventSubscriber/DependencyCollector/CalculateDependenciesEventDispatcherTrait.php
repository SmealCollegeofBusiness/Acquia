<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\Core\Entity\EntityInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;

/**
 * Provides a method to dispatch calculate dependencies event.
 */
trait CalculateDependenciesEventDispatcherTrait {

  /**
   * Dispatches dependency calculation event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to use in the event.
   *
   * @return \Drupal\depcalc\Event\CalculateEntityDependenciesEvent
   *   The event.
   *
   * @throws \Exception
   */
  protected function dispatchCalculateDependencies(EntityInterface $entity): CalculateEntityDependenciesEvent {
    $wrapper = new DependentEntityWrapper($entity);
    $dependencies = new DependencyStack();
    $event = new CalculateEntityDependenciesEvent($wrapper, $dependencies);
    $this->container->get('event_dispatcher')->dispatch(DependencyCalculatorEvents::CALCULATE_DEPENDENCIES, $event);
    return $event;
  }

}
