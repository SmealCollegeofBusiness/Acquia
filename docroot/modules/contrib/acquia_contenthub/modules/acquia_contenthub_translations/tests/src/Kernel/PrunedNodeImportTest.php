<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel;

use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface as ETMInterface;
use Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_contenthub\Kernel\ImportExportTestBase;
use Drupal\Tests\acquia_contenthub_translations\Traits\EntityTranslationDbAssertions;

/**
 * Tests multi-translation nodes with pruning of languages.
 *
 * @requires module depcalc
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class PrunedNodeImportTest extends ImportExportTestBase {

  use EntityTranslationDbAssertions;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'file',
    'taxonomy',
    'language',
    'field',
    'depcalc',
    'acquia_contenthub_test',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setup(): void {
    parent::setup();
    $this->installConfig(['language']);
    $this->installSchema('acquia_contenthub_subscriber', [SubscriberTracker::IMPORT_TRACKING_TABLE]);
    $this->installSchema('acquia_contenthub_translations',
      [EntityTranslations::TABLE, EntityTranslationsTracker::TABLE]
    );
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->config(ContentHubTranslationsSettingsForm::CONFIG)->set('selective_language_import', TRUE)->save();
  }

  /**
   * Fixtures to import.
   *
   * @var array
   */
  protected $fixtures = [
    0 => [
      'cdf' => 'node/node-translations.json',
      'expectations' => 'expectations/node/node_translations.php',
    ],
    1 => [
      'cdf' => 'node/node-translations-non-default-lang-node.json',
      'expectations' => 'expectations/node/node_translations_non_default_lang_node.php',
    ],
  ];

  /**
   * Tests that node with en default will not have unnecessary be translation.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPrunedNodeImportWithMultipleTranslations(): void {
    $node_uuid = 'b0137bab-a80e-4305-84fe-4d99ffd906c5';
    $unnecessary_language = 'be';
    $this->importFixture(0);
    $languages = array_keys(\Drupal::languageManager()->getLanguages());
    $this->assertNotContains($unnecessary_language, $languages);
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $node_uuid);
    $translations = array_keys($node->getTranslationLanguages());
    $this->assertNotContains($unnecessary_language, $translations);
  }

  /**
   * Tests that node with two translations.
   *
   * Will have non default translation tracked.
   *
   * @covers \Drupal\acquia_contenthub_translations\TranslationFacilitator::trackTranslation
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPrunedNodeImportWithLanguageTracking(): void {
    $language = ConfigurableLanguage::createFromLangcode('be');
    $language->save();
    \Drupal::languageManager()->reset();
    $node_uuid = 'b0137bab-a80e-4305-84fe-4d99ffd906c5';
    $this->importFixture(0);
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $node_uuid);
    $translations = array_keys($node->getTranslationLanguages(FALSE));
    // Asserts that even on default node save,
    // non default languages are tracked.
    foreach ($translations as $translation) {
      $this->assertTranslationsRows([
        $node_uuid => [
          [
            'entity_uuid' => $node_uuid,
            'langcode' => $translation,
            'operation_flag' => ETMInterface::NO_ACTION,
          ],
        ],
      ], 1);
    }
  }

  /**
   * Tests that entity with only default language will be imported.
   *
   * And default language will be enabled on subscriber.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPrunedNodeImportWithDifferentDefaultLanguage(): void {
    $node_uuid = 'c3910d90-e4ff-467e-9bb4-5c1b5bb43008';
    $default_language = 'be';
    $this->importFixture(1);
    $languages = array_keys(\Drupal::languageManager()->getLanguages());
    $this->assertContains($default_language, $languages);
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', $node_uuid);
    $translations = array_keys($node->getTranslationLanguages());
    $this->assertContains($default_language, $translations);
    $undesired_languages = $this->container->get('acquia_contenthub_translations.undesired_language_registrar')->getUndesiredLanguages();
    $this->assertContains($default_language, $undesired_languages);
  }

}
