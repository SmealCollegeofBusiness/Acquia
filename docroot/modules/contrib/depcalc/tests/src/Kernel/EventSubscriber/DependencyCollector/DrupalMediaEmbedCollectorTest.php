<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Kernel\MediaEmbedFilterTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Class DrupalMediaEmbedCollectorTest.
 *
 * @requires module media
 *
 * @group depcalc
 *
 * @package Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector
 */
class DrupalMediaEmbedCollectorTest extends MediaEmbedFilterTestBase {

  use NodeCreationTrait, ContentTypeCreationTrait;

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
    'media',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('node');
    $this->installConfig('node');
    $this->installSchema('node', 'node_access');

    $this->createContentType([
      'type' => 'page',
    ]);
    Media::create([
      'uuid' => static::EMBEDDED_ENTITY_UUID_2,
      'bundle' => 'image',
      'name' => 'Another media'
    ])->save();

    $this->calculator = \Drupal::service('entity.dependency.calculator');
  }

  /**
   * Test dependency calculation.
   *
   * Checks the node's dependencies contains embedded media entities.
   *
   * @param array $embed_attributes
   *   Attributes to add for the embedded media entity.
   *
   * @dataProvider providerTestExtractEmbeddedMediaEntities
   */
  public function testExtractEmbeddedMediaEntities(array $embed_attributes) {
    $embed_code = '';
    foreach ($embed_attributes as $embed_attribute) {
      $embed_code .= $this->createEmbedCode($embed_attribute);
    }

    $node = $this->createNode([
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
   * Data provider for testExtractEmbeddedMediaEntities().
   */
  public function providerTestExtractEmbeddedMediaEntities() {
    return [
      'embed_media' => [
        [
          [
            'data-entity-type' => 'media',
            'data-entity-uuid' => static::EMBEDDED_ENTITY_UUID,
          ],
        ],
      ],
      'embed_multiple_media' => [
        [
          [
            'data-entity-type' => 'media',
            'data-entity-uuid' => static::EMBEDDED_ENTITY_UUID,
          ],
          [
            'data-entity-type' => 'media',
            'data-entity-uuid' => static::EMBEDDED_ENTITY_UUID_2,
          ],
        ],
      ],
    ];
  }

}
