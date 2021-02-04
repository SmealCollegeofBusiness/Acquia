<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CleanupStubs;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CleanUpStubsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cleans up default stubs after import.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\CleanupStubs
 */
class DefaultStubCleanup implements EventSubscriberInterface {

  /**
   * Get subscribed events and add onStubsCleanup for default stubs.
   *
   * @return array
   *   Array of $events.
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[AcquiaContentHubEvents::CLEANUP_STUBS][] = 'onStubsCleanup';
    return $events;
  }

  /**
   * By default, we delete all stubs.
   *
   * @param \Drupal\acquia_contenthub\Event\CleanUpStubsEvent $event
   *   The cleanup stubs $event.
   *
   * @see \Drupal\acquia_contenthub\EventSubscriber\CleanupStubs\DefaultStubCleanup
   * @see \Drupal\acquia_contenthub\StubTracker::cleanUp
   */
  public function onStubsCleanup(CleanUpStubsEvent $event) {
    if (!$event->getStack()->hasDependency($event->getEntity()->uuid())) {
      $event->deleteStub();
    }
  }

}
