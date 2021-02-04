<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook;

use Acquia\Hmac\ResponseSigner;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use GuzzleHttp\Psr7\Response;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Gets files during preview in content as a service.
 *
 * @package Drupal\acquia_contenthub_preview\EventSubscriber\HandleWebhook
 */
class GetFile implements EventSubscriberInterface {

  /**
   * The common actions object.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $common;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * GetFile constructor.
   *
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common
   *   The common actions object.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(ContentHubCommonActions $common, StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->common = $common;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::HANDLE_WEBHOOK][] =
      ['onHandleWebhook', 1000];
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
    $client = $event->getClient();
    $settings = $client->getSettings();
    $client_uuid = $settings->getUuid();

    if ('successful' !== $payload['status'] || empty($payload['uuid']) || 'getFile' !== $payload['crud'] || $payload['initiator'] === $client_uuid || empty($payload['cdf'])) {
      return;
    }

    $file_uri = $payload['cdf']['uri'];
    $file_scheme = $payload['cdf']['scheme'];

    if ($this->streamWrapperManager->isValidScheme($file_scheme) && is_file($file_uri)) {
      $binary = new BinaryFileResponse($file_uri, 200, [], ($file_scheme === 'private' ? FALSE : TRUE), 'inline');
      $response = $this->getResponse($event, '', $binary);
      $event->setResponse($response);
      $event->stopPropagation();
    }
  }

  /**
   * Handles webhook response.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   Handle webhook event.
   * @param string $body
   *   Body of request.
   * @param \Symfony\Component\HttpFoundation\Response|null $response
   *   SymfonyResponse.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Returns signed response.
   */
  protected function getResponse(HandleWebhookEvent $event, string $body, SymfonyResponse $response = NULL) {
    $http_message_factory = new DiactorosFactory();
    $psr7_request = $http_message_factory->createRequest($event->getRequest());

    $signer = new ResponseSigner($event->getKey(), $psr7_request);
    if (!$response) {
      $response = new Response(200, [], $body);
    }
    else {
      $response = $http_message_factory->createResponse($response);
    }

    return $signer->signResponse($response);
  }

}
