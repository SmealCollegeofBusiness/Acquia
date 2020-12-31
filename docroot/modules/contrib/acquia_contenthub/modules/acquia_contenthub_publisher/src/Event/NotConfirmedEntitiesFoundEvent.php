<?php

namespace Drupal\acquia_contenthub_publisher\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class NotConfirmedEntitiesFoundEvent.
 *
 * @package Drupal\acquia_contenthub_publisher\Event
 */
class NotConfirmedEntitiesFoundEvent extends Event {

  /**
   * Tracking information about "stale" entities.
   *
   * @var array
   */
  protected $items;

  /**
   * NotConfirmedEntitiesFoundEvent constructor.
   *
   * @param array $items
   *   The list of "stale" entities.
   */
  public function __construct(array $items) {
    $this->items = $items;
  }

  /**
   * Returns the list of "stale" entities".
   *
   * @return array
   *   the list of "stale" entities.
   */
  public function getItems(): array {
    return $this->items;
  }

}
