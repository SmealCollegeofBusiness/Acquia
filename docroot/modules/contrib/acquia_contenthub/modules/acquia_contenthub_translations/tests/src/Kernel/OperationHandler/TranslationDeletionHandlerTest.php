<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\OperationHandler;

use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface as ETMInterface;
use Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf\TrackTranslations;
use Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\acquia_contenthub_translations\Traits\EntityTranslationDbAssertions;
use Drupal\Tests\acquia_contenthub_translations\Traits\TranslationCreatorTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler
 *
 * @group acuqia_contenthub_translations
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class TranslationDeletionHandlerTest extends KernelTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use EntityTranslationDbAssertions;
  use TranslationCreatorTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
    'content_translation',
    'depcalc',
    'field',
    'filter',
    'language',
    'node',
    'text',
    'user',
    'system',
  ];

  /**
   * The SUT.
   *
   * @var \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler
   */
  protected $deletionHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('configurable_language');
    $this->installConfig([
      'field',
      'filter',
      'node',
      'language',
    ]);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('acquia_contenthub_translations', [
      EntityTranslations::TABLE,
      EntityTranslationsTracker::TABLE,
    ]);

    $this->logger = new LoggerMock();
    $this->deletionHandler = new TranslationDeletionHandler(
      $this->container->get('acquia_contenthub_translations.manager'),
      $this->prophesize(LoggerChannelInterface::class)->reveal()
    );
    ConfigurableLanguage::createFromLangcode('hu')->save();
    $this->createContentType([
      'type' => 'test',
      'name' => 'Test',
    ])->save();
  }

  /**
   * Tests translation pruning.
   *
   * @covers ::deleteTranslation
   */
  public function testDeleteTranslation(): void {
    $node = $this->initNode();

    $this->deletionHandler->deleteTranslation($node, 'hu');
    $lang = $node->language()->getId();
    $this->assertTrackerRows([
      $node->uuid() => [
        [
          'entity_uuid' => $node->uuid(),
          'entity_type' => $node->getEntityTypeId(),
          'original_default_language' => $lang,
          'default_language' => $lang,
        ],
      ],
    ], 1);
    $this->assertTranslationsRows([], 0);
  }

  /**
   * @covers ::deleteTrackedEntity
   */
  public function testDeleteTrackedEntity(): void {
    $node = $this->initNode();

    $this->deletionHandler->deleteTrackedEntity($node);
    $this->assertTrackerRows([], 0);
    $this->assertTranslationsRows([], 0);
  }

  /**
   * @covers ::pruneTranslations
   */
  public function testPruneTranslations(): void {
    ConfigurableLanguage::createFromLangcode('es')->save();
    ConfigurableLanguage::createFromLangcode('sw')->save();
    $node = $this->initNode();

    // Translation gets deleted on prune if its operation is set to NO_ACTION.
    $this->deletionHandler->pruneTranslations($node, ['en']);
    $node->save();
    $this->assertFalse($node->hasTranslation('hu'));
    $this->assertTranslationsRows([], 0);
    TrackTranslations::$isSyndicating = FALSE;

    $this->addTranslation($node, 'es', 'hu');
    $tracked_entity = $this->container->get('acquia_contenthub_translations.manager')->getTrackedEntity($node->uuid());
    // Translation doesn't get removed if operation is set NO_DELETE|NO_UPDATE.
    $tracked_entity->addLanguages(
      [
        'es' => ETMInterface::NO_ACTION,
        'hu' => ETMInterface::NO_DELETION | ETMInterface::NO_UPDATE,
      ]
    );
    $tracked_entity->save();

    $this->deletionHandler->pruneTranslations($node, ['en']);
    $node->save();
    $this->assertFalse($node->hasTranslation('es'));
    $this->assertTranslationsRows([
      $node->uuid() => [
        [
          'entity_uuid' => $node->uuid(),
          'langcode' => 'hu',
          'operation_flag' => ETMInterface::NO_DELETION | ETMInterface::NO_UPDATE,
        ],
      ],
    ], 1);

    // One of the translation is not updatable nor deletable, therefore
    // cannot be deleted. NO_UPDATE flag alone would still make it deletable.
    $this->addTranslation($node, 'es', 'sw');
    // Need to get this again from manager to get fresh tracked entity.
    $tracked_entity = $this->container->get('acquia_contenthub_translations.manager')->getTrackedEntity($node->uuid());
    $tracked_entity->addLanguages(
      [
        'es' => ETMInterface::NO_DELETION | ETMInterface::NO_UPDATE,
        'sw' => ETMInterface::NO_UPDATE,
      ]
    );
    $tracked_entity->save();

    $this->deletionHandler->pruneTranslations($node, ['en']);
    $node->save();
    $this->assertFalse($node->hasTranslation('sw'));
    $this->assertTranslationsRows([
      $node->uuid() => [
        [
          'entity_uuid' => $node->uuid(),
          'langcode' => 'hu',
          'operation_flag' => ETMInterface::NO_DELETION | ETMInterface::NO_UPDATE,
        ],
        [
          'entity_uuid' => $node->uuid(),
          'langcode' => 'es',
          'operation_flag' => ETMInterface::NO_UPDATE | ETMInterface::NO_DELETION,
        ],
      ],
    ], 2);
  }

  /**
   * Initializes a test node.
   *
   * @return \Drupal\node\NodeInterface
   *   The test node.
   *
   * @throws \Exception
   */
  protected function initNode(): NodeInterface {
    TrackTranslations::$isSyndicating = TRUE;
    $node = $this->createNode([
      'type' => 'test',
      'title' => 'Test content',
    ]);
    $this->addTranslation($node, 'hu');

    return $node;
  }

}
