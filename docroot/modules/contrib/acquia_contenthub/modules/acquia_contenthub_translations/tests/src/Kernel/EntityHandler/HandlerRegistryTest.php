<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\EntityHandler;

use Drupal\acquia_contenthub_translations\EntityHandler\HandlerRegistry;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\EntityHandler\HandlerRegistry
 *
 * @requires module depcalc
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Kernel
 */
class HandlerRegistryTest extends KernelTestBase {

  /**
   * System under test.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityHandler\HandlerRegistry
   */
  protected $sut;

  /**
   * The acquia_contenthub_translations config object used by the registry.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

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
  public function setUp(): void {
    parent::setUp();

    $this->config = $this->container->get('acquia_contenthub_translations.config');
    $this->sut = $this->container->get('acquia_contenthub_translations.nt_entity_handler.registry');
  }

  /**
   * @covers ::getHandlerIdFor
   */
  public function testGetHandlerIdForDefaultEntities(): void {
    // Default configuration.
    $this->assertEquals('flexible',
      $this->sut->getHandlerIdFor('file')
    );
    $this->assertEquals('removable',
      $this->sut->getHandlerIdFor('path_alias')
    );
    $this->assertEquals('removable',
      $this->sut->getHandlerIdFor('redirect')
    );
    $this->assertEquals('unspecified',
      $this->sut->getHandlerIdFor('unspecified')
    );
  }

  /**
   * @covers ::getUnspecified
   * @covers ::addToUnspecified
   */
  public function testUnspecifiedList(): void {
    $this->sut->addToUnspecified('random_entity');

    $this->assertEquals('unspecified',
      $this->sut->getHandlerIdFor('random_entity')
    );

    $list = $this->config->get(HandlerRegistry::UNSPECIFIED_CONFIG_KEY);
    $this->assertTrue(isset($list['random_entity']));

    $this->assertTrue($this->sut->isUnspecified('random_entity'));
  }

  /**
   * @covers ::getHandlerMapping
   * @covers ::addEntityToRegistry
   */
  public function testEntityHandlerMapping(): void {
    $this->sut->addEntityToRegistry('an_entity', 'removable');

    $this->assertEquals('removable',
      $this->sut->getHandlerIdFor('an_entity')
    );

    $list = $this->config->get(HandlerRegistry::ENTITY_HANDLERS_CONFIG_KEY);
    $this->assertEquals('removable', $list['an_entity']);
  }

}
