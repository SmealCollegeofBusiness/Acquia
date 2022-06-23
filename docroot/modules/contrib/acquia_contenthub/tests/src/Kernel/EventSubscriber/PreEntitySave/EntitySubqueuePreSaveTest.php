<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\PreEntitySave;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\acquia_contenthub_subscriber\EventSubscriber\PreEntitySave\EntitySubqueuePreSave;
use Drupal\depcalc\DependencyStack;
use Drupal\entityqueue\Entity\EntityQueue;
use Drupal\entityqueue\Entity\EntitySubqueue;
use Drupal\Tests\acquia_contenthub\Kernel\AcquiaContentHubSerializerTestBase;

/**
 * Test that new revisions are handled correctly in PreEntitySave event.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub_subscriber\EventSubscriber\PreEntitySave\EntitySubqueuePreSave
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\PreEntitySave
 */
class EntitySubqueuePreSaveTest extends AcquiaContentHubSerializerTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
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
   * Tests CreateNewRevision event subscriber.
   *
   * @covers ::onPreEntitySave
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNewRevision() {
    // Create a test content type.
    $this->createContentType();
    // Create a test node.
    $node = $this->createNode();

    // Add a entity queue with minimum data only.
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
    $entity_subqueue_uuid = $entity_subqueue->uuid();

    // Not saving entity subqueue due to integrity constraints.
    $subqueue = EntitySubqueue::create([
      'queue' => $entity_queue->id(),
      'name' => $entity_queue->id(),
      'title' => 'Actual entity subqueue',
      'langcode' => $entity_queue->language()->getId(),
      'attached_entity' => $node,
    ]);

    $settings = $this->clientFactory->getClient()->getSettings();
    $cdf = new CDFObject('drupal8_content_entity', $subqueue->uuid(), date('c'), date('c'), $settings->getUuid());
    $event = new PreEntitySaveEvent($subqueue, new DependencyStack(), $cdf);

    $database = \Drupal::service('database');
    $create_new_revision = new EntitySubqueuePreSave($database);
    $create_new_revision->onPreEntitySave($event);
    $this->assertTrue($event->isPropagationStopped());

    $subqueue_deleted = $this->entityTypeManager
      ->getStorage('entity_subqueue')
      ->loadByProperties([
        'uuid' => $entity_subqueue_uuid,
      ]);
    $this->assertEmpty($subqueue_deleted);
  }

}
