<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\SerializeContentField;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\SerializeContentField\RemoveEntitySubqueueRevisionFieldSerialization;
use Drupal\entityqueue\Entity\EntityQueue;
use Drupal\entityqueue\Entity\EntitySubqueue;
use Drupal\Tests\acquia_contenthub\Kernel\AcquiaContentHubSerializerTestBase;

/**
 * Tests Remove ID and Revision Field Serialization.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\SerializeContentField\RemoveEntitySubqueueRevisionFieldSerialization
 *
 * @requires module depcalc
 * @requires module entityqueue
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\SerializeContentField
 */
class RemoveEntitySubqueueRevisionFieldSerializerTest extends AcquiaContentHubSerializerTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'entityqueue',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_subqueue');
  }

  /**
   * Tests the serialization of removal of ID and Revision field.
   *
   * @covers ::onSerializeContentField
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRemoveEntitySubqueueRevisionField() {
    $entity = $this->createNode();

    // Add a entity queue with minimum data only.
    $entity_queue = EntityQueue::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'handler' => 'multiple',
      'entity_settings' => [
        'target_type' => 'node',
      ],
    ]);
    $entity_queue->save();

    $subqueue = EntitySubqueue::create([
      'queue' => $entity_queue->id(),
      'name' => $this->randomString(),
      'title' => $this->randomString(),
      'langcode' => $entity_queue->language()->getId(),
      'attached_entity' => $entity,
    ]);
    $subqueue->save();

    $settings = $this->clientFactory->getClient()->getSettings();
    $cdf = new CDFObject('drupal8_content_entity', $subqueue->uuid(), date('c'), date('c'), $settings->getUuid());
    $remove_revision_field = new RemoveEntitySubqueueRevisionFieldSerialization();

    foreach ($subqueue as $field_name => $field) {
      $event = new SerializeCdfEntityFieldEvent($subqueue, $field_name, $field, $cdf);
      $remove_revision_field->onSerializeContentField($event);

      if ($field_name === 'revision_id') {
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
