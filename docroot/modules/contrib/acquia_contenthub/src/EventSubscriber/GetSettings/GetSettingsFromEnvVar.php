<?php

namespace Drupal\acquia_contenthub\EventSubscriber\GetSettings;

use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\AcquiaContentHubSettingsEvent;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gets the ContentHub Server settings from environment variable.
 */
class GetSettingsFromEnvVar implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Array containing the necessary environment variable keys.
   */
  const ENVIRONMENT_VARIABLES = [
    'acquia_contenthub_api_secret',
    'acquia_contenthub_api_key',
    'acquia_contenthub_hostname',
    'acquia_contenthub_client_name',
    'acquia_contenthub_origin',
    'acquia_contenthub_shared_secret',
    'acquia_contenthub_webhook_url',
    'acquia_contenthub_webhook_uuid',
    'acquia_contenthub_settings_url',
  ];

  /**
   * Acquia ContentHub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Drupal messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * GetSettingsFromEnvVarTest constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Acquia ContentHub logger channel.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Drupal messenger interface.
   */
  public function __construct(LoggerChannelInterface $logger, MessengerInterface $messenger) {
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::GET_SETTINGS][] = ['onGetSettings', 100];
    return $events;
  }

  /**
   * Extract settings from environment and create a Settings object.
   *
   * @param \Drupal\acquia_contenthub\Event\AcquiaContentHubSettingsEvent $event
   *   The dispatched event.
   *
   * @see \Acquia\ContentHubClient\Settings
   */
  public function onGetSettings(AcquiaContentHubSettingsEvent $event) {
    $credentials = [];

    foreach (self::ENVIRONMENT_VARIABLES as $env_variable) {
      $credential = getenv($env_variable);

      if ($credential) {
        $credentials[$env_variable] = $credential;
      }
    }

    if (count($credentials) === 0) {
      // If there are no environment variables set, then we should keep going
      // and not log all the errors in isValidCredential(). We assume, the user
      // do not want to use this way of registration.
      return;
    }

    $success = $this->isValidCredential($credentials);

    if (!$success) {
      return;
    }

    $settings = new Settings(
      $credentials['acquia_contenthub_client_name'],
      $credentials['acquia_contenthub_origin'],
      $credentials['acquia_contenthub_api_key'],
      $credentials['acquia_contenthub_api_secret'],
      $credentials['acquia_contenthub_hostname'],
      $credentials['acquia_contenthub_shared_secret'],
      [
        'url' => $credentials['acquia_contenthub_webhook_url'],
        'uuid' => $credentials['acquia_contenthub_webhook_uuid'],
        'settings_url' => $credentials['acquia_contenthub_settings_url'],
      ]
    );

    $event->setProvider('environment_variable');
    $event->setSettings($settings);
    $event->stopPropagation();

  }

  /**
   * Checks credentials are all set and valid.
   *
   * @param array $credentials
   *   Credentials for registering ACH.
   *
   * @return bool
   *   TRUE if there is no error at all.
   */
  protected function isValidCredential(array $credentials): bool {
    $errors = [];

    if (count(self::ENVIRONMENT_VARIABLES) !== count($credentials)) {
      $errors[] = $this->t('Some of the credentials missing from the environment variables.');
    }

    foreach (self::ENVIRONMENT_VARIABLES as $variable) {
      if (!isset($credentials[$variable])) {
        $errors[] = $this->t('Credential missing from environment variables: @var', ['@var' => $variable]);
      }
    }

    foreach (['acquia_contenthub_hostname', 'acquia_contenthub_webhook_url'] as $url) {
      if (isset($credentials[$url]) && !UrlHelper::isValid($credentials[$url], TRUE)) {
        $errors[] = $this->t('@url is not a valid url. Please insert another one.', ['@url' => $url]);
      }
    }

    foreach ($errors as $error) {
      $this
        ->messenger
        ->addWarning($this->t('Environment variables set for registering ACH, but something went wrong. Error: @error', ['@error' => $error]));
    }

    return empty($errors);
  }

}
