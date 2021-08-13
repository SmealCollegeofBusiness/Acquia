<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Subscribes to exclude Paragraph parent id.
 */
class RemoveParagraphParentId extends ExcludeContentFieldBase {

  /**
   * {@inheritdoc}
   */
  public static $priority = 101;

  /**
   * {@inheritDoc}
   */
  public function shouldExclude(ExcludeEntityFieldEvent $event): bool {
    $entity = $event->getEntity();
    return $entity instanceof Paragraph && $event->getFieldName() === 'parent_id';
  }

}
