<?php

namespace Drupal\acquia_contenthub_publisher;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Enqueues candidate entities for publishing.
 */
class ContentHubEntityEnqueuer {

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Logger Channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The publisher exporting queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The publisher tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $publisherTracker;

  /**
   * ContentHubEntityEnqueuer constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   The logger channel.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\acquia_contenthub_publisher\PublisherTracker $publisher_tracker
   *   The Publisher Tracker.
   *
   * @throws \ReflectionException
   */
  public function __construct(
    ClientFactory $client_factory,
    LoggerChannelInterface $logger_channel,
    EventDispatcherInterface $dispatcher,
    QueueFactory $queue_factory,
    PublisherTracker $publisher_tracker
  ) {
    $this->clientFactory = $client_factory;
    $this->logger = $logger_channel;
    $this->dispatcher = $dispatcher;
    $this->queue = $queue_factory->get('acquia_contenthub_publish_export');
    $this->publisherTracker = $publisher_tracker;
  }

  /**
   * Enqueues candidate entities for publishing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to enqueue to ContentHub.
   * @param string $op
   *   Entity operation.
   *
   * @throws \Exception
   */
  public function enqueueEntity(EntityInterface $entity, string $op): void {
    if (!$this->clientFactory->isConfigurationSet()) {
      return;
    }

    $entity_type_id = $entity->getEntityTypeId();
    $uuid = $entity->uuid();
    $this->logger->info(
      'Attempting to add entity with (UUID: @uuid, Entity type: @entity_type) to the export queue after operation: @op.',
      [
        '@uuid' => $uuid,
        '@entity_type' => $entity_type_id,
        '@op' => $op,
      ]
    );

    $event = $this->dispatchEvent($entity, $op);
    if (!$event->getEligibility()) {
      $reason = $event->getReason();
      $this->logger->info('Entity with (UUID: @uuid, Entity type: @entity_type) not eligible to be added to the export queue. Reason: @reason', [
        '@uuid' => $uuid,
        '@entity_type' => $entity_type_id,
        '@reason' => $reason,
      ]);
      return;
    }

    $queue_id = $this->addItemsToExportQueue($entity, $event);
    $this->publisherTracker->queue($entity);
    $this->publisherTracker->setQueueItemByUuid($uuid, $queue_id);

    $this->logger->info(
      'Entity with (UUID: @uuid, Entity type: @entity_type) added to the export queue and to the tracking table.',
      [
        '@uuid' => $uuid,
        '@entity_type' => $entity_type_id,
      ]
    );
  }

  /**
   * Adds items to publisher export queue.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to add to publisher export queue.
   * @param \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent $event
   *   The ContentHubEntityEligibilityEvent.
   *
   * @return int|null
   *   Queue id.
   */
  protected function addItemsToExportQueue(EntityInterface $entity, ContentHubEntityEligibilityEvent $event): ?int {
    $item = new \stdClass();
    $item->type = $entity->getEntityTypeId();
    $item->uuid = $entity->uuid();

    if ($event->getCalculateDependencies() === FALSE) {
      $item->calculate_dependencies = FALSE;
    }

    return $this->queue->createItem($item);
  }

  /**
   * Dispatches event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to enqueue to ContentHub.
   * @param string $op
   *   Entity operation.
   *
   * @return \Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent
   *   ContentHubEntityEligibilityEvent instance.
   */
  protected function dispatchEvent(EntityInterface $entity, string $op): ContentHubEntityEligibilityEvent {
    $event = new ContentHubEntityEligibilityEvent($entity, $op);
    $this->dispatcher->dispatch(ContentHubPublisherEvents::ENQUEUE_CANDIDATE_ENTITY, $event);

    return $event;
  }

}
