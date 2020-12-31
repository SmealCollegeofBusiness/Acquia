<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;

/**
 * Class TermParentCollectorTest.
 *
 * @coversDefaultClass \Drupal\depcalc\EventSubscriber\DependencyCollector\TermParentCollector
 *
 * @package Drupal\depcalc\EventSubscriber\DependencyCollector
 *
 * @group depcalc
 */
class TermParentCollectorTest extends KernelTestBase {

  use CalculateDependenciesEventDispatcherTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'field',
    'filter',
    'language',
    'content_translation',
    'node',
    'system',
    'text',
    'user',
    'file',
    'taxonomy',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \ReflectionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('filter_format');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('system', 'sequences');
    $this->installSchema('file', 'file_usage');
    $this->installConfig([
      'node',
      'user',
      'file',
      'filter',
    ]);
  }

  /**
   * @covers ::onCalculateDependencies
   *
   * @throws \Exception
   */
  public function testParentWasAdded() {
    $parent = Term::create([
      'vid' => 'tags',
      'status' => 1,
      'name' => 'Term parent',
    ]);
    $parent->save();
    $child = Term::create([
      'vid' => 'tags',
      'status' => 1,
      'name' => 'Term child',
    ]);
    $child->parent->entity = $parent;
    $child->save();

    $event = $this->dispatchCalculateDependencies($child, []);
    $dependencies = $event->getDependencies();

    $this->assertArrayHasKey($parent->uuid(), $dependencies, 'Taxonomy term should have parent as a dependency');
  }

}
