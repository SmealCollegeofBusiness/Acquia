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
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Class LinkFieldCollectorTest to check Link field dependencies.
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

  use UserCreationTrait;

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
    'book',
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
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', 'node_access');
    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installConfig('filter');
    $this->installConfig('node');
    $this->installConfig(['field', 'system']);
    $this->installSchema('book', 'book');
    $this->installConfig('book');

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
    for ($i = 0; $i < 2; $i++) {
      // Nodes to be linked as "entity:".
      $linked_nodes['entity'][] = $this->drupalCreateNode([]);
      // Nodes to be linked as "internal:".
      $linked_nodes['internal'][] = $this->drupalCreateNode([]);
    }

    foreach ($linked_nodes as $key => $linked_node_array) {
      foreach ($linked_node_array as $linked_node) {
        /** @var \Drupal\node\NodeInterface $linked_node */
        $uri_key = $key === 'entity' ? "$key:" : "$key:/";
        $node->get('link')->appendItem([
          'uri' => "{$uri_key}node/{$linked_node->id()}",
          'title' => $linked_node->label(),
        ]);
      }
    }

    // Add internal route to check module dependency for the given route.
    $node->get('link')->appendItem([
      'uri' => 'internal:/book',
      'title' => 'Books',
    ]);

    // Add internal route with route name outside of the standard
    // $MODULE_NAME.ROUTE_DESCRIPTION to assert it won't fail.
    $node->get('link')->appendItem([
      'uri' => 'internal:/',
      'title' => 'No route',
    ]);

    // Adding an empty link to make sure empty links
    // won't break dependency calculation.
    // See issue 3199592.
    $node->get('link')->appendItem([
      'uri' => NULL,
    ]);

    $node->save();

    try {
      $wrapper = new DependentEntityWrapper($node);
    }
    catch (\Exception $exception) {
      $this->markTestIncomplete($exception->getMessage());
    }

    $dependencies = $this->calculator->calculateDependencies($wrapper, new DependencyStack());

    foreach ($linked_nodes as $linked_node_array) {
      foreach ($linked_node_array as $linked_node) {
        $this->assertArrayHasKey($linked_node->uuid(), $dependencies);
      }
    }

    // Check whether the module has been added to the module dependencies.
    $this->assertContains('book', $dependencies['module']);
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
