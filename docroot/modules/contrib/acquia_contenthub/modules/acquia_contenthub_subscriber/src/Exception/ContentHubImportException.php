<?php

namespace Drupal\acquia_contenthub_subscriber\Exception;

/**
 * An exception that occurred in some part of the Acquia Content Hub.
 */
class ContentHubImportException extends \Exception {

  /**
   * Imported UUIDs that have problems.
   *
   * @var array
   */
  protected $uuids = [];

  /**
   * Sets the list of UUIDs that have problems.
   *
   * @param array $uuids
   *   An array of UUIDs.
   */
  public function setUuids(array $uuids = []) {
    $this->uuids = $uuids;
  }

  /**
   * Returns the list of UUIDs that have issues.
   *
   * @return array
   *   An array of UUIDs.
   */
  public function getUuids() {
    return $this->uuids;
  }

  /**
   * Checks if entities are missing from Content Hub.
   *
   * @return bool
   *   TRUE if entities are missing from Content Hub.
   */
  public function isEntitiesMissing() {
    return $this->getCode() == 100;
  }

  /**
   * Checks if entities have invalid UUID.
   *
   * @return bool
   *   TRUE if entities have invalid UUID.
   */
  public function isInvalidUuid() {
    return $this->getCode() == 101;
  }

}
