<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

/**
 * Tests publisher tracker methods.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_publisher\PublisherTracker
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class PublisherTrackerTest extends NullifyQueueIdTest {

  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create test node.
    $this->node = $this->createNode();
  }

  /**
   * Test case to list tracked entities in tracking table.
   *
   * @covers ::listTrackedEntities
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testListTrackedEntities(): void {
    $list_tracked_entities_after = $this->publisherTracker->listTrackedEntities('queued', 'node');
    $this->assertNotEmpty($list_tracked_entities_after);

    // Delete the node.
    $this->node->delete();

    // Make sure that node is deleted from the tracking table also.
    $list_tracked_entities_before = $this->publisherTracker->listTrackedEntities('queued', 'node');
    $this->assertEmpty($list_tracked_entities_before);
  }

  /**
   * Test case to nullifies hashes.
   *
   * @covers ::nullifyHashes
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testNullifyHashes(): void {
    while ($item = $this->queue->claimItem()) {
      $this->queueWorker->processItem($item->data);
    }

    $hash_before = $this->getTrackingTableColByUuid($this->node->uuid(), 'hash');
    $this->assertNotEmpty($hash_before);

    $status = $this->getTrackingTableColByUuid($this->node->uuid(), 'status');
    // Nullifies hashes in the Publisher Tracker.
    $this->publisherTracker->nullifyHashes([$status], ['node'], [$this->node->uuid()]);

    $hash_after = $this->getTrackingTableColByUuid($this->node->uuid(), 'hash');
    $this->assertEmpty($hash_after);
  }

  /**
   * Test case to validate whether entity is tracked.
   *
   * @covers ::isTracked
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIsTracked() {
    $is_tracked = $this->publisherTracker->isTracked($this->node->uuid());
    $this->assertTrue($is_tracked);
  }

  /**
   * Test case to update the queue id.
   *
   * @covers ::setQueueItemByUuid
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testSetQueueItemByUuid() {
    $expected_queue_id = 1;

    $this->publisherTracker->setQueueItemByUuid($this->node->uuid(), $expected_queue_id);
    $actual_queue_id = $this->getTrackingTableColByUuid($this->node->uuid(), 'queue_id');
    $this->assertEqual($actual_queue_id, $expected_queue_id);
  }

  /**
   * Test case to delete the entity from tracking table.
   *
   * @covers ::delete
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testDelete() {
    $this->publisherTracker->delete($this->node->uuid());

    $is_deleted = $this->getTrackingTableColByUuid($this->node->uuid());
    $this->assertEmpty($is_deleted);
  }

  /**
   * Test case to fetch the tracking record for a given uuid.
   *
   * @covers ::getRecord
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testGetRecord() {
    $record = $this->publisherTracker->getRecord($this->node->uuid());
    $this->assertIsObject($record);
  }

  /**
   * Test case to fetch the tracking entity for a given uuid.
   *
   * @covers ::get
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testGet() {
    $record = $this->publisherTracker->get($this->node->uuid());
    $this->assertIsObject($record);
  }

  /**
   * Test case to fetch the Queue ID for a given uuid.
   *
   * @covers ::getQueueId
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testGetQueueId() {
    $uuid = $this->node->uuid();

    // Queue id should not be empty when node is created.
    $queue_id_after = $this->publisherTracker->getQueueId($uuid);
    $this->assertNotEmpty($queue_id_after);

    // Delete the node.
    $this->node->delete();

    // Queue id should be empty when node is deleted.
    $queue_id_before = $this->publisherTracker->getQueueId($uuid);
    $this->assertEmpty($queue_id_before);
  }

  /**
   * Test case to update the entity status.
   *
   * @covers ::track
   * @covers ::queue
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testInsertOrUpdate() {
    // Status is set to queued whenever a new entity is created.
    $queued_status = $this->getTrackingTableColByUuid($this->node->uuid(), 'status');
    $this->assertEquals($queued_status, 'queued');

    // Process the queue.
    while ($item = $this->queue->claimItem()) {
      $this->queueWorker->processItem($item->data);
    }
    // Status changed to exported after queue process.
    $exported_status = $this->getTrackingTableColByUuid($this->node->uuid(), 'status');
    $this->assertEquals($exported_status, 'exported');

    // Change the tracking table entity status to "queued".
    $this->publisherTracker->queue($this->node);
    $status_changed_to_queue = $this->getTrackingTableColByUuid($this->node->uuid(), 'status');
    $this->assertEquals($status_changed_to_queue, 'queued');

    $hash = $this->getTrackingTableColByUuid($this->node->uuid(), 'hash');
    // Change the tracking table entity status to "exported".
    $this->publisherTracker->track($this->node, $hash);
    $status_changed_to_export = $this->getTrackingTableColByUuid($this->node->uuid(), 'status');
    $this->assertEquals($status_changed_to_export, 'exported');
  }

  /**
   * Fetch tracking table column for a given uuid.
   *
   * @param string $entity_uuid
   *   Entity Id.
   * @param string $col_name
   *   Column name.
   *
   * @return string|bool
   *   The tracking table respective data.
   */
  protected function getTrackingTableColByUuid(string $entity_uuid, string $col_name = 'entity_uuid'): ?string {
    $query = \Drupal::database()->select(self::TABLE_NAME, 't');
    $query->fields('t', [$col_name]);
    $query->condition('entity_uuid', $entity_uuid);

    return $query->execute()->fetchField();
  }

  /**
   * {@inheritDoc}
   */
  public function tearDown() {
    $this->node->delete();
    $this->queue->deleteQueue();

    parent::tearDown();
  }

}
