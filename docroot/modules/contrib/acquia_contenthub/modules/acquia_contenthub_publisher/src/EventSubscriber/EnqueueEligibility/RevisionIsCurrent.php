<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents;
use Drupal\acquia_contenthub_publisher\EntityModeratedRevision;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to entity eligibility to prevent enqueuing unpublished revisions.
 */
class RevisionIsCurrent implements EventSubscriberInterface {

  /**
   * The Entity Moderated Revision Service.
   *
   * @var \Drupal\acquia_contenthub_publisher\EntityModeratedRevision
   */
  protected $entityModeratedRevision;

  /**
   * ImportedEntity constructor.
   *
   * @param \Drupal\acquia_contenthub_publisher\EntityModeratedRevision $entity_moderated_revision
   *   Entity Moderated Revision Service.
   */
  public function __construct(EntityModeratedRevision $entity_moderated_revision) {
    $this->entityModeratedRevision = $entity_moderated_revision;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentHubPublisherEvents::ENQUEUE_CANDIDATE_ENTITY][] =
      ['onEnqueueCandidateEntity', 100];
    return $events;
  }

  /**
   * Allows to enqueue only the current revision of an entity.
   *
   * @param \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent $event
   *   The event to determine entity eligibility.
   *
   * @throws \Exception
   */
  public function onEnqueueCandidateEntity(ContentHubEntityEligibilityEvent $event) {
    $entity = $event->getEntity();

    // If entity transitioned from published to unpublished state
    // then do not prevent export.
    if ($this->entityModeratedRevision->isTransitionedToUnpublished($entity)) {
      return;
    }

    if (!$this->entityModeratedRevision->isPublishedRevision($entity)) {
      // This revision has no published translation then do not syndicate.
      $event->setEligibility(FALSE);
      $event->stopPropagation();
    }
  }

}
