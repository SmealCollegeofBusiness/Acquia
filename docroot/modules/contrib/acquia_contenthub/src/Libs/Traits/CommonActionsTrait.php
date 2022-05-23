<?php

namespace Drupal\acquia_contenthub\Libs\Traits;

/**
 * Helper trait containing methods for both publisher and subscriber actions.
 */
trait CommonActionsTrait {

  /**
   * Update db status.
   *
   * @return array
   *   Returns a list of all the pending database updates..
   */
  public function getUpdateDbStatus(): array {
    require_once DRUPAL_ROOT . "/core/includes/install.inc";
    require_once DRUPAL_ROOT . "/core/includes/update.inc";

    drupal_load_updates();

    return \update_get_update_list();
  }

}
