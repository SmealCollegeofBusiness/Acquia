<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel;

use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface;
use Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf\TrackTranslations;
use Drupal\acquia_contenthub_translations\TranslationFacilitator;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests translation facilitator.
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\TranslationFacilitator
 */
class TranslationFacilitatorTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc',
    'user',
    'node',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
    'language',
    'filter',
    'system',
  ];

  /**
   * Entity translation manager.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
   */
  private $entityTranslationManager;

  /**
   * Translation facilitator.
   *
   * @var \Drupal\acquia_contenthub_translations\TranslationFacilitator
   */
  private $translationFacilitator;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['language', 'filter']);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('acquia_contenthub_translations',
      [EntityTranslationsTracker::TABLE, EntityTranslations::TABLE]
    );
    $this->entityTranslationManager = $this->container->get('acquia_contenthub_translations.manager');
    $this->translationFacilitator = new TranslationFacilitator($this->entityTranslationManager);
  }

  /**
   * Tests that translation details are tracked.
   *
   * Whenever a translation is being created/updated.
   *
   * @covers ::trackTranslation
   */
  public function testTrackTranslation(): void {
    $hi_language = ConfigurableLanguage::createFromLangcode('hi');
    $hi_language->save();
    \Drupal::languageManager()->reset();
    // Local node creation and translation.
    // This will call trackTranslation through the hook implementation.
    $node1 = $this->createNode();
    $node1->addTranslation('hi', ['title' => 'hi node 1'])->save();
    $node1_tracked_entity = $this->entityTranslationManager->getTrackedEntity($node1->uuid());
    $node1_languages = $node1_tracked_entity->languages();
    foreach ($node1_languages as $operation_flag) {
      $this->assertEquals(EntityTranslationManagerInterface::NO_DELETION | EntityTranslationManagerInterface::NO_UPDATE, $operation_flag);
    }
    // Node creation and translation as part of syndication process.
    $track_translation = new TrackTranslations();
    $track_translation->onParseCdf();
    $node2 = $this->createNode();
    $node2->addTranslation('hi', ['title' => 'hi node 2'])->save();
    $node2_tracked_entity = $this->entityTranslationManager->getTrackedEntity($node2->uuid());
    $node2_languages = $node2_tracked_entity->languages();
    foreach ($node2_languages as $operation_flag) {
      $this->assertEquals(EntityTranslationManagerInterface::NO_ACTION, $operation_flag);
    }
    // Updating the syndicated translation locally.
    $track_translation::$isSyndicating = FALSE;
    $node2_hi = $node2->getTranslation('hi');
    $node2_hi->set('title', 'hi node 2 updated');
    $node2_hi->save();
    $node2_tracked_entity = $this->entityTranslationManager->getTrackedEntity($node2->uuid());
    $this->assertEquals(EntityTranslationManagerInterface::NO_UPDATE, $node2_tracked_entity->languages()['hi']);
  }

}
