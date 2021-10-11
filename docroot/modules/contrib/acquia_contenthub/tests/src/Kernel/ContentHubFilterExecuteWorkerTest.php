<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker\ContentHubFilterExecuteWorker;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\WatchdogAssertsTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker\ContentHubFilterExecuteWorker
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ContentHubFilterExecuteWorkerTest extends KernelTestBase {

  use WatchdogAssertsTrait;

  /**
   * ContentHubFilterExecuteWorker instance.
   *
   * @var \Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker\ContentHubFilterExecuteWorker
   */
  protected $filterExecuteWorker;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'field',
    'filter',
    'depcalc',
    'dblog',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'system',
    'user',
    'node',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setup();

    $dispatcher = $this->prophesize(EventDispatcher::class);
    $client_factory = $this->prophesize(ClientFactory::class);
    $client = $this->prophesize(ContentHubClient::class);
    $settings = $this->prophesize(Settings::class);

    $logger_channel = $this->container->get('acquia_contenthub_subscriber.logger_channel');

    $client->getSettings()->willReturn($settings->reveal());
    $client_factory->getClient()->willReturn($client->reveal());

    $this->filterExecuteWorker = new ContentHubFilterExecuteWorker(
      $dispatcher->reveal(),
      $client_factory->reveal(),
      $logger_channel,
      [],
      '',
      ''
    );
  }

  /**
   * @covers ::processItem
   *
   * @throws \Exception
   */
  public function testProcessItemException() {
    $this->expectExceptionMessage('Filter uuid not found.');
    $this->expectException(\Exception::class);
    $this->filterExecuteWorker->processItem([]);
  }

  /**
   * @covers ::processItem
   *
   * @throws \Exception
   */
  public function testProcessItemLogginMsgWithEmptyWebhookUuuid() {
    $this->installSchema('dblog', 'watchdog');

    $data = new \stdClass();
    $data->filter_uuid = 'some-filter-uuid';
    $this->filterExecuteWorker->processItem($data);

    $this->assertLogMessage('acquia_contenthub_subscriber', 'Your webhook is not properly setup. ContentHub cannot execute filters without an active webhook. Please set your webhook up properly and try again.');
  }

}
