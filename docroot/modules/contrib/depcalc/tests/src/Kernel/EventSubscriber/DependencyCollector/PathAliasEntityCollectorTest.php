<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

/**
 * Test path alias dependency collector.
 *
 * @coversDefaultClass \Drupal\depcalc\EventSubscriber\DependencyCollector\PathAliasEntityCollector
 *
 * @group depcalc
 *
 * @package Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector
 */
class PathAliasEntityCollectorTest extends EntityPathAliasCollectorTest {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Tests dependencies calculation for path alias.
   *
   * @covers ::onCalculateDependencies
   *
   * @throws \Exception
   */
  public function testDependencyCollector() {
    // Path alias dependency on node.
    $path_alias_dependencies = $this->calculateDependencies($this->alias);
    $this->assertArrayHasKey($this->node->uuid(), $path_alias_dependencies);
  }

}
