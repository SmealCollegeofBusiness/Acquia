<?php

namespace Drupal\Tests\acquia_contenthub_dashboard\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_dashboard\Controller\ContentHubDashboardController;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\State;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests Acquia ContentHub Dashboard Controller and drupalSettings.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub_dashboard\Kernel
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_dashboard\Controller\ContentHubDashboardController
 */
class ContentHubDashboardTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'acquia_contenthub_dashboard',
  ];

  /**
   * The Acquia ContentHub Dashboard Controller.
   *
   * @var \Drupal\acquia_contenthub_dashboard\Controller\ContentHubDashboardController
   */
  protected $contentHubDashboardController;

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->setUpClientFactory();
    $this->setUpCsrfToken();
    $this->setUpState();
    $this->setUpModuleHandler();
    $this->setUpQueue();

    $this->contentHubDashboardController = new ContentHubDashboardController(
      $this->container->get('acquia_contenthub.client.factory'),
      $this->container->get('csrf_token'),
      $this->container->get('state'),
      $this->container->get('module_handler'),
      $this->container->get('queue'),
      $this->setUpConfigUrl()->reveal()
    );
  }

  /**
   * Setups API settings for this test.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The test settings.
   */
  protected function setUpApiSettings(): ObjectProphecy {
    $settings = $this->prophesize(Settings::class);
    $settings->getUuid()->willReturn('3a89ff1b-8869-419d-b931-f2282aca3e88');
    $settings->getName()->willReturn('foo');
    $settings->getUrl()->willReturn('http://www.example.com');
    $settings->getApiKey()->willReturn('apikey');
    $settings->getSecretKey()->willReturn('apisecret');

    return $settings;
  }

  /**
   * Setups CH client factory for this test.
   */
  protected function setUpClientFactory() {
    $settings = $this->setUpApiSettings()->reveal();
    $client = $this->prophesize(ContentHubClient::class);
    $clientFactory = $this->prophesize(ClientFactory::class);
    $clientFactory->getSettings()->willReturn($settings);
    $client->getSettings()->willReturn($settings);
    $clientFactory
      ->getClient()
      ->willReturn($client->reveal());
    $this->container->set('acquia_contenthub.client.factory',
      $clientFactory->reveal());
  }

  /**
   * Setups CSRF Token service for this test.
   */
  protected function setUpCsrfToken() {
    $csrf_token = $this->prophesize(CsrfTokenGenerator::class);
    $csrf_token->get('rest')->willReturn('SESSION_TOKEN');
    $this->container->set('csrf_token', $csrf_token->reveal());
  }

  /**
   * Setups State service for this test.
   */
  protected function setUpState() {
    $state = $this->prophesize(State::class);
    $state->get('system.css_js_query_string')->willReturn('xyzwabc');
    $this->container->set('state', $state->reveal());
  }

  /**
   * Setups Module handler service for this test.
   */
  protected function setUpModuleHandler() {
    $mod_handler = $this->prophesize(ModuleHandlerInterface::class);
    $extension = $this->prophesize(Extension::class);
    $extension->getPath()->willReturn('admin/acquia-contenthub/contenthub-dashboard');
    $mod_handler->getModule('acquia_contenthub_dashboard')->willReturn($extension->reveal());
    $mod_handler->moduleExists('acquia_contenthub_publisher')->willReturn(TRUE);
    $mod_handler->moduleExists('acquia_contenthub_subscriber')->willReturn(FALSE);
    $this->container->set('module_handler', $mod_handler->reveal());
  }

  /**
   * Setups Queue service for this test.
   */
  protected function setUpQueue() {
    $queue_service = $this->prophesize(QueueFactory::class);

    $export_queue = $this->prophesize(QueueInterface::class);
    $export_queue->numberOfItems()->willReturn(42);

    $import_queue = $this->prophesize(QueueInterface::class);
    $import_queue->numberOfItems()->willReturn(0);

    $queue_service->get('acquia_contenthub_publish_export')->willReturn($export_queue->reveal());
    $queue_service->get('acquia_contenthub_subscriber_import')->willReturn($import_queue->reveal());

    $this->container->set('queue', $queue_service->reveal());
  }

  /**
   * Returns the test config URL object.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The test config URL object.
   */
  protected function setUpConfigUrl(): ObjectProphecy {
    $config_url = $this->prophesize(Url::class);
    $config_url->toString()->willReturn('/admin/config/services/acquia-contenthub');
    return $config_url;
  }

  /**
   * Tests Acquia ContentHub Dashboard controller.
   *
   * @covers ::loadContentHubDashboard
   * @covers ::getDrupalSettings
   * @covers ::getApiSettings
   * @covers ::getAcquiaContentHubModuleData
   */
  public function testContentHubDashboard() {
    $request = Request::createFromGlobals();
    $build = $this->contentHubDashboardController->loadContentHubDashboard($request);
    $drupal_settings_array = $build['#attached']['drupalSettings']['acquia_contenthub_dashboard'];
    $this->assertEquals('SESSION_TOKEN', $drupal_settings_array['token']);
    $this->assertApiSettings($drupal_settings_array);
    $this->assertModuleData($drupal_settings_array);
  }

  /**
   * Tests Acquia ContentHub Dashboard Index Page for the iFrame.
   *
   * @covers ::indexPage
   */
  public function testContentHubDashboardIndexPage() {
    $request = Request::createFromGlobals();
    $build = $this->contentHubDashboardController->indexPage($request);
    $content = $build->getContent();
    $this->assertNotEmpty($content);
  }

  /**
   * Assertions for API settings.
   *
   * @param array $drupal_settings_array
   *   Array with test drupalSettings.
   */
  protected function assertApiSettings(array $drupal_settings_array) {
    $this->assertEquals('http://www.example.com', $drupal_settings_array['host']);
    $this->assertEquals('apikey', $drupal_settings_array['public_key']);
    $this->assertEquals('apisecret', $drupal_settings_array['secret_key']);
    $this->assertEquals('3a89ff1b-8869-419d-b931-f2282aca3e88', $drupal_settings_array['client']);
    $this->assertEquals('2', $drupal_settings_array['ch_version']);
  }

  /**
   * Assertions for module data.
   *
   * @param array $drupal_settings_array
   *   Array with test drupalSettings.
   */
  protected function assertModuleData(array $drupal_settings_array) {
    $this->assertEquals(TRUE, $drupal_settings_array['publisher']);
    $this->assertEquals(FALSE, $drupal_settings_array['subscriber']);
    $this->assertEquals(42, $drupal_settings_array['export_queue_count']);
    $this->assertEquals(0, $drupal_settings_array['import_queue_count']);
  }

}
