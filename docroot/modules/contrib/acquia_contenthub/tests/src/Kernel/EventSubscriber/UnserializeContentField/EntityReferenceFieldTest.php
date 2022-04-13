<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\UnserializeContentField;

use Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField\EntityReferenceField;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\depcalc\DependencyStack;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Test that entity reference are handled correctly during unserialization.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField\EntityReferenceField
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\EntityReferenceField
 */
class EntityReferenceFieldTest extends KernelTestBase {
  use NodeCreationTrait;

  /**
   * Entity Bundle name.
   */
  protected const BUNDLE = 'article';

  /**
   * Field name.
   */
  protected const FIELD_NAME = 'test_field_name';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'depcalc',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * Logger channel mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $loggerChannel;

  /**
   * EntityReferenceField instance.
   *
   * @var \Drupal\acquia_contenthub\EventSubscriber\UnserializeContentField\EntityReferenceField
   */
  protected $entityRefFieldInstance;

  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $refNode;

  /**
   * Unserialize Cdf entity field event.
   *
   * @var \Drupal\acquia_contenthub\Event\UnserializeCdfEntityFieldEvent
   */
  protected $event;

  /**
   * Dependency stack.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $stack;

  /**
   * Entity type.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityType;

  /**
   * Field meta data.
   *
   * @var string[]
   */
  protected $metaData;

  /**
   * Field mock value.
   *
   * @var string[]
   */
  protected $mockField;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'field',
      'filter',
      'node',
    ]);

    $this->stack = $this->prophesize(DependencyStack::class);
    $this->entityType = $this->prophesize(EntityTypeInterface::class);
    $this->metaData = [
      'type' => 'entity_reference',
      'target' => 'node',
    ];

    $this->refNode = $this->createNode();
    $this->mockField = [
      'value' => [
        'en' => [
          'target_id' => $this->refNode->uuid(),
        ],
      ],
    ];
    $this->event = new UnserializeCdfEntityFieldEvent($this->entityType->reveal(), self::BUNDLE, self::FIELD_NAME, $this->mockField, $this->metaData, $this->stack->reveal());
    $this->loggerChannel = new LoggerMock();
    $this->entityRefFieldInstance = new EntityReferenceField($this->loggerChannel);
  }

  /**
   * @covers ::onUnserializeContentField
   *
   * @throws \Exception
   */
  public function testEntityReferenceFieldUnSerializerException(): void {
    $uuid = $this->refNode->uuid();
    $this->refNode->delete();
    $this->entityRefFieldInstance->onUnserializeContentField($this->event);

    $log_messages = $this->loggerChannel->getLogMessages();
    $this->assertNotEmpty($log_messages);
    $this->assertEquals("No entity found with uuid $uuid.", $log_messages[RfcLogLevel::ERROR][0]);
    $this->assertFalse($this->event->isPropagationStopped());
  }

  /**
   * @covers ::onUnserializeContentField
   *
   * @throws \Exception
   */
  public function testEntityReferenceFieldUnSerializer(): void {
    $this->entityRefFieldInstance->onUnserializeContentField($this->event);
    $event_expected_value = [
      'en' => [
        self::FIELD_NAME => [
          [
            'target_id' => $this->refNode->id(),
          ],
        ],
      ],
    ];

    $this->assertEquals($event_expected_value, $this->event->getValue());
    $this->assertEquals(self::BUNDLE, $this->event->getBundle());
    $this->assertEquals(self::FIELD_NAME, $this->event->getFieldName());
    $this->assertTrue($this->event->isPropagationStopped());
  }

  /**
   * @covers ::onUnserializeContentField
   *
   * @throws \Exception
   */
  public function testEntityReferenceFieldUnSerializerWithInvalidFieldType(): void {
    $this->metaData = [
      'type' => 'invalid_type',
    ];

    $this->event = new UnserializeCdfEntityFieldEvent($this->entityType->reveal(), self::BUNDLE, self::FIELD_NAME, $this->mockField, $this->metaData, $this->stack->reveal());
    $this->entityRefFieldInstance->onUnserializeContentField($this->event);

    $this->assertEmpty($this->event->getValue());
    $this->assertEquals([], $this->event->getValue());
  }

  /**
   * @covers ::onUnserializeContentField
   *
   * @throws \Exception
   */
  public function testEntityReferenceFieldUnSerializerWithEmptyField(): void {
    $this->mockField = [
      'value' => [],
    ];
    $this->event = new UnserializeCdfEntityFieldEvent($this->entityType->reveal(), self::BUNDLE, self::FIELD_NAME, $this->mockField, $this->metaData, $this->stack->reveal());
    $this->entityRefFieldInstance->onUnserializeContentField($this->event);

    $this->assertEmpty($this->event->getValue());
    $this->assertEquals([], $this->event->getValue());
    $this->assertTrue($this->event->isPropagationStopped());
  }

  /**
   * @covers ::onUnserializeContentField
   *
   * @throws \Exception
   */
  public function testEntityReferenceFieldUnSerializerWithEmptyFieldValue(): void {
    $this->mockField = [
      'value' => [
        'en' => [],
      ],
    ];
    $this->event = new UnserializeCdfEntityFieldEvent($this->entityType->reveal(), self::BUNDLE, self::FIELD_NAME, $this->mockField, $this->metaData, $this->stack->reveal());
    $this->entityRefFieldInstance->onUnserializeContentField($this->event);
    $event_expected_value = [
      'en' => [
        self::FIELD_NAME => [],
      ],
    ];
    $this->assertEquals($event_expected_value, $this->event->getValue());
    $this->assertTrue($this->event->isPropagationStopped());
  }

}
