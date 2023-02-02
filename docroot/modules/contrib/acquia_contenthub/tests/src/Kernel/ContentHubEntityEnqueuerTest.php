<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_publisher\ContentHubEntityEnqueuer;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests for ContentHubEntityEnqueuer class.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_publisher\ContentHubEntityEnqueuer
 * @group acquia_contenthub_publisher
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ContentHubEntityEnqueuerTest extends EntityKernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;
  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * The publisher exporting queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The client factory mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $clientFactory;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The publisher tracker.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $publisherTracker;

  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Logger mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * Drupal config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'depcalc',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('acquia_contenthub_publisher', ['acquia_contenthub_publisher_export_tracking']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'field',
      'filter',
      'node',
    ]);

    $this->createContentType([
      'type' => 'article',
    ]);

    $this->node = $this->createNode([
      'type' => 'article',
    ]);

    $settings = $this->prophesize(Settings::class);
    $settings
      ->getWebhook(Argument::any())
      ->willReturn('98213529-0000-0001-0000-123456789123');

    $this->client = $this->prophesize(ContentHubClient::class);
    $this->client
      ->getSettings()
      ->willReturn($settings->reveal());

    $this->clientFactory = $this->prophesize(ClientFactory::class);
    $this->clientFactory
      ->getClient()
      ->willReturn($this->client->reveal());

    $this->configFactory = $this->container->get('config.factory');
    $this->config = $this->configFactory->getEditable('acquia_contenthub.admin_settings');
    $this->config->set('send_contenthub_updates', FALSE);
    $this->config->save();

    $this->dispatcher = $this->container->get('event_dispatcher');
    $this->queueFactory = $this->container->get('queue');
    $this->publisherTracker = $this->container->get('acquia_contenthub_publisher.tracker');
    $this->logger = new LoggerMock();

    $this->queue = $this->queueFactory->get('acquia_contenthub_publish_export');
    $this->queue->deleteQueue();
  }

  /**
   * @covers ::enqueueEntity
   *
   * @throws \Exception
   */
  public function testEnqueueEntityWithConfigurationSet(): void {
    $this->assertEmpty($this->queue->numberOfItems());

    $this->clientFactory
      ->isConfigurationSet()
      ->willReturn(TRUE);

    $this->enqueueEntity();

    $this->assertEquals(1, $this->queue->numberOfItems());

    /** @var \Drupal\acquia_contenthub_publisher\PublisherTracker $pub_tracker */
    $pub_tracker = $this->container->get('acquia_contenthub_publisher.tracker');
    $is_tracked = $pub_tracker->isTracked($this->node->uuid());
    $this->assertTrue($is_tracked);

    $track_queue_id = $pub_tracker->getQueueId($this->node->uuid());
    $this->assertNotEmpty($track_queue_id);
  }

  /**
   * @covers ::enqueueEntity
   *
   * @throws \Exception
   */
  public function testEnqueueEntityWithConfigurationNotSet(): void {
    $this->clientFactory
      ->isConfigurationSet()
      ->willReturn(FALSE);

    $this->enqueueEntity();
    /** @var \Drupal\acquia_contenthub_publisher\PublisherTracker $pub_tracker */
    $pub_tracker = $this->container->get('acquia_contenthub_publisher.tracker');
    $is_tracked = $pub_tracker->isTracked($this->node->uuid());
    $this->assertFalse($is_tracked);
  }

  /**
   * @covers ::enqueueEntity
   *
   * @throws \Exception
   */
  public function testEnqueueEntityLogsWithEligibility(): void {
    $this->clientFactory
      ->isConfigurationSet()
      ->willReturn(TRUE);

    $this->enqueueEntity();
    $uuid = $this->node->uuid();
    $entity_type_id = $this->node->getEntityTypeId();
    $log_messages = $this->logger->getLogMessages();
    $this->assertEquals(
      "Attempting to add entity with (UUID: {$uuid}, Entity type: {$entity_type_id}) to the export queue after operation: update.",
      $log_messages[RfcLogLevel::INFO][0]
    );

    $this->assertEquals(
      "Entity with (UUID: {$uuid}, Entity type: {$entity_type_id}) added to the export queue and to the tracking table.",
      $log_messages[RfcLogLevel::INFO][1]
    );
  }

  /**
   * @covers ::enqueueEntity
   *
   * @throws \Exception
   */
  public function testEnqueueEntityLogsWithoutEligibility(): void {
    $this->clientFactory
      ->isConfigurationSet()
      ->willReturn(TRUE);

    $this->node->set('uuid', 'd12da227');
    $this->node->save();

    $this->enqueueEntity();

    $log_messages = $this->logger->getLogMessages();
    $uuid = $this->node->uuid();
    $entity_type_id = $this->node->getEntityTypeId();
    $this->assertEquals(
      "Attempting to add entity with (UUID: {$uuid}, Entity type: {$entity_type_id}) to the export queue after operation: update.",
      $log_messages[RfcLogLevel::INFO][0]
    );

    $this->assertEquals(
      "Entity with (UUID: {$uuid}, Entity type: {$entity_type_id}) not eligible to be added to the export queue. Reason: Missing entity uuid.",
      $log_messages[RfcLogLevel::INFO][1]
    );
  }

  /**
   * @covers ::enqueueEntity
   *
   * @throws \Exception
   */
  public function testWithAddEntitiesToInterestListBySiteRole(): void {
    $this->config->set('send_contenthub_updates', TRUE)->save();

    $response = $this->prophesize(ResponseInterface::class);
    $this->client
      ->addEntitiesToInterestListBySiteRole(Argument::any(), Argument::any(), Argument::any())
      ->willReturn($response->reveal());

    $this->clientFactory
      ->isConfigurationSet()
      ->willReturn(TRUE);

    $this->enqueueEntity();

    $log_messages = $this->logger->getLogMessages();
    $this->assertEquals(
      "Attempting to add entity with (UUID: {$this->node->uuid()}, Entity type: {$this->node->getEntityTypeId()}) to the export queue after operation: update.",
      $log_messages[RfcLogLevel::INFO][0]
    );

    $this->assertEquals(
      "Entity with (UUID: {$this->node->uuid()}, Entity type: {$this->node->getEntityTypeId()}) added to the export queue and to the tracking table.",
      $log_messages[RfcLogLevel::INFO][1]
    );

    $this->assertEquals(
      'The entity (node: ' . $this->node->uuid() . ') has been added to the interest list with status "QUEUED-TO-EXPORT" for webhook: 98213529-0000-0001-0000-123456789123.',
      $log_messages[RfcLogLevel::INFO][2]
    );
  }

  /**
   * @covers ::enqueueEntity
   *
   * @throws \Exception
   */
  public function testWithoutContentHubClient(): void {
    $this->config->set('send_contenthub_updates', TRUE)->save();
    $this->clientFactory
      ->isConfigurationSet()
      ->willReturn(TRUE);

    $this->clientFactory
      ->getClient()
      ->willReturn([]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Error trying to connect to the Content Hub. Make sure this site is registered to Content hub.');
    $this->enqueueEntity();
  }

  /**
   * Enqueue entity.
   *
   * @throws \Exception
   */
  protected function enqueueEntity(): void {
    $factory = $this->clientFactory->reveal();
    $ch_entity_enqueuer = new ContentHubEntityEnqueuer($this->configFactory, $factory, $this->logger, $this->dispatcher, $this->queueFactory, $this->publisherTracker);
    $ch_entity_enqueuer->enqueueEntity($this->node, 'update');
  }

}
