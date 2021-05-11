<?php

namespace Drupal\acquia_contenthub\EventSubscriber\DependencyCollector;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Drupal\depcalc\EventSubscriber\DependencyCollector\BaseDependencyCollector;
use Drupal\redirect\Entity\Redirect;

/**
 * Collector for collecting redirect entities for content entity.
 *
 * @package Drupal\depcalc\EventSubscriber\DependencyCollector
 */
class EntityRedirectCollector extends BaseDependencyCollector {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * EntityRedirectCollector constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
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
   * Reacts on CALCULATE_DEPENDENCIES event.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   Event.
   *
   * @throws \Exception
   */
  public function onCalculateDependencies(CalculateEntityDependenciesEvent $event) {
    if (!$this->moduleHandler->moduleExists('redirect')) {
      return;
    }
    $entity = $event->getEntity();

    if ($entity->getEntityTypeId() === 'redirect' || !($entity instanceof ContentEntityInterface)) {
      return;
    }
    $redirects = $this->getRedirectEntities($entity);
    if (!empty($redirects)) {
      $event->getWrapper()->addModuleDependencies(['redirect']);
      foreach ($redirects as $redirect) {
        $this->addDependency($event, $redirect);
      }
    }
  }

  /**
   * Get list of redirect entities for given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   *
   * @return \Drupal\redirect\Entity\Redirect[]
   *   Redirect list.
   */
  protected function getRedirectEntities(EntityInterface $entity): array {
    /** @var \Drupal\redirect\RedirectRepository $redirect_service */
    $redirect_service = \Drupal::service('redirect.repository');
    try {
      $entity_url = "/{$entity->toUrl()->getInternalPath()}";
      $internal_entity_url = 'internal:' . $entity_url;
      $destination_urls = [$internal_entity_url];
      if ($this->moduleHandler->moduleExists('path_alias')) {
        $alias = \Drupal::service('path_alias.manager')->getAliasByPath($entity_url);
        $internal_alias_url = 'internal:' . $alias;
        $destination_urls = array_unique([
          $internal_alias_url,
          $internal_entity_url,
        ]);
      }
      $destination_urls[] = 'entity:' . $entity->getEntityTypeId() . '/' . $entity->id();
      return $redirect_service->findByDestinationUri($destination_urls);
    }
    catch (UndefinedLinkTemplateException | \Exception $e) {
      return [];
    }
  }

  /**
   * Adds redirect as a dependency.
   *
   * @param \Drupal\depcalc\Event\CalculateEntityDependenciesEvent $event
   *   Event.
   * @param \Drupal\redirect\Entity\Redirect $redirect
   *   Redirect.
   *
   * @throws \Exception
   */
  protected function addDependency(CalculateEntityDependenciesEvent $event, Redirect $redirect): void {
    $redirect_wrapper = new DependentEntityWrapper($redirect);
    $local_dependencies = [];
    $redirect_wrapper_dependencies = $this->getCalculator()
      ->calculateDependencies($redirect_wrapper, $event->getStack(), $local_dependencies);
    $this->mergeDependencies($redirect_wrapper, $event->getStack(), $redirect_wrapper_dependencies);
    $event->addDependency($redirect_wrapper);
  }

}
