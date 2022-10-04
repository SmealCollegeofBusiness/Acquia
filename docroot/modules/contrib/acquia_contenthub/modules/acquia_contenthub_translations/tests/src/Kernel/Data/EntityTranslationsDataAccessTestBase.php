<?php

namespace Drupal\Tests\acquia_contenthub_translations\Kernel\Data;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Traits\CommonRandomGenerator;
use Drupal\Tests\acquia_contenthub_translations\Traits\EntityTranslationDbAssertions;

/**
 * Basic setup for dao tests.
 */
abstract class EntityTranslationsDataAccessTestBase extends KernelTestBase {

  use CommonRandomGenerator;
  use EntityTranslationDbAssertions;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_translations',
    'acquia_contenthub_subscriber',
    'depcalc',
    'user',
  ];

  /**
   * SUT object.
   *
   * @var \Drupal\acquia_contenthub_translations\Data\EntityTranslationsDAOInterface
   */
  protected $sut;

  /**
   * Inserts values into db.
   *
   * @param array $values
   *   The values used for the entity.
   *
   * @return array
   *   The generated uuid and the values used for the entity.
   */
  protected function insertDummyData(array $values): array {
    $uuid = $this->generateUuid();
    $test = [
      'entity_uuid' => $uuid,
    ];
    $test = array_merge($test, $values);
    $this->sut->insert($test);
    return [$uuid, $test];
  }

}
