<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\EventSubscriber\HandleWebhook;

use Acquia\ContentHubClient\CDFDocument;
use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\acquia_contenthub_translations\EventSubscriber\HandleWebhook\ImportUpdateTranslatableAssets;
use Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentCreatorTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests translatable entities for import in webhook landing.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\EventSubscriber\HandleWebhook\ImportUpdateTranslatableAssets
 *
 * @group acquia_contenthub_translations
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class ImportUpdateTranslatableAssetsTest extends KernelTestBase {

  use CdfDocumentCreatorTrait;

  /**
   * Fixtures to import.
   *
   * @var array
   */
  protected $fixtures = [
    0 => 'node/node-no-common-languages.json',
  ];

  /**
   * Node uuid.
   */
  public const NODE_UUID = '89ea74ad-930a-46a3-9c4d-402d381e45d3';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_translations',
    'acquia_contenthub_subscriber',
    'depcalc',
    'system',
    'user',
  ];

  /**
   * Content Hub translation config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $translationConfig;

  /**
   * Subscriber tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * Content hub client mock.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $client;

  /**
   * Translation channel logger mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $logger;

  /**
   * Susbcriber channel logger mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $subscriberLogger;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installSchema('acquia_contenthub_subscriber', SubscriberTracker::IMPORT_TRACKING_TABLE);
    $this->translationConfig = $this->config(ContentHubTranslationsSettingsForm::CONFIG);
    $this->translationConfig->set('selective_language_import', TRUE)->save();
    $this->tracker = $this->container->get('acquia_contenthub_subscriber.tracker');
    $this->client = $this->prophesize(ContentHubClient::class);
    $settings = new Settings('foo', '89ea74ad-930a-46a3-9c4d-402d381e45d4', 'apikey', 'secretkey', 'https://example.com');
    $this->client
      ->getSettings()
      ->willReturn($settings);
    $this->logger = new LoggerMock();
    $this->subscriberLogger = new LoggerMock();
  }

  /**
   * Test node asset import with no common-languages.
   *
   * @covers ::onHandleWebhook
   *
   * @throws \Exception
   */
  public function testNodeAssetImportWithNoCommonLanguage(): void {
    $cdf_document = $this->createCdfDocumentFromFixtureFile($this->fixtures[0], 'acquia_contenthub_translations');
    $node_cdf_object = $cdf_document->getCdfEntity(self::NODE_UUID);
    $this->client
      ->getEntities([self::NODE_UUID])
      ->willReturn(new CDFDocument($node_cdf_object));
    $assetImporter = $this->getAssertImporter();
    $event = $this->getEvent([self::NODE_UUID]);
    $assetImporter->onHandleWebhook($event);
    $info_messages = $this->logger->getInfoMessages();
    $logs = ['node : ' . self::NODE_UUID];
    $message = sprintf('These entities (%s) were not added to import queue as these are in foreign languages.',
      implode(', ', $logs)
    );
    $this->assertContains($message, $info_messages);
    $import_queue = \Drupal::queue('acquia_contenthub_subscriber_import');
    $this->assertEquals(0, $import_queue->numberOfItems());
    $this->assertFalse($this->tracker->isTracked(self::NODE_UUID));
  }

  /**
   * Tests asset import for already tracked node.
   *
   * @covers ::onHandleWebhook
   *
   * @throws \Exception
   */
  public function testTrackedNodeAssetImportWithNoCommonLanguage(): void {
    $entity = $this->prophesize(EntityInterface::class);
    $entity
      ->uuid()
      ->willReturn(self::NODE_UUID);
    $entity
      ->getEntityTypeId()
      ->willReturn('node');
    $entity
      ->id()
      ->willReturn(1);
    $this->tracker->track($entity->reveal(), 'random-hash');
    $this
      ->client
      ->addEntitiesToInterestList(Argument::any(), Argument::any())
      ->willReturn($this->prophesize(ResponseInterface::class)->reveal());
    $assetImporter = $this->getAssertImporter();
    $event = $this->getEvent([self::NODE_UUID]);
    $assetImporter->onHandleWebhook($event);
    $message = sprintf('Attempting to add entity with UUID %s to the import queue.', self::NODE_UUID);
    $this->assertContains($message, $this->logger->getInfoMessages());
    $import_queue = \Drupal::queue('acquia_contenthub_subscriber_import');
    $item = $import_queue->claimItem();
    $this->assertEquals(implode(', ', [self::NODE_UUID]), $item->data->uuids);
    $this->assertEquals(SubscriberTracker::QUEUED, $this->tracker->getStatusByUuid(self::NODE_UUID));
  }

  /**
   * Instantiates asset importer.
   *
   * @return \Drupal\acquia_contenthub_translations\EventSubscriber\HandleWebhook\ImportUpdateTranslatableAssets
   *   Assert importer object.
   *
   * @throws \Exception
   */
  protected function getAssertImporter(): ImportUpdateTranslatableAssets {
    return new ImportUpdateTranslatableAssets(
      $this->container->get('queue'),
      $this->tracker,
      $this->subscriberLogger,
      $this->container->get('config.factory'),
      $this->container->get('acquia_contenthub.cdf_metrics_manager'),
      $this->logger,
      $this->container->get('language_manager'),
      $this->container->get('acquia_contenthub_translations.undesired_language_registrar')
    );
  }

  /**
   * Creates new event from payload.
   *
   * @param array $uuids
   *   Uuids to importing payload.
   *
   * @return \Drupal\acquia_contenthub\Event\HandleWebhookEvent
   *   Event object.
   *
   * @throws \Exception
   */
  protected function getEvent(array $uuids): HandleWebhookEvent {
    $request = Request::createFromGlobals();
    $key = new Key('id', 'secret');

    $payload = [
      'status' => 'successful',
      'crud' => 'update',
      'initiator' => current($uuids),
    ];
    foreach ($uuids as $uuid) {
      $payload['assets'][] = [
        'uuid' => $uuid,
        'type' => 'drupal8_content_entity',
      ];
    }

    return new HandleWebhookEvent($request, $payload, $key, $this->client->reveal());
  }

}
