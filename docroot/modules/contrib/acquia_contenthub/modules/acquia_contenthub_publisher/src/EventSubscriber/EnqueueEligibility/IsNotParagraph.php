<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to entity eligibility to prevent enqueueing paragraphs.
 */
class IsNotParagraph implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentHubPublisherEvents::ENQUEUE_CANDIDATE_ENTITY][] =
      ['onEnqueueCandidateEntity', 50];
    return $events;
  }

  /**
   * Prevent paragraphs from enqueueing.
   *
   * @param \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent $event
   *   The event to determine entity eligibility.
   *
   * @throws \Exception
   */
  public function onEnqueueCandidateEntity(ContentHubEntityEligibilityEvent $event) {
    // Never export Paragraph entities as main entities.
    // They should only be exported as dependencies.
    $entity = $event->getEntity();
    if ($entity instanceof ParagraphInterface) {
      $event->setEligibility(FALSE);
      $event->setReason('Entity is of type paragraph.');
      $event->stopPropagation();
    }
  }

}
