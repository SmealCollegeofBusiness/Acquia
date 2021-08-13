<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemovePathAliasField;
use Drupal\KernelTests\KernelTestBase;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\acquia_contenthub\Kernel\Stubs\DrupalVersion;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests remove path alias field serialization.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemovePathAliasField
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExcludeContentField
 */
class RemovePathAliasFieldTest extends KernelTestBase {

  use NodeCreationTrait, ContentTypeCreationTrait;
  use DrupalVersion;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'field',
    'filter',
    'depcalc',
    'acquia_contenthub',
    'node',
    'text',
    'user',
    'system',
    'path',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    if (version_compare(\Drupal::VERSION, '9.0', '>=')) {
      static::$modules[] = 'path_alias';
    }

    parent::setup();
    self::$modules = array_merge(parent::$modules, self::$modules);

    if (version_compare(\Drupal::VERSION, '8.8.0', '>=')) {
      $this->installEntitySchema('path_alias');
    }

    $this->installConfig('node');
    $this->installConfig('field');
    $this->installConfig('filter');

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

  }

  /**
   * Tests the removal of path alias field.
   *
   * @covers ::excludeContentField
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRemovePathAliasField() {
    $this->createContentType([
      'type' => 'article',
      'name' => 'article',
    ]);

    $node = $this->createNode([
      'type' => 'article',
    ]);

    $path_alias = PathAlias::create([
      'path' => '/node/' . $node->id(),
      'alias' => 'new_test_path',
    ]);
    $path_alias->save();

    $remove_id_and_revision_field = new RemovePathAliasField();
    foreach ($node as $field_name => $field) {
      $event = new ExcludeEntityFieldEvent($node, $field_name, $field);
      $remove_id_and_revision_field->excludeContentField($event);

      if ($event->getEntity()->getEntityTypeId() !== 'path_alias'
        && $event->getFieldName() === 'path') {
        $this->assertTrue($event->isExcluded());
        $this->assertTrue($event->isPropagationStopped());
      }
      else {
        $this->assertFalse($event->isExcluded());
        $this->assertFalse($event->isPropagationStopped());
      }
    }
  }

}
