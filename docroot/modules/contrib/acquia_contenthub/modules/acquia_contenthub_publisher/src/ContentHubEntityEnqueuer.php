<?php

namespace Drupal\acquia_contenthub_publisher;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Syndication\SyndicationStatus;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Libs\InterestList\InterestListTrait;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Enqueues candidate entities for publishing.
 */
class ContentHubEntityEnqueuer {

  use InterestListTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
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
    ConfigFactoryInterface $config_factory,
    ClientFactory $client_factory,
    LoggerChannelInterface $logger_channel,
    EventDispatcherInterface $dispatcher,
    QueueFactory $queue_factory,
    PublisherTracker $publisher_tracker
  ) {
    $this->configFactory = $config_factory;
    $this->clientFactory = $client_factory;
    $this->logger = $logger_channel;
    $this->dispatcher = $dispatcher;
    $this->queue = $queue_factory->get('acquia_contenthub_publish_export');
    $this->publisherTracker = $publisher_tracker;
    $this->config = $this->configFactory->get('acquia_contenthub.admin_settings');
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

    $this->logger
      ->info(
      'Entity with (UUID: @uuid, Entity type: @entity_type) added to the export queue and to the tracking table.',
      [
        '@uuid' => $uuid,
        '@entity_type' => $entity_type_id,
      ]
    );

    $send_update = $this->config->get('send_contenthub_updates') ?? TRUE;
    if (!$send_update) {
      return;
    }

    $client = $this->clientFactory->getClient();
    if (!$client instanceof ContentHubClient) {
      $msg = 'Error trying to connect to the Content Hub. Make sure this site is registered to Content hub.';
      $this->logger->error($msg);

      throw new \Exception($msg);
    }

    $interest_list = $this->buildInterestList([$uuid], SyndicationStatus::QUEUED_TO_EXPORT);
    $webhook = $client->getSettings()->getWebhook('uuid');
    try {
      $client->addEntitiesToInterestListBySiteRole($webhook, 'PUBLISHER', $interest_list);
      $this->logger
        ->info('The entity (@entity_type: @entity_uuid) has been added to the interest list with status "@syndication_status" for webhook: @webhook.',
          [
            '@entity_type' => $entity->getEntityTypeId(),
            '@entity_uuid' => $uuid,
            '@syndication_status' => SyndicationStatus::QUEUED_TO_EXPORT,
            '@webhook' => $webhook,
          ]
        );
    }
    catch (\Exception $e) {
      $this->logger
        ->error('Error adding the entity (@entity_type: @entity_uuid) to the interest list with status "@syndication_status" for webhook: @webhook. Error message: @exception.',
          [
            '@entity_type' => $entity->getEntityTypeId(),
            '@entity_uuid' => $uuid,
            '@syndication_status' => SyndicationStatus::QUEUED_TO_EXPORT,
            '@webhook' => $webhook,
            '@exception' => $e->getMessage(),
          ]
        );
    }
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
    $this->dispatcher->dispatch($event, ContentHubPublisherEvents::ENQUEUE_CANDIDATE_ENTITY);

    return $event;
  }

}
