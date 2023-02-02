<?php

namespace Drupal\Tests\acquia_contenthub_dashboard\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;

/**
 * Tests Acquia ContentHub Dashboard module installation.
 *
 * @group acquia_contenthub_dashboard
 *
 * @package Drupal\Tests\acquia_contenthub_dashboard\Kernel
 */
class ContentHubDashboardInstallTest extends KernelTestBase {

  use AcquiaContentHubAdminSettingsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_test',
  ];

  /**
   * Tests module installation without client.
   *
   * @throws \Exception
   */
  public function testContentHubDashboardInstallationWithoutClient(): void {
    $this->installModules(TRUE, FALSE);
    $this->assertEmpty($this->getAllowedOriginsConfig());
  }

  /**
   * Tests module installation without subscriber.
   *
   * @throws \Exception
   */
  public function testContentHubDashboardInstallationWithoutSubscriber(): void {
    $this->createAcquiaContentHubAdminSettings([
      'webhook' => [
        'uuid' => '00000000-0000-0001-0000-123456789123',
      ],
    ]);
    $this->installModules(FALSE);

    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $subscriber_enabled */
    $subscriber_module_exists = \Drupal::service('module_handler')->moduleExists('acquia_contenthub_subscriber');
    $this->assertFalse($subscriber_module_exists);
    $this->assertEmpty($this->getAllowedOriginsConfig());
  }

  /**
   * Tests module installation success.
   *
   * @throws \Exception
   */
  public function testContentHubDashboardInstallationSuccess(): void {
    $this->createAcquiaContentHubAdminSettings([
      'webhook' => [
        'uuid' => '00000000-0000-0001-0000-123456789123',
      ],
    ]);
    $this->installModules();
    $allowed_origins = $this->getAllowedOriginsConfig();
    $this->assertNotEmpty($allowed_origins);
    $this->assertEquals('https://www.example.com', current($allowed_origins));
  }

  /**
   * Install modules.
   *
   * @param bool $enable_subscriber
   *   Flag to enable subscriber module.
   * @param bool $enable_server_Test
   *   Flag to enable acquia server test module.
   *
   * @throws \Exception
   */
  protected function installModules(bool $enable_subscriber = TRUE, bool $enable_server_Test = TRUE): void {
    if ($enable_subscriber) {
      $this->container->get('module_installer')->install(['acquia_contenthub_subscriber']);
    }

    if ($enable_server_Test) {
      $this->container->get('module_installer')->install(['acquia_contenthub_server_test']);
    }

    $this->container->get('module_installer')->install(['acquia_contenthub_dashboard']);
  }

  /**
   * Get allowed origins configurations.
   *
   * @return array|null
   *   Allowed origins config.
   *
   * @throws \Exception
   */
  protected function getAllowedOriginsConfig(): ?array {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->get('acquia_contenthub_dashboard.settings');
    return $config->get('allowed_origins');
  }

}
