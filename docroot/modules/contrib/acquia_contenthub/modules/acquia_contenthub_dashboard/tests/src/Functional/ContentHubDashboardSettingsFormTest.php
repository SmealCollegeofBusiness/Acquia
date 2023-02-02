<?php

namespace Drupal\Tests\acquia_contenthub_dashboard\Functional;

use Drupal\acquia_contenthub_test\MockDataProvider;
use Drupal\Tests\acquia_contenthub\Functional\ContentHubSettingsFormTest;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;

/**
 * Tests the Content Hub settings form.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\Form\ContentHubSettingsForm
 *
 * @group acquia_contenthub_dashboard
 */
class ContentHubDashboardSettingsFormTest extends ContentHubSettingsFormTest {

  use AcquiaContentHubAdminSettingsTrait;

  /**
   * Tests whether fields rendered properly.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAutoPublisherDiscoveryFieldNotExists() {
    $session = $this->assertSession();

    $this->drupalGet(self::CH_SETTINGS_FORM_PATH);
    $session->fieldNotExists('Automatic Publisher Discovery');
  }

  /**
   * Tests whether fields rendered properly.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAutoPublisherDiscoveryFieldExists() {
    $this->createAcquiaContentHubAdminSettings([
      'webhook' => [
        'uuid' => '00000000-0000-0001-0000-123456789123',
      ],
    ]);
    $this->installModules();
    $session = $this->assertSession();

    $this->drupalGet(self::CH_SETTINGS_FORM_PATH);
    $session->fieldExists('Automatic Publisher Discovery');
    $session->fieldEnabled('Automatic Publisher Discovery');
    $session->fieldValueEquals('Automatic Publisher Discovery', TRUE);
  }

  /**
   * Tests the successful registration.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testContentHubSettingsPageWithValidData() {
    $this->installModules();
    $session = $this->assertSession();

    $settings = [
      'hostname' => MockDataProvider::VALID_HOSTNAME,
      'api_key' => MockDataProvider::VALID_API_KEY,
      'secret_key' => MockDataProvider::VALID_SECRET,
      'client_name' => MockDataProvider::VALID_CLIENT_NAME,
      'webhook' => MockDataProvider::VALID_WEBHOOK_URL,
      'automatic_publisher_discovery' => TRUE,
    ];

    // Successful attempt to register client.
    $this->drupalGet(self::CH_SETTINGS_FORM_PATH);
    $this->submitForm($settings, 'Register Site');
    $session->pageTextContains('Site successfully connected to Content Hub. To change connection settings, unregister the site first.');
    $session->statusCodeEquals(200);
    $session->buttonNotExists('Register Site');
    $session->buttonExists('Update Public URL');
    $session->linkExists('Unregister Site');

    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('acquia_contenthub_dashboard.settings');
    $this->assertTrue($config->get('auto_publisher_discovery'));
  }

  /**
   * Install subscriber and dashboard modules.
   */
  protected function installModules(): void {
    $this->container->get('module_installer')->install(['acquia_contenthub_subscriber']);
    $this->container->get('module_installer')->install(['acquia_contenthub_dashboard']);
  }

}
