<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook;

use Acquia\Hmac\ResponseSigner;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use GuzzleHttp\Psr7\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Trait with helper functions for handle webhook.
 *
 * @package Drupal\acquia_contenthub_publisher
 */
trait HandleWebhookTrait {

  /**
   * Handles webhook response.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   Handle webhook event.
   * @param string $body
   *   Body of request.
   * @param int $response_code
   *   The return response code.
   * @param \Symfony\Component\HttpFoundation\Response|null $response
   *   SymfonyResponse.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Returns signed response.
   */
  protected function getResponse(HandleWebhookEvent $event, string $body, int $response_code, SymfonyResponse $response = NULL): ResponseInterface {
    if (class_exists(DiactorosFactory::class)) {
      $http_message_factory = new DiactorosFactory();
    }
    else {
      $http_message_factory = new PsrHttpFactory(new ServerRequestFactory(), new StreamFactory(), new UploadedFileFactory(), new ResponseFactory());
    }
    $psr7_request = $http_message_factory->createRequest($event->getRequest());

    $signer = new ResponseSigner($event->getKey(), $psr7_request);
    if (!$response) {
      $response = new Response($response_code, [], $body);
    }
    else {
      $response = $http_message_factory->createResponse($response);
    }

    return $signer->signResponse($response);
  }

}
