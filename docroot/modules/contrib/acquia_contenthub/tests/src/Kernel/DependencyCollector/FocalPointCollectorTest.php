<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\DependencyCollector;

use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Test focal point dependency collector.
 *
 * @group acquia_contenthub
 *
 * @requires module crop
 * @requires module focal_point
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class FocalPointCollectorTest extends KernelTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;

  protected const FIELD_NAME = 'field_image';
  protected const FIELD_TYPE = 'image';
  protected const BUNDLE = 'article';
  protected const ENTITY_TYPE = 'node';

  /**
   * The crop storage.
   *
   * @var \Drupal\crop\CropStorageInterface
   */
  protected $cropStorage;

  /**
   * The dependency calculator.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'acquia_contenthub',
    'crop',
    'focal_point',
    'depcalc',
    'file',
    'field',
    'filter',
    'image',
    'node',
    'system',
    'text',
    'user',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('node');
    $this->installConfig('field');
    $this->installConfig('filter');
    $this->installConfig('file');
    $this->installConfig('focal_point');

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('crop');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $this->createContentType([
      'type' => self::BUNDLE,
      'name' => self::BUNDLE,
    ]);
    $this->addField();

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->cropStorage = $entity_type_manager->getStorage('crop');

    $this->calculator = \Drupal::service('entity.dependency.calculator');
  }

  /**
   * Tests dependencies calculation for an entity reference field.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testDependenciesCollection() {
    // Create test image file.
    $file = File::create([
      'uri' => 'public://test.jpg',
      'uuid' => '4dcb20e3-b3cd-4b09-b157-fb3609b3fc93',
    ]);
    $file->save();

    /** @var \Drupal\crop\CropInterface $crop */
    $crop = $this->cropStorage->create([
      'type' => 'focal_point',
      'entity_id' => $file->id(),
      'entity_type' => $file->getEntityTypeId(),
      'uri' => $file->getFileUri(),
      'x' => '100',
      'y' => '150',
      'width' => '200',
      'height' => '250',
    ]);
    $crop->save();

    // Create node.
    $entity = $this->createNode([
      'type' => self::BUNDLE,
      self::FIELD_NAME => $file->id(),
    ]);

    $dependencies = $this->calculateDependencies($entity);
    $this->assertArrayHasKey($crop->uuid(), $dependencies);
  }

  /**
   * Calculates dependencies for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node.
   *
   * @return array
   *   Dependencies array.
   *
   * @throws \Exception
   */
  private function calculateDependencies(NodeInterface $node): array {
    $wrapper = new DependentEntityWrapper($node);
    return $this->calculator->calculateDependencies($wrapper, new DependencyStack());
  }

  /**
   * Add a field to the content type.
   */
  private function addField() {
    FieldStorageConfig::create([
      'entity_type' => self::ENTITY_TYPE,
      'field_name' => self::FIELD_NAME,
      'type' => self::FIELD_TYPE,
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'entity_type' => self::ENTITY_TYPE,
      'field_name' => self::FIELD_NAME,
      'bundle' => self::BUNDLE,
      'label' => $this->randomMachineName(),
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('node', self::BUNDLE)
      ->setComponent(self::FIELD_NAME, [
        'type' => 'image_focal_point',
        'settings' => [],
      ])
      ->save();
  }

}
