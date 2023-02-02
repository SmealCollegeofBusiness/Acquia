<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\ContentHubLoggingClient;
use Acquia\ContentHubClient\Settings as AcquiaSettings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Client\ProjectVersionClient;
use Drupal\acquia_contenthub\Event\AcquiaContentHubSettingsEvent;
use Drupal\acquia_contenthub\Exception\EventServiceUnreachableException;
use Drupal\acquia_contenthub\Libs\Common\PlatformCompatibilityChecker;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\WatchdogAssertsTrait;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests the client factory.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\Client\ClientFactory
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ClientFactoryTest extends EntityKernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;
  use WatchdogAssertsTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'field',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_test',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setup(): void {
    parent::setUp();
    $pv_client = $this->prophesize(ProjectVersionClient::class);
    $pv_client
      ->getContentHubReleases()
      ->willReturn(['latest' => '8.x-2.25']);
    $pv_client
      ->getDrupalReleases(Argument::any())
      ->willReturn(['also_available' => '9.2.1', 'latest' => '8.9.16']);
    $this->container->set('acquia_contenthub.project_version_client', $pv_client->reveal());

    $platform_checker = $this->prophesize(PlatformCompatibilityChecker::class);
    $platform_checker->intercept(Argument::type(ContentHubClient::class))->willReturnArgument();
    $this->container->set('acquia_contenthub.platform_checker', $platform_checker->reveal());
  }

  /**
   * Test case when content hub configured via core config or UI.
   *
   * @param string $name
   *   Client name.
   * @param string $uuid
   *   Client UUID.
   * @param string $api_key
   *   API Key.
   * @param string $secret_key
   *   Secret Key.
   * @param string $url
   *   Hostname.
   * @param string $shared_secret
   *   Shared secret key.
   *
   * @dataProvider settingsDataProvider
   *
   * @see GetSettingsFromCoreConfig
   *
   * @throws \Exception
   */
  public function testGetClientConfiguredByCoreConfig(string $name, string $uuid, string $api_key, string $secret_key, string $url, string $shared_secret): void {
    $ch_settings = [
      'client_name' => $name,
      'origin' => $uuid,
      'api_key' => $api_key,
      'secret_key' => $secret_key,
      'hostname' => $url,
      'shared_secret' => $shared_secret,
    ];
    $this->createAcquiaContentHubAdminSettings($ch_settings);

    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $clientFactory */
    $clientFactory = $this->container->get('acquia_contenthub.client.factory');
    $settings = $clientFactory->getClient()->getSettings();

    // Check that settings has loaded from correct storage (provider).
    $this->assertEquals($clientFactory->getProvider(), 'core_config');

    // Check all values.
    $this->assertEquals($settings->getName(), $name);
    $this->assertEquals($settings->getUuid(), $uuid);
    $this->assertEquals($settings->getApiKey(), $api_key);
    $this->assertEquals($settings->getSecretKey(), $secret_key);
    $this->assertEquals($settings->getUrl(), $url);
    $this->assertEquals($settings->getSharedSecret(), $shared_secret);
  }

  /**
   * Test case when content hub configured via system settings.
   *
   * @param string $name
   *   Client name.
   * @param string $uuid
   *   Client UUID.
   * @param string $api_key
   *   API Key.
   * @param string $secret_key
   *   Secret Key.
   * @param string $url
   *   Hostname.
   * @param string $shared_secret
   *   Shared secret key.
   *
   * @dataProvider settingsDataProvider
   *
   * @throws \ReflectionException
   */
  public function testGetClientConfiguredBySettings(string $name, string $uuid, string $api_key, string $secret_key, string $url, string $shared_secret) {
    // Get existing values from settings.php file.
    $system_settings = Settings::getAll();
    // Merge our settings.
    $system_settings['acquia_contenthub.settings'] = new AcquiaSettings(
      $name,
      $uuid,
      $api_key,
      $secret_key,
      $url,
      $shared_secret
    );

    // Re-initialize (update) settings.
    new Settings($system_settings);

    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $clientFactory */
    $clientFactory = $this->container->get('acquia_contenthub.client.factory');
    $settings = $clientFactory->getClient()->getSettings();

    $this->assertEquals($clientFactory->getProvider(), 'core_settings');
    $this->assertEquals($settings->getName(), $name);
    $this->assertEquals($settings->getUuid(), $uuid);
    $this->assertEquals($settings->getApiKey(), $api_key);
    $this->assertEquals($settings->getSecretKey(), $secret_key);
    $this->assertEquals($settings->getUrl(), $url);
    $this->assertEquals($settings->getSharedSecret(), $shared_secret);
  }

  /**
   * Test case to verify event logging url.
   *
   * @covers ::getLoggingUrl
   *
   * @throws \Exception
   */
  public function testLoggingUrl() {
    $event_service_url = 'https://event-service.com';
    $this->createAcquiaContentHubAdminSettings(['event_service_url' => $event_service_url]);

    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $client_factory */
    $client_factory = $this->container->get('acquia_contenthub.client.factory');

    $output = $client_factory->getLoggingUrl();
    $this->assertEquals($event_service_url, $output);
  }

  /**
   * Test case to verify event logging url exception.
   *
   * @param string $name
   *   Client name.
   * @param string $uuid
   *   Client UUID.
   * @param string $api_key
   *   API Key.
   * @param string $secret_key
   *   Secret Key.
   * @param string $url
   *   Hostname.
   * @param string $shared_secret
   *   Shared secret key.
   *
   * @covers ::getEventLoggingUrlFromRemoteSettings
   *
   * @dataProvider settingsDataProvider
   *
   * @throws \Exception
   */
  public function testGetEventLoggingUrlFromRemoteSettings(string $name, string $uuid, string $api_key, string $secret_key, string $url, string $shared_secret) {
    $this->enableModules(['dblog']);
    $this->installSchema('dblog', 'watchdog');

    $ch_settings = [
      'client_name' => $name,
      'origin' => $uuid,
      'api_key' => $api_key,
      'secret_key' => $secret_key,
      'hostname' => $url,
      'shared_secret' => $shared_secret,
    ];
    $this->createAcquiaContentHubAdminSettings($ch_settings);
    $settings = new AcquiaSettings($name, $uuid, $api_key, $secret_key, $url, $shared_secret);
    $client_factory = $this->getClientFactory($settings);
    $client_factory->getEventLoggingUrlFromRemoteSettings();
    $this->assertLogMessage('acquia_contenthub',
      'Event logging service url not found in remote settings.'
    );
  }

  /**
   * Test case to verify event logging url exception.
   *
   * @covers ::saveAchAdminSettingsConfig
   *
   * @throws \Exception
   */
  public function testSaveAchAdminSettingsConfig() {
    $event_logging_url = 'https://example.com';
    $key = 'event_service_url';

    $config = $this->config('acquia_contenthub.admin_settings');
    $this->assertNull($config->get($key));

    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $client_factory */
    $client_factory = $this->container->get('acquia_contenthub.client.factory');

    $client_factory->saveAchAdminSettingsConfig($key, $event_logging_url);

    $config = $this->config('acquia_contenthub.admin_settings');
    $this->assertEquals($event_logging_url, $config->get($key));
  }

  /**
   * Provides sample data for client's settings.
   *
   * @return array
   *   Settings.
   */
  public function settingsDataProvider(): array {
    return [
      [
        'test-client',
        '00000000-0000-0001-0000-123456789123',
        '12312321312321',
        '12312321312321',
        'https://dev.content-hub.dev',
        '12312321312321',
        'https://example.com',
      ],
    ];
  }

  /**
   * Returns a new content hub client factory.
   *
   * The event dispatcher has been mocked so unnecessary api calls could be
   * avoided in project version client.
   *
   * @param \Acquia\ContentHubClient\Settings $settings
   *   The content hub settings to use for mocking.
   *
   * @return \Drupal\acquia_contenthub\Client\ClientFactory
   *   A bootstrapped client factory.
   *
   * @throws \Exception
   */
  protected function getClientFactory(AcquiaSettings $settings): ClientFactory {
    $dispatcher = $this->prophesize(EventDispatcher::class);
    $dispatcher->dispatch(
      Argument::any(),
      Argument::type('string'),
    )->will(function ($args) use ($settings) {
      $event = $args[0];
      if ($event instanceof AcquiaContentHubSettingsEvent) {
        $event->setSettings($settings);
      }
    });

    $platform_checker = $this->prophesize(PlatformCompatibilityChecker::class);
    $platform_checker->intercept(Argument::type(ContentHubClient::class))->willReturnArgument();

    $this->container->set('event_dispatcher', $dispatcher);
    return new ClientFactory(
      $dispatcher->reveal(),
      $this->container->get('acquia_contenthub.logger_channel'),
      $this->container->get('extension.list.module'),
      $this->container->get('config.factory'),
      $platform_checker->reveal(),
    );
  }

  /**
   * Test case to check if event microservice is unreachable.
   *
   * For vague event logging url.
   *
   * @throws \Exception
   */
  public function testEventServiceNotReachable(): void {
    $settings = new AcquiaSettings(
      'test-client',
      '00000000-0000-0001-0000-123456789123',
      '12312321312321',
      '12312321312321',
      'https://dev.content-hub.dev',
      '12312321312321',
      ['event_service_url' => 'http://example.com']
    );
    $error_msg = 'Error during contacting Event Micro Service: Client error:';
    $cdf_object = $this->getMockBuilder(ClientFactory::class)
      ->setConstructorArgs([
        $this->container->get('event_dispatcher'),
        $this->container->get('acquia_contenthub.logger_channel'),
        $this->container->get('extension.list.module'),
        $this->container->get('config.factory'),
        $this->container->get('acquia_contenthub.platform_checker'),
      ])
      ->onlyMethods(['checkLoggingClient', 'getSettings'])
      ->getMock();
    $cdf_object->method('getSettings')
      ->willReturn($settings);
    $cdf_object->method('checkLoggingClient')
      ->with($this->anything())
      ->willThrowException(new EventServiceUnreachableException($error_msg));
    $this->container->set('acquia_contenthub.client.factory', $cdf_object);

    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $client_factory */
    $client_factory = $this->container->get('acquia_contenthub.client.factory');
    $this->expectException(EventServiceUnreachableException::class);
    $this->expectExceptionMessage($error_msg);
    $client_factory->getLoggingClient();
  }

  /**
   * Tests check logging client method.
   *
   * To assert event service is reachable or not.
   *
   * @covers ::checkLoggingClient
   */
  public function testCheckLoggingClient(): void {
    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $client_factory */
    $client_factory = $this->container->get('acquia_contenthub.client.factory');
    $logging_client = $this->prophesize(ContentHubLoggingClient::class);
    $logging_client
      ->ping()
      ->shouldBeCalled()
      ->willThrow(new ClientException('', $this->prophesize(Request::class)->reveal(), new Response(404)));
    $this->expectException(EventServiceUnreachableException::class);
    $this->expectExceptionMessage('Error during contacting Event Micro Service:');
    $client_factory->checkLoggingClient($logging_client->reveal());
    $logging_client = $this->prophesize(ContentHubLoggingClient::class);
    $logging_client
      ->ping()
      ->shouldBeCalled()
      ->willReturn(new Response());
    $client_factory->checkLoggingClient($logging_client->reveal());
    // Empty assertion which means no exception was raised.
    $this->addToAssertionCount(1);
  }

  /**
   * Tests checkLoggingClient with logging client not set.
   *
   * @covers ::checkLoggingClient
   *
   * @throws \Exception
   */
  public function testLoggingClientNotSet(): void {
    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $client_factory */
    $client_factory = $this->container->get('acquia_contenthub.client.factory');
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Content Hub Logging Client is not configured.');
    $client_factory->checkLoggingClient();
  }

}
