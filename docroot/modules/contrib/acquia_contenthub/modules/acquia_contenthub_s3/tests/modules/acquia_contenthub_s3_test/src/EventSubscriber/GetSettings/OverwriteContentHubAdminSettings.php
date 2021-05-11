<?php

namespace Drupal\acquia_contenthub_s3_test\EventSubscriber\GetSettings;

use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\AcquiaContentHubSettingsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Overrides acquia_contenthub.admin_settings.
 *
 * Use ::overwrite static method inside a test case to overwrite the Settings.
 *
 * @package Drupal\acquia_contenthub_s3_test\EventSubscriber\GetSettings
 */
class OverwriteContentHubAdminSettings implements EventSubscriberInterface {

  /**
   * Settings object containing Content Hub Client configuration.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected static $chSettings;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AcquiaContentHubEvents::GET_SETTINGS => ['onGetSettings', 100000],
    ];
  }

  /**
   * Implicitly overwrites acquia_contenthub.admin_settings.
   *
   * @param \Drupal\acquia_contenthub\Event\AcquiaContentHubSettingsEvent $event
   *   The settings altering event.
   */
  public function onGetSettings(AcquiaContentHubSettingsEvent $event) {
    $event->setSettings($this->getSettings());
    $event->stopPropagation();
  }

  /**
   * Returns the Settings object.
   *
   * @return \Acquia\ContentHubClient\Settings
   *   The Content Hub settings.
   */
  public function getSettings() {
    return static::$chSettings ?? new Settings(...array_fill(0, 5, NULL));
  }

  /**
   * Overwrite Content Hub Settings completely.
   *
   * @param \Acquia\ContentHubClient\Settings $settings
   *   The Settings to overwrite with.
   */
  public static function overwrite(Settings $settings) {
    static::$chSettings = $settings;
  }

}
