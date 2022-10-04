<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel;

use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\Data\TrackedEntity;
use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface;
use Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf\TrackTranslations;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Prophecy\Argument;

/**
 * Tests entity with missing uuid for pruning handlers.
 *
 * @requires module depcalc
 *
 * @group acquia_contenthub_translations
 *
 * @package acquia_contenthub_translations
 */
class MissingUuidEntityImportTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * Invalid entity with null uuid.
   *
   * @var \Drupal\Core\Entity\TranslatableInterface|object
   */
  protected $invalidEntity;

  /**
   * Valid node entity.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $validEntity;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_translations',
    'acquia_contenthub_subscriber',
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
   * Update handler.
   *
   * @var \Drupal\acquia_contenthub_translations\OperationHandler\TranslationUpdateHandler
   */
  protected $updateHandler;

  /**
   * Deletion handler.
   *
   * @var \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler
   */
  protected $deletionHandler;

  /**
   * Translation facilitator.
   *
   * @var \Drupal\acquia_contenthub_translations\TranslationFacilitator
   */
  protected $translationFacilitator;

  /**
   * Entity translation manager.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityTranslationsManager;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->entityTranslationsManager = $this->prophesize(EntityTranslationManagerInterface::class);
    $this->container->set('acquia_contenthub_translations.manager', $this->entityTranslationsManager->reveal());
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
    ConfigurableLanguage::createFromLangcode('hi')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    \Drupal::languageManager()->reset();
    $this->invalidEntity = $this->prophesize(TranslatableInterface::class)->reveal();
    $this->updateHandler = $this->container->get('acquia_contenthub_translations.handler.update');
    $this->deletionHandler = $this->container->get('acquia_contenthub_translations.handler.deletion');
    $this->translationFacilitator = $this->container->get('acquia_contenthub_translations.translation_facilitator');
    $this->validEntity = $this->createNode();
  }

  /**
   * Tests that valid entity with multiple translations is handled properly.
   *
   * @covers \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler::pruneTranslations
   * @covers \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler::deleteTranslation
   * @covers \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler::deleteTrackedEntity
   * @covers \Drupal\acquia_contenthub_translations\OperationHandler\TranslationUpdateHandler::updateTrackedEntity
   * @covers \Drupal\acquia_contenthub_translations\TranslationFacilitator::trackTranslation
   *
   * @throws \Drupal\acquia_contenthub_translations\Exceptions\InvalidAttributeException
   */
  public function testHandlersWithMultipleTranslationsValidEntity(): void {
    $tracked_entity = new TrackedEntity($this->entityTranslationsManager->reveal(), [
      'uuid' => $this->validEntity->uuid(),
      'type' => $this->validEntity->getEntityTypeId(),
      'original_default_language' => $this->validEntity->language()->getId(),
      'default_language' => $this->validEntity->language()->getId(),
      'created' => time(),
      'changed' => time(),
    ]);
    $this->assertEmpty($tracked_entity->languages());
    $this->entityTranslationsManager->getTrackedEntity(
      $this->validEntity->uuid()
    )->shouldBeCalledTimes(6)->willReturn(
      // While tracking with Translation facilitator.
      NULL,
      $tracked_entity,
      $tracked_entity,
      $tracked_entity,
      $tracked_entity,
      $tracked_entity
    );
    $this->entityTranslationsManager
      ->trackEntity(
        $this->validEntity->uuid(),
        $this->validEntity->getEntityTypeId(),
        $this->validEntity->language()->getId(),
        $this->validEntity->language()->getId()
      )
      ->shouldBeCalledTimes(1)
      ->willReturn($tracked_entity);
    $this->entityTranslationsManager
      ->updateTrackedEntity($tracked_entity)
      ->shouldBeCalledTimes(4);
    $this->entityTranslationsManager
      ->deleteTrackedEntity($tracked_entity->uuid())
      ->shouldBeCalledTimes(1);
    $this->validEntity->addTranslation('hi', ['title' => 'hi node translation']);
    $this->translationFacilitator->trackTranslation($this->validEntity, 'create');
    $this->assertNotEmpty($tracked_entity->languages());
    $this->assertEquals(['hi' => EntityTranslationManagerInterface::NO_DELETION | EntityTranslationManagerInterface::NO_UPDATE], $tracked_entity->languages());
    $this->updateHandler->updateTrackedEntity($this->validEntity);
    $this->deletionHandler->deleteTrackedEntity($this->validEntity);
    $this->deletionHandler->deleteTranslation($this->validEntity, 'hi');
    $this->assertEmpty($tracked_entity->languages());
    TrackTranslations::$isSyndicating = TRUE;
    $this->validEntity->addTranslation('es', ['title' => 'es node translation']);
    $this->translationFacilitator->trackTranslation($this->validEntity, 'create');
    $this->deletionHandler->pruneTranslations($this->validEntity, ['en']);
    $this->assertEquals(['en'], array_keys($this->validEntity->getTranslationLanguages()));
    $this->assertEmpty($tracked_entity->languages());
  }

  /**
   * Tests handlers with invalid entity.
   *
   * @covers \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler::pruneTranslations
   * @covers \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler::deleteTranslation
   * @covers \Drupal\acquia_contenthub_translations\OperationHandler\TranslationDeletionHandler::deleteTrackedEntity
   * @covers \Drupal\acquia_contenthub_translations\OperationHandler\TranslationUpdateHandler::updateTrackedEntity
   * @covers \Drupal\acquia_contenthub_translations\TranslationFacilitator::trackTranslation
   */
  public function testHandlersWithInvalidEntity(): void {
    $this->entityTranslationsManager->getTrackedEntity(Argument::type('string'))->shouldNotBeCalled();
    $this->updateHandler->updateTrackedEntity($this->invalidEntity);
    $this->deletionHandler->deleteTrackedEntity($this->invalidEntity);
    $this->deletionHandler->deleteTranslation($this->invalidEntity, 'en');
    $this->deletionHandler->pruneTranslations($this->invalidEntity, ['en']);
    $this->translationFacilitator->trackTranslation($this->invalidEntity, 'create');
    /** @var \Drupal\acquia_contenthub_translations\Data\EntityTranslations $entity_tracking */
    $entity_tracking = $this->container->get('acquia_contenthub_translations.entity');
    $this->assertEmpty($entity_tracking->getAll());
    /** @var \Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker $entity_translations_tracking */
    $entity_translations_tracking = $this->container->get('acquia_contenthub_translations.tracking');
    $this->assertEmpty($entity_translations_tracking->getAll());
  }

}
