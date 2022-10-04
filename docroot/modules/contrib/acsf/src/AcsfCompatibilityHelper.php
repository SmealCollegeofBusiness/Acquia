<?php

namespace Drupal\acsf;

use Drupal\file\FileInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Provides a way to have backwards compatibility for older Drupal versions.
 */
class AcsfCompatibilityHelper {

  /**
   * Generates a random password depending on the Drupal version.
   *
   * @return string
   *   A random generated password.
   */
  public static function generatePassword(): string {
    if (function_exists('user_password')) {
      // Drupal 9 or less.
      return user_password();
    }
    else {
      // Drupal 10+
      return \Drupal::service('password_generator')->generate();
    }
  }

  /**
   * Gets the file permanent status code depending on the Drupal version.
   *
   * @return int
   *   The file permanent status code.
   */
  public static function getFileStatusPermanent(): int {
    if (defined('FILE_STATUS_PERMANENT')) {
      // Drupal 9 or less.
      return FILE_STATUS_PERMANENT;
    }
    else {
      // Drupal 10+
      return FileInterface::STATUS_PERMANENT;
    }
  }

  /**
   * Gets if the KernelEvent is the main one depending on the drupal version.
   *
   * @param Symfony\Component\HttpKernel\Event\KernelEvent $event
   *   The event.
   *
   * @return bool
   *   True if the event is main, false otherwise.
   */
  public static function checkKernelEventIsMain(KernelEvent $event): bool {
    if ($event instanceof FilterResponseEvent) {
      // Drupal 9 or less.
      return $event->isMasterRequest();
    }
    elseif ($event instanceof ResponseEvent) {
      // Drupal 10+
      return $event->isMainRequest();
    }
    throw new Exception('Incompatible KernelEvent type.');
  }

}
