<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubConnectionManager;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * Tests orphaned entities handler.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\ContentHubConnectionManager
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\Unregister
 */
class ContentHubConnectionManagerTest extends KernelTestBase {

  /**
   * The ContentHubConnectionManager object.
   *
   * @var \Drupal\acquia_contenthub\ContentHubConnectionManager
   */
  protected $connectionManager;

  /**
   * Content Hub client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * Content Hub client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $factory;

  /**
   * Content Hub client settings.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $settings;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'acquia_contenthub',
    'depcalc',
    'node',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->settings = $this->prophesize(Settings::class);
    $this->factory = $this->prophesize(ClientFactory::class);
    $this->client = $this->prophesize(ContentHubClient::class);

    $this->client
      ->getSettings()
      ->willReturn($this->settings->reveal());

    $this->factory
      ->getClient()
      ->willReturn($this->client->reveal());

    $this->configFactory = $this->container->get('config.factory');
    $this->logger = new LoggerMock();
  }

  /**
   * @covers ::unregister
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testUnregister(array $response, array $expected, bool $unregister_flag): void {
    $connection_manager = new ContentHubConnectionManager($this->configFactory, $this->factory->reveal(), $this->logger, $this->settings->reveal());
    $this->mockClientData($response);
    $event = $this->mockUnregisterEvent();

    $unregister = $connection_manager->unregister($event);
    $this->assertEquals($unregister_flag, $unregister);

    $log_messages = $this->logger->getLogMessages();
    $this->assertNotEmpty(array_keys($log_messages));
    $this->assertEqualsCanonicalizing($expected, $log_messages);
  }

  /**
   * @covers ::checkClient
   *
   * @throws \Exception
   */
  public function testCheckClientException(): void {
    $factory = $this->prophesize(ClientFactory::class);
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Client is not configured.');
    $connection_manager = new ContentHubConnectionManager($this->configFactory, $factory->reveal(), $this->logger, $this->settings->reveal());
    $connection_manager->checkClient();
  }

  /**
   * @covers ::checkClient
   *
   * @throws \Exception
   */
  public function testCheckClient(): void {
    $response = new Response(200, [], json_encode([]));
    $this->client
      ->ping()
      ->willReturn($response);
    $connection_manager = new ContentHubConnectionManager($this->configFactory, $this->factory->reveal(), $this->logger, $this->settings->reveal());
    $check_client = $connection_manager->checkClient();
    $this->assertInstanceOf(ContentHubConnectionManager::class, $check_client);
  }

  /**
   * @covers ::checkClient
   *
   * @throws \Exception
   */
  public function testCheckClientPingException(): void {
    $response = new Response(400, [], json_encode([]));
    $this->client
      ->ping()
      ->willReturn($response);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Client could not reach Content Hub.');
    $connection_manager = new ContentHubConnectionManager($this->configFactory, $this->factory->reveal(), $this->logger, $this->settings->reveal());
    $connection_manager->checkClient();
  }

  /**
   * Mock ACH unregister event.
   *
   * @return \Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent
   *   Mock event.
   */
  protected function mockUnregisterEvent(): AcquiaContentHubUnregisterEvent {
    $event = new AcquiaContentHubUnregisterEvent('webhook-uuid', 'client-uuid');
    $event->setDefaultFilter('default-filter-uuid');
    $orphan_filters = [
      'filter-uuid-1',
      'filter-uuid-2',
    ];
    $event->setOrphanedFilters($orphan_filters);
    $event->setClientName('client-name');

    return $event;
  }

  /**
   * Mock client data.
   *
   * @param array $response
   *   Mock client.
   *
   * @throws \Exception
   */
  protected function mockClientData(array $response): void {
    $this->client
      ->deleteWebhook(Argument::any())
      ->willReturn($response[0]);

    $this->client
      ->deleteFilter(Argument::any())
      ->willReturn($response[1]);

    $this->client
      ->deleteClient(Argument::any())
      ->willReturn($response[2]);
  }

  /**
   * Data provider for testUnregister.
   */
  public function dataProvider(): array {
    return [
      [
        [
          new Response(400),
          new Response(400),
          new Response(400),
        ],
        [
          RfcLogLevel::ERROR => [
            'Could not unregister webhook: Bad Request',
            'Some error occurred during webhook deletion.',
          ],
        ],
        FALSE,
      ],
      [
        [
          new Response(200),
          new Response(400),
          new Response(400),
        ],
        [
          RfcLogLevel::ERROR => [
            'Could not delete default filter for webhook: Bad Request',
            'Some error occurred during webhook deletion.',
          ],
        ],
        FALSE,
      ],
      [
        [
          new Response(200),
          new Response(200),
          new Response(400),
        ],
        [
          RfcLogLevel::ERROR => [
            'Could not delete client: Bad Request',
          ],
        ],
        FALSE,
      ],
      [
        [
          new Response(200),
          new Response(200),
          new Response(200),
        ],
        [
          RfcLogLevel::NOTICE => [
            'Successfully unregistered client client-name',
          ],
        ],
        TRUE,
      ],
    ];
  }

}
