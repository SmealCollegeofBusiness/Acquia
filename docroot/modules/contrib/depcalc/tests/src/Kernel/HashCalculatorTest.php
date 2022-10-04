<?php

namespace Drupal\Tests\depcalc\Kernel;

use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Class HashCalculatorTest.
 *
 * @group depcalc
 */
class HashCalculatorTest extends KernelTestBase {

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
    'language',
    'node',
    'text',
    'user',
    'system',
  ];

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('configurable_language');
    $this->installConfig([
      'filter',
      'node',
      'language'
    ]);

    ConfigurableLanguage::createFromLangcode('hi')->save();
    $this->createContentType([
      'type' => 'article',
    ]);

    $this->entity = $this->createNode([
      'type' => 'article',
      'title' => 'My article',
    ]);
  }

  /**
   * Tests the hash with no translation.
   */
  public function testHashWithNoTranslation(): void {
    $dependentEntityWrapper = new DependentEntityWrapper($this->entity);
    $this->entity->set('title', 'Article updated.')->save();
    $dependentEntityWrapper1 = new DependentEntityWrapper($this->entity);
    $this->assertNotSame($dependentEntityWrapper1->getHash(), $dependentEntityWrapper->getHash());
  }

  /**
   * Tests the hash with translation.
   */
  public function testHashWithTranslation(): void {
    $dependentEntityWrapper = new DependentEntityWrapper($this->entity);
    $this->entity->addTranslation('hi', [
      'title' => 'My article Hindi',
    ]);
    $dependentEntityWrapper1 = new DependentEntityWrapper($this->entity);
    $this->assertNotSame($dependentEntityWrapper1->getHash(), $dependentEntityWrapper->getHash());

    $entity = $this->entity->getTranslation('hi');
    $entity->set('title', 'My article Hindi Updated')->save();
    $dependentEntityWrapper2 = new DependentEntityWrapper($entity);
    $this->assertNotSame($dependentEntityWrapper2->getHash(), $dependentEntityWrapper1->getHash());

    $this->entity->removeTranslation('hi');
    $dependentEntityWrapper3 = new DependentEntityWrapper($this->entity);
    $this->assertNotSame($dependentEntityWrapper3->getHash(), $dependentEntityWrapper1->getHash());
  }

  /**
   * Tests hash calculation for config entities.
   */
  public function testHashWithConfigEntity(): void {
    $test_content_type = $this->createContentType([
      'type' => 'test',
      'name' => 'Test content type'
    ]);
    $test_content_type->save();
    $dependentEntityWrapper1 = new DependentEntityWrapper($test_content_type);
    $test_content_type->set('name', 'Test content type updated')->save();
    $dependentEntityWrapper2 = new DependentEntityWrapper($test_content_type);
    $this->assertNotSame($dependentEntityWrapper1->getHash(), $dependentEntityWrapper2->getHash());
  }

}
