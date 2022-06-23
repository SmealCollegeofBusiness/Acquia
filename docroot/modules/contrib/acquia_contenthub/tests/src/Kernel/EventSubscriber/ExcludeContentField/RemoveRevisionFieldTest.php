<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemoveRevisionField;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests remove revision field serialization.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemoveRevisionField
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExcludeContentField
 */
class RemoveRevisionFieldTest extends KernelTestBase {

  use NodeCreationTrait, ContentTypeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
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
   * Tests the removal of ID and Revision field.
   *
   * @covers ::excludeContentField
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRemoveRevisionField() {
    $this->createContentType([
      'type' => 'article',
      'name' => 'article',
    ]);

    $node = $this->createNode([
      'type' => 'article',
    ]);

    $remove_id_and_revision_field = new RemoveRevisionField();
    foreach ($node as $field_name => $field) {
      $event = new ExcludeEntityFieldEvent($node, $field_name, $field);
      $remove_id_and_revision_field->excludeContentField($event);

      if ($field_name === $event->getEntity()->getEntityType()->getKey('revision')) {
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
