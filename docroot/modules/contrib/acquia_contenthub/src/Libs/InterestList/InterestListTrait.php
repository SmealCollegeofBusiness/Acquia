<?php

namespace Drupal\acquia_contenthub\Libs\InterestList;

/**
 * Helper trait to return an interest list.
 *
 * @package Drupal\acquia_contenthub\Libs\Syndication
 */
trait InterestListTrait {

  /**
   * Returns an array containing the interests.
   *
   * @param array $uuids
   *   The entity uuids to build interest list from.
   * @param string $status
   *   The syndication status.
   * @param string|null $reason
   *   The reason of syndication, either manual or filter uuid.
   * @param string|null $event_ref
   *   The referenced event's uuid.
   *
   * @return array
   *   The interest list array containing uuids, status, reason, event ref etc.
   */
  public function buildInterestList(array $uuids, string $status, ?string $reason = NULL, ?string $event_ref = NULL): array {
    $interest_list = [];
    $interest_list['uuids'] = $uuids;
    $interest_list['status'] = $status;
    if ($reason) {
      $interest_list['reason'] = $reason;
    }
    if ($event_ref) {
      $interest_list['event_ref'] = $event_ref;
    }
    return $interest_list;
  }

}
