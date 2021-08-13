<?php

namespace Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class ExcludeContentFieldBase.
 *
 * Base class to exclude content field from being added to the
 * serialized output.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\ExcludeContentField
 */
abstract class ExcludeContentFieldBase implements EventSubscriberInterface {

  /**
   * Priority of the subscriber.
   *
   * @var int
   */
  public static $priority = 0;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::EXCLUDE_CONTENT_ENTITY_FIELD][] =
      ['excludeContentField', self::$priority];
    return $events;
  }

  /**
   * Prevent entity fields from being added to the serialized output.
   *
   * @param \Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent $event
   *   The content entity field serialization event.
   */
  abstract public function shouldExclude(ExcludeEntityFieldEvent $event): bool;

  /**
   * Sets the "exclude" flag.
   *
   * @param \Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent $event
   *   The content entity field serialization event.
   */
  public function excludeContentField(ExcludeEntityFieldEvent $event) {
    if ($this->shouldExclude($event)) {
      $event->exclude();
      $event->stopPropagation();
    }
  }

}
