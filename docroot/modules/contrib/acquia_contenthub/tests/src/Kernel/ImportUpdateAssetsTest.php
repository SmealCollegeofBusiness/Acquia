<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook\ImportUpdateAssets;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook\ImportUpdateAssets
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ImportUpdateAssetsTest extends KernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;

  /**
   * ImportUpdateAssets instance.
   *
   * @var \Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook\ImportUpdateAssets
   */
  protected $importUpdateAssets;

  /**
   * Subscriber tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $subTracker;

  /**
   * The logger channel mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $loggerChannel;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'field',
    'filter',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_server_test',
    'acquia_contenthub_test',
    'system',
    'user',
    'node',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setup();

    $this->installSchema('acquia_contenthub_subscriber', 'acquia_contenthub_subscriber_import_tracking');

    $this->createAcquiaContentHubAdminSettings();

    $dispatcher = $this->prophesize(EventDispatcher::class);
    $this->loggerChannel = new LoggerMock();
    $this->subTracker = $this->container->get('acquia_contenthub_subscriber.tracker');
    $config_factory = $this->container->get('config.factory');
    $queue_factory = $this->container->get('queue');
    $cdf_metrics_manager = $this->container->get('acquia_contenthub.cdf_metrics_manager');

    $this->importUpdateAssets = new ImportUpdateAssets(
      $queue_factory,
      $dispatcher->reveal(),
      $this->subTracker,
      $this->loggerChannel,
      $config_factory,
      $cdf_metrics_manager
    );
  }

  /**
   * @covers ::onHandleWebhook
   *
   * @throws \Exception
   */
  public function testOnHandleWebhookWithAutoUpdateDisabledEntityStatus() {
    $uuid = '00000000-0003-460b-ac74-b6bed08b4441';
    $this->subTracker->queue($uuid);
    $this->subTracker->setStatusByUuid($uuid, $this->subTracker::AUTO_UPDATE_DISABLED);

    $request = Request::createFromGlobals();
    $key = new Key('id', 'secret');

    $payload = [
      'status' => 'successful',
      'crud' => 'update',
      'assets' => [
        [
          'uuid' => $uuid,
          'type' => 'drupal8_content_entity',
        ],
      ],
      'initiator' => $uuid,
    ];

    $client_factory = $this->container->get('acquia_contenthub.client.factory');

    $event = new HandleWebhookEvent($request, $payload, $key, $client_factory->getClient());

    $this->assertEmpty($this->loggerChannel->getLogMessages());

    $this->importUpdateAssets->onHandleWebhook($event);
    $log_messages = $this->loggerChannel->getLogMessages();
    $this->assertNotEmpty($log_messages);

    // Assert there are info in log messages.
    $this->assertNotEmpty($log_messages[RfcLogLevel::INFO]);

    // Assert first message in info.
    $this->assertEquals(
      'Entity with UUID 00000000-0003-460b-ac74-b6bed08b4441 was not added to the import queue because it has auto update disabled.',
      $log_messages[RfcLogLevel::INFO][0]
    );
  }

}
