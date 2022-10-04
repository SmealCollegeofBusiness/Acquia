<?php

namespace Drupal\Tests\Kernel;

use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm;
use Drupal\depcalc\DependencyStack;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentCreatorTrait;

/**
 * Tests the single entity translation import.
 *
 * @requires module depcalc
 * @requires module paragraphs
 * @requires module entity_reference_revisions
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class ParagraphEntityImportTest extends EntityKernelTestBase {

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
   * Fixtures to import.
   *
   * @var array
   */
  protected $fixtures = [
    // Node & paragraph has 2 languages - hi(default) & en.
    0 => 'node/node-with-paragraph-translation.json',
    // Node has 2 & new paragraph has 1 language - hi(default) &en.
    1 => 'node/node-with-paragraph-no-translation.json',
    // Newly added paragraph - hi.
    2 => 'node/paragraph-no-translation.json',
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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    parent::setup();

    $this->installConfig(['language']);

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
  }

  /**
   * Tests paragraphs while importing with single translation.
   */
  public function testParagraphSaveWithWorkflowsTranslation(): void {
    // First import.
    $cdf_document_v1 = $this->createCdfDocumentFromFixtureFile($this->fixtures[0], 'acquia_contenthub_translations');
    $this->cdfSerializer->unserializeEntities($cdf_document_v1, new DependencyStack());
    $this->assertEmpty($this->undesiredLanguageRegistrar->getUndesiredLanguages());

    // Second import.
    $cdf_document_v2 = $this->createCdfDocumentFromFixtureFile($this->fixtures[1], 'acquia_contenthub_translations');
    $this->cdfSerializer->unserializeEntities($cdf_document_v2, new DependencyStack());
    $this->assertContains('hi', $this->undesiredLanguageRegistrar->getUndesiredLanguages());

    // Third import - new paragraph.
    $cdf_document_v3 = $this->createCdfDocumentFromFixtureFile($this->fixtures[2], 'acquia_contenthub_translations');
    $this->cdfSerializer->unserializeEntities($cdf_document_v3, new DependencyStack());
    $this->assertContains('hi', $this->undesiredLanguageRegistrar->getUndesiredLanguages());
  }

}
