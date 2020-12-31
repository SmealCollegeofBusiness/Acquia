<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\node\Entity\Node;

/**
 * Class WorkflowCollectorTest.
 *
 * @coversDefaultClass \Drupal\depcalc\EventSubscriber\DependencyCollector\WorkflowCollector
 *
 * @package Drupal\depcalc\EventSubscriber\DependencyCollector
 *
 * @group depcalc
 */
class WorkflowCollectorTest extends KernelTestBase {

  use ContentModerationTestTrait;
  use CalculateDependenciesEventDispatcherTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'field',
    'node',
    'system',
    'text',
    'user',
    'path_alias',
    'content_moderation',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \ReflectionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', 'sequences');
    $this->installConfig([
      'node',
      'user',
      'content_moderation',
    ]);
  }

  /**
   * @covers ::onCalculateDependencies
   *
   * @throws \Exception
   */
  public function testEntityHasModerationInfoDependency() {
    $workflow = $this->createEditorialWorkflow();
    NodeType::create([
      'name' => 'Moderation type',
      'type' => 'moderation_type',
    ])->save();
    $entity = Node::create(['type' => 'moderation_type', 'title' => 'New node 1']);
    $entity->save();

    $this->addEntityTypeAndBundleToWorkflow($workflow, $entity->getEntityTypeId(), $entity->bundle());
    $event = $this->dispatchCalculateDependencies($entity);
    $dependencies = $event->getDependencies();

    $this->assertArrayHasKey($workflow->uuid(), $dependencies);
  }

}
