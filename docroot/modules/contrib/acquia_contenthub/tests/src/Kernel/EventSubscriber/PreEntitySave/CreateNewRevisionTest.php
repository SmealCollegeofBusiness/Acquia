<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\PreEntitySave;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\acquia_contenthub\EventSubscriber\PreEntitySave\CreateNewRevision;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\depcalc\DependencyStack;
use Drupal\Tests\acquia_contenthub\Kernel\AcquiaContentHubSerializerTestBase;

/**
 * Test that new revisions are handled correctly in PreEntitySave event.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\PreEntitySave\CreateNewRevision
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\PreEntitySave
 */
class CreateNewRevisionTest extends AcquiaContentHubSerializerTestBase {

  /**
   * The CreateNewRevision class instance.
   *
   * @var \Drupal\acquia_contenthub\EventSubscriber\PreEntitySave\CreateNewRevision
   */
  protected $createNewRevision;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $stub_tracker = $this->container->get('acquia_contenthub.stub.tracker');
    $this->createNewRevision = new CreateNewRevision($stub_tracker);
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

    $settings = $this->clientFactory->getClient()->getSettings();
    $cdf = new CDFObject('drupal8_content_entity', $node->uuid(), date('c'), date('c'), $settings->getUuid());
    $event = new PreEntitySaveEvent($node, new DependencyStack(), $cdf);
    $this->createNewRevision->onPreEntitySave($event);

    $entity = $event->getEntity();
    $this->assertTrue($entity->isNewRevision());
  }

  /**
   * Tests getEntityTypeManager method.
   *
   * @covers ::getEntityTypeManager
   */
  public function testGetEntityTypeManager() {
    $entity_type_manager = $this->createNewRevision->getEntityTypeManager();
    $this->assertInstanceOf(EntityTypeManagerInterface::class, $entity_type_manager);
  }

}
