<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Drupal\acquia_contenthub\Libs\Depcalc\DepcalcCacheRebuildTrait;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests depcalc cache rebuild from a trait.
 *
 * @group acquia_contenthub
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class DepcalcCacheRebuildTest extends SubscriberTrackerTest {

  use NodeCreationTrait;
  use DepcalcCacheRebuildTrait;

  /**
   * Depcalc operator.
   *
   * @var \Drupal\acquia_contenthub\Libs\Depcalc\DepcalcCacheOperator
   */
  private $operator;

  /**
   * Depcalc cache.
   *
   * @var \Drupal\depcalc\Cache\DepcalcCacheBackend
   */
  private $depcalcCache;

  /**
   * Node 3.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $node3;

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
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tracker->track($this->node1, sha1(json_encode($this->node1->toArray())));
    $this->operator = $this->container->get('acquia_contenthub.depcalc_cache_operator');
    $this->depcalcCache = $this->container->get('cache.depcalc');
    $dep_calculator = $this->container->get('entity.dependency.calculator');
    // This is necessary for table creation.
    $dep_calculator->calculateDependencies(new DependentEntityWrapper($this->node1), new DependencyStack());
    $this->depcalcCache->deleteAllPermanent();
    $this->node3 = $this->createNode();
    $this->tracker->track($this->node3, sha1(json_encode($this->node3->toArray())));
  }

  /**
   * Tests depcalc cache rebuild for entities from tracking table.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCacheRebuildFromImportTracking(): void {
    $this->assertTrue($this->operator->tableExists());
    $this->assertTrue($this->operator->cacheIsEmpty());
    // Rebuild cache from import tracking table.
    $this->rebuildDepalcCache();
    $this->assertFalse($this->operator->cacheIsEmpty());
    $node_uuids = $n_uuids = [$this->node1->uuid(), $this->node2->uuid(), $this->node3->uuid()];
    $cached_entities = array_keys($this->depcalcCache->getMultiple($node_uuids));
    $this->assertEqualsCanonicalizing($n_uuids, $cached_entities);
    $this->depcalcCache->deleteAllPermanent();
    $this->node3->delete();
    $this->rebuildDepalcCache();
    $cached_entities = array_keys($this->depcalcCache->getMultiple($n_uuids));
    $this->assertContains($this->node1->uuid(), $cached_entities, 'Cache rebuilt for node 1 as it exists.');
    $this->assertContains($this->node2->uuid(), $cached_entities, 'Cache rebuilt for node 2 as it exists.');
    $this->assertNotContains($this->node3->uuid(), $cached_entities, 'Cache isn\'t rebuilt for node 3 as it\'s deleted.');
  }

}
