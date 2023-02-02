<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;

/**
 * Tests the NullifyQueueId class.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class NullifyQueueIdTest extends EntityKernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;

  /**
   * Exported entity tracking Table.
   */
  const TABLE_NAME = 'acquia_contenthub_publisher_export_tracking';

  /**
   * Queue name.
   */
  const QUEUE_NAME = 'acquia_contenthub_publish_export';

  /**
   * Entity Bundle name.
   */
  protected const BUNDLE = 'article';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'acquia_contenthub_server_test',
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

    $this->createAcquiaContentHubAdminSettings();
    $factory = $this->container->get('acquia_contenthub.client.factory');

    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'acquia_contenthub',
      'acquia_contenthub_publisher',
      'system',
      'user',
    ]);

    // Creates sample node type.
    $this->createNodeType();

    $origin_uuid = '00000000-0000-0001-0000-123456789123';
    $configFactory = $this->container->get('config.factory');
    $config = $configFactory->getEditable('acquia_contenthub.admin_settings');
    $config->set('origin', $origin_uuid);
    $config->set('send_contenthub_updates', TRUE);
    $config->save();

    // Acquia ContentHub export queue service.
    $this->contentHubQueue = $this->container->get('acquia_contenthub_publisher.acquia_contenthub_export_queue');

    // Add Content Hub tracker service.
    $this->publisherTracker = \Drupal::service('acquia_contenthub_publisher.tracker');

    $common = $this->getMockBuilder(ContentHubCommonActions::class)
      ->setConstructorArgs([
        $this->container->get('event_dispatcher'),
        $this->container->get('entity.cdf.serializer'),
        $this->container->get('entity.dependency.calculator'),
        $factory,
        $this->container->get('logger.factory'),
        $this->container->get('config.factory'),
      ])
      ->onlyMethods(['getUpdateDbStatus'])
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createNodeType() {
    // Create the node bundle required for testing.
    $type = NodeType::create([
      'type' => self::BUNDLE,
      'name' => self::BUNDLE,
    ]);
    $type->save();
  }

  /**
   * Creates node samples.
   *
   * @return \Drupal\node\NodeInterface
   *   Node object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createNode(): NodeInterface {
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => self::BUNDLE,
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
