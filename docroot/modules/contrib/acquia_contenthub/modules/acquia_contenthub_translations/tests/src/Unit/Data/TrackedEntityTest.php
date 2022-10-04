<?php

namespace Drupal\Tests\acquia_contenthub_translations\Unit\Data;

use Drupal\acquia_contenthub_translations\Data\TrackedEntity;
use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface;
use Drupal\acquia_contenthub_translations\Exceptions\InvalidAttributeException;
use Drupal\Tests\acquia_contenthub_translations\Traits\TrackedEntityGeneratorTrait;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\Data\TrackedEntity
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Unit
 */
class TrackedEntityTest extends UnitTestCase {

  use TrackedEntityGeneratorTrait;

  /**
   * The mocked translation manager.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->manager = $this->prophesize(EntityTranslationManagerInterface::class);
  }

  /**
   * @covers ::uuid
   * @covers ::type
   * @covers ::originalDefaultLanguage
   * @covers ::defaultLanguage
   * @covers ::languages
   * @covers ::changed
   * @covers ::created
   */
  public function testInstantiationWithValidValues(): void {
    [$values, $tracked_entity] = $this->generateTrackedEntity($this->manager->reveal());

    $this->assertEquals($values['uuid'], $tracked_entity->uuid());
    $this->assertEquals($values['type'], $tracked_entity->type());
    $this->assertEquals($values['original_default_language'], $tracked_entity->originalDefaultLanguage());
    $this->assertEquals($values['default_language'], $tracked_entity->defaultLanguage());
    $this->assertEquals($values['languages'], $tracked_entity->languages());
    $this->assertEquals($values['changed'], $tracked_entity->changed());
    $this->assertEquals($values['created'], $tracked_entity->created());
  }

  /**
   * Tests instantiation with missing fields.
   */
  public function testInstantiationWithMissingUuidField(): void {
    $this->expectException(InvalidAttributeException::class);
    $this->generateTrackedEntity($this->manager->reveal(), function (&$values) {
      unset($values['uuid']);
    });
  }

  /**
   * Tests instantiation with missing fields.
   */
  public function testInstantiationWithMissingTypeField(): void {
    $this->expectException(InvalidAttributeException::class);
    $this->generateTrackedEntity($this->manager->reveal(), function (&$values) {
      unset($values['type']);
    });
  }

  /**
   * Tests instantiation with missing fields.
   */
  public function testInstantiationWithMissingOriginalLangField(): void {
    $this->expectException(InvalidAttributeException::class);
    $this->generateTrackedEntity($this->manager->reveal(), function (&$values) {
      unset($values['original_default_language']);
    });
  }

  /**
   * Tests instantiation with missing fields.
   */
  public function testInstantiationWithMissingDefaultLangField(): void {
    $this->expectException(InvalidAttributeException::class);
    $this->generateTrackedEntity($this->manager->reveal(), function (&$values) {
      unset($values['default_language']);
    });
  }

  /**
   * @covers ::setLanguages
   * @covers ::setDefaultLanguage
   */
  public function testSetFieldValues(): void {
    $tracked_entity = $this->generateTrackedEntity($this->manager->reveal(), function ($values) {
      $values['original_default_language'] = 'de';
      $values['default_language'] = 'en';
      $values['languages'] = ['es' => 1];
    })[1];

    $tracked_entity->setDefaultLanguage('en');
    $this->assertNotEquals('en', $tracked_entity->defaultLanguage());

    $tracked_entity->setLanguages(['de' => 1]);
    $this->assertNotEquals(['de' => 1], $tracked_entity->defaultLanguage());

    $changed = $tracked_entity->getChangedValues();
    $this->assertEquals([
      'default_language' => 'en',
      'languages' => ['de' => 1],
    ], $changed);
  }

  /**
   * @covers ::addLanguages
   */
  public function testAddLanguages(): void {
    $expectation = $this->generateTrackedEntity($this->manager->reveal(), function (&$values) {
      $values['languages'] = [
        'es' => 2,
        'hu' => 3,
      ];
    })[1];
    $this->manager->updateTrackedEntity(Argument::type(TrackedEntity::class));

    $tracked_entity = $this->generateTrackedEntity($this->manager->reveal(), function (&$values) {
      $values['languages'] = ['es' => 2];
    })[1];

    $tracked_entity->addLanguages(['hu' => 3]);
    $changed = $tracked_entity->getChangedValues();
    $this->assertEquals([
      'languages' => [
        'es' => 2,
        'hu' => 3,
      ],
    ], $changed);
    $entity = $tracked_entity->save();

    $this->assertEquals(
      $expectation->languages(),
      $entity->languages(),
    );
  }

  /**
   * @covers ::save
   * @covers ::getChangedValues
   * @covers ::isChanged
   */
  public function testSave(): void {
    $expectation = $this->generateTrackedEntity($this->manager->reveal(), function (&$values) {
      $values['default_language'] = 'en';
      $values['languages'] = ['de' => 1];
    })[1];
    $this->manager->updateTrackedEntity(Argument::type(TrackedEntity::class));

    $tracked_entity = $this->generateTrackedEntity($this->manager->reveal(), function (&$values) {
      $values['default_language'] = 'de';
      $values['languages'] = ['es' => 1];
    })[1];

    $tracked_entity->setDefaultLanguage('en');
    $tracked_entity->setLanguages(['de' => 1]);

    $changed = $tracked_entity->getChangedValues();
    $this->assertTrue($tracked_entity->isChanged());
    $this->assertEquals([
      'default_language' => 'en',
      'languages' => ['de' => 1],
    ], $changed);

    $entity = $tracked_entity->save();
    $this->assertEquals(
      $expectation->defaultLanguage(),
      $entity->defaultLanguage(),
    );
    $this->assertEquals(
      $expectation->languages(),
      $entity->languages(),
    );
  }

  /**
   * @covers ::isTranslationUpdatable
   * @covers ::isTranslationDeletable
   *
   * @dataProvider operationFlagTestDataProvider
   */
  public function testOperationFlagChecks(int $flag, string $method) {
    $this->manager->updateTrackedEntity(Argument::type(TrackedEntity::class));
    $tracked_entity = $this->generateTrackedEntity($this->manager->reveal(), function (&$values) use ($flag) {
      $values['original_default_language'] = 'de';
      $values['default_language'] = 'en';
      $values['languages'] = ['es' => $flag];
    })[1];

    $actual = $tracked_entity->{$method}('es');
    $this->assertFalse($actual);

    $tracked_entity->setLanguages(['es' => EntityTranslationManagerInterface::NO_ACTION]);
    $tracked_entity->save();

    $actual = $tracked_entity->{$method}('es');
    $this->assertTRUE($actual);
  }

  /**
   * @covers ::isTranslationUpdatable
   * @covers ::isTranslationDeletable
   */
  public function testOperationFlagChecksWithNonExistingLanguages(): void {
    $this->manager->updateTrackedEntity(Argument::type(TrackedEntity::class));
    $tracked_entity = $this->generateTrackedEntity($this->manager->reveal())[1];

    $actual = $tracked_entity->isTranslationUpdatable('en');
    $this->assertTrue($actual);

    $actual = $tracked_entity->isTranslationDeletable('en');
    $this->assertFalse($actual);
  }

  /**
   * Returns test input for testOperationFlagChecks.
   */
  public function operationFlagTestDataProvider(): array {
    return [
      [EntityTranslationManagerInterface::NO_UPDATE, 'isTranslationUpdatable'],
      [EntityTranslationManagerInterface::NO_DELETION, 'isTranslationDeletable'],
    ];
  }

}
