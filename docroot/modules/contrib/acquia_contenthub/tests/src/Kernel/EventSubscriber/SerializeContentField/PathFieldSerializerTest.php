<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\SerializeContentField;

use Drupal\Core\Language\Language;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\acquia_contenthub\Kernel\AcquiaContentHubSerializerTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Stubs\DrupalVersion;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests Path Field Serialization.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 *
 * @covers \Drupal\acquia_contenthub\EventSubscriber\SerializeContentField\PathFieldSerializer
 */
class PathFieldSerializerTest extends AcquiaContentHubSerializerTestBase {

  use DrupalVersion;
  use UserCreationTrait;

  /**
   * Path field name.
   */
  protected const FIELD_NAME = 'path';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'acquia_contenthub_test',
    'path',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function setUp(): void {
    if (version_compare(\Drupal::VERSION, '9.0', '>=')) {
      static::$modules[] = 'path_alias';
    }
    parent::setUp();
    self::$modules = array_merge(parent::$modules, self::$modules);

    if (version_compare(\Drupal::VERSION, '8.8.0', '>=')) {
      $this->installEntitySchema('path_alias');
    }

    $this->setUpCurrentUser();
  }

  /**
   * Tests the serialization of the path field.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPathFieldSerialization() {
    $node = $this->createNode();

    $this->entity = PathAlias::create([
      'path' => '/node/' . $node->id(),
      'alias' => 'new_test_path',
    ]);

    $this->entity->save();
    $field = $this->entity->get(self::FIELD_NAME);

    $event = $this->dispatchSerializeEvent(self::FIELD_NAME, $field);
    $actual_output = $event->getFieldData()['value'][Language::LANGCODE_NOT_SPECIFIED]['value'];

    // Check expected output after path field serialization.
    $this->assertEquals($node->uuid(), $actual_output);
  }

  /**
   * Tests the serialization of the node path field.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNodePathFieldSerialization() {
    $this->entity = $this->createNode();
    $field = $this->entity->get(self::FIELD_NAME);

    PathAlias::create([
      'path' => '/node/' . $this->entity->id(),
      'alias' => 'new_test_path',
    ]);

    $event = $this->dispatchSerializeEvent(self::FIELD_NAME, $field);
    $this->assertTrue($event->isPropagationStopped());
  }

}
