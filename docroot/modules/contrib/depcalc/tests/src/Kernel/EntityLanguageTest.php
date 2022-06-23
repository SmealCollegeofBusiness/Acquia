<?php

namespace Drupal\Tests\depcalc\Kernel;

use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Class EntityLanguageTest.
 *
 * Test language dependency in node.
 *
 * @group depcalc
 */
class EntityLanguageTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'depcalc',
    'field',
    'filter',
    'node',
    'system',
    'user',
    'language',
    'text',
  ];

  /**
   * Calculates all the dependencies of a given entity.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * Node object.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * Language object.
   *
   * @var \Drupal\Core\Language
   */
  protected $language;


  /**
   * Dependencies uuids.
   *
   * @var array
   */
  protected $dependencies;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['language', 'field', 'filter', 'node', 'system']);

    $this->installSchema('node', 'node_access');
    $this->installSchema('system', 'sequences');

    $this->installEntitySchema('node_type');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    $this->language = ConfigurableLanguage::load('en');
    $this->calculator = $this->container->get('entity.dependency.calculator');

    $this->createContentType([
      'type' => 'article',
      'name' => 'article',
    ]);

    $this->node = $this->createNode([
      'type' => 'article',
      'language' => 'en',
    ]);
  }

  /**
   * Tests node dependencies when entity translation is not enabled.
   */
  public function testEntityTranslationNotEnabled(): void {
    $this->runEntityTranslationTest();
    $this->assertArrayNotHasKey($this->language->uuid(), $this->dependencies);
  }

  /**
   * Tests node dependencies when entity translation is enabled.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEntityTranslationEnabled(): void {

    $contentLanguageSettings = ContentLanguageSettings::loadByEntityTypeBundle('node', 'article');
    $contentLanguageSettings->setThirdPartySetting('content_translation', 'enabled', TRUE)
      ->save();
    $this->runEntityTranslationTest();
    $this->assertArrayHasKey($this->language->uuid(), $this->dependencies);
  }

  /**
   * Calculate entity dependencies.
   */
  protected function runEntityTranslationTest() {
    try {
      $wrapper = new DependentEntityWrapper($this->node);
    }
    catch (\Exception $exception) {
      $this->markTestIncomplete($exception->getMessage());
    }

    $this->calculator->calculateDependencies($wrapper, new DependencyStack());
    $this->dependencies = $wrapper->getDependencies();

  }

}
