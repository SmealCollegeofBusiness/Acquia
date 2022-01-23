<?php

namespace Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\EnqueueEligibility;

use Drupal\acquia_contenthub\StubTracker;
use Drupal\acquia_contenthub_publisher\Event\ContentHubEntityEligibilityEvent;
use Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\ImportedEntity;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests ImportedEntity event subscriber.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\EnqueueEligibility
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\ImportedEntity
 */
class ImportedEntityTest extends UnitTestCase {

  public const UUID = 'de9606dc-56fa-4b09-bcb1-988533edc814';

  /**
   * Module Handler mock.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
  }

  /**
   * Tests entities which are already tracked for import.
   *
   * @covers ::onEnqueueCandidateEntity
   *
   * @dataProvider dataProvider()
   */
  public function testTrackedEntities(bool $is_subscriber_enabled, bool $is_subscriber_tracked, array $imported_entities, bool $is_eligible) {
    $this
      ->moduleHandler
      ->moduleExists('acquia_contenthub_subscriber')
      ->shouldBeCalled()
      ->willReturn($is_subscriber_enabled);
    $entity = $this->prophesize(EntityInterface::class);
    $entity
      ->uuid()
      ->willReturn(self::UUID);
    $stub_tracker = $this->prophesize(StubTracker::class);
    if ($is_subscriber_enabled) {
      $subscriber_tracker = $this->prophesize(SubscriberTracker::class);
      $subscriber_tracker
        ->isTracked(self::UUID)
        ->willReturn($is_subscriber_tracked);
      $stub_tracker
        ->getImportedEntities()
        ->willReturn($imported_entities);
      $container = $this->prophesize(ContainerInterface::class);
      $container
        ->get('acquia_contenthub_subscriber.tracker')
        ->willReturn($subscriber_tracker->reveal());
      \Drupal::setContainer($container->reveal());
    }

    $imported_entity_checker = new ImportedEntity($this->moduleHandler->reveal(), $stub_tracker->reveal());
    $event = new ContentHubEntityEligibilityEvent($entity->reveal(), 'any operation');
    $imported_entity_checker->onEnqueueCandidateEntity($event);
    $this->assertEquals($is_eligible, $event->getEligibility());
    $this->assertEquals(!$is_eligible, $event->isPropagationStopped());
  }

  /**
   * Data provider.
   *
   * @return array
   *   Data provider array.
   */
  public function dataProvider(): array {
    return [
      [
        FALSE,
        FALSE,
        [],
        TRUE,
      ],
      [
        TRUE,
        TRUE,
        [],
        FALSE,
      ],
      [
        TRUE,
        FALSE,
        [self::UUID],
        FALSE,
      ],
      [
        TRUE,
        TRUE,
        [self::UUID],
        FALSE,
      ],
    ];
  }

}
