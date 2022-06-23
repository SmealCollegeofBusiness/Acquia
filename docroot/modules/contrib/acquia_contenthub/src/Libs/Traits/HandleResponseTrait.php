<?php

namespace Drupal\acquia_contenthub\Libs\Traits;

use Acquia\Hmac\ResponseSigner;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use GuzzleHttp\Psr7\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Trait with helper functions for converting symfony response to PSR-7.
 *
 * @package Drupal\acquia_contenthub_publisher
 */
trait HandleResponseTrait {

  /**
   * Handles webhook response.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   Handle webhook event.
   * @param string $body
   *   Body of request. Defaults to empty string.
   * @param int $response_code
   *   Return response code. Defaults to 200.
   * @param \Symfony\Component\HttpFoundation\Response|null $response
   *   SymfonyResponse.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Returns signed response.
   */
  protected function getResponse(HandleWebhookEvent $event, string $body = '', int $response_code = SymfonyResponse::HTTP_OK, SymfonyResponse $response = NULL): ResponseInterface {
    $http_message_factory = $this->createPsrFactory();
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

  /**
   * Returns PSR factory.
   *
   * @return \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   *   Psr http factory.
   */
  protected function createPsrFactory(): HttpMessageFactoryInterface {
    return new PsrHttpFactory(new ServerRequestFactory(), new StreamFactory(), new UploadedFileFactory(), new ResponseFactory());
  }

}
