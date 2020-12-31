<?php

namespace Drupal\acquia_contenthub_preview\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Restricts the access to the general media page.
 */
class DenyAccessSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER][] = ['onAlterRoutes', -1001];
    return $events;
  }

  /**
   * Sets access permission to false on everything but the webhook.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The collection of routes.
   */
  public function alterRoutes(RouteCollection $collection) {

    $allow_access = [
      'acquia_contenthub.webhook',
      'acquia_contenthub.admin_settings',
      'acquia_contenthub_preview.preview',
    ];

    foreach ($collection->all() as $route_name => $route) {
      if (!in_array($route_name, $allow_access)) {
        $route->setRequirements([
          '_access' => 'FALSE',
        ]);
      }
    }
  }

}
