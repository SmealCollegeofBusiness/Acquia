<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel;

use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm;
use Drupal\depcalc\DependencyStack;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentCreatorTrait;

/**
 * Tests that subsequent imports don't change default language.
 *
 * @requires module depcalc
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class ConsecutiveEntityImportTest extends KernelTestBase {

  use CdfDocumentCreatorTrait;

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
   * Undesired language registrar.
   *
   * @var \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface
   */
  protected $undesiredLanguageRegistrar;

  /**
   * Entity cdf serializer.
   *
   * @var \Drupal\acquia_contenthub\EntityCdfSerializer
   */
  protected $cdfSerializer;

  /**
   * Entity translation manager.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
   */
  protected $entityTranslationManager;

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
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->config(ContentHubTranslationsSettingsForm::CONFIG)->set('selective_language_import', TRUE)->save();
    $this->undesiredLanguageRegistrar = $this->container->get('acquia_contenthub_translations.undesired_language_registrar');
    $this->cdfSerializer = $this->container->get('entity.cdf.serializer');
    $this->entityTranslationManager = $this->container->get('acquia_contenthub_translations.manager');
  }

  /**
   * Node UUID under test.
   */
  public const NODE_UUID = '89ea74ad-930a-46a3-9c4d-402d381e45d3';

  /**
   * Fixtures to import.
   *
   * @var array
   */
  protected $fixtures = [
    // First iteration where entity has 2 languages - es(default) & ro.
    0 => 'node/node-no-common-languages.json',
    // Second iteration where entity has 3 languages - es(default), ro and en.
    1 => 'node/node-with-one-common-language.json',
  ];

  /**
   * Tests that on addition of accepted translation to entity.
   *
   * Its default language is not changed on 2nd iteration.
   *
   * @covers \Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf::filterCdfDocument
   */
  public function testNodeConsecutiveImport(): void {
    // First import.
    $cdf_document_v1 = $this->createCdfDocumentFromFixtureFile($this->fixtures[0], 'acquia_contenthub_translations');
    $this->cdfSerializer->unserializeEntities($cdf_document_v1, new DependencyStack());
    $this->assertContains('es', $this->undesiredLanguageRegistrar->getUndesiredLanguages());
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', self::NODE_UUID);
    $this->assertNotContains('ro', array_keys($node->getTranslationLanguages()));
    $this->assertEquals('es', $node->language()->getId());
    $tracked_entity_v1 = $this->entityTranslationManager->getTrackedEntity(self::NODE_UUID);
    $this->assertEquals('es', $tracked_entity_v1->defaultLanguage());
    $this->assertEmpty($tracked_entity_v1->languages());
    // Second import.
    $cdf_document_v2 = $this->createCdfDocumentFromFixtureFile($this->fixtures[1], 'acquia_contenthub_translations');
    $this->cdfSerializer->unserializeEntities($cdf_document_v2, new DependencyStack());
    $this->assertContains('es', $this->undesiredLanguageRegistrar->getUndesiredLanguages());
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity.repository')->loadEntityByUuid('node', self::NODE_UUID);
    $this->assertNotContains('ro', array_keys($node->getTranslationLanguages()));
    $this->assertContains('en', array_keys($node->getTranslationLanguages()));
    $this->assertEquals('es', $node->language()->getId(), 'Default language wasn\'t changed on 2nd import');
    $tracked_entity_v2 = $this->entityTranslationManager->getTrackedEntity(self::NODE_UUID);
    $this->assertEquals('es', $tracked_entity_v2->defaultLanguage());
    $this->assertArrayHasKey('en', $tracked_entity_v2->languages());
  }

}
