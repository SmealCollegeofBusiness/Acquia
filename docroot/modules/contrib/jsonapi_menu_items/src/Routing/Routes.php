<?php

namespace Drupal\jsonapi_menu_items\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi_menu_items\Resource\MenuItemsResource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 *
 * Each Menu will result in a jsonapi resource at:
 * /{jsonapi_namespace}/menu_items/{menu_id}
 */
class Routes implements ContainerInjectionInterface {
  const RESOURCE_NAME = MenuItemsResource::class;
  const JSONAPI_RESOURCE_KEY = '_jsonapi_resource';

  /**
   * {@inheritdoc}
   */
  public function __construct() {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = new RouteCollection();

    $route = new Route('/%jsonapi%/menu_items/{menu}');
    $route->addDefaults([
      static::JSONAPI_RESOURCE_KEY => static::RESOURCE_NAME,
    ]);
    $route->setOption('parameters', [
      'menu' => [
        'type' => 'entity:menu',
      ],
    ]);
    $routes->add('jsonapi_menu_items.menu', $route);
    $routes->addRequirements(['_access' => 'TRUE']);

    return $routes;
  }

}
