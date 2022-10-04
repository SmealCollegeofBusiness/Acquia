<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\Data;

use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\Exceptions\TranslationDataException;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker
 *
 * @group acquia_contenthub_translations
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class EntityTranslationsTrackerTest extends EntityTranslationsDataAccessTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('acquia_contenthub_translations', EntityTranslationsTracker::TABLE);

    $this->sut = new EntityTranslationsTracker(
      $this->container->get('database')
    );
  }

  /**
   * @covers ::insert
   */
  public function testInsertWithValidData(): void {
    [$uuid, $test] = $this->insertDummyData([
      'entity_type' => 'node',
      'original_default_language' => 'en',
      'default_language' => 'de',
    ]);
    $this->assertTrackerRows([$uuid => [$test]], 1);
  }

  /**
   * Tests input with an extra attribute.
   *
   * The attribute will not get inserted to db, nor it throws error. It is
   * omitted.
   *
   * @covers ::insert
   */
  public function testInsertWithPartiallyValidData(): void {
    [$uuid, $test] = $this->insertDummyData([
      'extra_attribute' => 'with_dummy_value',
      'entity_type' => 'node',
      'original_default_language' => 'en',
      'default_language' => 'de',
    ]);
    unset($test['extra_attribute']);
    $this->assertTrackerRows([$uuid => [$test]], 1);
  }

  /**
   * Tests input with multiple data.
   *
   * @covers ::insert
   */
  public function testInsertWithMultipleData(): void {
    $uuid1 = $this->generateUuid();
    $uuid2 = $this->generateUuid();
    $test = [
      [
        'entity_uuid' => $uuid1,
        'entity_type' => 'type',
        'original_default_language' => 'en',
        'default_language' => 'hu',
      ],
      [
        'entity_uuid' => $uuid2,
        'entity_type' => 'type',
        'original_default_language' => 'en',
        'default_language' => 'hu',
      ],
    ];
    $this->sut->insert($test);

    $this->assertTrackerRows([
      $uuid1 => [$test[0]],
      $uuid2 => [$test[1]],
    ], 2);
  }

  /**
   * @covers ::deleteByUuid
   */
  public function testDeleteByUuid(): void {
    [$uuid, $test] = $this->insertDummyData([
      'entity_type' => 'node',
      'original_default_language' => 'en',
      'default_language' => 'de',
    ]);
    $this->assertTrackerRows([$uuid => [$test]], 1);

    $this->sut->deleteByUuid($uuid);
    $this->assertTrackerRows([], 0);
  }

  /**
   * @covers ::deleteBy
   */
  public function testDeleteByField(): void {
    [$uuid, $test] = $this->insertDummyData([
      'entity_type' => 'node',
      'original_default_language' => 'en',
      'default_language' => 'de',
    ]);
    [$uuid2, $test2] = $this->insertDummyData([
      'entity_type' => 'node',
      'original_default_language' => 'de',
      'default_language' => 'es',
    ]);

    $this->assertTrackerRows([
      $uuid => [$test],
      $uuid2 => [$test2],
    ], 2);

    $this->sut->deleteBy('entity_type', 'node');
    $this->assertTrackerRows([], 0);
  }

  /**
   * @covers ::deleteBy
   */
  public function testDeleteByFieldWithAdditionalCondition(): void {
    [$uuid] = $this->insertDummyData([
      'entity_type' => 'node',
      'original_default_language' => 'en',
      'default_language' => 'de',
    ]);
    [$uuid2, $test] = $this->insertDummyData([
      'entity_type' => 'node',
      'original_default_language' => 'de',
      'default_language' => 'es',
    ]);

    $this->sut->deleteBy('entity_type', 'node', [
      'field_name' => 'entity_uuid',
      'value' => $uuid,
      'operator' => '=',
    ]);
    $this->assertTrackerRows([$uuid2 => [$test]], 1);
  }

  /**
   * @covers ::update
   */
  public function testUpdateWithValidData(): void {
    [$uuid, $test] = $this->insertDummyData([
      'entity_type' => 'node',
      'original_default_language' => 'en',
      'default_language' => 'de',
    ]);
    $this->assertTrackerRows([$uuid => [$test]], 1);
    $update = [
      'values' => [
        'default_language' => 'en',
      ],
    ];
    $this->sut->update($uuid, $update);
    $test = array_replace($test, $update['values']);
    $this->assertTrackerRows([$uuid => [$test]], 1);
  }

  /**
   * @covers ::update
   * @covers ::validateUpdateFields
   */
  public function testUpdateWithInvalidField(): void {
    [$uuid, $test] = $this->insertDummyData([
      'entity_type' => 'node',
      'original_default_language' => 'en',
      'default_language' => 'de',
    ]);
    $this->assertTrackerRows([$uuid => [$test]], 1);
    $update = [
      'values' => [
        'entity_uuid' => $this->generateUuid(),
      ],
    ];
    $this->expectException(TranslationDataException::class);
    $this->sut->update($uuid, $update);
  }

}
