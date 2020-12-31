<?php

namespace Drupal\acquia_contenthub;

use Drupal\Core\Extension\ModuleHandler;

/**
 * Utility class; encapsulates static general-purpose methods.
 *
 * @package Drupal\acquia_contenthub
 */
class PubSubModuleStatusChecker {

  public const ACQUIA_CONTENTHUB_PUBLISHER_MODULE_ID = 'acquia_contenthub_publisher';

  public const ACQUIA_CONTENTHUB_SUBSCRIBER_MODULE_ID = 'acquia_contenthub_subscriber';

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandlerService;

  /**
   * PubSubModuleStatusChecker constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler_service
   *   Module Handler Service.
   */
  public function __construct(ModuleHandler $module_handler_service) {
    $this->moduleHandlerService = $module_handler_service;
  }

  /**
   * Determines if publisher module is installed and enabled.
   *
   * @return bool
   *   Is the module enabled?
   */
  public function isPublisher(): bool {
    return $this->moduleEnabled(self::ACQUIA_CONTENTHUB_PUBLISHER_MODULE_ID);
  }

  /**
   * Determines if subscriber module is installed and enabled.
   *
   * @return bool
   *   Is the module enabled?
   */
  public function isSubscriber(): bool {
    return $this->moduleEnabled(self::ACQUIA_CONTENTHUB_SUBSCRIBER_MODULE_ID);
  }

  /**
   * Checks if site has dual configuration (pub/sub).
   *
   * @return bool
   *   Are both modules enabled?
   */
  public function siteHasDualConfiguration(): bool {
    return $this->isSubscriber() && $this->isPublisher();
  }

  /**
   * Determines if a module is installed and enabled.
   *
   * @param string $module_id
   *   Id of the module.
   *
   * @return bool
   *   Does the module exist?
   */
  private function moduleEnabled(string $module_id): bool {
    return $this->moduleHandlerService->moduleExists($module_id);
  }

}
