<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExportUnownedContentOnDualConfigSites;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Acquia\ContentHubClient\CDFDocument;
use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\Event\PrunePublishCdfEntitiesEvent;
use Drupal\acquia_contenthub\PubSubModuleStatusChecker;
use Drupal\acquia_contenthub_subscriber\EventSubscriber\PrunePublishEntities\PruneImportedEntitiesFromExport;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests that imported entities are properly pruned from export.
 *
 * @group acquia_contenthub_subscriber
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 *
 * @covers \Drupal\acquia_contenthub_subscriber\EventSubscriber\PrunePublishEntities\PruneImportedEntitiesFromExport
 */
class PruneImportedEntitiesFromExportTest extends EntityKernelTestBase {

  /**
   * The Status Checker.
   *
   * @var \Drupal\acquia_contenthub\PubSubModuleStatusChecker
   */
  private $checker;

  /**
   * The mocked Event object.
   *
   * @var \Drupal\acquia_contenthub\Event\PrunePublishCdfEntitiesEvent|\PHPUnit\Framework\MockObject\MockObject
   */
  private $event;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'user',
    'field',
    'depcalc',
    'acquia_contenthub',
    PubSubModuleStatusChecker::ACQUIA_CONTENTHUB_SUBSCRIBER_MODULE_ID,
  ];

  /**
   * Imported entity tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker|\Prophecy\Prophecy\ObjectProphecy
   */
  private $tracker;

  /**
   * Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient|\Prophecy\Prophecy\ObjectProphecy
   */
  private $client;

  /**
   * CDF Document.
   *
   * @var \Acquia\ContentHubClient\CDFDocument|\Prophecy\Prophecy\ObjectProphecy
   */
  private $document;

  /**
   * This site's origin.
   *
   * @var string
   */
  private $thisSiteOrigin;

  /**
   * The untracked unowned uuid.
   *
   * @var string
   */
  private $untrackedUnownedUuid;

  /**
   * The untracked owned uuid.
   *
   * @var string
   */
  private $untrackedOwnedUuid;

  /**
   * The tracked unowned uuid.
   *
   * @var string
   */
  private $trackedUnownedUuid;

  /**
   * The other site's origin.
   *
   * @var string
   */
  private $otherSiteOrigin;

  /**
   * The Drupal database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $time = time();
    $entity_type = 'user';
    $this->database = \Drupal::database();
    $this->thisSiteOrigin = 'this_site_uuid';
    $this->otherSiteOrigin = 'some-other-origin';
    $this->untrackedOwnedUuid = 'some-uuid-1';
    $this->trackedUnownedUuid = 'some-uuid-2';
    $this->untrackedUnownedUuid = 'some-uuid-3';

    $this->installEntitySchema($entity_type);
    $this->installSchema($entity_type, ['users_data']);
    $this->installSchema('acquia_contenthub_subscriber', 'acquia_contenthub_subscriber_import_tracking');
    $this->createUser([
      'uuid' => $this->untrackedUnownedUuid,
      'name' => 'foo',
      'mail' => 'foo@foo.com',
    ]);

    $tracked_objects = [
      $this->trackedUnownedUuid => new CDFObject($entity_type, $this->trackedUnownedUuid, $time, $time, $this->otherSiteOrigin, []),
    ];

    $untracked_objects = [
      $this->untrackedOwnedUuid => new CDFObject($entity_type, $this->untrackedOwnedUuid, $time, $time, $this->thisSiteOrigin, []),
      $this->untrackedUnownedUuid => new CDFObject($entity_type, $this->untrackedUnownedUuid, $time, $time, $this->otherSiteOrigin, []),
    ];
    $untracked_objects[$this->untrackedUnownedUuid]->addAttribute('entity_type', CDFAttribute::TYPE_ARRAY_STRING, $entity_type);
    $untracked_objects[$this->untrackedUnownedUuid]->addAttribute('hash', CDFAttribute::TYPE_ARRAY_STRING, $this->otherSiteOrigin);
    $this->trackImportedCdf(...array_values($tracked_objects));

    $this->tracker = new SubscriberTracker($this->database);

    $this->checker = $this->prophesize(PubSubModuleStatusChecker::class);
    $this->checker->siteHasDualConfiguration()->willReturn(TRUE);

    $this->client = $this->prophesize(ContentHubClient::class);
    $this->client->getEntities()
      ->withArguments([array_keys($untracked_objects)])
      ->willReturn(new CDFDocument(...array_values($untracked_objects)));

    $this->document = new CDFDocument(...array_values(array_merge($tracked_objects, $untracked_objects)));
    $this->event = new PrunePublishCdfEntitiesEvent($this->client->reveal(), $this->document, $this->thisSiteOrigin);
  }

  /**
   * Read the function name for more info.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \ReflectionException
   */
  public function testIfSiteHasDualConfigurationEntitiesOriginatedElsewhereWontBeExported(): void {
    // Expected starting state.
    $this->assertFalse($this->tracker->isTracked($this->untrackedOwnedUuid));
    $this->assertTrue($this->tracker->isTracked($this->trackedUnownedUuid));
    $this->assertFalse($this->tracker->isTracked($this->untrackedUnownedUuid));

    $this->triggerEvent($this->event);

    $document = $this->event->getDocument();
    // Subscriber created, so eligible to publish.
    $this->assertTrue($document->hasEntity($this->untrackedOwnedUuid));
    // Subscriber imported, ineligible to publish.
    $this->assertFalse($document->hasEntity($this->trackedUnownedUuid));
    $this->assertTrue($this->tracker->isTracked($this->trackedUnownedUuid));
    // Other origin owned, ineligible to publish and should now be in tracking.
    $this->assertFalse($document->hasEntity($this->untrackedUnownedUuid));
  }

  /**
   * Triggers onPrunePublishCdfEntitiesIfSiteHasDualConfiguration subscriber.
   *
   * @param \Drupal\acquia_contenthub\Event\PrunePublishCdfEntitiesEvent $event
   *   Handle PruneImportedEntitiesFromExport event.
   *
   * @throws \ReflectionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function triggerEvent(PrunePublishCdfEntitiesEvent $event): void {
    $handler = new PruneImportedEntitiesFromExport($this->tracker, $this->checker->reveal(), \Drupal::service('entity.repository'));

    $handler->onPrunePublishCdfEntitiesIfSiteHasDualConfiguration($event);
  }

  /**
   * Track the imported cdf objects.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject ...$objects  @codingStandardsIgnoreLine
   *   CDF objects for the client.
   *
   * @throws \Exception
   */
  private function trackImportedCdf(CDFObject ...$objects): void {
    $query = $this->database
      ->insert('acquia_contenthub_subscriber_import_tracking')
      ->fields([
        'entity_uuid',
        'entity_type',
        'entity_id',
        'first_imported',
        'last_imported',
        'hash',
        'status',
      ]);

    foreach ($objects as $object) {
      $query->values([
        $object->getUuid(),
        $object->getType(),
        1,
        date('c'),
        date('c'),
        'some-hash-value',
        SubscriberTracker::IMPORTED,
      ]);
    }

    $query->execute();
  }

}
