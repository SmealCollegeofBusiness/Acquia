<?php

namespace Drupal\acquia_contenthub_dashboard\EventSubscriber\HandleWebhook;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Update allowed origins config.
 *
 * @package Drupal\acquia_contenthub_dashboard\EventSubscriber\HandleWebhook
 */
class UpdateAllowedOrigins implements EventSubscriberInterface {

  /**
   * Acquia ContentHub Admin Settings Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * UpdateAllowedOrigins constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->getEditable('acquia_contenthub_dashboard.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::HANDLE_WEBHOOK][] = ['onHandleWebhook', 110];
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
    if (!$this->config->get('auto_publisher_discovery')) {
      return;
    }

    $payload = $event->getPayload();
    $assets = isset($payload['assets']) ? current($payload['assets']) : [];
    $client = $event->getClient();
    $settings = $client->getSettings();
    $client_uuid = $settings->getUuid();

    if (!$this->isPayloadApplicable($payload, $client_uuid)) {
      return;
    }

    $publisher_uuid = $assets['uuid'] ?? '';
    if (empty($publisher_uuid)) {
      return;
    }

    $client_entity = $client->getEntity($publisher_uuid);
    if (!$client_entity instanceof ClientCDFObject) {
      return;
    }

    $pub_attribute = $client_entity->getAttribute('publisher');
    if (!$pub_attribute) {
      return;
    }

    $is_publisher = $pub_attribute->getValue()['und'] ?? FALSE;
    if (!$is_publisher) {
      return;
    }

    $metadata = $client_entity->getMetadata();
    $webhook = $metadata['settings']['webhook'] ?? [];
    if (empty($webhook)) {
      return;
    }

    $webhook_url[] = $webhook['settings_url'];
    $saved_origins = $this->config->get('allowed_origins') ?? [];
    $origins_to_add = array_unique(array_merge($saved_origins, $webhook_url));

    $this->config->set('allowed_origins', $origins_to_add);
    $this->config->save();
  }

  /**
   * Validate the payload.
   *
   * @param array $payload
   *   The payload.
   * @param string $client_uuid
   *   Client uuid.
   *
   * @return bool
   *   TRUE if valid FALSE otherwise.
   */
  protected function isPayloadApplicable(array $payload, string $client_uuid): bool {
    $assets = isset($payload['assets']) ? current($payload['assets']) : [];

    return isset($payload['status']) && $payload['status'] === 'successful'
      && isset($payload['crud']) && $payload['crud'] === 'update'
      && $payload['initiator'] && $payload['initiator'] !== $client_uuid
      && !empty($assets)
      && isset($assets['type']) && $assets['type'] === 'client';
  }

}
