<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\Component\Utility\Html;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * The EntityEmbedCollectorTest class.
 *
 * @requires module entity_embed
 *
 * @group depcalc
 *
 * @package Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector
 */
class EntityEmbedCollectorTest extends KernelTestBase {

  use NodeCreationTrait;
  use UserCreationTrait;
  use ContentTypeCreationTrait;

  /**
   * The UUID to use for the embedded entity.
   *
   * @var string
   */
  const EMBEDDED_ENTITY_UUID = 'e7a3e1fe-b69b-417e-8ee4-c80cb7640e63';

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
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig('filter');
    $this->installConfig('node');

    // Create a user with required permissions. Ensure that we don't use user 1
    // because that user is treated in special ways by access control handlers.
    $user = $this->createUser([
      'access content',
    ]);
    $this->container->set('current_user', $user);

    // Create a sample node to be embedded.
    $this->createContentType(['type' => 'page', 'name' => 'Basic page']);

    $this->createNode([
      'title' => 'Embed Test Node',
      'uuid' => static::EMBEDDED_ENTITY_UUID,
    ]);
    $this->createNode([
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
   * Gets an embed code with given attributes.
   *
   * @param array $attributes
   *   The attributes to add.
   *
   * @return string
   *   A string containing a drupal-entity dom element.
   *
   * @see EntityEmbedFilterTestBase::createEmbedCode()
   */
  protected function createEmbedCode(array $attributes): string {
    $dom = Html::load('<drupal-entity>This placeholder should not be rendered.</drupal-entity>');
    $xpath = new \DOMXPath($dom);
    $drupal_entity = $xpath->query('//drupal-entity')[0];
    foreach ($attributes as $attribute => $value) {
      $drupal_entity->setAttribute($attribute, $value);
    }
    return Html::serialize($dom);
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
