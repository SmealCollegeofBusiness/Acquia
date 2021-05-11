<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CleanupStubs;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CleanUpStubsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cleans up Content moderation state stubs after import.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\CleanupStubs
 */
class ContentModerationStateStubCleanup implements EventSubscriberInterface {

  /**
   * Get subscribed events and add onStubsCleanup for content moderation.
   *
   * @return array
   *   Array of $events.
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[AcquiaContentHubEvents::CLEANUP_STUBS][] = ['onStubsCleanup', 10];
    return $events;
  }

  /**
   * By default, we delete all stubs. Moderation is dynamic, so don't delete it.
   *
   * @param \Drupal\acquia_contenthub\Event\CleanUpStubsEvent $event
   *   The cleanup stubs $event.
   *
   * @see \Drupal\acquia_contenthub\EventSubscriber\CleanupStubs\DefaultStubCleanup
   * @see \Drupal\acquia_contenthub\StubTracker::cleanUp
   */
  public function onStubsCleanup(CleanUpStubsEvent $event) {
    if ($event->getEntity()->getEntityTypeId() === 'content_moderation_state') {
      $event->stopPropagation();
    }
  }

}
