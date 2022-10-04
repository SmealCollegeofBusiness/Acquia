<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\Data;

use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface;
use Drupal\acquia_contenthub_translations\Exceptions\TranslationDataException;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\Data\EntityTranslations
 *
 * @group acquia_contenthub_translations
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class EntityTranslationsTest extends EntityTranslationsDataAccessTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('acquia_contenthub_translations', EntityTranslations::TABLE);

    $this->sut = new EntityTranslations(
      $this->container->get('database')
    );
  }

  /**
   * Tests insert method with valid data.
   *
   * @covers ::insert
   */
  public function testInsertWithValidData(): void {
    [$uuid, $test] = $this->insertDummyData([
      'langcode' => 'en',
      'operation_flag' => EntityTranslationManagerInterface::NO_UPDATE | EntityTranslationManagerInterface::NO_DELETION,
    ]);
    $this->assertTranslationsRows([$uuid => [$test]], 1);
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
      'langcode' => 'en',
      'operation_flag' => EntityTranslationManagerInterface::NO_ACTION,
    ]);
    unset($test['extra_attribute']);
    $this->assertTranslationsRows([$uuid => [$test]], 1);
  }

  /**
   * @covers ::insert
   */
  public function testInsertWithMultipleData(): void {
    $uuid1 = $this->generateUuid();
    $uuid2 = $this->generateUuid();
    $test = [
      [
        'entity_uuid' => $uuid1,
        'langcode' => 'en',
        'operation_flag' => 1,
      ],
      [
        'entity_uuid' => $uuid2,
        'langcode' => 'en',
        'operation_flag' => 2,
      ],
    ];
    $this->sut->insert($test);

    $this->assertTranslationsRows([
      $uuid1 => [$test[0]],
      $uuid2 => [$test[1]],
    ], 2);
  }

  /**
   * Tests record removal.
   *
   * @covers ::deleteByUuid
   */
  public function testDeleteByUuid(): void {
    [$uuid, $test] = $this->insertDummyData([
      'langcode' => 'en',
      'operation_flag' => EntityTranslationManagerInterface::NO_ACTION,
    ]);
    $this->assertTranslationsRows([$uuid => [$test]], 1);

    $this->sut->deleteByUuid($uuid);
    $this->assertTranslationsRows([], 0);
  }

  /**
   * Tests record removal.
   *
   * @covers ::deleteBy
   */
  public function testDeleteByField(): void {
    [$uuid, $test] = $this->insertDummyData([
      'langcode' => 'en',
      'operation_flag' => EntityTranslationManagerInterface::NO_ACTION,
    ]);
    $this->assertTranslationsRows([$uuid => [$test]], 1);

    $this->sut->deleteBy('langcode', 'en');
    $this->assertTranslationsRows([], 0);
  }

  /**
   * Tests record removal with additional condition.
   *
   * @covers ::deleteBy
   */
  public function testDeleteByFieldWithAdditionalCondition(): void {
    [$uuid, $test] = $this->insertDummyData([
      'langcode' => 'en',
      'operation_flag' => EntityTranslationManagerInterface::NO_ACTION,
    ]);
    [$uuid2, $test2] = $this->insertDummyData([
      'langcode' => 'de',
      'operation_flag' => EntityTranslationManagerInterface::NO_ACTION,
    ]);
    $this->assertTranslationsRows([
      $uuid => [$test],
      $uuid2 => [$test2],
    ], 2);

    $this->sut->deleteBy('entity_uuid', $uuid, [
      'field_name' => 'langcode',
      'value' => 'en',
      'operator' => '=',
    ]);
    $this->assertTranslationsRows([$uuid2 => [$test2]], 1);
  }

  /**
   * Tests record update.
   *
   * @covers ::update
   */
  public function testUpdateWithValidData(): void {
    [$uuid, $test] = $this->insertDummyData([
      'langcode' => 'en',
      'operation_flag' => EntityTranslationManagerInterface::NO_ACTION,
    ]);
    $this->assertTranslationsRows([$uuid => [$test]], 1);
    $update = [
      'values' => [
        'langcode' => 'de',
      ],
      'langcode' => 'en',
    ];
    $this->sut->update($uuid, $update);
    $test = array_replace($test, $update['values']);
    $this->assertTranslationsRows([$uuid => [$test]], 1);
  }

  /**
   * Tests record update with invalid data.
   *
   * @covers ::validateUpdateFields
   * @covers ::update
   */
  public function testUpdateWithInvalidField(): void {
    [$uuid, $test] = $this->insertDummyData([
      'langcode' => 'en',
      'operation_flag' => EntityTranslationManagerInterface::NO_ACTION,
    ]);
    $this->assertTranslationsRows([$uuid => [$test]], 1);
    $update = [
      'values' => [
        'entity_uuid' => $this->generateUuid(),
      ],
      'langcode' => 'en',
    ];
    $this->expectException(TranslationDataException::class);
    $this->sut->update($uuid, $update);
  }

}
