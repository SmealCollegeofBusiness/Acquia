<?php

namespace Drupal\acquia_contenthub\Exception;

/**
 * Thrown on platform incompatibility.
 */
class PlatformIncompatibilityException extends \Exception {

  /**
   * General message of platform incompatibility error.
   *
   * @var string
   */
  public static $incompatiblePlatform = 'This version of module is not compatible with the current Content Hub Service API. ' .
   'Please downgrade the module a major version or contact Acquia Support. ' .
   'The subscription is not yet enabled for extended features.';

}
