<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\DeleteRemoteEntity;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\DeleteRemoteEntityEvent;
use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Removes deleted remote entities from the publisher tracking table.
 */
class UpdateTracking implements EventSubscriberInterface {

  /**
   * The publisher tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $tracker;

  /**
   * The acquia_contenthub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $channel;

  /**
   * UpdateTracking constructor.
   *
   * @param \Drupal\acquia_contenthub_publisher\PublisherTracker $tracker
   *   The publisher tracker.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(PublisherTracker $tracker, LoggerChannelFactoryInterface $logger_factory) {
    $this->tracker = $tracker;
    $this->channel = $logger_factory->get('acquia_contenthub_publisher');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::DELETE_REMOTE_ENTITY][] = 'onDeleteRemoteEntity';
    return $events;
  }

  /**
   * Removes deleted remote entities from the publisher tracking table.
   *
   * @param \Drupal\acquia_contenthub\Event\DeleteRemoteEntityEvent $event
   *   The DeleteRemoteEntityEvent object.
   *
   * @throws \Exception
   */
  public function onDeleteRemoteEntity(DeleteRemoteEntityEvent $event) {
    if ($this->tracker->get($event->getUuid())) {
      $this->tracker->delete('entity_uuid', $event->getUuid());
      $this->channel
        ->info(sprintf("Removed tracking for entity with UUID = \"%s\".", $event->getUuid()));
    }
  }

}
