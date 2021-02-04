<?php

namespace Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
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
   * ImportUpdateAssets constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Event dispatcher.
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   The subscription tracker.
   */
  public function __construct(QueueFactory $queue, EventDispatcherInterface $dispatcher, SubscriberTracker $tracker) {
    $this->queue = $queue->get('acquia_contenthub_subscriber_import');
    $this->dispatcher = $dispatcher;
    $this->tracker = $tracker;
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
    $payload = $event->getPayload();
    $client = $event->getClient();
    // @todo Would be nice to have one place with statuses list - $payload['status'].
    // @todo The same regarding $payload['crud'] and supported types ($asset['type']).
    if ($payload['status'] == 'successful' && $payload['crud'] == 'update' && isset($payload['assets']) && count($payload['assets']) && $payload['initiator'] != $client->getSettings()->getUuid()) {
      $uuids = [];
      $types = ['drupal8_content_entity', 'drupal8_config_entity'];
      foreach ($payload['assets'] as $asset) {
        if (in_array($asset['type'], $types)) {
          if ($this->tracker->isTracked($asset['uuid'])) {
            $status = $this->tracker->getStatusByUuid($asset['uuid']);
            if ($status === SubscriberTracker::AUTO_UPDATE_DISABLED) {
              continue;
            }
          }
          $uuids[] = $asset['uuid'];
          $this->tracker->queue($asset['uuid']);
        }
      }
      if ($uuids) {
        $client->addEntitiesToInterestList($client->getSettings()->getWebhook('uuid'), $uuids);
        $item = new \stdClass();
        $item->uuids = implode(', ', $uuids);
        $queue_id = $this->queue->createItem($item);
        if (empty($queue_id)) {
          return;
        }
        $this->tracker->setQueueItemByUuids($uuids, $queue_id);
        $event = new BuildClientCdfEvent(ClientCDFObject::create($client->getSettings()->getUuid(), ['settings' => $client->getSettings()->toArray()]));
        $this->dispatcher->dispatch(AcquiaContentHubEvents::BUILD_CLIENT_CDF, $event);
        $this->clientCDFObject = $event->getCdf();
        $client->putEntities($this->clientCDFObject);
      }
    }
  }

}
