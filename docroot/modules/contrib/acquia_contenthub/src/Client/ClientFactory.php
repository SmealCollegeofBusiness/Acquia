<?php

namespace Drupal\acquia_contenthub\Client;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\ContentHubLoggingClient;
use Acquia\ContentHubClient\Settings;
use Acquia\Hmac\Exception\KeyNotFoundException;
use Acquia\Hmac\KeyLoader;
use Acquia\Hmac\RequestAuthenticator;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\AcquiaContentHubSettingsEvent;
use Drupal\acquia_contenthub\Exception\EventServiceUnreachableException;
use Drupal\acquia_contenthub\Libs\Common\PlatformCompatibilityChecker;
use Drupal\acquia_contenthub\Libs\Traits\HandleResponseTrait;
use Drupal\acquia_contenthub\Libs\Traits\ResponseCheckerTrait;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Instantiates an Acquia ContentHub Client object.
 *
 * @see \Acquia\ContentHubClient\ContentHub
 */
class ClientFactory {

  use HandleResponseTrait;
  use ResponseCheckerTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The contenthub client object.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * Content Hub Event logging client.
   *
   * @var \Acquia\ContentHubClient\ContentHubLoggingClient
   */
  protected $loggingClient;

  /**
   * Settings Provider.
   *
   * @var string
   */
  protected $settingsProvider;

  /**
   * Settings object.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $settings;

  /**
   * ACH Logger Channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The platform checker service.
   *
   * @var \Drupal\acquia_contenthub\Libs\Common\PlatformCompatibilityChecker
   */
  protected $platformChecker;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * Acquia ContentHub Admin Settings Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * ClientManagerFactory constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   ACH logger channel.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\acquia_contenthub\Libs\Common\PlatformCompatibilityChecker $checker
   *   The platform checker service.
   */
  public function __construct(EventDispatcherInterface $dispatcher, LoggerChannelInterface $logger, ModuleExtensionList $module_list, ConfigFactoryInterface $config_factory, PlatformCompatibilityChecker $checker) {
    $this->dispatcher = $dispatcher;
    $this->logger = $logger;
    $this->moduleList = $module_list;
    $this->platformChecker = $checker;
    $this->config = $config_factory->getEditable('acquia_contenthub.admin_settings');
    // Whenever a new client is constructed, make sure settings are invoked.
    $this->populateSettings();
  }

  /**
   * Call the event to populate contenthub settings.
   */
  protected function populateSettings() {
    $event = new AcquiaContentHubSettingsEvent();
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::GET_SETTINGS);
    $this->settings = $event->getSettings();
    $this->settingsProvider = $event->getProvider();
  }

  /**
   * Verifies whether Content Hub has been configured or not.
   *
   * @return bool
   *   TRUE if configuration is set, FALSE otherwise.
   */
  public function isConfigurationSet(Settings $settings = NULL): bool {
    $settings = $settings ?? $this->getSettings();

    // If any of these variables is empty, then we do NOT have a valid
    // connection.
    // @todo add validation for the Hostname.
    if (!$settings
      || !Uuid::isValid($settings->getUuid())
      || empty($settings->getName())
      || empty($settings->getUrl())
      || empty($settings->getApiKey())
      || empty($settings->getSecretKey())
    ) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Instantiates the content hub client.
   *
   * @param \Acquia\ContentHubClient\Settings|null $settings
   *   The Settings object or null.
   *
   * @return \Acquia\ContentHubClient\ContentHubClient|bool
   *   The ContentHub Client
   *
   * @throws \Exception
   */
  public function getClient(Settings $settings = NULL) {
    if (!$settings) {
      if (isset($this->client)) {
        return $this->platformChecker->intercept($this->client);
      }
      $settings = $this->getSettings();
    }

    if (!$this->isConfigurationSet($settings)) {
      return FALSE;
    }

    // Override configuration.
    $languages_ids = array_keys(\Drupal::languageManager()->getLanguages());
    $languages_ids[] = CDFObject::LANGUAGE_UNDETERMINED;

    $config = [
      'base_url' => $settings->getUrl(),
      'client-languages' => $languages_ids,
      'client-user-agent' => $this->getClientUserAgent(),
    ];

    $this->client = new ContentHubClient(
      $this->logger,
      $settings,
      $settings->getMiddleware(),
      $this->dispatcher,
      $config
    );

    return $this->platformChecker->intercept($this->client);
  }

  /**
   * Instantiates the content hub logging client.
   *
   * @return \Acquia\ContentHubClient\ContentHubLoggingClient|null
   *   The ContentHub Logging Client
   *
   * @throws \Exception
   */
  public function getLoggingClient(Settings $settings = NULL): ?ContentHubLoggingClient {
    if (!$settings) {
      if (isset($this->loggingClient)) {
        return $this->loggingClient;
      }
      $settings = $this->getSettings();
    }

    // If any of these variables is empty, then we do NOT have a valid
    // connection.
    // @todo add validation for the Hostname.
    if (!$settings
      || !Uuid::isValid($settings->getUuid())
      || empty($settings->getName())
      || empty($settings->getUrl())
      || empty($settings->getApiKey())
      || empty($settings->getSecretKey())
    ) {
      return NULL;
    }

    $config = [
      'base_url' => $this->getLoggingUrl(),
      'client-user-agent' => $this->getClientUserAgent(),
    ];

    $this->loggingClient = new ContentHubLoggingClient(
      $this->logger,
      $settings,
      $settings->getMiddleware(),
      $this->dispatcher,
      $config
    );

    $this->checkLoggingClient($this->loggingClient);

    return $this->loggingClient;
  }

  /**
   * Checks whether Logging Client is reachable or not.
   *
   * @param \Acquia\ContentHubClient\ContentHubLoggingClient|null $logging_client
   *   Logging client.
   *
   * @throws \Exception
   */
  public function checkLoggingClient(ContentHubLoggingClient $logging_client = NULL) {
    if (is_null($logging_client)) {
      throw new \RuntimeException('Content Hub Logging Client is not configured.');
    }

    try {
      $resp = $logging_client->ping();
    }
    catch (\Exception $e) {
      throw new EventServiceUnreachableException(sprintf('Error during contacting Event Micro Service: %s', $e->getMessage()));
    }
    if (!$this->isSuccessful($resp)) {
      throw new EventServiceUnreachableException(sprintf('Content Hub Logging Client could not reach Event Micro Service: status code: %s, body: %s', $resp->getStatusCode(), $resp->getBody()));
    }
  }

  /**
   * Returns Client's user agent.
   *
   * @return string
   *   User Agent.
   */
  protected function getClientUserAgent() {
    // Find out the module version in use.
    $module_info = $this->moduleList->getExtensionInfo('acquia_contenthub');
    $module_version = (isset($module_info['version'])) ? $module_info['version'] : '0.0.0';
    $drupal_version = (isset($module_info['core'])) ? $module_info['core'] : '0.0.0';

    return 'AcquiaContentHub/' . $drupal_version . '-' . $module_version;
  }

  /**
   * Gets the settings provider from the settings event for contenthub settings.
   *
   * @return string
   *   The name of settings' provider.
   */
  public function getProvider() {
    return $this->settingsProvider;
  }

  /**
   * Returns a settings object containing CH credentials and other related info.
   *
   * @return \Acquia\ContentHubClient\Settings
   *   ContentHub Client settings.
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * Makes a call to get a client response based on the client name.
   *
   * Note, this receives a Symfony request, but uses a PSR7 Request to Auth.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Acquia\Hmac\KeyInterface|bool
   *   Authentication Key, FALSE otherwise.
   */
  public function authenticate(Request $request) {
    if (!$this->getClient()) {
      return FALSE;
    }

    $keys = [
      $this->client->getSettings()->getApiKey() => $this->client->getSettings()->getSecretKey(),
      'Webhook' => $this->client->getSettings()->getSharedSecret(),
    ];
    $keyLoader = new KeyLoader($keys);

    $authenticator = new RequestAuthenticator($keyLoader);

    $http_message_factory = $this->createPsrFactory();
    $psr7_request = $http_message_factory->createRequest($request);

    try {
      return $authenticator->authenticate($psr7_request);
    }
    catch (KeyNotFoundException $exception) {
      $this->logger
        ->debug('HMAC validation failed. [authorization_header = %authorization_header]', [
          '%authorization_header' => $request->headers->get('authorization'),
        ]);
    }

    return FALSE;
  }

  /**
   * Wrapper for register method.
   *
   * @param string $name
   *   The client name.
   * @param string $url
   *   The content hub api hostname.
   * @param string $api_key
   *   The api key.
   * @param string $secret
   *   The secret key.
   * @param string $api_version
   *   The api version, default v1.
   *
   * @return \Acquia\ContentHubClient\ContentHubClient
   *   Return Content Hub client.
   *
   * @throws \Exception
   *
   * @see \Acquia\ContentHubClient\ContentHubClient::register()
   */
  public function registerClient(string $name, string $url, string $api_key, string $secret, string $api_version = 'v2'): ContentHubClient {
    $client = ContentHubClient::register($this->logger, $this->dispatcher, $name, $url, $api_key, $secret, $api_version);
    $this->client = $this->platformChecker->interceptAndDelete($client);
    return $this->client;
  }

  /**
   * Returns Event logging URL for given Content Hub Service URL.
   *
   * @return string
   *   Event Logging Url to use for logging. Varies with CH realm.
   *
   * @throws \Exception
   */
  public function getLoggingUrl(): string {
    if (!$this->config->get('event_service_url')) {
      $event_logging_url = $this->getEventLoggingUrlFromRemoteSettings();
      $this->saveAchAdminSettingsConfig('event_service_url', $event_logging_url);
    }

    return $this->config->get('event_service_url');
  }

  /**
   * Fetch event logging url from ACH remote settings.
   *
   * @todo proper handling of event logger exception.
   *
   * @return array|string
   *   Event logging url.
   *
   * @throws \ReflectionException
   */
  public function getEventLoggingUrlFromRemoteSettings() {
    $remote_settings = $this->getClient()->getRemoteSettings();
    if (isset($remote_settings['event_service_url'])) {
      return $remote_settings['event_service_url'];
    }

    $this->logger->error('Event logging service url not found in remote settings.');
    return '';
  }

  /**
   * Save the ACH admin settings configurations.
   *
   * @param string $key
   *   Config key.
   * @param string $value
   *   Config value.
   */
  public function saveAchAdminSettingsConfig(string $key, string $value): void {
    $this->config->set($key, $value);
    $this->config->save();
  }

}
