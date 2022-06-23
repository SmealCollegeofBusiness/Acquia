<?php

namespace Drupal\Tests\acquia_contenthub\Unit;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CleanUpStubsEvent;
use Drupal\acquia_contenthub\StubTracker;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\depcalc\DependencyStack;
use Drupal\node\NodeStorageInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests StubTracker class.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Unit
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\StubTracker
 */
class StubTrackerTest extends UnitTestCase {

  /**
   * Array of uuids.
   */
  protected const UUIDS = [
    '3f0b403c-4093-4caa-ba78-37df21125f09',
    '3f0b403c-4093-4caa-ba78-37df21125f10',
  ];

  /**
   * Stub tracker.
   *
   * @var \Drupal\acquia_contenthub\StubTracker
   */
  protected $stubTracker;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    $stack = new DependencyStack();
    $dispatcher = $this->prophesize(EventDispatcherInterface::class);
    $entity1 = $this->prophesize(EntityInterface::class);
    $entity2 = $this->prophesize(EntityInterface::class);
    $entity1
      ->getEntityTypeId()
      ->willReturn('node');
    $entity1
      ->uuid()
      ->willReturn(self::UUIDS[0]);
    $entity1
      ->id()
      ->willReturn(1);
    $entity1
      ->delete()
      ->willReturn();
    $entity2
      ->getEntityTypeId()
      ->willReturn('node');
    $entity2
      ->uuid()
      ->willReturn(self::UUIDS[1]);
    $entity2
      ->id()
      ->willReturn(2);
    $entity2
      ->delete()
      ->willReturn();
    $dispatcher
      ->dispatch(Argument::any(), AcquiaContentHubEvents::CLEANUP_STUBS)
      ->will(
        function ($event_data) {
          $event = $event_data[1];
          if ($event instanceof CleanUpStubsEvent) {
            $event->deleteStub();
          }
        });
    $this->stubTracker = new StubTracker($dispatcher->reveal());
    $this->stubTracker->setStack($stack);
    $this->stubTracker->track($entity1->reveal());
    $this->stubTracker->track($entity2->reveal());
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $node_storage = $this->prophesize(NodeStorageInterface::class);
    $node_storage
      ->load(1)
      ->willReturn($entity1->reveal());
    $node_storage
      ->load(2)
      ->willReturn($entity2->reveal());
    $entity_type_manager
      ->getStorage('node')
      ->willReturn($node_storage->reveal());
    $container = $this->prophesize(ContainerInterface::class);
    $container
      ->get('entity_type.manager')
      ->willReturn($entity_type_manager->reveal());
    \Drupal::setContainer($container->reveal());
  }

  /**
   * Tests getImportedEntities.
   *
   * @covers ::getImportedEntities
   */
  public function testImportedEntities() {
    $imported_entities = $this->stubTracker->getImportedEntities();
    $this->assertEqualsCanonicalizing(self::UUIDS, $imported_entities);
  }

  /**
   * Tests whether stubs are tracked in tracker.
   *
   * @covers ::hasStub
   */
  public function testStubAvailable() {
    // These entities are tracked.
    $this->assertTrue($this->stubTracker->hasStub('node', 1));
    $this->assertTrue($this->stubTracker->hasStub('node', 2));
    // These entities are not tracked.
    $this->assertFalse($this->stubTracker->hasStub('node', 3));
    $this->assertFalse($this->stubTracker->hasStub('node_type', 'article'));
  }

  /**
   * Tests whether tracker has started tracking or not.
   *
   * @covers ::isTracking
   */
  public function testTrackingStarted() {
    $this->assertTrue($this->stubTracker->isTracking());
    $stub_tracker = new StubTracker($this->prophesize(EventDispatcherInterface::class)->reveal());
    $this->assertFalse($stub_tracker->isTracking());
  }

  /**
   * Tests whether entities are being tracked or not.
   *
   * @covers ::track
   */
  public function testTracking() {
    $uuid3 = '3f0b403c-4093-4caa-ba78-37df21125f11';
    $uuid4 = '3f0b403c-4093-4caa-ba78-37df21125f12';
    $entity3 = $this->prophesize(EntityInterface::class);
    $entity4 = $this->prophesize(EntityInterface::class);
    $entity3
      ->getEntityTypeId()
      ->willReturn('node');
    $entity3
      ->uuid()
      ->willReturn($uuid3);
    $entity3
      ->id()
      ->willReturn(3);
    $entity4
      ->getEntityTypeId()
      ->willReturn('node');
    $entity4
      ->uuid()
      ->willReturn($uuid4);
    $entity4
      ->id()
      ->willReturn(4);
    $this->stubTracker->track($entity3->reveal());
    $this->stubTracker->track($entity4->reveal());
    $imported_entities = $this->stubTracker->getImportedEntities();
    $this->assertContains($uuid3, $imported_entities);
    $this->assertContains($uuid4, $imported_entities);
    $this->assertTrue($this->stubTracker->hasStub('node', 3));
    $this->assertTrue($this->stubTracker->hasStub('node', 4));
  }

  /**
   * Tests full cleanup of stubs(In case of exceptions)
   *
   * @covers ::cleanUp
   *   cleanUp(TRUE)
   */
  public function testFullCleanup() {
    $this->stubTracker->cleanUp(TRUE);
    $this->assertStubDeletion();
  }

  /**
   * Tests partial cleanup of stubs(When everything goes well)
   *
   * @covers ::cleanUp
   *   cleanUp()
   */
  public function testPartialCleanup() {
    $this->stubTracker->cleanUp();
    $this->assertStubDeletion();
  }

  /**
   * Asserts stubs have been deleted.
   */
  protected function assertStubDeletion(): void {
    $this->assertEmpty($this->stubTracker->getImportedEntities());
    $this->assertFalse($this->stubTracker->hasStub('node', 1));
    $this->assertFalse($this->stubTracker->hasStub('node', 2));
  }

}
