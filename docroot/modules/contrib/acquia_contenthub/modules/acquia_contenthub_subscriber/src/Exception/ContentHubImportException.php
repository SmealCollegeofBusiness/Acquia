<?php

namespace Drupal\acquia_contenthub_subscriber\Exception;

/**
 * An exception that occurred in some part of the Acquia Content Hub.
 */
class ContentHubImportException extends \Exception {

  public const MISSING_ENTITIES = 100;
  public const INVALID_UUID = 101;
  public const PARTIAL_IMPORT = 102;
  public const INFINITE_RECURSION = 103;

  /**
   * Imported UUIDs that have problems.
   *
   * @var array
   */
  protected $uuids = [];

  /**
   * Uuid triggering the missing entity/missing dependency error.
   *
   * Format uuid => error_message.
   *
   * @var array
   */
  protected $triggeringUuids = [];

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
   * Sets the error triggering Uuid.
   *
   * @param array $triggering_uuids
   *   Uuid triggering the error.
   */
  public function setTriggeringUuids(array $triggering_uuids):void {
    $this->triggeringUuids = $triggering_uuids;
  }

  /**
   * Returns the error triggering Uuid array.
   *
   * @return array
   *   Array of Uuid triggering the error.
   */
  public function getTriggeringUuids(): array {
    return $this->triggeringUuids;
  }

  /**
   * Checks if entities are missing from Content Hub.
   *
   * @return bool
   *   TRUE if entities are missing from Content Hub.
   */
  public function isEntitiesMissing() {
    return $this->getCode() == self::MISSING_ENTITIES;
  }

  /**
   * Checks if entities have invalid UUID.
   *
   * @return bool
   *   TRUE if entities have invalid UUID.
   */
  public function isInvalidUuid() {
    return $this->getCode() == self::INVALID_UUID;
  }

}
