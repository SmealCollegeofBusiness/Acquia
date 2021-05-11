<?php

namespace Drupal\depcalc\EventSubscriber\DependencyCollector;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Drupal\depcalc\FieldExtractor;

/**
 * Link Field Collector.
 *
 * Handles dependency calculation of menu link fields.
 */
class LinkFieldCollector extends BaseDependencyCollector {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * LinkFieldCollector constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[DependencyCalculatorEvents::CALCULATE_DEPENDENCIES][] = ['onCalculateDependencies'];
    return $events;
  }

  /**
   * Calculates menu link dependencies.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   The dependency calculation event.
   */
  public function onCalculateDependencies(CalculateEntityDependenciesEvent $event) {
    // Get the entity.
    $entity = $event->getEntity();

    // Confirm the entity is an instance of ContentEntityInterface.
    if ($entity instanceof ContentEntityInterface) {
      $fields = FieldExtractor::getFieldsFromEntity(
        $entity,
        function (ContentEntityInterface $entity, $field_name, FieldItemListInterface $field) {
          return $field->getFieldDefinition()->getType() === 'link';
        });
      if (!$fields) {
        return;
      }
      // Loop through entity fields.
      foreach ($fields as $field) {
        // Get event dependencies.
        /**
         * Loop through field items for relevant dependencies.
         *
         * @var \Drupal\link\Plugin\Field\FieldType\LinkItem $item
         */
        foreach ($field as $item) {

          // If values are empty, continue to next menu_link item.
          if ($item->isEmpty()) {
            continue;
          }

          // Check if link is external (no deps required).
          if ($item->isExternal()) {
            continue;
          }

          // Get values.
          $values = $item->getValue();

          // Explode the uri first by a colon to retrieve the link type.
          list($uri_type, $uri_reference) = explode(':', $values['uri'], 2);

          // URI handling switch.
          switch ($uri_type) {
            // Entity link.
            case 'entity';
              // Explode entity to get the type and id.
              list($entity_type, $entity_id) = explode('/', $uri_reference, 2);

              // Load the entity.
              $uri_entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
              if (!is_null($uri_entity)) {
                $entity_wrapper = new DependentEntityWrapper($uri_entity);

                // Merge and add dependencies.
                $local_dependencies = [];
                $this->mergeDependencies($entity_wrapper, $event->getStack(), $this->getCalculator()->calculateDependencies($entity_wrapper, $event->getStack(), $local_dependencies));
                $event->addDependency($entity_wrapper);
              }
              break;

            // Internal link.
            case 'internal':
              // SUPPORT FOR INTERNAL LINKS.
              $url = $item->getUrl();
              // Only add the dependency for valid routes.
              // e.g. /node/123, /admin/config etc.
              if ($url && $url->isRouted()) {
                $route_params = $url->getRouteParameters();
                if (!empty($route_params)) {
                  try {
                    $storage = $this->entityTypeManager->getStorage(key($route_params));
                  }
                  catch (\Exception $e) {
                    $storage = NULL;
                  }
                  $uri_entity = !is_null($storage) ? $storage->load(current($route_params)) : NULL;
                  if (!is_null($uri_entity)) {
                    $entity_wrapper = new DependentEntityWrapper($uri_entity);
                    // Merge and add dependencies.
                    $local_dependencies = [];
                    $this->mergeDependencies($entity_wrapper, $event->getStack(), $this->getCalculator()->calculateDependencies($entity_wrapper, $event->getStack(), $local_dependencies));
                    $event->addDependency($entity_wrapper);
                  }
                }
                else {
                  $route_name = $url->getRouteName();
                  // It's an assumption that all the route names in routing.yml
                  // will be named like module_name.route_name
                  // If that module exists and installed, it'll be added as
                  // a module dependency.
                  list($module_name) = explode('.', $route_name, 2);
                  if ($this->moduleHandler->moduleExists($module_name)) {
                    $event->getWrapper()->addModuleDependencies([$module_name]);
                  }
                }
              }
              break;
          }
        }
      }
    }
  }

}
