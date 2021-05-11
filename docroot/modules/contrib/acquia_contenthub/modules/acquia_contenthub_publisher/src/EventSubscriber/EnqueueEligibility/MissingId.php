<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Any entity that is missing its id shouldn't be enqueued.
 *
 * @package Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility
 */
class MissingId implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentHubPublisherEvents::ENQUEUE_CANDIDATE_ENTITY][] =
      ['onEnqueueCandidateEntity', 999];

    return $events;
  }

  /**
   * Skips entities that are missing ids.
   *
   * @param \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent $event
   *   The event to determine entity eligibility.
   *
   * @throws \Exception
   */
  public function onEnqueueCandidateEntity(ContentHubEntityEligibilityEvent $event) {
    $entity = $event->getEntity();

    if (empty($entity->id())) {
      $event->setEligibility(FALSE);
      $event->stopPropagation();
    }
  }

}
