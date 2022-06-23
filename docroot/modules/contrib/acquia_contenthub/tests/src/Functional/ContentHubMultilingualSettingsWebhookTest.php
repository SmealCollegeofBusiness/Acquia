<?php

namespace Drupal\Tests\acquia_contenthub\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the Webhook Url is unchanged with multilingual settings.
 *
 * @group acquia_contenthub
 */
class ContentHubMultilingualSettingsWebhookTest extends BrowserTestBase {

  /**
   * User that has administer permission.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $authorizedUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'acquia_contenthub',
    'language',
    'locale',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setUp();

    // User to manage languages.
    $this->authorizedUser = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($this->authorizedUser);

    // Adding French Language: FR.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Set language detection to url.
    $this->drupalGet('/admin/config/regional/language/detection');
    $this->submitForm([
      'language_interface[enabled][language-url]' => TRUE,
      'language_interface[enabled][language-selected]' => TRUE,
    ], 'Save settings');

    // Set prefixes to en and fr.
    $this->drupalGet('/admin/config/regional/language/detection/url');
    $this->submitForm([
      'language_negotiation_url_part' => 'path_prefix',
      'prefix[en]' => 'en',
      'prefix[fr]' => 'fr',
    ], 'Save configuration');

    // Set Language Detection for selected language.
    $this->drupalGet('/admin/config/regional/language/detection/selected');
    $this->submitForm([
      'edit-selected-langcode' => 'en',
    ], 'Save configuration');

    // Clear cache to refresh changes.
    drupal_flush_all_caches();
  }

  /**
   * Tests the webhook path is not changed if multilingual settings are enabled.
   */
  public function testContentHubWebhookPath() {
    $base_path = \Drupal::request()->getBasePath();
    $webhook_path = Url::fromRoute('acquia_contenthub.webhook')->toString();
    $this->assertEquals($base_path . '/acquia-contenthub/webhook', $webhook_path);
  }

}
