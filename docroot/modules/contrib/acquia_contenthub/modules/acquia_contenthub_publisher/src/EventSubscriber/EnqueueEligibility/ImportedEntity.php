<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Any entity that has been previously imported shouldn't be enqueued.
 *
 * @package Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility
 */
class ImportedEntity implements EventSubscriberInterface {

  /**
   * The subscriber tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $subscriberTracker;

  /**
   * ImportedEntity constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module Handler Interface.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    if ($module_handler->moduleExists('acquia_contenthub_subscriber')) {
      $this->subscriberTracker = \Drupal::service('acquia_contenthub_subscriber.tracker');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentHubPublisherEvents::ENQUEUE_CANDIDATE_ENTITY][] =
      ['onEnqueueCandidateEntity', 500];
    return $events;
  }

  /**
   * Prevents tracked imported entities to end up in the export queue.
   *
   * @param \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent $event
   *   The event to determine entity eligibility.
   *
   * @throws \Exception
   */
  public function onEnqueueCandidateEntity(ContentHubEntityEligibilityEvent $event) {
    $entity = $event->getEntity();
    if (!empty($this->subscriberTracker) && $this->subscriberTracker->isTracked($entity->uuid())) {
      $event->setEligibility(FALSE);
      $event->stopPropagation();
    }
  }

}
