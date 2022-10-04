<?php

namespace Drupal\acquia_contenthub\Libs\Traits;

use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;

/**
 * Encapsulates common helper functions for response checks.
 */
trait ResponseCheckerTrait {

  /**
   * Checks if the response was successful.
   *
   * @param \Psr\Http\Message\ResponseInterface|null $response
   *   The response.
   *
   * @return bool
   *   True if the response returned with 2xx status code.
   */
  protected function isSuccessful(?ResponseInterface $response): bool {
    if (is_null($response)) {
      return FALSE;
    }

    return (new HttpFoundationFactory())
      ->createResponse($response)
      ->isSuccessful();
  }

}
