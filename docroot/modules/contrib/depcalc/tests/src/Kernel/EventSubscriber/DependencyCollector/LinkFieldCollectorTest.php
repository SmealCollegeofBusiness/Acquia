<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\link\LinkItemInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Class LinkFieldCollectorTest.
 *
 * @group depcalc
 *
 * @package Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector
 */
class LinkFieldCollectorTest extends KernelTestBase {

  use NodeCreationTrait {
    createNode as drupalCreateNode;
  }

  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'field',
    'filter',
    'link',
    'node',
    'path_alias',
    'system',
    'user',
    'text',
  ];

  /**
   * Calculates all the dependencies of a given entity.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('node', 'node_access');
    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installConfig('filter');
    $this->installConfig('node');
    $this->installConfig(['field', 'system']);

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'link',
      'entity_type' => 'node',
      'type' => 'link',
      'cardinality' => -1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'page',
      'label' => 'link',
      'settings' => ['link_type' => LinkItemInterface::LINK_GENERIC],
    ]);
    $field->save();

    $this->calculator = Drupal::service('entity.dependency.calculator');
  }

  /**
   * Test dependency calculation.
   *
   * Checks the node's dependencies contains entities referenced in link field.
   */
  public function testLinkFieldCollector() {
    $node = $this->drupalCreateNode([]);

    $linked_nodes = [];
    for ($i = 0; $i < 4; $i++) {
      $linked_nodes[] = $this->drupalCreateNode([]);
    }

    foreach ($linked_nodes as $linked_node) {
      /** @var \Drupal\node\NodeInterface $linked_node */
      $id = $linked_node->id();
      $node->get('link')->appendItem([
        'uri' => "entity:node/$id",
        'title' => $linked_node->label(),
      ]);
    }

    $node->save();

    try {
      $wrapper = new DependentEntityWrapper($node);
    }
    catch (\Exception $exception) {
      $this->markTestIncomplete($exception->getMessage());
    }

    $dependencies = $this->calculator->calculateDependencies($wrapper, new DependencyStack());

    foreach ($linked_nodes as $linked_node) {
      $this->assertArrayHasKey($linked_node->uuid(), $dependencies);
    }
  }

  /**
   * Test dependency calculation.
   *
   * Checks the node's dependencies contains entities which have been deleted.
   */
  public function testLinkFieldCollectorDeletedReference() {
    $node = $this->drupalCreateNode([]);

    $linked_node = $this->drupalCreateNode([]);
    $id = $linked_node->id();

    $node->get('link')->appendItem([
      'uri' => "entity:node/$id",
      'title' => $linked_node->label(),
    ]);

    $node->save();

    try {
      $wrapper = new DependentEntityWrapper($node);
    }
    catch (\Exception $exception) {
      $this->markTestIncomplete($exception->getMessage());
    }

    $dependencies = $this->calculator->calculateDependencies($wrapper,
      new DependencyStack());
    $this->assertArrayHasKey($linked_node->uuid(), $dependencies);

    $linked_node->delete();

    try {
      $wrapper = new DependentEntityWrapper($node);
    }
    catch (\Exception $exception) {
      $this->markTestIncomplete($exception->getMessage());
    }

    $dependencies = $this->calculator->calculateDependencies($wrapper,
      new DependencyStack());
    $this->assertArrayNotHasKey($linked_node->uuid(), $dependencies);
  }

}
