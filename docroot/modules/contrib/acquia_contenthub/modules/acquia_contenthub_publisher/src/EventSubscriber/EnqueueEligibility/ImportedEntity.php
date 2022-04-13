<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub\StubTracker;
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
   * The stub tracker.
   *
   * @var \Drupal\acquia_contenthub\StubTracker
   */
  protected $stubTracker;

  /**
   * ImportedEntity constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module Handler Interface.
   * @param \Drupal\acquia_contenthub\StubTracker $stub_tracker
   *   The stub tracker.
   */
  public function __construct(ModuleHandlerInterface $module_handler, StubTracker $stub_tracker) {
    if ($module_handler->moduleExists('acquia_contenthub_subscriber')) {
      $this->subscriberTracker = \Drupal::service('acquia_contenthub_subscriber.tracker');
    }
    $this->stubTracker = $stub_tracker;
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
    // Bail early if this isn't a dual site.
    if (empty($this->subscriberTracker)) {
      return;
    }
    $entity_uuid = $event->getEntity()->uuid();
    $imported_entities = $this->stubTracker->getImportedEntities();
    if ($this->subscriberTracker->isTracked($entity_uuid) || in_array($entity_uuid, $imported_entities, TRUE)) {
      $event->setEligibility(FALSE);
      $event->setReason('Entity has already been imported.');
      $event->stopPropagation();
    }
  }

}
