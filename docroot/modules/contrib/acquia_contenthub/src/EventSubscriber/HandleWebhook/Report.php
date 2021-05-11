<?php

namespace Drupal\acquia_contenthub\EventSubscriber\HandleWebhook;

use Acquia\Hmac\ResponseSigner;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\Core\Extension\ModuleExtensionList;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles webhooks with a payload requesting site report.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\HandleWebhook
 */
class Report implements EventSubscriberInterface {

  const PAYLOAD_REPORT = 'report';

  /**
   * The common contenthub actions object.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $common;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * Report constructor.
   *
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common
   *   Common Actions.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   List of modules.
   */
  public function __construct(ContentHubCommonActions $common, ModuleExtensionList $module_list) {
    $this->common = $common;
    $this->moduleList = $module_list;
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

    if ('successful' !== $payload['status'] || self::PAYLOAD_REPORT !== $payload['crud']) {
      return;
    }

    $response = $this->getResponse($event, json_encode($this->getReport()));
    $event->setResponse($response);
    $event->stopPropagation();
  }

  /**
   * Get Response method.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   Webhook event.
   * @param string $body
   *   Body of response.
   *
   * @todo Copied from preview. Move it a more generic class.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Signed response.
   */
  protected function getResponse(HandleWebhookEvent $event, string $body): ResponseInterface {
    $response = new GuzzleResponse(Response::HTTP_OK, [], $body);

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

  /**
   * Get report of site modules and db update status.
   *
   * @return array
   *   Array of modules and db update status.
   */
  protected function getReport(): array {
    return [
      'modules' => $this->moduleList->getAllInstalledInfo(),
      'updatedb-status' => $this->common->getUpdateDbStatus(),
    ];
  }

}
