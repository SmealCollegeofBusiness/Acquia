<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Tests that selected entities are successfully excluded for the queue.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class EntityTypeOrBundleExcludeTest extends QueueingTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'user',
    'acquia_contenthub_server_test',
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
    $this->installSchema('system', ['sequences']);
    $this->installConfig([
      'acquia_contenthub_publisher',
    ]);

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('acquia_contenthub_publisher.exclude_settings');
    $config
      ->set('exclude_entity_types', ['node_type', 'user'])
      ->set('exclude_bundles', ['node:bundle_test']);
    $config->save();
  }

  /**
   * Tests that expected entities are excluded.
   */
  public function testIsExcluded() {
    // Makes sure queue is empty before this test.
    $this->contentHubQueue->purgeQueues();

    NodeType::create([
      'type' => 'bundle_test',
    ])->save();

    NodeType::create([
      'type' => 'bundle_test_2',
    ])->save();

    $this->assertEquals(
      $this->contentHubQueue->getQueueCount(),
      0,
      'Node type config entity not queued.'
    );

    // Creates a new user an assert is excluded as expected.
    $user = User::create([
      'name' => $this->randomString(),
      'mail' => 'email1@example.com',
    ]);
    $user->save();

    $this->assertEquals(
      $this->contentHubQueue->getQueueCount(),
      0,
      'User not queued.'
    );

    // Creates a new published node of an excluded bundle.
    $node_exclude = Node::create([
      'type' => 'bundle_test',
      'title' => 'Should not queue',
    ]);
    $node_exclude->setPublished();
    $node_exclude->save();

    $this->assertEquals(
      $this->contentHubQueue->getQueueCount(),
      0,
      'Node not queued.'
    );

    // Creates a new published node of a not excluded bundle.
    $node_exclude = Node::create([
      'type' => 'bundle_test_2',
      'title' => 'Should queue',
    ]);
    $node_exclude->setPublished();
    $node_exclude->save();

    $this->assertEquals(
      $this->contentHubQueue->getQueueCount(),
      1,
      'Node queued.'
    );

  }

}
