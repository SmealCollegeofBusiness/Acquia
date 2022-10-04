<?php

namespace Drupal\acquia_contenthub_translations\EventSubscriber\ParseCdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Facilitates tracking of entities in translation tracking table.
 */
class TrackTranslations implements EventSubscriberInterface {

  /**
   * Informs that whether translations are being created.
   *
   * Out of syndication or not.
   *
   * @var bool
   */
  public static $isSyndicating = FALSE;

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::PARSE_CDF][] = ['onParseCdf', 1000];
    return $events;
  }

  /**
   * Enables syndication flag.
   */
  public function onParseCdf(): void {
    static::$isSyndicating = TRUE;
  }

}
