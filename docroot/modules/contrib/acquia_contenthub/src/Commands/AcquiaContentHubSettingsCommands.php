<?php

namespace Drupal\acquia_contenthub\Commands;

use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\AcquiaContentHubSettingsEvent;
use Drupal\acquia_contenthub\EventSubscriber\GetSettings\GetSettingsFromCoreConfig;
use Drupal\acquia_contenthub\EventSubscriber\GetSettings\GetSettingsFromCoreSettings;
use Drupal\acquia_contenthub\EventSubscriber\GetSettings\GetSettingsFromEnvVar;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\Table;

/**
 * Drush commands for getting the Settings used for Acquia Content Hub.
 *
 * @package Drupal\acquia_contenthub\Commands
 */
class AcquiaContentHubSettingsCommands extends DrushCommands {

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * The Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Acquia ContentHub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * Drupal messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * AcquiaContentHubSiteCommands constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   Acquia ContentHub logger channel.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Drupal messenger interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(ClientFactory $client_factory, LoggerChannelInterface $logger_channel, MessengerInterface $messenger, ConfigFactoryInterface $configFactory) {
    $this->clientFactory = $client_factory;
    $this->configFactory = $configFactory;
    $this->loggerChannel = $logger_channel;
    $this->messenger = $messenger;
  }

  /**
   * Get Content Hub settings.
   *
   * @command acquia:contenthub-settings
   * @aliases ach-chs,acquia-contenthub-settings
   */
  public function getContentHubSettings(): void {
    $this->output->writeln('Content Hub Credentials:');
    $in_use_provider = $this->clientFactory->getProvider();
    $config_settings = $this->getSettingsFromCoreConfig();
    $core_settings = $this->getSettingsFromCoreSettings();
    $env_var_settings = $this->getSettingsFromEnvVar();
    $content = [];
    foreach ([$config_settings, $core_settings, $env_var_settings] as $setting_obj) {
      $settings = $setting_obj['settings'];
      $provider = $setting_obj['provider'];
      if (($settings instanceof Settings) && !empty($provider)) {
        $content[] = [
          $settings->getUrl() ?? '',
          $settings->getName() ?? '',
          $settings->getApiKey() ?? '',
          $settings->getSecretKey() ?? '',
          $settings->getUuid() ?? '',
          $settings->getSharedSecret() ?? '',
          $provider ?? '',
          $in_use_provider === $provider ? 'Yes' : 'No',
        ];
      }
    }

    (new Table($this->output))
      ->setHeaders([
        'Hostname',
        'Name',
        'API Key',
        'Secret Key',
        'Origin UUID',
        'Shared Secret',
        'Settings Provider',
        'In Use',
      ])
      ->setRows($content)
      ->render();
  }

  /**
   * Get settings from Drupal Configuration.
   *
   * @return array
   *   Array of settings object and settings provider.
   */
  protected function getSettingsFromCoreConfig(): array {
    $event = new AcquiaContentHubSettingsEvent();
    $config_setter = new GetSettingsFromCoreConfig($this->configFactory);
    $config_setter->onGetSettings($event);
    return $this->returnSettings($event);
  }

  /**
   * Get settings from Drupal Core Settings.
   *
   * @return array
   *   Array of settings object and settings provider.
   */
  protected function getSettingsFromCoreSettings(): array {
    $event = new AcquiaContentHubSettingsEvent();
    $core_settings_setter = new GetSettingsFromCoreSettings();
    $core_settings_setter->onGetSettings($event);
    return $this->returnSettings($event);
  }

  /**
   * Get settings from Environment variables.
   *
   * @return array
   *   Array of settings object and settings provider.
   */
  protected function getSettingsFromEnvVar(): array {
    $event = new AcquiaContentHubSettingsEvent();
    $env_var_setter = new GetSettingsFromEnvVar($this->loggerChannel, $this->messenger);
    $env_var_setter->onGetSettings($event);
    return $this->returnSettings($event);
  }

  /**
   * Extract settings from event.
   *
   * @param \Drupal\acquia_contenthub\Event\AcquiaContentHubSettingsEvent $event
   *   The settings event.
   *
   * @return array
   *   Array of settings object and settings provider.
   */
  protected function returnSettings(AcquiaContentHubSettingsEvent $event) {
    return [
      'settings' => $event->getSettings() ?? NULL,
      'provider' => $event->getProvider() ?? '',
    ];
  }

}
