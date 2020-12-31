<?php

namespace Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Class EntityFormDisplayDependencyCollectorTest.
 *
 * @group depcalc
 *
 * @package Drupal\Tests\depcalc\Kernel\EventSubscriber\DependencyCollector
 *
 * @covers \Drupal\depcalc\EventSubscriber\DependencyCollector\EntityFormDisplayDependencyCollector
 */
class EntityFormDisplayDependencyCollectorTest extends KernelTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'depcalc',
    'field',
    'filter',
    'node',
    'path_alias',
    'system',
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

    $this->installConfig('node');
    $this->installConfig('field');
    $this->installConfig('filter');

    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');

    $this->calculator = \Drupal::service('entity.dependency.calculator');
  }

  /**
   * Tests dependency calculation of entity form display.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testEntityFormDisplayDependencyCollection() {
    $bundle = 'depcalc_dummy_content_type';
    $entityType = 'node';
    ($this->createContentType([
      'type' => $bundle,
      'name' => 'Depcalc. Dummy content type',
    ]))->save();

    $enabledFormDisplay = $this->createFormDisplay($entityType, $bundle, 'depcalc_dummy_form_mode', TRUE);

    $disabledFormDisplay = $this->createFormDisplay($entityType, $bundle, 'depcalc_dummy_form_mode_disabled', FALSE);

    $node = $this->createNode(['type' => 'depcalc_dummy_content_type']);
    $node->save();

    $dependencies = $this->calculator->calculateDependencies(
      new DependentEntityWrapper($node),
      new DependencyStack()
    );

    $this->assertNotEmpty($dependencies);
    $this->assertArrayHasKey($enabledFormDisplay->uuid(), $dependencies);
    $this->assertArrayNotHasKey($disabledFormDisplay->uuid(), $dependencies);

    /** @var \Drupal\depcalc\DependentEntityWrapper $formDisplayEntityWrapper */
    $formDisplayEntityWrapper = $dependencies[$enabledFormDisplay->uuid()];
    $this->assertEqual($formDisplayEntityWrapper->getUuid(), $enabledFormDisplay->uuid());
    $this->assertEqual($enabledFormDisplay->getEntityTypeId(), 'entity_form_display');
  }

  /**
   * Creates a form display for a given entity type.
   *
   * @param string $targetEntityType
   *   Target entity type.
   * @param string $bundle
   *   Bundle name.
   * @param string $mode
   *   Form mode name.
   * @param bool $status
   *   Publishing status.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Form display.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createFormDisplay(string $targetEntityType, string $bundle, string $mode, bool $status) {
    (EntityFormMode::create([
      'id' => sprintf('%s.%s', $targetEntityType, $mode),
      'targetEntityType' => $targetEntityType,
      'bundle' => $bundle,
      'mode' => $mode,
    ]))->save();

    $formDisplay = EntityFormDisplay::create([
      'targetEntityType' => $targetEntityType,
      'bundle' => $bundle,
      'mode' => $mode,
      'status' => $status,
    ]);

    $formDisplay->save();

    return $formDisplay;
  }

}
