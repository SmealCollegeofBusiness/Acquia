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
 * @group acquia_contenthub
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
   * Mocked ContentHubClient.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $client;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
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
    $this->client = $this->prophesize(ContentHubClient::class);
    $settings = $this->prophesize(Settings::class);

    $logger_channel = $this->container->get('acquia_contenthub_subscriber.logger_channel');

    $this->client->getSettings()->willReturn($settings->reveal());
    $client_factory->getClient()->willReturn($this->client->reveal());

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
    $this->client->isFeatured()->willReturn(FALSE);

    $data = new \stdClass();
    $data->filter_uuid = 'some-filter-uuid';
    $this->filterExecuteWorker->processItem($data);

    $this->assertLogMessage('acquia_contenthub_subscriber', 'Your webhook is not properly setup. ContentHub cannot execute filters without an active webhook. Please set your webhook up properly and try again.');
  }

  /**
   * Tests the scroll time window in case of featured accounts.
   *
   * @throws \Exception
   */
  public function testGetNormalizedScrollTimeWindowValueWhenAccountIsFeatured(): void {
    $this->installSchema('dblog', 'watchdog');

    $this->client->isFeatured()->willReturn(TRUE);

    $scroll_time_window = $this->filterExecuteWorker->getNormalizedScrollTimeWindowValue();
    $this->assertTrue($scroll_time_window === '10m');
  }

  /**
   * Tests the scroll time window in case of non-featured accounts.
   *
   * @throws \Exception
   */
  public function testGetNormalizedScrollTimeWindowValueWhenAccountIsNonFeatured(): void {
    $this->installSchema('dblog', 'watchdog');

    $this->client->isFeatured()->willReturn(FALSE);

    $scroll_time_window = $this->filterExecuteWorker->getNormalizedScrollTimeWindowValue();
    $this->assertTrue($scroll_time_window === '10');
  }

}
