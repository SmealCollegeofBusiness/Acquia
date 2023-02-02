<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\EventSubscriber\PruneCdf;

use Acquia\ContentHubClient\CDFDocument;
use Drupal\acquia_contenthub\Event\EntityDataTamperEvent;
use Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent;
use Drupal\acquia_contenthub_subscriber\CdfImporterInterface;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EventSubscriber\EntityDataTamper\NormalizeFieldValues;
use Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf;
use Drupal\acquia_contenthub_translations\Form\ContentHubTranslationsSettingsForm;
use Drupal\depcalc\DependencyStack;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\acquia_contenthub_translations\Traits\CdfGeneratorForTranslationsTrait;
use Prophecy\Argument;

/**
 * Tests processing of non-translatable entities.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf
 *
 * @requires module depcalc
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class PruneCdfLanguagesWithNonTranslatableEntitiesTest extends KernelTestBase {

  use CdfGeneratorForTranslationsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
    'depcalc',
    'language',
    'system',
    'user',
  ];

  /**
   * Cdf pruner.
   *
   * @var \Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf
   */
  protected $cdfPruner;

  /**
   * Entity translation manager.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
   */
  protected $entityTranslationManager;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('configurable_language');

    $this->installSchema('acquia_contenthub_translations',
      [EntityTranslationsTracker::TABLE, EntityTranslations::TABLE]
    );

    $this->entityTranslationManager = $this->container->get('acquia_contenthub_translations.manager');
    $this->cdfPruner = $this->newPruneLanguagesFromCdf();
  }

  /**
   * Tests if removable entities such as redirect and path_alias are removed.
   */
  public function testLanguagePruningWithRemovableLanguages(): void {
    ConfigurableLanguage::createFromLangcode('hu')->save();

    $cdf1 = $this->generateCdfObject([
      'type' => 'drupal8_config_entity',
      'metadata' => [
        'default_language' => 'hu',
        'langcode' => 'hu',
      ],
      'attributes' => [
        'entity_type' => [
          'value' => ['und' => 'configurable_language'],
        ],
      ],
    ]);
    $cdf2 = $this->generateCdfObject();
    $cdf3 = $this->generateCdfObject([
      'attributes' => [
        'entity_type' => [
          'value' => ['und' => 'redirect'],
        ],
      ],
    ]);
    $cdf4 = $this->generateCdfObject([
      'metadata' => [
        'languages' => ['en', 'hu'],
        'translatable' => TRUE,
      ],
      'attributes' => [
        'entity_type' => [
          'value' => ['und' => 'node'],
        ],
      ],
    ], [$cdf1->getUuid(), $cdf2->getUuid(), $cdf3->getUuid()]);

    $cdf_document = new CDFDocument($cdf1, $cdf2, $cdf3, $cdf4);
    $remaining = [
      $cdf1->getUuid() => $cdf1,
      $cdf4->getUuid() => $cdf4,
    ];
    $event = $this->triggerPruneCdfEntityEvent($cdf_document);
    $document = $event->getCdf();

    $this->assertCount(2, $document->getEntities(),
      'Subscriber has the hu language enabled, does not get removed.'
    );
    $this->assertSame($remaining, $document->getEntities());
  }

  /**
   * Tests language pruning with file entity as a flexible entity.
   *
   * @covers \Drupal\acquia_contenthub_translations\EventSubscriber\EntityDataTamper\NormalizeFieldValues::onDataTamper()
   */
  public function testLanguageFlexiblePruning(): void {
    $cdf = $this->generateCdfObject([
      'attributes' => [
        'entity_type' => [
          'value' => ['und' => 'file'],
        ],
      ],
      'metadata' => [
        'data' => base64_encode(json_encode(
          [
            'file_uri' => [
              'value' => [
                'de' => 'some_uri',
              ],
            ],
          ]
        )),
      ],
    ]);

    $cdf1 = $this->generateCdfObject([
      'type' => 'drupal8_config_entity',
      'metadata' => [
        'default_language' => 'de',
        'langcode' => 'de',
      ],
      'attributes' => [
        'entity_type' => [
          'value' => ['und' => 'configurable_language'],
        ],
      ],
    ]);

    $cdf2 = $this->generateCdfObject([
      'metadata' => [
        'languages' => ['en', 'de'],
        'translatable' => TRUE,
      ],
      'attributes' => [
        'entity_type' => [
          'value' => ['und' => 'node'],
        ],
      ],
    ], [$cdf->getUuid(), $cdf1->getUuid()]);

    $cdf_document = new CDFDocument($cdf, $cdf1, $cdf2);
    $event = $this->triggerPruneCdfEntityEvent($cdf_document);
    $document = $event->getCdf();

    $cdf = $document->getCdfEntity($cdf->getUuid());
    $this->assertEquals('en', $cdf->getMetadata()['default_language'],
      'File is language flexible, changed from de to en.'
    );

    $event = new EntityDataTamperEvent($document, new DependencyStack());
    $normalizer = new NormalizeFieldValues($this->entityTranslationManager);
    $normalizer->onDataTamper($event);
    $document = $event->getCdf();
    $cdf = $document->getCdfEntity($cdf->getUuid());

    $expected_metadata = [
      'file_uri' => [
        'value' => [
          'en' => 'some_uri',
        ],
      ],
    ];
    $this->assertSame(
      $expected_metadata,
      json_decode(base64_decode($cdf->getMetadata()['data']), TRUE),
      'NormalizeFieldValues updates file values with correct langcodes.'
    );
  }

  /**
   * Triggers PruneCdfEntitiesEvent action.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf_document
   *   The CDF document to pass to the event.
   *
   * @return \Drupal\acquia_contenthub\Event\PruneCdfEntitiesEvent
   *   Returns modified event object.
   *
   * @throws \Exception
   */
  protected function triggerPruneCdfEntityEvent(CDFDocument $cdf_document): PruneCdfEntitiesEvent {
    $event = new PruneCdfEntitiesEvent($cdf_document);
    $this->cdfPruner->onPruneCdf($event);
    return $event;
  }

  /**
   * Returns a new PruneLanguagesFromCdf object.
   *
   * @return \Drupal\acquia_contenthub_translations\EventSubscriber\PruneCdf\PruneLanguagesFromCdf
   *   The object.
   *
   * @throws \Exception
   */
  protected function newPruneLanguagesFromCdf(): PruneLanguagesFromCdf {
    $config = $this->config(ContentHubTranslationsSettingsForm::CONFIG);
    $config->set('selective_language_import', TRUE)
      ->save();
    $cdf_importer = $this->prophesize(CdfImporterInterface::class);
    $cdf_importer->requestToRepublishEntities(Argument::type('array'));

    return new PruneLanguagesFromCdf(
      $this->container->get('acquia_contenthub_translations.undesired_language_registrar'),
      $config,
      $this->container->get('language_manager'),
      new LoggerMock(),
      $this->entityTranslationManager,
      $this->container->get('acquia_contenthub_translations.nt_entity_handler.context'),
      $cdf_importer->reveal()
    );
  }

}
