<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel;

use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EntityTranslationsManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub_translations\Traits\EntityTranslationDbAssertions;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\EntityTranslationsManager
 *
 * @group acquia_contenthub_translations
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class EntityTranslationsManagerTest extends KernelTestBase {

  use EntityTranslationDbAssertions;

  /**
   * Sut object.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationsManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_translations',
    'acquia_contenthub_subscriber',
    'depcalc',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('acquia_contenthub_translations', [
      EntityTranslations::TABLE, EntityTranslationsTracker::TABLE,
    ]);

    $this->manager = new EntityTranslationsManager(
      $this->container->get('acquia_contenthub_translations.entity'),
      $this->container->get('acquia_contenthub_translations.tracking'),
      $this->container->get('database'),
    );
  }

  /**
   * @covers ::trackEntity
   */
  public function testTrackEntity(): void {
    $values = $this->insertTrackRecord();
    $this->assertTrackerRows(['some_uuid' => [$values]], 1);
  }

  /**
   * @covers ::getTrackedEntity
   */
  public function testGetTrackedEntity(): void {
    $this->insertTrackRecord();

    $entity = $this->manager->getTrackedEntity('some_uuid');
    $this->assertEquals('some_uuid', $entity->uuid());
    $this->assertEquals('some_type', $entity->type());
    $this->assertEquals('en', $entity->originalDefaultLanguage());
    $this->assertEquals('de', $entity->defaultLanguage());
  }

  /**
   * @covers ::trackTranslation
   */
  public function testTrackTranslation(): void {
    $this->insertTrackRecord();

    $values = [
      'entity_uuid' => 'some_uuid',
      'langcode' => 'en',
      'operation_flag' => 1,
    ];
    $this->manager->trackTranslation(...array_values($values));
    $this->assertTranslationsRows(['some_uuid' => [$values]], 1);
  }

  /**
   * @covers ::removeTranslation
   */
  public function testRemoveTranslation(): void {
    $this->insertTrackRecord();

    $values = [
      'entity_uuid' => 'some_uuid',
      'langcode' => 'en',
      'operation_flag' => 1,
    ];
    $this->manager->trackTranslation(...array_values($values));
    $this->assertTranslationsRows(['some_uuid' => [$values]], 1);

    $this->manager->removeTranslation('some_uuid', 'en');
    $this->assertTranslationsRows([], 0);
  }

  /**
   * @covers ::deleteTrackedEntity
   */
  public function testDeleteTrackedEntity(): void {
    $this->insertTrackRecord();
    $values1 = [
      'entity_uuid' => 'some_uuid',
      'langcode' => 'lang1',
      'operation_flag' => 2,
    ];
    $this->manager->trackTranslation(...array_values($values1));

    $values2 = [
      'entity_uuid' => 'some_uuid',
      'langcode' => 'lang2',
      'operation_flag' => 3,
    ];
    $this->manager->trackTranslation(...array_values($values2));

    $this->assertTranslationsRows(['some_uuid' => [$values1, $values2]], 2);

    $this->manager->deleteTrackedEntity('some_uuid');
    $this->assertTranslationsRows([], 0);
    $this->assertTrackerRows([], 0);
  }

  /**
   * @covers ::updateTrackedEntity
   */
  public function testUpdateTrackedEntity(): void {
    $this->insertTrackRecord();

    $values1 = [
      'entity_uuid' => 'some_uuid',
      'langcode' => 'lang1',
      'operation_flag' => 2,
    ];
    $this->manager->trackTranslation(...array_values($values1));
    $entity = $this->manager->getTrackedEntity('some_uuid');
    $entity->setDefaultLanguage('sw');

    $this->manager->updateTrackedEntity($entity);
    $entity = $this->manager->getTrackedEntity('some_uuid');
    $this->assertEquals('sw', $entity->defaultLanguage());
  }

  /**
   * Inserts values into the tracker table.
   *
   * @return string[]
   *   Array of values inserted.
   */
  protected function insertTrackRecord(): array {
    $values = [
      'entity_uuid' => 'some_uuid',
      'entity_type' => 'some_type',
      'original_default_language' => 'en',
      'default_language' => 'de',
    ];

    $this->manager->trackEntity(...array_values($values));
    return $values;
  }

}
