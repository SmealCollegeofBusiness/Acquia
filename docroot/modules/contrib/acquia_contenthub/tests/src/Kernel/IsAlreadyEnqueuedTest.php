<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that entities aren't added to the queue multiple times.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class IsAlreadyEnqueuedTest extends QueueingTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'user',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'type' => 'bundle_test',
    ])->save();
  }

  /**
   * Tests that node isn't enqueued more than once.
   */
  public function testIsAlreadyEnqueued() {

    // Makes sure queue is empty before this test.
    $this->contentHubQueue->purgeQueues();

    // Creates a new published node.
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'bundle_test',
      'title' => 'Title',
    ]);
    $node->setPublished();
    $node->save();

    $this->assertEquals(
      $this->contentHubQueue->getQueueCount(),
      1,
      'Node created and queued.'
    );

    $node->setTitle('New title');
    $node->save();
    $this->assertEquals(
      $this->contentHubQueue->getQueueCount(),
      1,
      'Node not queued again.'
    );
  }

}
