<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\PartialFetchOnImport;

use Acquia\ContentHubClient\CDFDocument;
use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_subscriber\CdfImporter;
use Drupal\depcalc\DependencyStack;
use Drupal\Tests\acquia_contenthub\Kernel\ImportExportTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentCreatorTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * CDF importer tests.
 *
 * @group acquia_contenthub_subscriber
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_subscriber\CdfImporter
 */
class CdfImporterTest extends ImportExportTestBase {

  use CdfDocumentCreatorTrait;

  /**
   * Node uuid.
   */
  protected const NODE_UUID = 'b729de7e-913d-4dc1-aacf-c40cd5fef034';

  /**
   * Fixtures for the test.
   *
   * @var array
   */
  protected $fixtures = [
    0 => [
      'cdf' => 'node/node-partial-fetch.json',
      'expectations' => 'expectations/node/node_partial_fetch.php',
    ],
  ];

  /**
   * Array of uuids that will be fetched in subsequent fetch.
   *
   * @var string[]
   */
  protected $partialFetchedUuids = [
    'b9d480a5-adac-48bc-9cc9-8f72fa5f3d81',
    '8758b479-5f42-4610-9f5c-0276164f0155',
    'b0264339-ceba-4500-b71b-582a89d081ee',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'taxonomy',
    'user',
    'node',
    'file',
    'field',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_test',
  ];

  /**
   * Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Subscriber tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * Logger Mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $loggerMock;

  /**
   * {@inheritDoc}
   */
  public function setup(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('acquia_contenthub_subscriber', 'acquia_contenthub_subscriber_import_tracking');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->tracker = $this->container->get('acquia_contenthub_subscriber.tracker');
    $this->loggerMock = new LoggerMock();
  }

  /**
   * Tests entities are only partially fetched on subsequent imports.
   *
   * @covers ::getCdfDocument
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testPartialCdfFetch(): void {
    // First import.
    $imported_entities = $this->tracker->listTrackedEntities($this->tracker::IMPORTED);
    $this->assertEmpty($imported_entities, 'Nothing imported yet');
    $this->importFixture(0);
    $imported_entities = $this->tracker->listTrackedEntities($this->tracker::IMPORTED);
    $this->assertNotEmpty($imported_entities, 'Entities got imported and tracked in the tracker table');

    // Second import.
    // Delete entities from subscriber tracker for partial fetch.
    array_walk($this->partialFetchedUuids, function ($uuid) {
      $this->tracker->delete('entity_uuid', $uuid);
    });
    $client = $this->prophesize(ContentHubClient::class);
    $node_document = $this->createCdfDocumentFromFixturePath($this->fixtures[0]['cdf'], [self::NODE_UUID]);
    // First call to service for fetching the node cdf.
    $client
      ->getEntities(array_combine([self::NODE_UUID], [self::NODE_UUID]))
      ->shouldBeCalled()
      ->willReturn($node_document);
    $document = $this->createCdfDocumentFromFixturePath($this->fixtures[0]['cdf'], $this->partialFetchedUuids);
    // 2nd call to service to fetch the list of entities not present in tracker.
    $client
      ->getEntities(array_combine($this->partialFetchedUuids, $this->partialFetchedUuids))
      ->shouldBeCalled()
      ->willReturn($document);

    $cdf_importer = $this->initializeCdfImporter($client);
    $node_cdf = current($node_document->getEntities());
    $node_dependencies = array_keys($node_cdf->getDependencies());
    $stack = new DependencyStack();
    $fetched_document = $cdf_importer->getCdfDocument($stack, self::NODE_UUID);
    // Assert only changed entities were fetched.
    $fetched_entities = array_keys($fetched_document->getEntities());
    $this->assertContains(self::NODE_UUID, $fetched_entities, 'Asserts that node entity was fetched from service.');
    foreach ($node_dependencies as $uuid) {
      if (in_array($uuid, $this->partialFetchedUuids, TRUE)) {
        $this->assertFalse($stack->hasDependency($uuid), 'Asserts that changed entities are not present in stack.');
        $this->assertContains($uuid, $fetched_entities, 'Asserts that changed entities are fetched from Service.');
        continue;
      }
      $this->assertTrue($stack->hasDependency($uuid), 'Asserts that unchanged entities are present in stack.');
    }
  }

  /**
   * Initializes CDF importer with mocks.
   *
   * @param \Prophecy\Prophecy\ObjectProphecy $client
   *   Mocked client.
   *
   * @return \Drupal\acquia_contenthub_subscriber\CdfImporter
   *   The CDF importer object.
   *
   * @throws \Exception
   */
  protected function initializeCdfImporter(ObjectProphecy $client): CdfImporter {
    $client_factory = $this->prophesize(ClientFactory::class);
    $client_factory
      ->getClient()
      ->willReturn($client);
    return new CdfImporter(
      $this->container->get('event_dispatcher'),
      $this->container->get('entity.cdf.serializer'),
      $client_factory->reveal(),
      $this->loggerMock,
      $this->tracker
    );
  }

  /**
   * Creates CDF document from fixture file with given uuids.
   *
   * @param string $fixture_path
   *   Fixture path.
   * @param array $uuids
   *   Array of uuids to include in the cdf document.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   CDF document.
   *
   * @throws \Exception
   */
  protected function createCdfDocumentFromFixturePath(string $fixture_path, array $uuids): CDFDocument {
    $data = $this->getCdfData($fixture_path);
    $document_parts = [];
    foreach ($data['entities'] as $entity) {
      if (in_array($entity['uuid'], $uuids, TRUE)) {
        $document_parts[] = $this->populateCdfObject($entity);
      }
    }

    return new CDFDocument(...$document_parts);
  }

}
