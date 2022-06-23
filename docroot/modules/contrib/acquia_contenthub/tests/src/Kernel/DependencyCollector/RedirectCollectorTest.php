<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\DependencyCollector;

use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Test entity redirect dependency collector.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class RedirectCollectorTest extends KernelTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use UserCreationTrait;
  use EntityReferenceTestTrait;
  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'acquia_contenthub',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
    'path_alias',
    'link',
    'menu_link_content',
    'redirect',
  ];

  /**
   * DependencyCalculator.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  private $calculator;

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setUp();

    $this->installConfig('node');
    $this->installConfig('field');
    $this->installConfig('filter');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('redirect');

    $this->calculator = \Drupal::service('entity.dependency.calculator');
  }

  /**
   * Tests redirect dependencies for given entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRedirectDependencies() {
    $bundle = 'depcalc_dummy_content_type';
    $contentType = $this->createContentType([
      'type' => $bundle,
      'name' => 'Depcalc. Dummy content type',
    ]);
    $contentType->save();
    $node = $this->createNode([
      'type' => $bundle,
    ]);
    $node->save();

    // Create path alias for this node.
    $path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => "/node/{$node->id()}",
      'alias' => "/dynamic-path-alias",
      'langcode' => 'en',
    ]);
    $path_alias->save();

    // Redirect with url: entity:node/1.
    $redirect_entity_array = [
      'redirect_source' => 'redirects/redirect1',
      'redirect_redirect' => 'entity:node/' . $node->id(),
      'language' => 'und',
      'status_code' => '301',
    ];
    $redirect_uuid_with_entity_url = $this->getRedirectUuid($redirect_entity_array);

    // Redirect with url: internal:/node/1.
    $redirect_internal_array = [
      'redirect_source' => 'redirects/redirect2',
      'redirect_redirect' => 'internal:/node/' . $node->id(),
      'language' => 'und',
      'status_code' => '301',
    ];
    $redirect_uuid_with_internal_url = $this->getRedirectUuid($redirect_internal_array);

    // Redirect with url: internal:/dynamic-path-alias.
    $redirect_path_alias_array = [
      'redirect_source' => 'redirects/redirect3',
      'redirect_redirect' => 'internal:/dynamic-path-alias',
      'language' => 'und',
      'status_code' => '301',
    ];
    $redirect_uuid_with_path_alias = $this->getRedirectUuid($redirect_path_alias_array);

    $dependencies = $this->calculateDependencies($node);
    $this->assertArrayHasKey($redirect_uuid_with_entity_url, $dependencies);
    $this->assertArrayHasKey($redirect_uuid_with_internal_url, $dependencies);
    $this->assertArrayHasKey($redirect_uuid_with_path_alias, $dependencies);
  }

  /**
   * Calculates dependencies for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node.
   *
   * @return array
   *   Dependencies array.
   *
   * @throws \Exception
   */
  private function calculateDependencies(NodeInterface $node): array {
    $wrapper = new DependentEntityWrapper($node);
    return $this->calculator->calculateDependencies($wrapper, new DependencyStack());
  }

  /**
   * Helper function to create redirect.
   *
   * @param array $input_array
   *   Input array for redirect entity.
   *
   * @return string
   *   Uuid of redirect created.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function getRedirectUuid(array $input_array): string {
    $storage = \Drupal::entityTypeManager()->getStorage('redirect');
    $redirect = $storage->create($input_array);
    $redirect->save();
    return $redirect->uuid();
  }

}
