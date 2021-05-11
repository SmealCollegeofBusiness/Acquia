<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Any entity that is already in the export queue shouldn't be enqueued.
 *
 * @package Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility
 */
class IsAlreadyEnqueued implements EventSubscriberInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The publisher tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $tracker;

  /**
   * IsAlreadyEnqueued constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\acquia_contenthub_publisher\PublisherTracker $tracker
   *   The publisher tracker.
   */
  public function __construct(Connection $database, PublisherTracker $tracker) {
    $this->database = $database;
    $this->tracker = $tracker;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentHubPublisherEvents::ENQUEUE_CANDIDATE_ENTITY][] =
      ['onEnqueueCandidateEntity', 50];
    return $events;
  }

  /**
   * Prevents entities already in the export queue to be enqueued again.
   *
   * @param \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent $event
   *   The event to determine entity eligibility.
   *
   * @throws \Exception
   */
  public function onEnqueueCandidateEntity(ContentHubEntityEligibilityEvent $event) {
    $uuid = $event->getEntity()->uuid();
    if (!Uuid::isValid($uuid)) {
      return;
    }
    // Get the queue_id from the tracker first.
    $item_id = $this->tracker->getQueueId($uuid);
    if (!empty($item_id)) {
      // This entity is already in the export queue. Log about it?
      $event->setEligibility(FALSE);
      $event->stopPropagation();
    }

  }

}
