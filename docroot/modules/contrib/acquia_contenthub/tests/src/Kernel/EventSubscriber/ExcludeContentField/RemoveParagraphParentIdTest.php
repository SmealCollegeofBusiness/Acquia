<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemoveParagraphParentId;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests remove paragraph parent_id field serialization.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField\RemoveParagraphParentId
 *
 * @requires module paragraphs
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ExcludeContentField
 */
class RemoveParagraphParentIdTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'paragraphs',
    'user',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setup();
  }

  /**
   * Tests the removal of paragraph parent_id field.
   *
   * @covers ::excludeContentField
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRemoveParagraphParentId() {
    $paragraph = Paragraph::create([
      'title' => 'Paragraph',
      'type' => 'text_paragraph',
      'text' => 'Text Paragraph',
    ]);

    $remove_id_and_revision_field = new RemoveParagraphParentId();
    foreach ($paragraph as $field_name => $field) {
      $event = new ExcludeEntityFieldEvent($paragraph, $field_name, $field);
      $remove_id_and_revision_field->excludeContentField($event);

      if ($field_name === 'parent_id') {
        $this->assertTrue($event->isExcluded());
        $this->assertTrue($event->isPropagationStopped());
      }
      else {
        $this->assertFalse($event->isExcluded());
        $this->assertFalse($event->isPropagationStopped());
      }
    }
  }

}
