<?php

namespace Drupal\Tests\Kernel;

use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm;
use Drupal\depcalc\DependencyStack;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentCreatorTrait;

/**
 * Tests the asymmetric paragraph translation import.
 *
 * @requires module depcalc
 * @requires module paragraphs
 * @requires module entity_reference_revisions
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class AsymmetricParagraphTranslationImportTest extends EntityKernelTestBase {

  use CdfDocumentCreatorTrait;

  /**
   * Entity cdf serializer.
   *
   * @var \Drupal\acquia_contenthub\EntityCdfSerializer
   */
  protected $cdfSerializer;

  /**
   * Undesired language registrar.
   *
   * @var \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface
   */
  protected $undesiredLanguageRegistrar;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Fixtures to import.
   *
   * @var array
   */
  protected $fixtures = [
    // Node with asymmetric paragraph with paragraph's default
    // language in site's default language.
    0 => 'node/node-with-asymmetric_paragraphs.json',
    // Node with asymmetric paragraph with paragraph's default
    // language not in site's default language.
    1 => 'node/node-with-asymmetric-paragraphs-non-default.json',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'field',
    'depcalc',
    'acquia_contenthub_test',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
    'language',
    'acquia_contenthub_translations_paragraphs_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setup();

    $this->installConfig(['language']);
    $this->languageManager = $this->container->get('language_manager');
    ConfigurableLanguage::createFromLangcode('hi')->save();
    $this->languageManager->reset();

    $this->installSchema('acquia_contenthub_subscriber', [SubscriberTracker::IMPORT_TRACKING_TABLE]);
    $this->installSchema('acquia_contenthub_translations',
      [EntityTranslations::TABLE, EntityTranslationsTracker::TABLE]
    );
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->config(ContentHubTranslationsSettingsForm::CONFIG)->set('selective_language_import', TRUE)->save();
    $this->undesiredLanguageRegistrar = $this->container->get('acquia_contenthub_translations.undesired_language_registrar');
    $this->cdfSerializer = $this->container->get('entity.cdf.serializer');
    $context_registry = $this->container->get('acquia_contenthub_translations.nt_entity_handler.registry');
    $context_registry->addEntityToRegistry('paragraph', 'removable');
    $context_registry->addEntityToOverriddenRegistry('paragraph');
  }

  /**
   * Tests paragraphs while importing with single translation.
   *
   * @dataProvider fixtureDataProvider
   */
  public function testAsymmetricParagraphImport(int $fixture): void {
    // Asymmetric paragraph.
    $cdf_document_v1 = $this->createCdfDocumentFromFixtureFile($this->fixtures[$fixture], 'acquia_contenthub_translations');
    $this->cdfSerializer->unserializeEntities($cdf_document_v1, new DependencyStack());
    $this->assertEmpty($this->undesiredLanguageRegistrar->getUndesiredLanguages());
    $this->assertSame(['en', 'hi'], array_keys($this->languageManager->getLanguages()));
  }

  /**
   * Data provider.
   *
   * @return array
   *   Data provider array.
   */
  public function fixtureDataProvider(): array {
    return [
      [0],
      [1],
    ];
  }

}
