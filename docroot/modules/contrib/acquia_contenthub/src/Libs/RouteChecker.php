<?php

namespace Drupal\acquia_contenthub\Libs;

/**
 * Helper class to carry out check for routes.
 *
 * @package Drupal\acquia_contenthub\Libs
 */
class RouteChecker {

  /**
   * Checks whether a route exists.
   *
   * @param string $route_name
   *   The name of the route.
   *
   * @return bool
   *   TRUE if the route exists.
   */
  public static function exists(string $route_name): bool {
    /** @var \Drupal\Core\Routing\RouteProvider $route_provider */
    $route_provider = \Drupal::service('router.route_provider');
    try {
      $route_provider->getRouteByName($route_name);
    }
    catch (\Exception $e) {
      return FALSE;
    }
    return TRUE;
  }

}
