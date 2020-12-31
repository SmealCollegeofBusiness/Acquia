<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Class EntityViewDisplayDependencyCollectorTest.
 *
 * @coversDefaultClass \Drupal\depcalc\EventSubscriber\DependencyCollector\EntityViewDisplayDependencyCollector
 *
 * @package Drupal\depcalc\EventSubscriber\DependencyCollector
 *
 * @group depcalc
 */
class EntityViewDisplayDependencyCollectorTest extends KernelTestBase {

  use NodeCreationTrait;
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
    'entity_test',
    'entity_test_third_party',
    'field',
    'field_ui',
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
  public function testEntityViewDisplaysWereAdded() {
    EntityViewMode::create([
      'id' => 'node.foobar1',
      'targetEntityType' => 'node',
      'status' => TRUE,
      'enabled' => TRUE,
      'label' => 'My view mode',
    ])->save();
    EntityViewMode::create([
      'id' => 'node.foobar2',
      'targetEntityType' => 'node',
      'status' => TRUE,
      'enabled' => TRUE,
      'label' => 'My view mode',
    ])->save();

    NodeType::create(
      ['type' => 'entity_view_display']
    )->save();
    $entity = $this->createNode([
      'type' => 'entity_view_display',
      'title' => 'Title Test',
    ]);

    $display1 = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'entity_view_display',
      'mode' => 'foobar1',
      'label' => 'My view mode 1',
      'status' => TRUE,
    ]);
    $display1->save();

    $display2 = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'entity_view_display',
      'mode' => 'foobar2',
      'label' => 'My view mode 2',
      'status' => TRUE,
    ]);
    $display2->save();

    $event = $this->dispatchCalculateDependencies($entity);
    $dependencies = $event->getDependencies();

    $this->assertArrayHasKey($display1->uuid(), $dependencies);
    $this->assertArrayHasKey($display2->uuid(), $dependencies);
  }

}
