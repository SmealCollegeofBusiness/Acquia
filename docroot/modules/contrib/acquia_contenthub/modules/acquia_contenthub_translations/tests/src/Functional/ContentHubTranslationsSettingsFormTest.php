<?php

namespace Drupal\Tests\acquia_contenthub_translations\Functional;

use Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Content Hub translations settings form.
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Functional
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm
 */
class ContentHubTranslationsSettingsFormTest extends BrowserTestBase {

  use AcquiaContentHubAdminSettingsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_test',
    'acquia_contenthub_server_test',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * Authorized user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $authorizedUser;

  /**
   * Unauthorized user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $unauthorizedUser;

  /**
   * Translation setting form path.
   */
  protected const FORM_PATH = '/admin/config/services/acquia-contenthub/translations';

  /**
   * Undesired language registrar.
   *
   * @var \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistrar
   */
  protected $registrar;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createAcquiaContentHubAdminSettings();
    $this->authorizedUser = $this->drupalCreateUser([
      'administer acquia content hub',
    ]);

    $this->unauthorizedUser = $this->drupalCreateUser();
    $this->drupalLogin($this->authorizedUser);
    $this->registrar = $this->container->get('acquia_contenthub_translations.undesired_language_registrar');
  }

  /**
   * Tests whether user has access to the form or not.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAccessToForm(): void {
    $session = $this->assertSession();

    $this->drupalGet(self::FORM_PATH);
    $session->pageTextContains('Translation syndication');
    $session->statusCodeEquals(200);

    $this->drupalLogout();
    $this->drupalLogin($this->unauthorizedUser);

    $this->drupalGet(self::FORM_PATH);
    $session->pageTextContains('Access denied');
    $session->statusCodeEquals(403);
  }

  /**
   * Tests form elements are rendered as per expectation.
   *
   * @covers ::buildForm
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testFormRendersWithoutUndesiredLanguageComponent(): void {
    $session = $this->assertSession();

    $this->drupalGet(self::FORM_PATH);
    $session->pageTextContains('Translation syndication');
    $session->statusCodeEquals(200);

    $session->fieldExists('Enable selective language import');
    $session->pageTextNotContains('List of undesired languages');
  }

  /**
   * Tests undesired language list only shows up.
   *
   * When at least one language is saved in config.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testUndesiredListIsVisible(): void {
    $this->registrar->markLanguagesUndesired('fr', 'en');
    $session = $this->assertSession();

    $this->drupalGet(self::FORM_PATH);
    $session->pageTextContains('Translation syndication');
    $session->statusCodeEquals(200);
    $session->fieldExists('Enable selective language import');
    $session->pageTextContains('List of undesired languages');
    $session->checkboxChecked('fr');
    $session->checkboxChecked('en');
  }

  /**
   * Tests language removed from undesired list should not show up on form.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testRemovedLanguageNotVisible(): void {
    $languages = ['fr', 'br', 'zh', 'pt', 'es'];
    $this->registrar->markLanguagesUndesired(...$languages);
    $session = $this->assertSession();
    $this->drupalGet(self::FORM_PATH);
    $session->pageTextContains('Translation syndication');
    $session->statusCodeEquals(200);
    $session->fieldExists('Enable selective language import');
    $session->pageTextContains('List of undesired languages');
    foreach ($languages as $language) {
      $session->checkboxChecked($language);
    }
    $removed_languages = ['br', 'pt'];
    $this->registrar->removeLanguageFromUndesired(...$removed_languages);
    $this->drupalGet(self::FORM_PATH);
    foreach ($languages as $language) {
      if (in_array($language, $removed_languages)) {
        $session->fieldNotExists($language);
        continue;
      }
      $session->checkboxChecked($language);
    }
  }

  /**
   * Tests form submits properly and config is saved.
   *
   * @covers ::submitForm
   */
  public function testFormSubmitsProperly(): void {
    $selective_language_enabled = $this->config(ContentHubTranslationsSettingsForm::CONFIG)->get('selective_language_import');
    $this->assertFalse($selective_language_enabled);
    $this->drupalGet(self::FORM_PATH);
    $data = [
      'selective_language_import' => TRUE,
    ];
    $this->submitForm($data, 'Save configuration');
    $selective_language_enabled = $this->config(ContentHubTranslationsSettingsForm::CONFIG)->get('selective_language_import');
    $this->assertTrue($selective_language_enabled);
  }

}
