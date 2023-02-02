<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\EventSubscriber\PruneCdf;

use Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf;
use Drupal\acquia_contenthub_translations\Exceptions\TranslationDataException;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentCreatorTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\acquia_contenthub_subscriber\Kernel\Mock\CdfImporterMock;
use Drupal\Tests\acquia_contenthub_translations\Traits\EntityTranslationDbAssertions;

/**
 * Tests that entity's default language is properly transformed.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf
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
   * CdfImporter mock.
   *
   * @var \Drupal\Tests\acquia_contenthub_subscriber\Kernel\Mock\CdfImporterMock
   */
  protected $cdfImporter;

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
    1 => 'node/node-prune-cdf-languages-with-missing-translatable-attributes.json',
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
    $event = $this->triggerPruneCdfEntityEvent();
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
    $this->assertEquals('"en" will be marked as undesired. This language will also be imported.', $info_messages[0]);
    $this->assertEquals('Incoming languages of 89728d01-c1aa-49d2-808c-67d4f98de98e having entity type "node_type": en,hi,fr,es', $info_messages[1]);
    $this->assertEquals('Languages marked as undesired: (en). These languages will also be imported.', $info_messages[2]);
    $this->assertEquals('Cdf document has been pruned w.r.t languages enabled on subscriber.', $info_messages[3]);
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
   * @covers ::isValidCdf
   * @covers ::requestRepublishForInvalidCdfs
   */
  public function testLanguagePruningWhenTranslatableAttributeIsMissing(): void {
    $this->cdfDocument = $this->createCdfDocumentFromFixtureFile($this->fixtures[1], 'acquia_contenthub_translations');

    $this->setLanguages(
      'en',
      ['en' => 'English'],
    );

    try {
      $this->triggerPruneCdfEntityEvent();
    }
    catch (\Exception $e) {
      $this->assertTrue($e instanceof TranslationDataException);
      $this->assertEquals('CDF validation failed due to missing "translatable" attribute.', $e->getMessage());
    }
    // Based on the parsed fixture the following entities are not processed.
    $expected = [
      '6940b1cf-fc00-41ff-656b-85af62ba0d93' => [
        [
          'uuid' => '93aa4fec-639c-4a7d-9c94-96f7123fccaf',
          'type' => 'node',
          'dependencies' => [
            '89728d01-c1aa-49d2-808c-67d4f98de98e',
            '04f771a8-0e6c-4f6f-b106-63f83a58cad0',
            '07650f47-6306-4675-958e-56a1205d3f06',
            'dcc0d17b-4424-4eca-b370-91cdfcaddee8',
            'e790d888-7136-4660-a0a4-37efc6482426',
            'c23609cb-1ef2-40b1-a49b-260cf98329cd',
            '835a3627-b65c-4d97-b57c-4a6f737f11cf',
            '9732f742-3ef9-4682-919a-cc13f8f211d0',
            '4110ccca-df5e-4324-a729-6f230a61a745',
            'ceb9d136-d3b2-4600-ad47-d372492d53ef',
            '37cc9c9c-774e-4f18-a689-502882e02100',
            'df5be0ce-22a7-4f1a-8ee2-c01cee2671a1',
            '9abb0e68-75d6-4dff-b363-e7c19cc17d26',
            'b65678a7-2b47-4fca-a180-3af55b5991d1',
            '7f6d8ef2-b0e9-40aa-a2e1-c8506881163b',
            '05695528-2ab4-4c55-a7d9-1879cd083429',
            '24c5283c-d14f-4070-ac1f-ffb58ab0bd19',
            'bf224e71-6c7e-4425-adc7-540de1d607e2',
            '89cf845d-1cef-488c-92ae-7c9f5b9d5f90',
          ],
        ],
        [
          'uuid' => '04f771a8-0e6c-4f6f-b106-63f83a58cad0',
          'type' => 'user',
          'dependencies' => [
            '07650f47-6306-4675-958e-56a1205d3f06',
            'dcc0d17b-4424-4eca-b370-91cdfcaddee8',
            'e790d888-7136-4660-a0a4-37efc6482426',
            'c23609cb-1ef2-40b1-a49b-260cf98329cd',
            '835a3627-b65c-4d97-b57c-4a6f737f11cf',
            '9732f742-3ef9-4682-919a-cc13f8f211d0',
            '4110ccca-df5e-4324-a729-6f230a61a745',
            'ceb9d136-d3b2-4600-ad47-d372492d53ef',
          ],
        ],
      ],
    ];

    $this->assertSame($expected, $this->cdfImporter->entitiesToRepublish);
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
    $this->cdfImporter = new CdfImporterMock();

    $this->pruneLanguagesFromCdfInstance = new PruneLanguagesFromCdf(
      $this->registrar, $this->translationConfig,
      $this->languageManager->reveal(), $this->logger,
      $this->entityTranslationManager,
      $this->container->get('acquia_contenthub_translations.nt_entity_handler.context'),
      $this->cdfImporter
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
