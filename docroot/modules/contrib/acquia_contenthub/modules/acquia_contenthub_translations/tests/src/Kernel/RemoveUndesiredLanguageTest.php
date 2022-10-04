<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel;

use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;

/**
 * Tests removal of configurable language effect on undesired languages.
 *
 * @group acquia_contenthub_translations
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class RemoveUndesiredLanguageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_translations',
    'acquia_contenthub_subscriber',
    'language',
    'user',
  ];

  /**
   * Undesired language registry interface.
   *
   * @var \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface
   */
  protected $undesiredLanguageRegistrar;

  /**
   * Logger mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $logger;

  /**
   * {@inheritDoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();
    $this->logger = new LoggerMock();
    $logger_factory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $this->installSchema('acquia_contenthub_subscriber', SubscriberTracker::IMPORT_TRACKING_TABLE);
    $logger_factory->get('acquia_contenthub_translations')->willReturn($this->logger);
    $logger_factory->get('acquia_contenthub')->willReturn(new LoggerMock());
    $this->container->set('logger.factory', $logger_factory->reveal());
    $this->installEntitySchema('configurable_language');
    $language = ConfigurableLanguage::createFromLangcode('hi');
    $language->save();
    \Drupal::languageManager()->reset();
    $this->undesiredLanguageRegistrar = $this->container->get('acquia_contenthub_translations.undesired_language_registrar');
    $this->undesiredLanguageRegistrar->markLanguagesUndesired($language->id());
  }

  /**
   * Tests that language is removed from undesired language list.
   *
   * If configurable language is deleted.
   *
   * @covers ::acquia_contenthub_translations_configurable_language_delete
   */
  public function testLanguageRemovedFromUndesiredLanguages(): void {
    $language = ConfigurableLanguage::load('hi');
    $this->assertTrue($this->undesiredLanguageRegistrar->isLanguageUndesired($language->id()));
    $language->delete();
    $this->assertFalse($this->undesiredLanguageRegistrar->isLanguageUndesired($language->id()));
    $this->assertContains(
      sprintf('Language(s) (%s) have been removed from undesired languages.', $language->id()),
      $this->logger->getInfoMessages()
    );
  }

  /**
   * Tests that language not marked as undesired will have no affect.
   *
   * @covers ::acquia_contenthub_translations_configurable_language_delete
   */
  public function testRemovalOfNotUndesiredLanguage(): void {
    $new_language = ConfigurableLanguage::createFromLangcode('es');
    $new_language->save();
    $old_undesired_languages = $this->undesiredLanguageRegistrar->getUndesiredLanguages();
    $new_language->delete();
    $new_undesired_languages = $this->undesiredLanguageRegistrar->getUndesiredLanguages();
    $this->assertEquals($old_undesired_languages, $new_undesired_languages);
    $this->assertNotContains(
      sprintf('Language(s) (%s) have been removed from undesired languages.', $new_language->id()),
      $this->logger->getInfoMessages()
    );
  }

}
