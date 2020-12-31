<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\NodeInterface;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;

/**
 * Class ConfigEntityDependencyCollector.
 *
 * @group depcalc
 *
 * @package Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector
 *
 * @covers \Drupal\depcalc\EventSubscriber\DependencyCollector\ConfigEntityDependencyCollector
 */
class ConfigEntityDependencyCollectorTest extends KernelTestBase {

  use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
  use Drupal\Tests\node\Traits\NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'breakpoint',
    'depcalc',
    'field',
    'filter',
    'image',
    'language',
    'node',
    'responsive_image',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * Calculates all the dependencies of a given entity.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  private $calculator;

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installConfig(['language', 'field', 'filter', 'node', 'system']);
    $this->installEntitySchema('path_alias');
    $this->calculator = Drupal::service('entity.dependency.calculator');
  }

  /**
   * Tests config entities dependency calculation.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testConfigEntitiesDependencyCalculation() {
    // Creating a new dependent image style.
    $style1 = $this->createImageStyle('dummy_image_style_1');
    $wrapper = new DependentEntityWrapper($style1);
    $dependencies = $this->calculator->calculateDependencies($wrapper, new DependencyStack());

    $this->assertNotEmpty($dependencies);
    $this->assertEmpty($dependencies['module']);
    $this->assertEqual(2, count($dependencies));
    $this->assertArrayHasKey($style1->uuid(), $dependencies);

    // Creating a one more dependent image style.
    $style2 = $this->createImageStyle('dummy_image_style_2');

    // Creating a responsive image style which depends on the above styles.
    /** @var \Drupal\responsive_image\ResponsiveImageStyleInterface $responsiveImageStyle */
    $responsiveImageStyle = ResponsiveImageStyle::create([
      'id' => 'dummy_responsive_image_style_1',
      'label' => 'Dummy responsive image style 1',
    ]);
    $responsiveImageStyle->addImageStyleMapping('dummy_breakpoint_1', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'dummy_image_style_1',
    ]);
    $responsiveImageStyle->addImageStyleMapping('dummy_breakpoint_2', '2x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'dummy_image_style_2',
    ]);
    $responsiveImageStyle->save();

    $wrapper = new DependentEntityWrapper($responsiveImageStyle);
    $dependencies = $this->calculator->calculateDependencies($wrapper, new DependencyStack());
    $this->assertNotEmpty($dependencies);
    $this->assertEqual(4, count($dependencies));
    $this->assertArrayHasKey($style1->uuid(), $wrapper->getDependencies());
    $this->assertArrayHasKey($style2->uuid(), $wrapper->getDependencies());

    $wrapper->addModuleDependencies(['image']);
    $dependencies = $this->calculator->calculateDependencies($wrapper, new DependencyStack());
    $this->assertEqual($dependencies['module'], ['image']);
  }

  /**
   * Tests language config dependencies calculation.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testLanguageConfigEntitiesDependencyCalculation() {
    list($csLanguage, $frLanguage) = $this->getTestLanguages();

    $bundle = 'article';
    $this->createContentType(['type' => $bundle, 'name' => 'Article']);
    $contentLanguageSettings = ContentLanguageSettings::loadByEntityTypeBundle('node', $bundle);

    $csNode = $this->createTestNode('cs');
    $csDependencies = $this->calculator->calculateDependencies(
      new DependentEntityWrapper($csNode),
      new DependencyStack()
    );
    $contentLanguageSettings->setDefaultLangcode('cs')
      ->setLanguageAlterable(FALSE)
      ->save();

    $this->assertArrayNotHasKey($csLanguage->uuid(), $csDependencies);
    $this->assertArrayNotHasKey($frLanguage->uuid(), $csDependencies);
    $this->assertArrayNotHasKey($contentLanguageSettings->uuid(), $csDependencies);

    $contentLanguageSettings->setDefaultLangcode('cs')
      ->setLanguageAlterable(TRUE)
      ->save();

    $csNode = $this->createTestNode('cs');

    $frNode = $csNode->addTranslation('fr');
    $frNode->set('title', $this->randomString());
    $frNode->save();

    $csDependencies = $this->calculator->calculateDependencies(
      new DependentEntityWrapper($csNode),
      new DependencyStack()
    );
    $frDependencies = $this->calculator->calculateDependencies(
      new DependentEntityWrapper($frNode),
      new DependencyStack()
    );

    $this->assertArrayHasKey('language', array_flip($csDependencies['module']));
    $this->assertArrayHasKey('language', array_flip($frDependencies['module']));

    /** @var \Drupal\depcalc\DependentEntityWrapperInterface $csNodeDependency */
    $csNodeDependency = $csDependencies[$csNode->uuid()];
    $this->assertArrayHasKey($csLanguage->uuid(), $csNodeDependency->getDependencies());
    $this->assertArrayHasKey($frLanguage->uuid(), $csNodeDependency->getDependencies());
    $this->assertArrayHasKey($contentLanguageSettings->uuid(), $csNodeDependency->getDependencies());

    $frNodeDependency = $csDependencies[$frNode->uuid()];
    $this->assertArrayHasKey($csLanguage->uuid(), $frNodeDependency->getDependencies());
    $this->assertArrayHasKey($frLanguage->uuid(), $frNodeDependency->getDependencies());
    $this->assertArrayHasKey($contentLanguageSettings->uuid(), $frNodeDependency->getDependencies());

    $dependenciesWrapper = $this->calculator->calculateDependencies(
      new DependentEntityWrapper($this->createTestNode('cs')),
      new DependencyStack()
    );
    $this->assertArrayHasKey($csLanguage->uuid(), $dependenciesWrapper);
    $this->assertArrayNotHasKey($frLanguage->uuid(), $dependenciesWrapper);
    $this->assertArrayHasKey($contentLanguageSettings->uuid(), $dependenciesWrapper);

    $dependenciesWrapper = $this->calculator->calculateDependencies(
      new DependentEntityWrapper($this->createTestNode('fr')),
      new DependencyStack()
    );
    $this->assertArrayHasKey($frLanguage->uuid(), $dependenciesWrapper);
    $this->assertArrayNotHasKey($csLanguage->uuid(), $dependenciesWrapper);
    $this->assertArrayHasKey($contentLanguageSettings->uuid(), $dependenciesWrapper);
  }

  /**
   * Creates a dummy image style.
   *
   * @param string $name
   *   Image style name.
   *
   * @return \Drupal\image\ImageStyleInterface
   *   Image style.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createImageStyle(string $name): EntityInterface {
    $style = ImageStyle::create([
      'name' => $name,
      'label' => $this->randomString(),
    ]);
    $style->save();
    return $style;
  }

  /**
   * Creates a test node with a given langcode.
   *
   * @param string $langcode
   *   Language code.
   *
   * @return \Drupal\node\NodeInterface
   *   Test node.
   */
  private function createTestNode(string $langcode): NodeInterface {
    return $this->createNode([
      'langcode' => $langcode,
      'type' => 'article',
      'title' => $this->randomString(),
    ]);
  }

  /**
   * Returns set of the test languages.
   *
   * @return array
   *   Languages list.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function getTestLanguages(): array {
    foreach (['cs', 'fr'] as $language) {
      (ConfigurableLanguage::create([
        'id' => $language,
        'label' => $this->randomString(),
      ]))->save();
    }

    return [
      ConfigurableLanguage::load('cs'),
      ConfigurableLanguage::load('fr'),
    ];
  }

}
