<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\EventSubscriber\PruneCdf;

use Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentCreatorTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\acquia_contenthub_translations\Traits\EntityTranslationDbAssertions;

/**
 * Tests that entity's default language is properly transformed.
 *
 * @group acquia_contenthub_translations
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class PruneLanguagesFromCdfTest extends EntityKernelTestBase {

  use CdfDocumentCreatorTrait;
  use EntityTranslationDbAssertions;

  /**
   * The CDF object.
   *
   * @var \Acquia\ContentHubClient\CDFDocument
   */
  protected $cdfDocument;

  /**
   * The PruneLanguagesFromCdf instance.
   *
   * @var \Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf
   */
  protected $pruneLanguagesFromCdfInstance;

  /**
   * The undesired language registrar.
   *
   * @var \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface
   */
  protected $registrar;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * An object containing the information for a language.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected $language;

  /**
   * The acquia_contenthub_translations settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $translationConfig;

  /**
   * Acquia content hub translations logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Entity translation manager.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
   */
  protected $entityTranslationManager;

  /**
   * Node uuid.
   */
  protected const NODE_UUID = '93aa4fec-639c-4a7d-9c94-96f7123fccaf';

  /**
   * Fixtures for the test.
   *
   * @var array
   */
  protected $fixtures = [
    0 => 'node/node-prune_cdf_languages.json',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
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

    $this->installConfig(['language']);
    $this->installSchema('acquia_contenthub_translations',
      [EntityTranslationsTracker::TABLE, EntityTranslations::TABLE]
    );
    $this->translationConfig = $this->config('acquia_contenthub_translations.settings');
    $this->registrar = $this->container->get('acquia_contenthub_translations.undesired_language_registrar');
    $this->entityTranslationManager = $this->container->get('acquia_contenthub_translations.manager');
    $this->languageManager = $this->prophesize(LanguageManagerInterface::class);
    $this->language = $this->prophesize(LanguageInterface::class);

    $this->logger = new LoggerMock();
    $this->cdfDocument = $this->createCdfDocumentFromFixtureFile($this->fixtures[0], 'acquia_contenthub_translations');
  }

  /**
   * Tests when selective language import is disabled.
   */
  public function testDisabledLanguageImport(): void {
    $this->setLanguages(
      'en',
      ['en' => 'English'],
      FALSE
    );
    $event = $this->triggerPruneCdfEntityEvent($this->cdfDocument);
    $this->assertSame($this->cdfDocument, $event->getCdf());
  }

  /**
   * Tests updating of default language.
   */
  public function testUpdatingDefaultLanguage(): void {
    $this->setLanguages(
      'hi',
      ['hi' => 'Hindi']
    );
    $this->triggerPruneCdfEntityEvent();
    $this->config('acquia_contenthub_translations.settings')->get('undesired_languages');
    $this->assertSame(['en'], $this->config('acquia_contenthub_translations.settings')->get('undesired_languages'));
    $info_messages = $this->logger->getInfoMessages();
    $this->assertEquals('Languages marked as undesired: (en). These languages will also be imported.', $info_messages[0]);
    $this->assertEquals('Cdf document has been pruned w.r.t languages enabled on subscriber.', $info_messages[1]);
    $user_uuid = '04f771a8-0e6c-4f6f-b106-63f83a58cad0';
    $node_expectation = [
      'entity_uuid' => self::NODE_UUID,
      'entity_type' => 'node',
      'original_default_language' => 'en',
      'default_language' => 'hi',
    ];
    // Added user entity's expectation
    // because it's translatable and has no common language.
    $user_expectation = [
      'entity_uuid' => $user_uuid,
      'entity_type' => 'user',
      'original_default_language' => 'en',
      'default_language' => 'en',
    ];
    $this->assertTrackerRows(
      [
        self::NODE_UUID => [$node_expectation],
        $user_uuid => [$user_expectation],
      ], 2);
  }

  /**
   * Enables required languages.
   *
   * @param string $default_language
   *   The default language code.
   * @param array $enabled_languages
   *   Key-value pair of enabled languages on subscriber.
   * @param bool $selective_language_import
   *   Enables selective language import.
   *
   * @throws \Exception
   */
  protected function setLanguages(string $default_language, array $enabled_languages, bool $selective_language_import = TRUE): void {
    $language_default = ConfigurableLanguage::createFromLangcode($default_language);
    $this->languageManager->getDefaultLanguage()->willReturn($language_default);
    $this->languageManager->getLanguages(LanguageInterface::STATE_ALL)->willReturn($enabled_languages);
    $this->translationConfig->set('selective_language_import', $selective_language_import)->save();
    $this->pruneLanguagesFromCdfInstance = new PruneLanguagesFromCdf(
      $this->registrar, $this->translationConfig,
      $this->languageManager->reveal(), $this->logger,
      $this->entityTranslationManager,
      $this->container->get('acquia_contenthub_translations.nt_entity_handler.context')
    );
  }

  /**
   * Triggers PruneCdfEntitiesEvent action.
   *
   * @return \Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent
   *   Returns modified event object.
   */
  protected function triggerPruneCdfEntityEvent(): PruneCdfEntitiesEvent {
    $event = new PruneCdfEntitiesEvent($this->cdfDocument);
    $this->pruneLanguagesFromCdfInstance->onPruneCdf($event);
    return $event;
  }

}
