<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_publisher\ContentHubEntityEnqueuer;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Prophecy\Argument;

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
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
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

    $ch_client = $this->prophesize(ContentHubClient::class);
    $this->clientFactory = $this->prophesize(ClientFactory::class);
    $this->clientFactory
      ->getClient(Argument::any())
      ->willReturn($ch_client->reveal());

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

    $this->enqueueEntity($this->clientFactory->reveal());

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

    $enqueue_entity = $this->enqueueEntity($this->clientFactory->reveal());
    $this->assertNull($enqueue_entity);
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

    $this->enqueueEntity($this->clientFactory->reveal());
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

    $this->enqueueEntity($this->clientFactory->reveal());

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
   * Enqueue entity.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   The client factory.
   *
   * @throws \Exception
   */
  protected function enqueueEntity(ClientFactory $factory): void {
    $ch_entity_enqueuer = new ContentHubEntityEnqueuer($factory, $this->logger, $this->dispatcher, $this->queueFactory, $this->publisherTracker);
    $ch_entity_enqueuer->enqueueEntity($this->node, 'update');
  }

}
