<?php

namespace Drupal\acquia_contenthub\EventSubscriber\HandleWebhook;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\Core\Config\Config;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles webhook with a payload to regenerate shared secret.
 *
 * And save it in config.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\HandleWebhook
 */
class RegenerateSharedSecret implements EventSubscriberInterface {

  /**
   * Webhook's regenerate shared secret crud.
   */
  public const CRUD = 'regenerate';

  /**
   * Content Hub config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Content Hub Logger Channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[AcquiaContentHubEvents::HANDLE_WEBHOOK][] =
      ['onHandleWebhook', 1000];
    return $events;
  }

  /**
   * RegenerateSharedSecret constructor.
   *
   * @param \Drupal\Core\Config\Config $config
   *   Content Hub config.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Content Hub logger channel.
   */
  public function __construct(Config $config, LoggerChannelInterface $logger) {
    $this->config = $config;
    $this->logger = $logger;
  }

  /**
   * On handle webhook event.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   The handle webhook event.
   */
  public function onHandleWebhook(HandleWebhookEvent $event): void {
    $payload = $event->getPayload();
    if (empty($payload['crud']) || self::CRUD !== $payload['crud']) {
      return;
    }
    $shared_secret = $payload['message'] ?? '';
    if (empty($shared_secret)) {
      $this->logger->error('Regenerated shared secret not found in the payload.');
      return;
    }

    $this->config->set('shared_secret', $shared_secret)->save();
    $this->logger->info('Regenerated shared secret has been updated in Content Hub settings config successfully.');
  }

}
