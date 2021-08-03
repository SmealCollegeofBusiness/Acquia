<?php

namespace Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Imports and updates assets.
 *
 * @package Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook
 */
class ImportUpdateAssets implements EventSubscriberInterface {

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The subscription tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * The acquia_contenthub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $channel;

  /**
   * Acquia ContentHub Admin Settings Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Client CDF object.
   *
   * @var \Acquia\ContentHubClient\CDF\ClientCDFObject
   */
  protected $clientCDFObject;

  /**
   * ImportUpdateAssets constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Event dispatcher.
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   The subscription tracker.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(
    QueueFactory $queue,
    EventDispatcherInterface $dispatcher,
    SubscriberTracker $tracker,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory) {
    $this->queue = $queue->get('acquia_contenthub_subscriber_import');
    $this->dispatcher = $dispatcher;
    $this->tracker = $tracker;
    $this->channel = $logger_factory->get('acquia_contenthub');
    $this->config = $config_factory->get('acquia_contenthub.admin_settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::HANDLE_WEBHOOK][] = 'onHandleWebhook';
    return $events;
  }

  /**
   * Handles webhook events.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   The HandleWebhookEvent object.
   *
   * @throws \Exception
   */
  public function onHandleWebhook(HandleWebhookEvent $event) {
    // @todo Would be nice to have one place with statuses list - $payload['status'].
    // @todo The same regarding $payload['crud'] and supported types ($asset['type']).
    $payload = $event->getPayload();
    $client = $event->getClient();

    // Nothing to do or log here.
    if ($payload['crud'] !== 'update') {
      return;
    }

    if ($payload['status'] !== 'successful' || !isset($payload['assets']) || !count($payload['assets'])) {
      $this->channel
        ->info("Payload will not be processed because it is not successful or it does not have assets.
        Payload data: " . print_r($payload, TRUE));
      return;
    }

    if ($payload['initiator'] === $client->getSettings()->getUuid()) {
      // Only log if we're trying to update something other than client objects.
      if ($payload['assets'][0]['type'] !== 'client') {
        $this->channel
          ->info("Payload will not be processed because its initiator is the existing client.
        Payload data: " . print_r($payload, TRUE));
      }

      return;
    }

    $uuids = [];
    $types = ['drupal8_content_entity', 'drupal8_config_entity'];
    foreach ($payload['assets'] as $asset) {
      $uuid = $asset['uuid'];
      $type = $asset['type'];
      if (!in_array($type, $types)) {
        $this->channel
          ->info("Entity with UUID $uuid was not added to the import queue because it has an unsupported type: $type");
        continue;
      }

      if ($this->tracker->isTracked($uuid)) {
        $status = $this->tracker->getStatusByUuid($uuid);
        if ($status === SubscriberTracker::AUTO_UPDATE_DISABLED) {
          $this->channel
            ->info("Entity with UUID $uuid was not added to the import queue because it has auto update disabled.");
          continue;
        }
      }

      $uuids[] = $uuid;
      $this->tracker->queue($uuid);
      $this->channel
        ->info("Attempting to add entity with UUID $uuid to the import queue.");

    }
    if ($uuids) {
      $item = new \stdClass();
      $item->uuids = implode(', ', $uuids);
      $queue_id = $this->queue->createItem($item);
      if (empty($queue_id)) {
        return;
      }
      $this->tracker->setQueueItemByUuids($uuids, $queue_id);
      $this->channel
        ->info('Entities with UUIDs @uuids added to the import queue and to the tracking table.',
          ['@uuids' => print_r($uuids, TRUE)]);
      if (!($this->config->get('send_contenthub_updates') ?? TRUE)) {
        return;
      }
      // Add entities to interest list.
      $client->addEntitiesToInterestList($client->getSettings()->getWebhook('uuid'), $uuids);

      // Update Client CDF metrics.
      $settings = $client->getSettings();
      $event = new BuildClientCdfEvent(ClientCDFObject::create($settings->getUuid(), ['settings' => $settings->toArray()]));
      $this->dispatcher->dispatch(AcquiaContentHubEvents::BUILD_CLIENT_CDF, $event);
      $this->clientCDFObject = $event->getCdf();
      $client->putEntities($this->clientCDFObject);
    }
  }

}
