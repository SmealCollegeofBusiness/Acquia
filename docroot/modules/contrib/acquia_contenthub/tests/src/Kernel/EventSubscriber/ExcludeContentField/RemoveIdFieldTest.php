<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemoveIdField;
use Drupal\entityqueue\Entity\EntityQueue;
use Drupal\entityqueue\Entity\EntitySubqueue;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests remove id field serialization.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemoveIdField
 *
 * @requires module depcalc
 * @requires module entityqueue
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExcludeContentField
 */
class RemoveIdFieldTest extends KernelTestBase {

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
    'entityqueue',
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
    $this->installEntitySchema('entity_subqueue');
  }

  /**
   * Tests the removal of id field.
   *
   * @covers ::excludeContentField
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRemoveIdField() {
    $this->createContentType([
      'type' => 'article',
      'name' => 'article',
    ]);

    $node = $this->createNode([
      'type' => 'article',
    ]);

    $remove_id_and_revision_field = new RemoveIdField();
    foreach ($node as $field_name => $field) {
      $event = new ExcludeEntityFieldEvent($node, $field_name, $field);
      $remove_id_and_revision_field->excludeContentField($event);

      if ($field_name === $event->getEntity()->getEntityType()->getKey('id')) {
        $this->assertTrue($event->isExcluded());
        $this->assertTrue($event->isPropagationStopped());
      }
      else {
        $this->assertFalse($event->isExcluded());
        $this->assertFalse($event->isPropagationStopped());
      }
    }
  }

  /**
   * Tests entitysubqueue not remove id field.
   *
   * @covers ::shouldExclude
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEntitySubqueueNotRemoveIdField() {
    $entity_queue = EntityQueue::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'handler' => 'simple',
      'entity_settings' => [
        'target_type' => 'node',
      ],
    ]);
    $entity_queue->save();
    $entity_subqueue = EntitySubqueue::load($entity_queue->id());

    $remove_id_and_revision_field = new RemoveIdField();
    foreach ($entity_subqueue as $field_name => $field) {
      $event = new ExcludeEntityFieldEvent($entity_subqueue, $field_name, $field);
      $this->assertFalse($remove_id_and_revision_field->shouldExclude($event));
    }
  }

}
