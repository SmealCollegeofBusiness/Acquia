<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Acquia\ContentHubClient\Syndication\SyndicationStatus;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubConnectionManager;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\ContentHubClientTestTrait;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\WatchdogAssertsTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub\ContentHubConnectionManager
 *
 * @group acquia_contenthub
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ContentHubConnectionManagerTest extends KernelTestBase {

  use NodeCreationTrait;
  use ContentHubClientTestTrait;
  use WatchdogAssertsTrait;

  /**
   * The connection manager.
   *
   * @var \Drupal\acquia_contenthub\ContentHubConnectionManager
   */
  protected $connManager;

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
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_test',
    'dblog',
    'depcalc',
    'filter',
    'node',
    'user',
  ];

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('filter');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('dblog', 'watchdog');

    $this->setUpAchConfig();

    $this->mockContentHubClientAndClientFactory($this->container);

    $this->settings = $this->prophesize(Settings::class);
    $this->container->set('acquia_contenthub.settings', $this->settings->reveal());
    $this->factory = $this->prophesize(ClientFactory::class);
    $this->client = $this->prophesize(ContentHubClient::class);
    $this->client
      ->getSettings()
      ->willReturn($this->settings->reveal());

    $this->factory
      ->getClient()
      ->willReturn($this->client->reveal());
    $this->container->set('acquia_contenthub.client.factory', $this->factory->reveal());

    $this->connManager = new ContentHubConnectionManager(
      $this->container->get('config.factory'),
      $this->container->get('acquia_contenthub.client.factory'),
      $this->container->get('acquia_contenthub.logger_channel'),
      $this->container->get('module_handler')
    );

    $this->configFactory = $this->container->get('config.factory');
    $this->logger = new LoggerMock();
    $this->moduleHandler = $this->container->get('module_handler');
  }

  /**
   * @covers ::getTrackedItemsFromSubscriber
   */
  public function testGetTrackedItemsFromSubscriber() {
    $this->setupSubscriber();

    $items = $this->connManager->getTrackedItemsFromSubscriber();
    $this->assertEquals([], $items);

    $node = $this->createNode();
    $node_2 = $this->createNode();
    $subscriber_tracker = $this->container->get('acquia_contenthub_subscriber.tracker');
    $subscriber_tracker->track($node, 'hash', $node->uuid());
    $subscriber_tracker->track($node_2, 'hash2', $node_2->uuid());

    $expect = [
      $node->uuid(),
      $node_2->uuid(),
    ];
    $items = $this->connManager->getTrackedItemsFromSubscriber();

    sort($expect);
    sort($items);
    $this->assertEquals($expect, $items);
  }

  /**
   * @covers ::getConfirmedTrackedItemsFromPublisher
   */
  public function testGetConfirmedTrackedItemsFromPublisher() {
    $this->setupPublisher();

    $items = $this->connManager->getConfirmedTrackedItemsFromPublisher();
    $this->assertEquals([], $items);

    $node = $this->createNode();
    $node_2 = $this->createNode();
    $publisher_tracker = $this->container->get('acquia_contenthub_publisher.tracker');
    $publisher_tracker->track($node, 'hash');
    $publisher_tracker->track($node_2, 'hash2');

    $items = $this->connManager->getConfirmedTrackedItemsFromPublisher();
    $this->assertEquals([], $items);

    $node_uuid = $node->uuid();
    $node_2_uuid = $node_2->uuid();
    $this->updateExportStatusInPublisherTracker(
      [$node_uuid, $node_2_uuid],
      PublisherTracker::CONFIRMED
    );

    $expect = [
      $node_uuid,
      $node_2_uuid,
    ];
    $items = $this->connManager->getConfirmedTrackedItemsFromPublisher();

    sort($expect);
    sort($items);
    $this->assertEquals($expect, $items);
  }

  /**
   * @covers ::syncWebhookInterestListWithTrackingTables
   * @covers ::syncSubscriber
   */
  public function testSyncSubscriber() {
    $this->setupSubscriber();

    $this->client->addEntitiesToInterestListBySiteRole()->shouldNotBeCalled();

    $this->connManager->syncSubscriber('some-uuid', TRUE);
    $this->connManager->syncSubscriber('some-uuid', FALSE);
    $this->connManager->syncSubscriber('', TRUE);
    $this->connManager->syncSubscriber('', FALSE);

    // Items should be added to the interest list.
    $node = $this->createNode();
    $node_2 = $this->createNode();
    $subscriber_tracker = $this->container->get('acquia_contenthub_subscriber.tracker');
    $subscriber_tracker->track($node, 'hash');
    $subscriber_tracker->track($node_2, 'hash2');

    // If arguments don't match the return value will be null, meaning the test
    // failed.
    $this->client->addEntitiesToInterestListBySiteRole('some-uuid', 'SUBSCRIBER', Argument::type('array'))
      ->shouldBeCalledTimes(1);
    $this->connManager->syncWebhookInterestListWithTrackingTables();
    $this->assertLogMessage('acquia_contenthub',
      'Added 2 imported entities to interest list for webhook uuid = "some-uuid".'
    );
  }

  /**
   * @covers ::syncWebhookInterestListWithTrackingTables
   * @covers ::syncPublisher
   */
  public function testSyncPublisherWithTrackedItems() {
    $this->setupPublisher();

    $this->client->addEntitiesToInterestListBySiteRole()->shouldNotBeCalled();

    // Tracking table is empty.
    $this->connManager->syncPublisher('some-uuid', TRUE);

    $this->connManager->syncPublisher('some-uuid', FALSE);
    $this->connManager->syncPublisher('', TRUE);
    $this->connManager->syncPublisher('', FALSE);

    // Items should be added to the interest list.
    $node = $this->createNode();
    $node_2 = $this->createNode();
    $publisher_tracker = $this->container->get('acquia_contenthub_publisher.tracker');
    $publisher_tracker->track($node, 'hash');
    $publisher_tracker->track($node_2, 'hash2');
    $node_uuid = $node->uuid();
    $node_2_uuid = $node_2->uuid();
    $this->updateExportStatusInPublisherTracker(
      [$node_uuid, $node_2_uuid],
      PublisherTracker::CONFIRMED
    );
    $uuids = [
      $node_uuid,
      $node_2_uuid,
    ];

    $interest_list = [
      'uuids' => $uuids,
      'status' => SyndicationStatus::EXPORT_SUCCESSFUL,
      'reason' => 'manual',
    ];

    // If arguments don't match the return value will be null, meaning the test
    // failed.
    $this->client->addEntitiesToInterestListBySiteRole('some-uuid', 'PUBLISHER', $interest_list)
      ->shouldBeCalledTimes(1);
    $this->connManager->syncWebhookInterestListWithTrackingTables();
    $this->assertLogMessage('acquia_contenthub',
      'Added 2 exported entities to interest list for webhook uuid = "some-uuid".'
    );
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
    $connection_manager = new ContentHubConnectionManager($this->configFactory, $factory->reveal(), $this->logger, $this->moduleHandler);
    $connection_manager->checkClient();
  }

  /**
   * @covers ::unregister
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testUnregister(array $response, array $expected, bool $unregister_flag): void {
    $this->mockClientData($response);
    $event = $this->mockUnregisterEvent();
    $connection_manager = new ContentHubConnectionManager($this->configFactory, $this->factory->reveal(), $this->logger, $this->moduleHandler);
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
  public function testCheckClient(): void {
    $response = new Response(200, [], json_encode([]));
    $this->client
      ->ping()
      ->willReturn($response);
    $connection_manager = new ContentHubConnectionManager($this->configFactory, $this->factory->reveal(), $this->logger, $this->moduleHandler);
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
    $connection_manager = new ContentHubConnectionManager($this->configFactory, $this->factory->reveal(), $this->logger, $this->moduleHandler);
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

  /**
   * Enables subscriber module and installs tracking table.
   */
  protected function setupSubscriber() {
    $this->enableModules(['acquia_contenthub_subscriber']);
    $this->installSchema('acquia_contenthub_subscriber', 'acquia_contenthub_subscriber_import_tracking');
  }

  /**
   * Enables publisher module and installs tracking table.
   */
  protected function setupPublisher() {
    $this->enableModules(['acquia_contenthub_publisher']);
    $this->installSchema('acquia_contenthub_publisher', 'acquia_contenthub_publisher_export_tracking');
  }

  /**
   * Updates export status in publisher.
   *
   * @param array $uuids
   *   The entities to update.
   * @param string $status
   *   Their statuses to change to.
   *
   * @todo refactor tracking services to encapsulate status changes.
   *
   * @throws \Exception
   */
  protected function updateExportStatusInPublisherTracker(array $uuids, string $status) {
    $database = $this->container->get('database');
    $update = $database->update('acquia_contenthub_publisher_export_tracking')
      ->fields(['status' => $status]);
    $update->condition('entity_uuid', $uuids, 'IN');
    $update->execute();
  }

  /**
   * Sets up content hub configuration for testing.
   *
   * @throws \Exception
   */
  protected function setUpAchConfig() {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('acquia_contenthub.admin_settings');
    $config->setData([
      'webhook' => [
        'uuid' => 'some-uuid',
      ],
    ]);
    $config->save();
  }

}
