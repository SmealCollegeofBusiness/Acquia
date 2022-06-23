<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\Settings as AcquiaSettings;
use Drupal\acquia_contenthub\Client\ProjectVersionClient;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Prophecy\Argument;

/**
 * Tests the client factory.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class ClientFactoryTest extends EntityKernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;

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
   */
  public function testGetClientConfiguredByCoreConfig(string $name, string $uuid, string $api_key, string $secret_key, string $url, string $shared_secret) {

    $ch_settings = [
      'client_name' => $name,
      'origin' => $uuid,
      'api_key' => $api_key,
      'secret_key' => $secret_key,
      'hostname' => $url,
      'shared_secret' => $shared_secret,
    ];
    $this->createAcquiaContentHubAdminSettings($ch_settings);

    Cache::invalidateTags(['acquia_contenthub_settings']);

    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $clientFactory */
    $clientFactory = \Drupal::service('acquia_contenthub.client.factory');

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
    $clientFactory = \Drupal::service('acquia_contenthub.client.factory');
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
      ],
    ];
  }

}
