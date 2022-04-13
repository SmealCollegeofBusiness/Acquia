<?php

namespace Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemoveNonPublicFiles;
use Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests field exclusion event for non-public files.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemoveNonPublicFiles
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField
 */
class RemoveNonPublicFilesTest extends UnitTestCase {

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The File scheme handler manager.
   *
   * @var \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface
   */
  protected $fileManager;

  /**
   * The field item.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface
   */
  protected $field;

  /**
   * The ExcludeEntityField event.
   *
   * @var \Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent
   */
  protected $event;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->dispatcher = new EventDispatcher();
    $this->fileManager = $this->prophesize(FileSchemeHandlerManagerInterface::class);
    $this->field = $this->prophesize(FieldItemListInterface::class);
  }

  /**
   * Tests field exclusion event for non-public files.
   */
  public function testExcludeEntityField(): void {
    $this->mockEvent();
    $this->assertEquals(FALSE, $this->event->isExcluded());
    $this->dispatcher->dispatch(AcquiaContentHubEvents::EXCLUDE_CONTENT_ENTITY_FIELD, $this->event);
    $this->assertEquals(TRUE, $this->event->isExcluded());
  }

  /**
   * Mocks event and other parameters.
   */
  public function mockEvent(): void {
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition
      ->getType()
      ->willReturn('file');
    $field_storage_definition = $this->prophesize(FieldStorageDefinitionInterface::class);
    $field_storage_definition
      ->getSetting('uri_scheme')
      ->willReturn('test_scheme');
    $field_definition
      ->getFieldStorageDefinition()
      ->willReturn($field_storage_definition->reveal());
    $this->field
      ->getFieldDefinition()
      ->willReturn($field_definition->reveal());

    $entity = $this->prophesize(ContentEntityInterface::class)->reveal();
    $this->dispatcher->addSubscriber(new RemoveNonPublicFiles($this->fileManager->reveal()));
    $this->event = new ExcludeEntityFieldEvent($entity, 'test_field', $this->field->reveal());
  }

}
