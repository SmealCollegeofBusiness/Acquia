<?php

namespace Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Deletes assets based on a webhook.
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
   * Acquia ContentHub Admin Settings Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * DeleteAssets constructor.
   *
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   The subscription tracker.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config Factory.
   */
  public function __construct(SubscriberTracker $tracker, ConfigFactoryInterface $config_factory) {
    $this->tracker = $tracker;
    $this->config = $config_factory->getEditable('acquia_contenthub.admin_settings');
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

    $send_update = $this->config->get('send_contenthub_updates') ?? TRUE;
    foreach ($assets as $asset) {
      if (!$this->isSupportedType($asset['type'])) {
        continue;
      }
      $entity = $this->tracker->getEntityByRemoteIdAndHash($asset['uuid']);
      if (!$entity) {
        // Clean up the tracker. The entity was deleted before import.
        $this->tracker->delete($asset['uuid']);

        // Clean up the interest list. The entity was deleted before import.
        if ($settings && $send_update) {
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
