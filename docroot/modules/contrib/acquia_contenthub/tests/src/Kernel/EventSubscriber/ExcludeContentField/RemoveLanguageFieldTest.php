<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemoveLanguageField;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests remove language field serialization.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemoveLanguageField
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExcludeContentField
 */
class RemoveLanguageFieldTest extends KernelTestBase {

  use NodeCreationTrait, ContentTypeCreationTrait;

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
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setup();

    $this->installConfig('node');
    $this->installConfig('field');
    $this->installConfig('filter');

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
  }

  /**
   * Tests the removal of language field.
   *
   * @covers ::excludeContentField
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRemoveLanguageField() {
    $this->createContentType([
      'type' => 'article',
      'name' => 'article',
    ]);

    $node = $this->createNode([
      'type' => 'article',
    ]);

    $remove_id_and_revision_field = new RemoveLanguageField();
    foreach ($node as $field_name => $field) {
      $event = new ExcludeEntityFieldEvent($node, $field_name, $field);
      $remove_id_and_revision_field->excludeContentField($event);

      if ($event->getField()->getFieldDefinition()->getType() == 'language') {
        if ($field_name === $event->getEntity()->getEntityType()->getKey('langcode')) {
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

}
