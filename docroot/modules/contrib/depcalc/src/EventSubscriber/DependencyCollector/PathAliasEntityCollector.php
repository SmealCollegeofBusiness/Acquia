<?php

namespace Drupal\depcalc\EventSubscriber\DependencyCollector;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\depcalc\Event\CalculateEntityDependenciesEvent;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

class PathAliasEntityCollector extends BaseDependencyCollector {

  /**
   * The url matcher.
   *
   * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
   */
  protected $matcher;

  public static function getSubscribedEvents() {
    $events = [];
    $events[DependencyCalculatorEvents::CALCULATE_DEPENDENCIES][] = ['onCalculateDependencies'];
    return $events;
  }

  public function __construct(UrlMatcherInterface $matcher) {
    $this->matcher = $matcher;
  }


  public function onCalculateDependencies(CalculateEntityDependenciesEvent $event) {
    // @todo remove version condition once 8.7 is no longer supported.
    if ($event->getEntity()->getEntityTypeId() === 'path_alias' && version_compare(\Drupal::VERSION, '8.8.0', '>=') && \Drupal::moduleHandler()->moduleExists('path_alias')) {
      /** @var \Drupal\path_alias\Entity\PathAlias $entity */
      $entity = $event->getEntity();
      $params = $this->matcher->match($entity->getPath());
      foreach ($params['_raw_variables']->keys() as $parameter) {
        if (!empty($params[$parameter]) && $params[$parameter] instanceof EntityInterface) {
          $entity_wrapper = new DependentEntityWrapper($params[$parameter]);
          $entity_wrapper->addDependency($event->getWrapper(), $event->getStack());
          $local_dependencies = [];
          $this->mergeDependencies($entity_wrapper, $event->getStack(), $this->getCalculator()
            ->calculateDependencies($entity_wrapper, $event->getStack(), $local_dependencies));
          $event->addDependency($entity_wrapper);
        }
      }
    }
  }

}
