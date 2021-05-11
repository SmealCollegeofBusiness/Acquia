<?php

namespace Drupal\acquia_contenthub_preview\EventSubscriber\HandleWebhook;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFDocument;
use Acquia\Hmac\ResponseSigner;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\Core\Url;
use GuzzleHttp\Psr7\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Previews entities across content as a service sites.
 *
 * @package Drupal\acquia_contenthub_preview\EventSubscriber\HandleWebhook
 */
class PreviewEntity implements EventSubscriberInterface {

  /**
   * The common actions object.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $common;

  /**
   * PreviewEntity constructor.
   *
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common
   *   The common actions object.
   */
  public function __construct(ContentHubCommonActions $common) {
    $this->common = $common;
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

    if ('successful' !== $payload['status'] || empty($payload['preview']) || 'preview' !== $payload['crud'] || $payload['initiator'] === $client_uuid || empty($payload['cdf'])) {
      return;
    }
    $cdf = $payload['cdf']['entities'];
    $document = new CDFDocument();
    foreach ($cdf as $object) {
      $cdfObject = CDFObject::fromArray($object);
      $document->addCdfEntity($cdfObject);
    }
    $this->common->importEntityCdfDocument($document);
    $url = Url::fromRoute('acquia_contenthub_preview.preview',
      ['uuid' => $payload['preview']],
      [
        'absolute' => TRUE,
        'base_url' => $event->getRequest()->getSchemeAndHttpHost(),
      ]
    );
    $response = $this->getResponse($event, '<iframe src="' . $url->toString() . '" width="1165" height="500"></iframe>');
    $event->setResponse($response);
    $event->stopPropagation();
  }

  /**
   * Handles webhook response.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   Handle webhook event.
   * @param string $body
   *   Body of request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Returns signed response.
   */
  protected function getResponse(HandleWebhookEvent $event, string $body) {
    $response = new Response(200, [], $body);

    if (class_exists(DiactorosFactory::class)) {
      $httpMessageFactory = new DiactorosFactory();
    }
    else {
      $httpMessageFactory = new PsrHttpFactory(new ServerRequestFactory(), new StreamFactory(), new UploadedFileFactory(), new ResponseFactory());
    }
    $psr7_request = $httpMessageFactory->createRequest($event->getRequest());

    $signer = new ResponseSigner($event->getKey(), $psr7_request);
    $signedResponse = $signer->signResponse($response);
    return $signedResponse;
  }

}
