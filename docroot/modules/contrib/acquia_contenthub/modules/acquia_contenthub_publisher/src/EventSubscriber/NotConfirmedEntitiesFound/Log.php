<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\NotConfirmedEntitiesFound;

use Drupal\acquia_contenthub_publisher\ContentHubPublisherEvents;
use Drupal\acquia_contenthub_publisher\Event\NotConfirmedEntitiesFoundEvent;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class Log.
 *
 * @package Drupal\acquia_contenthub_publisher\EventSubscriber\NotConfirmedEntitiesFound
 */
class Log implements EventSubscriberInterface {

  /**
   * Acquia Logger Channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Log constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   Acquia Logger Channel.
   */
  public function __construct(LoggerChannelInterface $logger_channel) {
    $this->logger = $logger_channel;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ContentHubPublisherEvents::NOT_CONFIRMED_ENTITIES_FOUND][] = 'onNotConfirmedEntitiesFound';

    return $events;
  }

  /**
   * Logs a brief information about "stale" entities found.
   *
   * @param \Drupal\acquia_contenthub_publisher\Event\NotConfirmedEntitiesFoundEvent $event
   *   Event.
   */
  public function onNotConfirmedEntitiesFound(NotConfirmedEntitiesFoundEvent $event) {
    $stale_entities_breakdown = [];
    array_map(function ($item) use (&$stale_entities_breakdown) {
      $stale_entities_breakdown[$item->entity_type][] = $item->entity_id;
    }, $event->getItems());
    array_walk($stale_entities_breakdown, function (&$value, $key) {
      $value = $key . ' [' . implode(', ', $value) . ']';
    });

    $this->logger->warning(
      '"Stale" entities found (type [ids]): %entities_breakdown.',
      ['%entities_breakdown' => implode('; ', $stale_entities_breakdown)]);
  }

}
