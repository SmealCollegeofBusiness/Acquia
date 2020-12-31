<?php

namespace Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class DeleteAssets.
 *
 * @package Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook
 */
class DeleteAssets implements EventSubscriberInterface {

  /**
   * The subscription tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * DeleteAssets constructor.
   *
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   The subscription tracker.
   */
  public function __construct(SubscriberTracker $tracker) {
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
  public function onHandleWebhook(HandleWebhookEvent $event): void {
    $payload = $event->getPayload();
    $assets = $payload['assets'] ?? [];
    $client = $event->getClient();
    $settings = $client->getSettings();
    $client_uuid = $settings->getUuid();

    if ('successful' !== $payload['status'] || 'delete' !== $payload['crud'] || $payload['initiator'] === $client_uuid || empty($assets)) {
      return;
    }

    foreach ($assets as $asset) {
      if (!$this->isSupportedType($asset['type'])) {
        continue;
      }
      $entity = $this->tracker->getEntityByRemoteIdAndHash($asset['uuid']);
      if (!$entity) {
        // Clean up the tracker. The entity was deleted before import.
        $this->tracker->delete($asset['uuid']);

        // Clean up the interest list. The entity was deleted before import.
        if ($settings) {
          $webhook_uuid = $settings->getWebhook('uuid');
          $client->deleteInterest($asset['uuid'], $webhook_uuid);
        }
        continue;
      }
      $status = $this->tracker->getStatusByUuid($asset['uuid']);

      // If entity updating is disabled, delete tracking but not the entity.
      if ($status === SubscriberTracker::AUTO_UPDATE_DISABLED) {
        $this->tracker->delete($asset['uuid']);
        continue;
      }
      $entity->delete();
    }
  }

  /**
   * Determines if given entity type is supported.
   *
   * @param string $type
   *   The CDF type.
   *
   * @return bool
   *   TRUE if is supported type; FALSE otherwise.
   */
  protected function isSupportedType(string $type): bool {
    $supported_types = [
      'drupal8_content_entity',
      'drupal8_config_entity',
    ];

    return in_array($type, $supported_types, TRUE);
  }

}
