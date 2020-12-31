<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\Tests\entity_embed\Kernel\EntityEmbedFilterTestBase;

/**
 * Class EntityEmbedCollectorTest.
 *
 * @requires module entity_embed
 *
 * @group depcalc
 *
 * @package Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector
 */
class EntityEmbedCollectorTest extends EntityEmbedFilterTestBase {

  /**
   * The UUID to use for the embedded entity.
   *
   * @var string
   */
  const EMBEDDED_ENTITY_UUID_2 = 'f3548e06-eb82-4c04-8499-3eb886da8f34';

  /**
   * Calculates all the dependencies of a given entity.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  private $calculator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'system',
    'field',
    'filter',
    'text',
    'user',
    'depcalc',
    'embed',
    'entity_embed',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('path_alias');

    $this->drupalCreateNode([
      'title' => 'Embed Test Node 2',
      'uuid' => static::EMBEDDED_ENTITY_UUID_2,
    ]);

    $this->calculator = \Drupal::service('entity.dependency.calculator');
  }

  /**
   * Test dependency calculation.
   *
   * Checks the node's dependencies contains embedded entities.
   *
   * @param array $embed_attributes
   *   Attributes to add for the embedded entity.
   *
   * @dataProvider providerTestExtractEmbeddedEntities
   */
  public function testExtractEmbeddedEntities(array $embed_attributes) {
    $embed_code = '';
    foreach ($embed_attributes as $embed_attribute) {
      $embed_code .= $this->createEmbedCode($embed_attribute);
    }

    $node = $this->drupalCreateNode([
      'body' => [
        [
          'value' => $embed_code,
          'format' => filter_default_format(),
        ],
      ],
    ]);

    try {
      $wrapper = new DependentEntityWrapper($node);
    }
    catch (\Exception $exception) {
      $this->markTestIncomplete($exception->getMessage());
    }

    $dependencies = $this->calculator->calculateDependencies($wrapper, new DependencyStack());

    foreach ($embed_attributes as $embed_attribute) {
      $this->assertArrayHasKey($embed_attribute['data-entity-uuid'], $dependencies);
    }

  }

  /**
   * Data provider for testExtractEmbeddedEntities().
   */
  public function providerTestExtractEmbeddedEntities() {
    return [
      'embed_node' => [
        [
          [
            'data-entity-type' => 'node',
            'data-entity-uuid' => static::EMBEDDED_ENTITY_UUID,
            'data-view-mode' => 'teaser',
          ],
        ],
      ],
      'embed_multiple_node' => [
        [
          [
            'data-entity-type' => 'node',
            'data-entity-uuid' => static::EMBEDDED_ENTITY_UUID,
            'data-view-mode' => 'teaser',
          ],
          [
            'data-entity-type' => 'node',
            'data-entity-uuid' => static::EMBEDDED_ENTITY_UUID_2,
            'data-view-mode' => 'teaser',
          ],
        ],
      ],
    ];
  }

}
