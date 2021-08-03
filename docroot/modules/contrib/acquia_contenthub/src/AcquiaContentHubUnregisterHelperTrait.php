<?php

namespace Drupal\acquia_contenthub;

use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Trait with helper functions for unregistration.
 *
 * @package Drupal\acquia_contenthub
 */
trait AcquiaContentHubUnregisterHelperTrait {

  /**
   * Checks if Discovery Interface route exists.
   *
   * @return bool
   *   TRUE if DI route exists, FALSE otherwise.
   */
  public function checkDiscoveryRoute(): bool {
    try {
      $route_provider = \Drupal::service('router.route_provider');
      $route = $route_provider->getRouteByName('acquia_contenthub_curation.discovery');
    }
    catch (RouteNotFoundException $exception) {
      $route = FALSE;
    }

    return (bool) $route;
  }

  /**
   * Format rows for render array.
   *
   * @return array
   *   Formatted array for table component.
   */
  protected function formatOrphanedFiltersTable(array $filters): array {
    $rows = [];

    foreach ($filters as $name => $uuid) {
      $rows[] = [$name, $uuid];
    }

    return $rows;
  }

}
