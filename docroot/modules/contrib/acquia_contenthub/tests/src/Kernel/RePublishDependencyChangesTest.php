<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests that entities whose depcalc cache got invalidated are republished.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class RePublishDependencyChangesTest extends QueueingTestBase {

  use UserCreationTrait;
  use TaxonomyTestTrait;
  use EntityReferenceTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'filter',
    'node',
    'path_alias',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * Created taxonomy vocabulary.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * Created taxonomy term.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    // Change container to database cache backends.
    $container
      ->register('cache_factory', 'Drupal\Core\Cache\CacheFactory')
      ->addArgument(new Reference('settings'))
      ->addMethodCall('setContainer', [new Reference('service_container')]);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('field_config');

    $this->vocabulary = $this->createVocabulary();

    NodeType::create([
      'type' => 'bundle_test',
    ])->save();

    $this->createEntityReferenceField('node', 'bundle_test', 'taxonomy_reference', 'taxonomy_reference', 'taxonomy_term');
    $this->term = $this->createTerm($this->vocabulary, ['name' => 'Test']);
  }

  /**
   * Tests that node isn't enqueued more than once.
   */
  public function testIsAlreadyEnqueued() {
    $user = $this->setUpCurrentUser();

    // Creates a new node with taxonomy reference.
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'bundle_test',
      'uid' => $user->id(),
      'taxonomy_reference' => $this->term->id(),
      'title' => 'Title',
    ]);
    $node->setPublished();
    $node->save();

    // Create path alias entity for the given node.
    $path = PathAlias::create([
      'path' => '/node/' . $node->id(),
      'alias' => 'new_test_path',
    ]);

    $path->save();

    $publisher_tracker = \Drupal::service('acquia_contenthub_publisher.tracker');

    $this->assertNotFalse($publisher_tracker->getQueueId($node->uuid()), 'Node queued for export');
    $this->assertNotFalse($publisher_tracker->getQueueId($path->uuid()), 'Path alias queued for export');
    $this->assertNotFalse($publisher_tracker->getQueueId($this->term->uuid()), 'Term queued for export');

    $this->contentHubQueue->purgeQueues();

    $wrapper = new DependentEntityWrapper($node);
    $stack = new DependencyStack();
    $calculator = \Drupal::service('entity.dependency.calculator');
    $calculator->calculateDependencies($wrapper, $stack);

    $this->term->delete();

    $this->assertNotFalse($publisher_tracker->getQueueId($node->uuid()), 'Node queued again after term reference delete');
    $this->assertNotFalse($publisher_tracker->getQueueId($path->uuid()), 'Path queued again after term reference delete');
    $this->assertFalse($publisher_tracker->getQueueId($this->term->uuid()), 'Term not queued for export anymore');
  }

}
