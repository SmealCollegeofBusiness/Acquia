<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\InvalidateDependencies;

use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\Event\InvalidateDependenciesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Re-queues for export entities whose depcalc cache got invalidated.
 *
 * @package Drupal\acquia_contenthub_publisher\EventSubscriber\InvalidateDependendencies
 */
class RePublishDependencyChanges implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[DependencyCalculatorEvents::INVALIDATE_DEPENDENCIES][] = ['onInvalidateDependencies'];
    return $events;
  }

  /**
   * Republishes entities whose depcalc cache got invalidated.
   *
   * @param \Drupal\depcalc\Event\InvalidateDependenciesEvent $event
   *   The Depcalc Invalidate dependencies event.
   *
   * @throws \Exception
   */
  public function onInvalidateDependencies(InvalidateDependenciesEvent $event) {
    /** @var \Drupal\depcalc\DependentEntityWrapperInterface[] $wrappers */
    $wrappers = $event->getWrappers();
    foreach ($wrappers as $wrapper) {
      $entity = $wrapper->getEntity();
      if ($entity) {
        _acquia_contenthub_publisher_enqueue_entity($entity, 'update');
      }
    }
  }

}
