<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\CDF\CDFObjectInterface;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the NullifyQueueId class.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class NullifyQueueIdTest extends EntityKernelTestBase {

  /**
   * Exported entity tracking Table.
   */
  const TABLE_NAME = 'acquia_contenthub_publisher_export_tracking';

  /**
   * Queue name.
   */
  const QUEUE_NAME = 'acquia_contenthub_publish_export';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'user',
    'node',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
  ];

  /**
   * Acquia ContentHub export queue.
   *
   * @var \Drupal\acquia_contenthub_publisher\ContentHubExportQueue
   */
  protected $contentHubQueue;

  /**
   * Queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Queue worker.
   *
   * @var \Drupal\Core\Queue\QueueWorkerInterface
   */
  protected $queueWorker;

  /**
   * Content Hub Publisher Tracker service.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $publisherTracker;

  /**
   * CDF Object.
   *
   * @var \Acquia\ContentHubClient\CDF\CDFObject
   */
  protected $cdfObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (version_compare(\Drupal::VERSION, '9.0', '>=')) {
      static::$modules[] = 'path_alias';
    }
    elseif (version_compare(\Drupal::VERSION, '8.8.0', '>=')) {
      $this->installEntitySchema('path_alias');
    }
    $this->installSchema('acquia_contenthub_publisher', [self::TABLE_NAME]);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'acquia_contenthub',
      'acquia_contenthub_publisher',
      'system',
      'user',
    ]);

    $origin_uuid = '00000000-0000-0001-0000-123456789123';
    $configFactory = $this->container->get('config.factory');
    $config = $configFactory->getEditable('acquia_contenthub.admin_settings');
    $config->set('origin', $origin_uuid);
    $config->save();

    // Acquia ContentHub export queue service.
    $this->contentHubQueue = $this->container->get('acquia_contenthub_publisher.acquia_contenthub_export_queue');

    // Add Content Hub tracker service.
    $this->publisherTracker = \Drupal::service('acquia_contenthub_publisher.tracker');

    $cdf_object = $this->getMockBuilder(CDFObjectInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $cdf_object->method('getOrigin')
      ->willReturn($origin_uuid);

    // Mock Acquia ContentHub Client.
    $response = $this->getMockBuilder('\Psr\Http\Message\ResponseInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $response->method('getStatusCode')
      ->willReturn(202);

    $contenthub_client = $this->getMockBuilder('\Acquia\ContentHubClient\ContentHubClient')
      ->disableOriginalConstructor()
      ->getMock();
    $contenthub_client->method('putEntities')
      ->with($this->captureArg($this->cdfObject))
      ->willReturn($response);
    $contenthub_client->method('deleteEntity')
      ->willReturn($response);
    $contenthub_client->method('getEntity')
      ->willReturn($cdf_object);

    $contenthub_client_factory = $this->getMockBuilder('\Drupal\acquia_contenthub\Client\ClientFactory')
      ->disableOriginalConstructor()
      ->getMock();
    $contenthub_client_factory->method('getClient')
      ->willReturn($contenthub_client);
    $this->container->set('acquia_contenthub.client.factory', $contenthub_client_factory);

    $contenthub_settings = $this->getMockBuilder('\Acquia\ContentHubClient\Settings')
      ->disableOriginalConstructor()
      ->getMock();
    $contenthub_settings->method('getUuid')
      ->willReturn($origin_uuid);

    $contenthub_client_factory->method('getSettings')
      ->willReturn($contenthub_settings);

    $contenthub_client->method('getSettings')
      ->willReturn($contenthub_settings);

    $common = $this->getMockBuilder(ContentHubCommonActions::class)
      ->setConstructorArgs([
        $this->container->get('event_dispatcher'),
        $this->container->get('entity.cdf.serializer'),
        $this->container->get('entity.dependency.calculator'),
        $this->container->get('acquia_contenthub.client.factory'),
      ])
      ->setMethods(['getUpdateDbStatus'])
      ->getMock();
    $this->container->set('acquia_contenthub_common_actions', $common);

    // Setup queue.
    $queue_factory = $this->container->get('queue');
    $queue_worker_manager = $this->container->get('plugin.manager.queue_worker');
    $this->queueWorker = $queue_worker_manager->createInstance(self::QUEUE_NAME);
    $this->queue = $queue_factory->get(self::QUEUE_NAME);
  }

  /**
   * Test "queue_id" nullification when entities loose their queued state.
   */
  public function testQueueIdNullification() {
    // Creates sample node type.
    $this->createNodeType('article', 'Article');
    // Get some node.
    $node = $this->createNode();

    // First check whether "queue_id" exists.
    $queue_id = $this->getQueueId($node->id(), 'queued');
    $this->assertNotEmpty($queue_id[0], 'Queue ID should not be empty');

    while ($item = $this->queue->claimItem()) {
      $this->queueWorker->processItem($item->data);
      // Nullification of queue_id.
      $this->publisherTracker->nullifyQueueId($item->data->uuid);
    }

    // "queue_id" must be empty, when entities are in exported state.
    $queue_id = $this->getQueueId($node->id(), 'exported');
    $this->assertEmpty($queue_id[0], 'Queue ID should be empty');
  }

  /**
   * Fetch "queue_id".
   *
   * @param int $entity_id
   *   Entity Id.
   * @param string $status
   *   Status of the entity.
   *
   * @return mixed
   *   The queue id.
   */
  protected function getQueueId($entity_id, $status) {
    $query = \Drupal::database()->select(self::TABLE_NAME, 't');
    $query->fields('t', ['queue_id']);
    $query->condition('entity_id', $entity_id);
    $query->condition('status', $status);

    return $query->execute()->fetchCol();
  }

  /**
   * Creates sample node types.
   *
   * @param string $type
   *   Machine name of the node type.
   * @param string $name
   *   Label of the node type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createNodeType($type, $name) {
    // Create the node bundle required for testing.
    $type = NodeType::create([
      'type' => $type,
      'name' => $name,
    ]);
    $type->save();
  }

  /**
   * Creates node samples.
   *
   * @return int
   *   Node id.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createNode() {
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => 'article',
      'langcode' => 'en',
      'created' => \Drupal::time()->getRequestTime(),
      'changed' => \Drupal::time()->getRequestTime(),
      'uid' => 1,
      'status' => Node::PUBLISHED,
    ]);
    $node->save();

    return $node;
  }

  /**
   * Captures $objects argument value of "putEntities" method.
   *
   * @param mixed $argument
   *   A method's argument.
   *
   * @return \PHPUnit\Framework\Constraint\Callback
   *   Callback.
   *
   * @see \Drupal\acquia_contenthub_publisher\Plugin\QueueWorker\ContentHubExportQueueWorker::processItem()
   */
  protected function captureArg(&$argument) {
    return $this->callback(function ($argument_to_mock) use (&$argument) {
      $argument = $argument_to_mock;
      return TRUE;
    });
  }

}
