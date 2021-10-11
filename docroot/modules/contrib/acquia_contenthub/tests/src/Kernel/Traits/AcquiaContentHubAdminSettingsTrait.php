<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Traits;

/**
 * Trait for ACH admin settings configurations.
 */
trait AcquiaContentHubAdminSettingsTrait {

  /**
   * Get Acquia Content Hub settings.
   *
   * @param array $settings
   *   Content Hub settings.
   */
  protected function createAcquiaContentHubAdminSettings(array $settings = []): void {
    $default_settings = [
      'client_name' => 'test-client',
      'origin' => '00000000-0000-0001-0000-123456789123',
      'api_key' => '12312321312321',
      'secret_key' => '12312321312321',
      'hostname' => 'https://dev.content-hub.dev',
      'shared_secret' => '12312321312321',
      'event_service_url' => 'http://example.com',
    ];

    $actual_settings = array_merge($default_settings, $settings);

    $admin_settings = \Drupal::configFactory()
      ->getEditable('acquia_contenthub.admin_settings');

    $admin_settings
      ->setData($actual_settings)
      ->save();
  }

}
