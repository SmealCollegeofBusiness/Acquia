<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\EntityHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFDocument;
use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use Drupal\acquia_contenthub_translations\EntityHandler\Context;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Traits\CommonRandomGenerator;

/**
 * Tests registered non-translatable entity handlers.
 *
 * @requires module depcalc
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class NonTranslatableEntityHandlerContextTest extends KernelTestBase {

  use CommonRandomGenerator;

  /**
   * System under test.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityHandler\NonTranslatableEntityHandlerContext
   */
  protected $sut;

  /**
   * The handler registry.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityHandler\HandlerRegistry
   */
  protected $handlerRegistry;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
    'acquia_contenthub_translations',
    'depcalc',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('acquia_contenthub_translations',
      [EntityTranslationsTracker::TABLE, EntityTranslations::TABLE]
    );

    $this->handlerRegistry = $this->container->get('acquia_contenthub_translations.nt_entity_handler.registry');
    $this->sut = $this->container->get('acquia_contenthub_translations.nt_entity_handler.context');
  }

  /**
   * Tests removable entities.
   *
   * The entity is removable if the entity type is registered as removable. A
   * variation of the entity is expected to be present in the CDF document.
   *
   * @covers \Drupal\acquia_contenthub_translations\EntityHandler\Removable::handleEntity
   */
  public function testRemovableEntities(): void {
    $uuid = $this->generateUuid();
    $origin = $this->generateUuid();
    $removable_entity = new CDFObject(
      'drupal8_content_entity', $uuid, time(),
      time(), $origin,
      [
        'default_language' => 'de',
      ]
    );
    $removable_entity->addAttribute('entity_type', 'string', 'removable_entity');

    $uuid2 = $this->generateUuid();
    $non_removable = new CDFObject(
      'drupal8_content_entity', $uuid2, time(),
      time(), $origin,
      [
        'default_language' => 'en',
      ]
    );
    $non_removable->addAttribute('entity_type', 'string', 'non_removable_entity');

    $cdf_doc = new CDFDocument($removable_entity, $non_removable);
    $this->sut->handle([$removable_entity, $non_removable], ['en'], new Context(
      $cdf_doc, ['de' => 'some_uuid']
    ));

    $this->assertCount(2, $cdf_doc->getEntities(),
      'No entities were registered as removable');

    $this->handlerRegistry->addEntityToRegistry('removable_entity', 'removable');
    $this->sut->handle([$removable_entity, $non_removable], ['en'], new Context(
      $cdf_doc, ['de' => 'some_uuid']
    ));

    $this->assertCount(1, $cdf_doc->getEntities(),
    'Removable entity "removable_entity" was removed from the CDF doc');
    $cdf = $cdf_doc->getCdfEntity($uuid2);
    $this->assertEquals($uuid2, $cdf->getUuid());
  }

  /**
   * @covers \Drupal\acquia_contenthub_translations\EntityHandler\LanguageFlexible::handleEntity
   */
  public function testLanguageFlexibleEntities(): void {
    $flexible = new CDFObject(
      'drupal8_content_entity', $this->generateUuid(), time(),
      time(), $this->generateUuid(),
      [
        'default_language' => 'de',
      ]
    );
    $flexible->addAttribute('entity_type', 'string', 'flexible_entity');
    $doc = new CDFDocument($flexible);
    $this->handlerRegistry->addEntityToRegistry(
      'flexible_entity',
      'flexible'
    );
    $this->sut->handle([$flexible], ['en'], new Context($doc, ['de' => 'some_uuid']));

    $cdf = current($doc->getEntities());
    $this->assertEquals('en', $cdf->getMetadata()['default_language'],
      "The entity's default language has been changed"
    );
  }

  /**
   * @covers \Drupal\acquia_contenthub_translations\EntityHandler\Unspecified::handleEntity
   */
  public function testUnspecifiedEntities(): void {
    $uuid = $this->generateUuid();
    $origin = $this->generateUuid();
    $unspecified = new CDFObject(
      'drupal8_content_entity', $uuid, time(),
      time(), $origin,
      [
        'default_language' => 'de',
      ]
    );
    $unspecified->addAttribute('entity_type', 'string', 'unspecified');
    $cdf_doc = new CDFDocument($unspecified);
    $this->sut->handle([$unspecified], ['en'], new Context(
      $cdf_doc, ['de' => 'some_uuid']
    ));

    $this->assertCount(1, $cdf_doc->getEntities(),
      'No entities were removed');

    $cdf = current($cdf_doc->getEntities());
    $this->assertEquals('de', $cdf->getMetadata()['default_language'],
      "The entity's default language has not been changed"
    );

    $unspecified_entities = $this->handlerRegistry->getUnspecified();
    $this->assertTrue(isset($unspecified_entities['unspecified']),
      'Entity has been added to the unspecified list'
    );
  }

}
