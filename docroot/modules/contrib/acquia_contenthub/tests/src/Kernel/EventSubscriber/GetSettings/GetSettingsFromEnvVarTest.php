<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\GetSettings;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\AcquiaContentHubSettingsEvent;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that environment variables can be used for settings.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\GetSettings\GetSettingsFromEnvVar
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\GetSettings
 */
class GetSettingsFromEnvVarTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'acquia_contenthub',
    'depcalc',
    'user',
  ];

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();

    $this->dispatcher = $this->container->get('event_dispatcher');
  }

  /**
   * Tests GetSettingsFromEnvVar event subscriber.
   *
   * @param array $credentials
   *   Credentials for registering with ACH.
   * @param string $settings_provider
   *   Expected settings provider.
   * @param array $expected_settings
   *   Expected setting values.
   *
   * @dataProvider credentialsDataProvider
   *
   * @throws \Exception
   */
  public function testGetSettingsFromEnvVar(array $credentials, string $settings_provider, array $expected_settings) {
    foreach ($credentials as $key => $value) {
      putenv("$key=$value");
    }

    $event = new AcquiaContentHubSettingsEvent();
    $this->dispatcher->dispatch(AcquiaContentHubEvents::GET_SETTINGS, $event);

    $this->assertEqual($event->getSettings()->toArray(), $expected_settings);
    $this->assertEqual($event->getProvider(), $settings_provider);
  }

  /**
   * Provides sample data for environment variables.
   *
   * @return array
   *   Credentials.
   */
  public function credentialsDataProvider() {
    return [
      [
        [
          'acquia_contenthub_api_secret' => 'secret_key_test',
          'acquia_contenthub_api_key' => 'api_key_test',
          'acquia_contenthub_hostname' => 'https://test-settings.com',
          'acquia_contenthub_client_name' => 'client_name_test',
          'acquia_contenthub_origin' => 'origin_test',
          'acquia_contenthub_shared_secret' => 'shared_secret_test',
          'acquia_contenthub_webhook_url' => 'https://test-settings-webhook.com',
          'acquia_contenthub_webhook_uuid' => 'webhook_uuid_test',
          'acquia_contenthub_settings_url' => 'webhook_settings_url_test',
        ],
        'environment_variable',
        [
          'name' => 'client_name_test',
          'uuid' => 'origin_test',
          'apiKey' => 'api_key_test',
          'secretKey' => 'secret_key_test',
          'url' => 'https://test-settings.com',
          'sharedSecret' => 'shared_secret_test',
          'webhook' => [
            'url' => 'https://test-settings-webhook.com',
            'uuid' => 'webhook_uuid_test',
            'settings_url' => 'webhook_settings_url_test',
          ],
        ],
      ],
      [
        [
          'acquia_contenthub_api_secret' => 'secret_key_test',
        ],
        'core_config',
        [
          'name' => NULL,
          'uuid' => FALSE,
          'apiKey' => NULL,
          'secretKey' => NULL,
          'url' => NULL,
          'sharedSecret' => NULL,
          'webhook' => [],
        ],
      ],
      [
        [],
        'core_config',
        [
          'name' => NULL,
          'uuid' => FALSE,
          'apiKey' => NULL,
          'secretKey' => NULL,
          'url' => NULL,
          'sharedSecret' => NULL,
          'webhook' => [],
        ],
      ],
    ];
  }

}
