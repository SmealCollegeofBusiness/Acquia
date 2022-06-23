<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\Core\Entity\EntityInterface;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Test node with path alias dependency collector.
 *
 * @coversDefaultClass \Drupal\depcalc\EventSubscriber\DependencyCollector\EntityPathAliasCollector
 *
 * @group depcalc
 *
 * @package Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector
 */
class EntityPathAliasCollectorTest extends KernelTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use UserCreationTrait;
  use PathAliasTestTrait;

  /**
   * The path alias.
   *
   * @var \Drupal\path_alias\PathAliasInterface
   */
  protected $alias;

  /**
   * The dependency calculator.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'depcalc',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['node', 'field', 'filter']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');

    // Create a test content type.
    $this->createContentType();
    // Create a test node.
    $this->node = $this->createNode();
    // Creates a new path alias.
    $this->alias = $this->createPathAlias("/node/{$this->node->id()}", "/test-alias");

    $this->calculator = \Drupal::service('entity.dependency.calculator');
  }

  /**
   * Tests dependencies calculation for node.
   *
   * @covers ::onCalculateDependencies
   *
   * @throws \Exception
   */
  public function testDependencyCollector() {
    // Node dependency on path alias.
    $node_dependencies = $this->calculateDependencies($this->node);
    $this->assertArrayHasKey($this->alias->uuid(), $node_dependencies);
  }

  /**
   * Calculates dependencies for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   *
   * @return array
   *   Dependencies array.
   *
   * @throws \Exception
   */
  protected function calculateDependencies(EntityInterface $entity): array {
    $wrapper = new DependentEntityWrapper($entity);

    return $this->calculator->calculateDependencies($wrapper, new DependencyStack());
  }

}
