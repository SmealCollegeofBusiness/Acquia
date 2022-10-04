<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\EventSubscriber\PruneCdf;

use Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf;
use Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentCreatorTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;

/**
 * Tests pruning of languages in a CDF.
 *
 * @requires module depcalc
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf
 */
class PruneCdfLanguagesTest extends KernelTestBase {

  use CdfDocumentCreatorTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'user',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
    'language',
    'system',
  ];

  /**
   * Undesired language registrar.
   *
   * @var \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface
   */
  protected $registrar;

  /**
   * Logger mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $logger;

  /**
   * Fixtures for the test.
   *
   * @var array
   */
  protected $fixtures = [
    0 => 'node/node-prune-cdf-languages.json',
  ];

  /**
   * Cdf document.
   *
   * @var \Acquia\ContentHubClient\CDFDocument
   */
  protected $cdfDocument;

  /**
   * Content hub translations config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $translationConfig;

  /**
   * Cdf pruner.
   *
   * @var \Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf
   */
  protected $cdfPruner;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Entity translation manager.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
   */
  protected $entityTranslationManager;

  /**
   * Non-translatable entity handler context.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityHandler\NonTranslatableEntityHandlerContext
   */
  protected $ntEntityHandlerContext;

  /**
   * {@inheritDoc}
   *
   * @throws \ReflectionException
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['language']);
    $this->installSchema('acquia_contenthub_translations',
      [EntityTranslationsTracker::TABLE, EntityTranslations::TABLE]
    );
    $this->registrar = $this->container->get('acquia_contenthub_translations.undesired_language_registrar');
    $this->logger = new LoggerMock();
    $this->cdfDocument = $this->createCdfDocumentFromFixtureFile($this->fixtures[0], 'acquia_contenthub_translations');
    $this->translationConfig = $this->config(ContentHubTranslationsSettingsForm::CONFIG);
    $this->translationConfig->set('selective_language_import', TRUE)->save();
    $this->languageManager = $this->container->get('language_manager');
    $this->entityTranslationManager = $this->container->get('acquia_contenthub_translations.manager');
    $this->ntEntityHandlerContext = $this->container->get('acquia_contenthub_translations.nt_entity_handler.context');
    $this->cdfPruner = $this->newPruneLanguagesFromCdf();
  }

  /**
   * Tests that no pruning happens for entities with und language.
   *
   * @covers ::onPruneCdf
   */
  public function testLanguagePruningForUndefinedLanguage(): void {
    // Default language is und for this entity.
    $uuid = '0612f69c-5968-4b40-9c1d-48a549b56325';
    $old_cdf_object = $this->cdfDocument->getCdfEntity($uuid);
    $event = $this->triggerPruneCdfEntityEvent();
    $new_cdf_object = $event->getCdf()->getCdfEntity($uuid);
    $this->assertEquals($old_cdf_object, $new_cdf_object);
  }

  /**
   * Tests that entities with single language will have no pruning.
   *
   * And language will be added to undesired list.
   *
   * @covers ::onPruneCdf
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSingleLanguageEntityPruning(): void {
    // Default language is be for this entity.
    $uuid = '0612f69c-5968-4b40-9c1d-48a549b56326';
    $old_cdf_object = $this->cdfDocument->getCdfEntity($uuid);
    $this->changeDefaultLanguage();
    $this->cdfPruner = $this->newPruneLanguagesFromCdf();
    $event = $this->triggerPruneCdfEntityEvent();
    $new_cdf_object = $event->getCdf()->getCdfEntity($uuid);
    $this->assertEquals($old_cdf_object, $new_cdf_object);
    $entity_default_language = $old_cdf_object->getMetadata()['default_language'];
    $this->assertEquals([$entity_default_language], $this->registrar->getUndesiredLanguages());
    $info_messages = $this->logger->getInfoMessages();
    $this->assertEquals('Languages marked as undesired: (' . $entity_default_language . '). These languages will also be imported.', $info_messages[0]);
  }

  /**
   * Tests that when there are no common languages.
   *
   * Only default language is imported.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPruningWithNoCommonLanguages(): void {
    // Default language is 'en' for this entity.
    $node_uuid = '93aa4fec-639c-4a7d-9c94-96f7123fccaf';
    $old_cdf_object = $this->cdfDocument->getCdfEntity($node_uuid);
    $old_languages = $old_cdf_object->getMetadata()['languages'];
    $default_language = $old_cdf_object->getMetadata()['default_language'];
    $config_languages = [
      'en' => '05695528-2ab4-4c55-a7d9-1879cd083429',
      'hi' => '24c5283c-d14f-4070-ac1f-ffb58ab0bd19',
      'fr' => 'bf224e71-6c7e-4425-adc7-540de1d607e2',
      'es' => '89cf845d-1cef-488c-92ae-7c9f5b9d5f90',
    ];
    $this->changeDefaultLanguage();
    ConfigurableLanguage::load('en')->delete();
    $this->languageManager->reset();
    $this->cdfPruner = $this->newPruneLanguagesFromCdf();
    $event = $this->triggerPruneCdfEntityEvent();
    $new_cdf_object = $event->getCdf()->getCdfEntity($node_uuid);
    $new_cdf_metadata = $new_cdf_object->getMetadata();
    $this->assertEquals($default_language, $new_cdf_metadata['default_language']);
    $this->assertEquals([$default_language], $new_cdf_metadata['languages']);
    $this->assertArrayHasKey($config_languages[$default_language], $new_cdf_object->getDependencies());
    $this->assertTrue($event->getCdf()->hasEntity($config_languages[$default_language]));
    $deleted_languages = array_values(array_diff($old_languages, $new_cdf_metadata['languages']));
    foreach ($deleted_languages as $deleted_language) {
      $this->assertArrayNotHasKey($config_languages[$deleted_language], $new_cdf_object->getDependencies());
      $this->assertFalse($event->getCdf()->hasEntity($config_languages[$deleted_language]));
    }
    $this->assertContains($default_language, $this->registrar->getUndesiredLanguages());
  }

  /**
   * Tests that when there's at least one common language.
   *
   * Common languages are imported and rest are not imported.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPruningWithCommonLanguages(): void {
    // Default language is en for this entity.
    $node_uuid = '93aa4fec-639c-4a7d-9c94-96f7123fccaf';
    $old_cdf_object = $this->cdfDocument->getCdfEntity($node_uuid);
    $old_languages = $old_cdf_object->getMetadata()['languages'];
    $default_language = $old_cdf_object->getMetadata()['default_language'];
    $config_languages = [
      'en' => '05695528-2ab4-4c55-a7d9-1879cd083429',
      'hi' => '24c5283c-d14f-4070-ac1f-ffb58ab0bd19',
      'fr' => 'bf224e71-6c7e-4425-adc7-540de1d607e2',
      'es' => '89cf845d-1cef-488c-92ae-7c9f5b9d5f90',
    ];
    $this->changeDefaultLanguage('hi');
    $this->cdfPruner = $this->newPruneLanguagesFromCdf();
    $event = $this->triggerPruneCdfEntityEvent();
    $new_cdf_object = $event->getCdf()->getCdfEntity($node_uuid);
    $new_cdf_metadata = $new_cdf_object->getMetadata();
    $this->assertEquals($default_language, $new_cdf_metadata['default_language']);
    $this->assertEquals(['en', 'hi'], $new_cdf_metadata['languages']);
    foreach ($new_cdf_metadata['languages'] as $language) {
      $this->assertArrayHasKey($config_languages[$language], $new_cdf_object->getDependencies());
      $this->assertTrue($event->getCdf()->hasEntity($config_languages[$language]));
      $this->assertNotContains($language, $this->registrar->getUndesiredLanguages());
    }
    $deleted_languages = array_values(array_diff($old_languages, $new_cdf_metadata['languages']));
    foreach ($deleted_languages as $deleted_language) {
      $this->assertArrayNotHasKey($config_languages[$deleted_language], $new_cdf_object->getDependencies());
      $this->assertFalse($event->getCdf()->hasEntity($config_languages[$deleted_language]));
    }
  }

  /**
   * Changes default langcode for site.
   *
   * @param string $langcode
   *   Langcode.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function changeDefaultLanguage(string $langcode = 'ro'): void {
    $language = ConfigurableLanguage::createFromLangcode($langcode);
    $language->save();
    // Change default langcode.
    $this->config('system.site')->set('default_langcode', $langcode)->set('langcode', $langcode)->save();
    $this->languageManager->reset();
  }

  /**
   * Triggers PruneCdfEntitiesEvent action.
   *
   * @return \Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent
   *   Returns modified event object.
   */
  protected function triggerPruneCdfEntityEvent(): PruneCdfEntitiesEvent {
    $event = new PruneCdfEntitiesEvent($this->cdfDocument);
    $this->cdfPruner->onPruneCdf($event);
    return $event;
  }

  /**
   * Returns a new PruneLanguagesFromCdf object.
   *
   * @return \Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf
   *   The object.
   */
  protected function newPruneLanguagesFromCdf(): PruneLanguagesFromCdf {
    return new PruneLanguagesFromCdf(
      $this->registrar, $this->translationConfig,
      $this->languageManager, $this->logger,
      $this->entityTranslationManager, $this->ntEntityHandlerContext
    );
  }

}
