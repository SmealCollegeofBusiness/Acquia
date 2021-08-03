<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\SerializeContentField;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Drupal\acquia_contenthub\EventSubscriber\SerializeContentField\RemoveIdAndRevisionFieldSerialization;
use Drupal\Tests\acquia_contenthub\Kernel\AcquiaContentHubSerializerTestBase;

/**
 * Tests Remove ID and Revision Field Serialization.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\SerializeContentField\RemoveIdAndRevisionFieldSerialization
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\SerializeContentField
 */
class RemoveIdAndRevisionFieldSerializerTest extends AcquiaContentHubSerializerTestBase {

  /**
   * Tests the serialization of removal of ID and Revision field.
   *
   * @covers ::onSerializeContentField
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRemoveIdAndRevisionField() {
    // Create a test content type.
    $this->createContentType();
    // Create a test node.
    $node = $this->createNode();

    $settings = $this->clientFactory->getClient()->getSettings();
    $cdf = new CDFObject('drupal8_content_entity', $node->uuid(), date('c'), date('c'), $settings->getUuid());
    $remove_id_and_revision_field = new RemoveIdAndRevisionFieldSerialization();

    foreach ($node as $field_name => $field) {
      $event = new SerializeCdfEntityFieldEvent($node, $field_name, $field, $cdf);
      $remove_id_and_revision_field->onSerializeContentField($event);

      if ($field_name === 'nid' || $field_name === 'vid') {
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
