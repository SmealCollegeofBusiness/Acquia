<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests subscriber tracker methods.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_subscriber\SubscriberTracker
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class SubscriberTrackerTest extends EntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * Node 1 object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node1;

  /**
   * Node 2 object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node2;

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
    'acquia_contenthub_subscriber',
    'acquia_contenthub_server_test',
  ];

  /**
   * Subscriber tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * {@inheritDoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'field',
      'filter',
      'node',
    ]);
    $this->node1 = $this->createNode();
    $this->node2 = $this->createNode();
    $this->installSchema('acquia_contenthub_subscriber', [SubscriberTracker::IMPORT_TRACKING_TABLE]);
    $this->tracker = $this->container->get('acquia_contenthub_subscriber.tracker');
    $this->tracker->track($this->node2, sha1(json_encode($this->node2->toArray())));
  }

  /**
   * Tests listing of tracked entities in subscriber tracker.
   *
   * @covers \Drupal\acquia_contenthub_subscriber\SubscriberTracker::listTrackedEntities
   */
  public function testListTrackedEntities(): void {
    $this->tracker->queue($this->node1->uuid());
    // Works with single status.
    $queued_entities = array_column($this->tracker->listTrackedEntities(SubscriberTracker::QUEUED), 'entity_uuid');
    $this->assertContains($this->node1->uuid(), $queued_entities);

    $tracked_entities = array_column($this->tracker->listTrackedEntities(SubscriberTracker::IMPORTED), 'entity_uuid');
    $this->assertContains($this->node2->uuid(), $tracked_entities);

    // Works with multiple statuses.
    $queued_tracked_entities = array_column($this->tracker->listTrackedEntities([SubscriberTracker::QUEUED, SubscriberTracker::IMPORTED]), 'entity_uuid');
    $this->assertContains($this->node1->uuid(), $queued_tracked_entities);
    $this->assertContains($this->node2->uuid(), $queued_tracked_entities);
  }

}
