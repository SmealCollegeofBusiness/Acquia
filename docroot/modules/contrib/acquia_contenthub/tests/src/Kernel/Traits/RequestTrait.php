<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Traits;

use Symfony\Component\HttpFoundation\Request;

/**
 * Trait for creating test signed request.
 */
trait RequestTrait {

  /**
   * Creates a test HMAC-Signed Request.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The HMAC signed request.
   */
  public function createSignedRequest(): Request {
    $request_global = Request::createFromGlobals();
    $request = $request_global->duplicate(NULL, NULL, NULL, NULL, NULL, [
      'REQUEST_URI' => 'http://example.com/acquia-contenthub/webhook',
      'SERVER_NAME' => 'example.com',
    ]);
    // @codingStandardsIgnoreStart
    $header = 'acquia-http-hmac headers="X-Custom-Signer1;X-Custom-Signer2",id="e7fe97fa-a0c8-4a42-ab8e-2c26d52df059",nonce="a9938d07-d9f0-480c-b007-f1e956bcd027",realm="CIStore",signature="0duvqeMauat7pTULg3EgcSmBjrorrcRkGKxRDtZEa1c=",version="2.0"';
    // @codingStandardsIgnoreEnd
    $request->headers->set('Authorization', $header);
    return $request;
  }

}
