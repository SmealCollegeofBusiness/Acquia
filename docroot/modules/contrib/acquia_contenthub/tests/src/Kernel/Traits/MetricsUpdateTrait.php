<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Traits;

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait for mocking calls to CH client for metrics updates.
 */
trait MetricsUpdateTrait {

  /**
   * Adds mocks for metrics related calls to CH client.
   *
   * @param \Prophecy\Prophecy\ObjectProphecy $client
   *   Prophecy object of CH client.
   */
  protected function mockMetricsCalls(ObjectProphecy $client): void {
    $client
      ->getRemoteSettings()
      ->willReturn([]);
    $client
      ->putEntities(Argument::any())
      ->willReturn(new Response('', 202));
    $client
      ->getEntity(Argument::any())
      ->willReturn();
  }

}
