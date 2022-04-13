<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\CdfMetricsManager;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests CdfMetricsManager.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\Client\CdfMetricsManager
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 *
 * @requires module depcalc
 */
class CdfMetricsManagerTest extends EntityKernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'acquia_contenthub_subscriber',
  ];

  /**
   * Client Origin Uuid.
   *
   * @var string
   */
  private $clientUuid;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * Client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $clientFactory;

  /**
   * CH Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $client;

  /**
   * Ch Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Mock logger.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $loggerMock;

  /**
   * Cdf Metrics manager.
   *
   * @var \Drupal\acquia_contenthub\Client\CdfMetricsManager
   */
  protected $cdfMetricsManager;

  /**
   * Settings object.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $settings;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->clientUuid = '2d5ddb2b-b8dd-42af-be20-35d409eb473f';
    $this->installSchema('acquia_contenthub_subscriber', 'acquia_contenthub_subscriber_import_tracking');
    $this->installSchema('acquia_contenthub_publisher', 'acquia_contenthub_publisher_export_tracking');
    $this->dispatcher = $this->container->get('event_dispatcher');
    $this->settings = new Settings('foo', $this->clientUuid, 'apikey', 'secretkey', 'https://example.com');
    $this->clientFactory = $this->prophesize(ClientFactory::class);
    $this->client = $this->prophesize(ContentHubClient::class);
    $this
      ->client
      ->getRemoteSettings()
      ->shouldBeCalled()
      ->willReturn(new \stdClass());
    $this
      ->clientFactory
      ->getClient()
      ->willReturn($this->client->reveal());
    $this
      ->clientFactory
      ->getSettings()
      ->willReturn($this->settings);
    $this->createAcquiaContentHubAdminSettings();
    $this->loggerMock = new LoggerMock();
    $this->config = $this->config('acquia_contenthub.admin_settings');
  }

  /**
   * Tests there are no updates to Client CDF.
   *
   * @covers ::sendClientCdfUpdates
   */
  public function testUnsuccessfulCdfCreation(): void {
    $this
      ->clientFactory
      ->getSettings()
      ->willReturn(NULL);
    $this
      ->client
      ->getRemoteSettings()
      ->shouldNotBeCalled();
    $this->sendCdfUpdates();
    $error_messages = $this->loggerMock->getErrorMessages();
    $this->assertEquals('Couldn\'t instantiate Content Hub Client or Content Hub settings.', $error_messages[0]);
  }

  /**
   * Tests successful creation of client cdf to CH service.
   *
   * @covers ::sendClientCdfUpdates
   *
   * @throws \Exception
   */
  public function testSuccessfulUpdateWithoutRemoteCdf(): void {
    $this
      ->client
      ->getEntity($this->clientUuid)
      ->shouldBeCalled()
      ->willReturn(NULL);
    $this->mockCdfUpdateResponse();
    $this->sendCdfUpdates();
    $this->assertEmpty($this->loggerMock->getLogMessages(), 'Asserts that client entity was created without any error.');
  }

  /**
   * Tests there are no updates to client cdf due to same hash.
   *
   * @covers ::sendClientCdfUpdates
   *
   * @throws \Exception
   */
  public function testNoUpdateWithSameHash(): void {
    $this->mockRemoteCdf(FALSE);
    $this
      ->client
      ->putEntities(Argument::any())
      ->shouldNotBeCalled();
    $this->sendCdfUpdates();
    $this->assertEmpty($this->loggerMock->getLogMessages());
  }

  /**
   * Tests successful update of Client CDF to CH service.
   *
   * @covers ::sendClientCdfUpdates
   *
   * @throws \Exception
   */
  public function testSuccessfulUpdateWithRemoteCdf(): void {
    $this->mockRemoteCdf();
    $this->mockCdfUpdateResponse();
    $this->sendCdfUpdates();
    $this->assertEmpty($this->loggerMock->getLogMessages(), 'Asserts that client entity was updated without any error.');
  }

  /**
   * Tests unsuccessful update to remote cdf.
   *
   * @covers ::sendClientCdfUpdates
   *
   * @throws \Exception
   */
  public function testUnsuccessfulUpdateWithRemoteCdf(): void {
    $this->mockRemoteCdf();
    $status_code = 401;
    $this->mockCdfUpdateResponse($status_code);
    $this->sendCdfUpdates();
    $debug_messages = $this->loggerMock->getDebugMessages();
    $this->assertEquals("Updating Client CDF failed with http status {$status_code}", $debug_messages[0]);
  }

  /**
   * Mocks remote cdf response.
   *
   * @param bool $change_hash
   *   Whether to change the hash of remote cdf or not.
   *
   * @throws \Exception
   */
  protected function mockRemoteCdf(bool $change_hash = TRUE): void {
    $remote_cdf = ClientCDFObject::create($this->clientUuid, ['settings' => $this->settings->toArray()]);
    $event = new BuildClientCdfEvent($remote_cdf);
    $this->dispatcher->dispatch(AcquiaContentHubEvents::BUILD_CLIENT_CDF, $event);
    $remote_cdf = $event->getCdf();
    if ($change_hash) {
      $remote_cdf->addAttribute('hash', CDFAttribute::TYPE_KEYWORD, 'random-hash');
    }
    $this
      ->client
      ->getEntity($this->clientUuid)
      ->shouldBeCalled()
      ->willReturn($remote_cdf);
  }

  /**
   * Mocks response for Client CDF update.
   *
   * @param int $status_code
   *   Status code for response.
   * @param string $message
   *   Response message.
   */
  protected function mockCdfUpdateResponse(int $status_code = 202, string $message = ''): void {
    $this
      ->client
      ->putEntities(Argument::any())
      ->shouldBeCalled()
      ->willReturn(new Response($message, $status_code));
  }

  /**
   * Instantiates the CdfMetricsManager object.
   *
   * And calls the sendClientCdfUpdates method.
   *
   * Assert initial log messages.
   *
   * @throws \Exception
   */
  protected function sendCdfUpdates(): void {
    $this->cdfMetricsManager = new CdfMetricsManager($this->clientFactory->reveal(), $this->config, $this->loggerMock, $this->dispatcher);
    $this->assertEmpty($this->loggerMock->getLogMessages());
    $this->cdfMetricsManager->sendClientCdfUpdates();
  }

}
