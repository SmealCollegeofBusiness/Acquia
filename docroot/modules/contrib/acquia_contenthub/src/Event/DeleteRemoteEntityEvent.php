<?php

namespace Drupal\acquia_contenthub\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * The event dispatched to inform of remote entity deletion.
 */
class DeleteRemoteEntityEvent extends Event {

  /**
   * The uuid of the remote entity being deleted.
   *
   * @var string
   */
  protected $uuid;

  /**
   * DeleteRemoteEntityEvent constructor.
   *
   * @param string $uuid
   *   The uuid of the deleted remote entity.
   */
  public function __construct(string $uuid) {
    $this->uuid = $uuid;
  }

  /**
   * Get the uuid of the deleted remote entity.
   *
   * @return string
   *   The uuid of the deleted remote entity.
   */
  public function getUuid() {
    return $this->uuid;
  }

}
