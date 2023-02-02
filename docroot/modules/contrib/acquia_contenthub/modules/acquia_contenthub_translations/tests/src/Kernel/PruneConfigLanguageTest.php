<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\ContentHubLoggingClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_subscriber\CdfImporter;
use Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker\ContentHubImportQueueWorker;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm;
use Drupal\Tests\acquia_contenthub\Kernel\ImportExportTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentCreatorTrait;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\MetricsUpdateTrait;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * Tests removal of unnecessary incoming configurable language.
 *
 * @group acquia_contenthub_translations
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class PruneConfigLanguageTest extends ImportExportTestBase {

  use CdfDocumentCreatorTrait;
  use MetricsUpdateTrait;

  /**
   * Sample node UUID.
   *
   * @var string
   */
  protected $nodeUuid = '237f9555-9e4f-49ff-ba63-51c25efab080';

  /**
   * Sample language UUID.
   *
   * @var string
   */
  protected $configLangUuid = '8d3300ac-60d5-4f45-b57c-9321ec78adc1';

  /**
   * Sample path alias UUID.
   *
   * @var string
   */
  protected $pathAliasUuid = '2ce2a3a7-761a-4077-83ec-1149c3213235';

  /**
   * Client factory service.
   *
   * @var string
   */
  protected $clientFactoryService = 'acquia_contenthub.client.factory';

  /**
   * Subscriber tracker service.
   *
   * @var string
   */
  protected $subscriberTrackerService = 'acquia_contenthub_subscriber.tracker';

  /**
   * Client instance.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $contentHubClient;

  /**
   * Client settings.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $settings;

  /**
   * Queue worker instance.
   *
   * @var \Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker\ContentHubImportQueueWorker
   */
  protected $contentHubImportQueueWorker;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_translations',
    'acquia_contenthub_subscriber',
    'node',
    'language',
    'user',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['language']);
    $this->installSchema('acquia_contenthub_subscriber', [SubscriberTracker::IMPORT_TRACKING_TABLE]);
    $this->installSchema('acquia_contenthub_translations',
      [EntityTranslations::TABLE, EntityTranslationsTracker::TABLE]
    );
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->config(ContentHubTranslationsSettingsForm::CONFIG)->set('selective_language_import', TRUE)->save();

    $this->contentHubClient = $this->prophesize(ContentHubClient::class);
    $this->settings = $this->prophesize(Settings::class);
    $this->settings->getWebhook('uuid')->willReturn('foo');
    $this->settings->getName()->willReturn('foo');
    $this->settings->getUuid()->willReturn('fefd7eda-4244-4fe4-b9b5-b15b89c61aa8');
    $this->settings->toArray()->willReturn(['name' => 'foo']);

    $client_factory_mock = $this->prophesize(ClientFactory::class);
    $this->mockMetricsCalls($this->contentHubClient);
    $this->contentHubClient->getSettings()->willReturn($this->settings->reveal());

    $cdf_document1 = $this->createCdfDocumentFromFixtureFile('node/node-prune-config-languages.json', 'acquia_contenthub_translations');
    $this->contentHubClient->getEntities([$this->nodeUuid => $this->nodeUuid])->willReturn($cdf_document1);
    $cdf_document2 = $this->createCdfDocumentFromFixtureFile('node/configurable_language-de.json', 'acquia_contenthub_translations');
    $this->contentHubClient->getEntities([$this->configLangUuid => $this->configLangUuid])->willReturn($cdf_document2);
    $cdf_document3 = $this->createCdfDocumentFromFixtureFile('node/path_alias-config-languages.json', 'acquia_contenthub_translations');
    $this->contentHubClient->getEntities([$this->pathAliasUuid => $this->pathAliasUuid])->willReturn($cdf_document3);
    $this->contentHubClient->addEntitiesToInterestList('foo', Argument::type('array'))->willReturn(new Response());

    $client_factory_mock->getClient()->willReturn($this->contentHubClient);
    $client_factory_mock->getSettings()->willReturn($this->settings->reveal());

    $ch_logging_client = $this->prophesize(ContentHubLoggingClient::class);
    $client_factory_mock->getLoggingClient()->willReturn($ch_logging_client->reveal());

    $this->container->set($this->clientFactoryService, $client_factory_mock->reveal());
    $subscriber_tracker_mock = $this->prophesize(SubscriberTracker::class);
    $this->container->set($this->subscriberTrackerService, $subscriber_tracker_mock->reveal());

    $common = $this->getMockBuilder(CdfImporter::class)
      ->setConstructorArgs([
        $this->container->get('event_dispatcher'),
        $this->container->get('entity.cdf.serializer'),
        $this->container->get($this->clientFactoryService),
        $this->container->get('acquia_contenthub_subscriber.logger_channel'),
        $this->container->get($this->subscriberTrackerService),
      ])
      ->onlyMethods(['getUpdateDbStatus'])
      ->getMock();
    $this->container->set('acquia_contenthub_common_actions', $common);

    $this->contentHubImportQueueWorker = $this->getMockBuilder(ContentHubImportQueueWorker::class)
      ->setConstructorArgs([
        $this->container->get('event_dispatcher'),
        $this->container->get('acquia_contenthub_subscriber.cdf_importer'),
        $this->container->get($this->clientFactoryService),
        $this->container->get($this->subscriberTrackerService),
        $this->container->get('acquia_contenthub.config'),
        $this->container->get('acquia_contenthub_subscriber.ch_logger'),
        $this->container->get('acquia_contenthub.cdf_metrics_manager'),
        [],
        NULL,
        NULL,
      ])
      ->addMethods([])
      ->getMock();
  }

  /**
   * Tests pruning of unnecessary incoming configurable language.
   *
   * @throws \Exception
   */
  public function testPruneConfigLanguage(): void {
    $this->contentHubClient->getInterestsByWebhookAndSiteRole(Argument::type('string'), Argument::type('string'))->willReturn([$this->nodeUuid]);
    $this->runImportQueueWorker([$this->nodeUuid]);

    $this->contentHubClient->getInterestsByWebhookAndSiteRole(Argument::type('string'), Argument::type('string'))->willReturn([$this->configLangUuid]);
    $this->runImportQueueWorker([$this->configLangUuid]);

    $this->contentHubClient->getInterestsByWebhookAndSiteRole(Argument::type('string'), Argument::type('string'))->willReturn([$this->pathAliasUuid]);
    $this->runImportQueueWorker([$this->pathAliasUuid]);

    $languages = \Drupal::languageManager()->getLanguages();
    $this->assertSame(['en'], array_keys($languages));
  }

  /**
   * Run import queue worker processItem method.
   *
   * @param array $uuids
   *   UUIDs which will be passed for the queue worker.
   *
   * @throws \Exception
   */
  protected function runImportQueueWorker(array $uuids) {
    $item = new \stdClass();
    $item->uuids = implode(', ', $uuids);
    $this->contentHubImportQueueWorker->processItem($item);
  }

}
