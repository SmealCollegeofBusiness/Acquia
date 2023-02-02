<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Traits;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophet;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper trait for tests utilizing ContentHubClient.
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\Traits
 */
trait ContentHubClientTestTrait {

  /**
   * Content hub client mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $client;

  /**
   * Content hub client factory mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $clientFactory;

  /**
   * Sets acquia_contenthub.client.factory service to the mocked ClientFactory.
   *
   * The factory will return a mocked ContentHubClient.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to set the client factory for.
   *
   * @throws \Exception
   */
  public function mockContentHubClientAndClientFactory(ContainerInterface $container): void {
    $prophet = new Prophet();

    $this->client = $prophet->prophesize(ContentHubClient::class);
    $this->clientFactory = $prophet->prophesize(ClientFactory::class);
    $this->clientFactory->getClient()->willReturn($this->client);

    $container->set('acquia_contenthub.client.factory', $this->clientFactory->reveal());
  }

  /**
   * Returns a client mock.
   *
   * @param callable|null $func
   *   Alter the client mock. Receives the context with the client, settings and
   *   config in it. The context is an array the before-mentioned respective
   *   keys.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The prophecy object.
   *
   * @throws \Exception
   */
  public function getMockedContentHubClient(callable $func = NULL): ObjectProphecy {
    $config = \Drupal::getContainer()->get('acquia_contenthub.config');
    $origin = $config->get('origin');
    $settings = $this->prophesize(Settings::class);
    $settings->getUuid()->willReturn($origin);
    $client = $this->prophesize(ContentHubClient::class);
    $client->getSettings()->willReturn($settings->reveal());
    if (!is_null($func)) {
      $context = [
        'client' => $client,
        'config' => $config,
        'settings' => $settings,
      ];
      $func($context)();
    }
    return $client;
  }

}
