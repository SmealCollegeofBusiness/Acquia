<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal;
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
 * Class EntityReferenceFieldDependencyCollectorTest.
 *
 * @group depcalc
 *
 * @package src\Kernel\EventSubscriber\DependencyCollector
 */
class EntityReferenceFieldDependencyCollectorTest extends KernelTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use UserCreationTrait;
  use EntityReferenceTestTrait;
  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'depcalc',
    'field',
    'filter',
    'node',
    'system',
    'taxonomy',
    'text',
    'user',
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
  protected function setUp() {
    parent::setUp();

    $this->installConfig('node');
    $this->installConfig('field');
    $this->installConfig('filter');

    $this->installSchema('node', 'node_access');
    $this->installSchema('system', ['sequences', 'key_value_expire']);

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');

    $this->calculator = Drupal::service('entity.dependency.calculator');
  }

  /**
   * Tests dependencies calculation for an entity reference field.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testDependenciesCollection() {
    $bundle = 'depcalc_dummy_content_type';
    $tagsFieldName = 'field_dummy_tags';
    $contentType = $this->createContentType([
      'type' => $bundle,
      'name' => 'Depcalc. Dummy content type',
    ]);
    $contentType->save();
    $this->createEntityReferenceField('node', $bundle, $tagsFieldName, 'Tags', 'taxonomy_term');

    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $node = $this->createNode(['type' => $bundle]);
    $node->setOwner($user1);
    $node->save();

    $dependencies = $this->calculateDependencies($node);
    $this->assertArrayHasKey($user1->uuid(), $dependencies);
    $this->assertArrayNotHasKey($user2->uuid(), $dependencies);

    $node->setOwner($user2);
    $node->save();

    $dependencies = $this->calculateDependencies($node);
    $this->assertArrayNotHasKey($user1->uuid(), $dependencies);
    $this->assertArrayHasKey($user2->uuid(), $dependencies);

    $vocab = $this->createVocabulary();
    $term1 = $this->createTerm($vocab);
    $term2 = $this->createTerm($vocab, ['parent' => ['target_id' => $term1->id()]]);
    $node->set($tagsFieldName, ['target_id' => $term1->id()]);
    $node->save();
    $dependencies = $this->calculateDependencies($node);
    $this->assertArrayHasKey($vocab->uuid(), $dependencies);
    $this->assertArrayHasKey($term1->uuid(), $dependencies);

    $node->set($tagsFieldName, ['target_id' => $term2->id()]);
    $node->save();

    $dependencies = $this->calculateDependencies($node);
    $this->assertArrayHasKey($vocab->uuid(), $dependencies);
    $this->assertArrayHasKey($term1->uuid(), $dependencies);
    $this->assertArrayHasKey($term2->uuid(), $dependencies);
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

}
