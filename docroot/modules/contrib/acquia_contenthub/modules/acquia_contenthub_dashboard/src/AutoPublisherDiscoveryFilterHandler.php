<?php

namespace Drupal\acquia_contenthub_dashboard;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubConnectionManager;
use Drupal\acquia_contenthub_dashboard\Libs\ContentHubAllowedOrigins;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Responsible for publisher discovery filter.
 *
 * @package Drupal\acquia_contenthub_dashboard
 */
class AutoPublisherDiscoveryFilterHandler {

  public const FILTER_NAME = 'client_publisher_filter';

  /**
   * The ContentHub client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $factory;

  /**
   * The Content Hub connection manager.
   *
   * @var \Drupal\acquia_contenthub\ContentHubConnectionManager
   */
  protected $chConnectionManager;

  /**
   * Content Hub client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * CH allowed origins.
   *
   * @var \Drupal\acquia_contenthub_dashboard\Libs\ContentHubAllowedOrigins
   */
  protected $chAllowedOrigins;

  /**
   * The acquia_contenthub_dashboard logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * DashboardConnectionManager constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   The ContentHub client factory.
   * @param \Drupal\acquia_contenthub\ContentHubConnectionManager $connection_manager
   *   Content Hub connection manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\acquia_contenthub_dashboard\Libs\ContentHubAllowedOrigins $ch_allowed_origins
   *   Content Hub allowed origins.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   The logger channel factory.
   *
   * @throws \Exception
   */
  public function __construct(
    ClientFactory $factory,
    ContentHubConnectionManager $connection_manager,
    ConfigFactoryInterface $config_factory,
    ContentHubAllowedOrigins $ch_allowed_origins,
    LoggerChannelInterface $logger_channel) {
    $this->factory = $factory;
    $this->chConnectionManager = $connection_manager;
    $this->configFactory = $config_factory;
    $this->chAllowedOrigins = $ch_allowed_origins;
    $this->loggerChannel = $logger_channel;
  }

  /**
   * Initializes the Connection Manager.
   */
  public function initialize() {
    if (empty($this->client)) {
      $this->client = $this->factory->getClient();
    }
  }

  /**
   * Adds filter to a Webhook.
   *
   * @param bool $auto_pub_discovery
   *   Automatic pub discovery flag.
   *
   * @throws \Exception
   */
  public function updateDefaultClientPublisherFilterToWebhook(bool $auto_pub_discovery): void {
    $this->initialize();
    $this->saveDashboardConfig('auto_publisher_discovery', $auto_pub_discovery);
    $webhook = $this->configFactory->getEditable('acquia_contenthub.admin_settings')->get('webhook');
    $webhook_uuid = $webhook['uuid'];
    if (!$auto_pub_discovery) {
      $this->removeClientPublisherFilterFromWebhook($webhook_uuid, self::FILTER_NAME);
      return;
    }
    $filter_query = [
      'bool' => [
        'must' => [
          [
            'match' => [
              'data.type' => 'client',
            ],
          ],
          [
            'match' => [
              'data.attributes.publisher.value.und' => 'true',
            ],
          ],
        ],
      ],
    ];
    $this->chConnectionManager->addDefaultFilterToWebhook($webhook_uuid);
    $additional_cors = $this->chAllowedOrigins->getAllowedOrigins();
    if (empty($additional_cors)) {
      return;
    }
    $this->saveDashboardConfig('allowed_origins', $additional_cors);
  }

  /**
   * Remove client publisher filter from webhook.
   *
   * @param string $webhook_uuid
   *   Webhook UUID.
   * @param string $filter_name
   *   Filter name.
   *
   * @throws \Exception
   */
  public function removeClientPublisherFilterFromWebhook(string $webhook_uuid, string $filter_name): void {
    $this->initialize();
    $filter = $this->client->getFilterByName($filter_name);
    if (empty($filter['uuid'])) {
      return;
    }

    try {
      $response = $this->client->removeFilterFromWebhook($filter['uuid'], $webhook_uuid);
      $message = 'Filter @filter_uuid successfully detached from webhook @webhook_uuid.';
      $context = [
        '@filter_uuid' => $filter['uuid'],
        '@webhook_uuid' => $webhook_uuid,
      ];
      $this->logResponse($response, $message, $context, 'remove_filter_from_webhook');
    }
    catch (\Exception $e) {
      $this->loggerChannel->error($e->getMessage());
    }
  }

  /**
   * Save auto publisher discovery config variable.
   *
   * @param string $key
   *   Config key.
   * @param mixed $value
   *   Config value.
   */
  public function saveDashboardConfig(string $key, $value): void {
    $config = $this->configFactory->getEditable('acquia_contenthub_dashboard.settings');
    if ($key === 'allowed_origins') {
      $existing_origins = $config->get('allowed_origins') ?? [];
      $value = array_unique(array_merge($existing_origins, $value));
    }

    $config->set($key, $value);
    $config->save();
  }

  /**
   * Log the response.
   *
   * @param mixed $response
   *   API response.
   * @param string $message
   *   Message to log.
   * @param array $context
   *   Context array.
   * @param string $action
   *   Log action.
   */
  protected function logResponse($response, string $message, array $context, string $action): void {
    if (isset($response['success']) && $response['success'] === FALSE) {
      $error_message = '';
      $error_context = [];
      if ($action === 'delete_filter') {
        $error_message = 'Filter @filter_uuid could not be deleted. Error: @error_message';
        $error_context = [
          '@filter_uuid' => $context['@filter_uuid'],
          '@error_message' => $response['error']['message'],
        ];
      }
      if ($action === 'remove_filter_from_webhook') {
        $error_message = 'Filter @filter_uuid could not be detached from webhook @webhook_uuid. Error: @error_message';
        $error_context = [
          '@filter_uuid' => $context['@filter_uuid'],
          '@webhook_uuid' => $context['@webhook_uuid'],
          '@error_message' => $response['error']['message'],
        ];
      }

      $this->loggerChannel->error($error_message, $error_context);
      return;
    }

    $this->loggerChannel->info($message, $context);
  }

}
