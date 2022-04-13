<?php

namespace Drupal\acquia_contenthub\EventSubscriber\FilterFields;

use Drupal\acquia_contenthub\Libs\Traits\RemoveNonPublicFilesTrait;
use Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface;
use Drupal\depcalc\DependencyCalculatorEvents;
use Drupal\depcalc\Event\FilterDependencyCalculationFieldsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class RemoveNonPublicFilesDependencies.
 *
 * Removes dependency on non-public files.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\FilterDeps
 */
class RemoveNonPublicFilesDependencies implements EventSubscriberInterface {

  use RemoveNonPublicFilesTrait;

  /**
   * The file scheme handler manager.
   *
   * @var \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface
   */
  protected $manager;

  /**
   * RemoveNonPublicFilesDependencies constructor.
   *
   * @param \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface $manager
   *   The file scheme handler manager service.
   */
  public function __construct(FileSchemeHandlerManagerInterface $manager) {
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[DependencyCalculatorEvents::FILTER_FIELDS][] = [
      'onFilterFields',
      1001,
    ];
    return $events;
  }

  /**
   * Filter fields.
   *
   * @param \Drupal\depcalc\Event\FilterDependencyCalculationFieldsEvent $event
   *   Filter Dependency Calculation Fields.
   */
  public function onFilterFields(FilterDependencyCalculationFieldsEvent $event) {
    $fields = array_filter($event->getFields(), function ($field) {
      return $this->includeField($field, $this->manager);
    });

    $event->setFields(...$fields);
  }

}
