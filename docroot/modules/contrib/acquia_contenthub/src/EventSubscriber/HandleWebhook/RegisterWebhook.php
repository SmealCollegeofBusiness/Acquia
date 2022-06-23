<?php

namespace Drupal\acquia_contenthub\EventSubscriber\HandleWebhook;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub\Libs\Traits\HandleResponseTrait;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Responsible for handling site registration webhook responses.
 */
class RegisterWebhook implements EventSubscriberInterface {

  use HandleResponseTrait;

  /**
   * The acquia_contenthub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $channel;

  /**
   * RegisterWebhook constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->channel = $logger_factory->get('acquia_contenthub');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::HANDLE_WEBHOOK][] = 'onHandleWebhook';
    return $events;
  }

  /**
   * The method called for the AcquiaContentHubEvents::HANDLE_WEBHOOK event.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   The dispatched event.
   */
  public function onHandleWebhook(HandleWebhookEvent $event) {
    $payload = $event->getPayload();
    if ($payload['status'] !== 'pending') {
      return;
    }

    $client = $event->getClient();
    $uuid = $payload['uuid'] ?? FALSE;
    if ($uuid && $payload['publickey'] === $client->getSettings()->getApiKey()) {
      $signed_response = $this->getResponse($event);
      $event->setResponse($signed_response);
      return;
    }

    $ip_address = $event->getRequest()->getClientIp();
    $message = new FormattableMarkup('Webhook [from IP = @IP] rejected (initiator and/or publickey do not match local settings): @whook', [
      '@IP' => $ip_address,
      '@whook' => print_r($payload, TRUE),
    ]);
    $this->channel->debug($message);
    $event->setResponse(new Response());
  }

}
