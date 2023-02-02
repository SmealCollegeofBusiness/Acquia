<?php

namespace Drupal\acquia_contenthub;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Syndication\SyndicationStatus;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Drupal\acquia_contenthub\Libs\InterestList\InterestListTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Responsible for connection management actions.
 *
 * @package Drupal\acquia_contenthub
 */
class ContentHubConnectionManager {

  use InterestListTrait;

  /**
   * Default cloud filter prefix.
   *
   * @var string
   */
  const DEFAULT_FILTER = 'default_filter_';

  /**
   * Error code received when trying to create a webhook that already exists.
   *
   * @var integer
   */
  const WEBHOOK_ALREADY_EXISTS = 4010;

  /**
   * The Config Factory Interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * The acquia_contenthub logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Content Hub settings.
   *
   * @var \Acquia\ContentHubClient\Settings|null
   */
  protected $settings;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * ContentHubConnectionManager constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config Factory.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   The ContentHub client factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientFactory $factory, LoggerInterface $logger, ModuleHandlerInterface $module_handler) {
    $this->configFactory = $config_factory;
    $this->client = $factory->getClient();
    $this->logger = $logger;
    $this->settings = $this->client ? $this->client->getSettings() : NULL;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Sets the client.
   *
   * It is acquired through ClientFactory:getClient() method.
   * However this can also be FALSE, therefore it is recommended to make sure
   * the client is bootstrapped before the connection manager is being used.
   *
   * @param \Acquia\ContentHubClient\ContentHubClient|false $client
   *   The Content Hub client if it's already been configured, FALSE otherwise.
   */
  public function setClient($client) {
    $this->client = $client;
  }

  /**
   * Obtains the Content Hub Admin Settings Configuration.
   *
   * @return \Drupal\Core\Config\Config
   *   The Editable Content Hub Admin Settings Configuration.
   */
  protected function getContentHubConfig() {
    return $this->configFactory->getEditable('acquia_contenthub.admin_settings');
  }

  /**
   * Registers a webhook if it has not been registered already.
   *
   * @param string $webhook_url
   *   The webhook url with to register. Provide the full route
   *   (/acquia-contenthub/webhook).
   *
   * @return array
   *   The response of the attempt.
   *
   * @throws \Exception
   */
  public function registerWebhook(string $webhook_url): array {
    $response = $this->client->addWebhook($webhook_url);
    if (isset($response['success']) && $response['success'] === FALSE) {
      if (isset($response['error']['code']) && $response['error']['code'] === self::WEBHOOK_ALREADY_EXISTS) {
        /** @var \Acquia\ContentHubClient\Webhook $wh */
        $wh = $this->client->getWebHook($webhook_url);
        $response['uuid'] = $wh->getUuid();
      }
      else {
        $this->logger->error('Unable to register Webhook URL = @url, Error @e_code: "@e_message".',
          [
            '@url' => $webhook_url,
            '@e_code' => $response['error']['code'],
            '@e_message' => $response['error']['message'],
          ]);
        return [];
      }
    }
    $this->addDefaultFilterToWebhook($response['uuid']);
    // Save Webhook Configuration.
    $this->saveWebhookConfig($response['uuid'], $webhook_url);
    return $response;
  }

  /**
   * Adds default filter to a Webhook.
   *
   * @param string $webhook_uuid
   *   The webhook UUID.
   *
   * @throws \Exception
   */
  public function addDefaultFilterToWebhook(string $webhook_uuid): void {
    $filter_name = self::DEFAULT_FILTER . $this->client->getSettings()->getName();
    $filter = $this->createDefaultFilter($filter_name);
    $list = $this->client->listFiltersForWebhook($webhook_uuid);
    if (isset($filter['uuid']) && is_array($list['data']) && in_array($filter['uuid'], $list['data'], TRUE)) {
      // The default filter is already attached to the current webhook.
      return;
    }

    // Default Filter for the current client exists but is not attached to this
    // client's webhook.
    if (!isset($filter['uuid'])) {
      return;
    }

    $response = $this->client->addFilterToWebhook($filter['uuid'], $webhook_uuid);
    if (isset($response['success']) && $response['success'] === FALSE) {
      $this->logger->error('Could not add default filter "@d_filter" to Webhook UUID = "@whuuid". Reason: "@reason"',
        [
          '@d_filter' => $filter_name,
          '@whuuid' => $webhook_uuid,
          '@reason' => $response['error']['message'],
        ]);
      return;
    }

    $this->logger->notice('Added filter "@filter" (@uuid) to Webhook UUID = "@whuuid".', [
      '@filter' => $filter_name,
      '@uuid' => $filter['uuid'],
      '@whuuid' => $webhook_uuid,
    ]);
  }

  /**
   * Updates the specified webhook on Content Hub.
   *
   * @param string $webhook_url
   *   The webhook to update.
   *
   * @return array
   *   The response of the attempt.
   *
   * @throws \Exception
   */
  public function updateWebhook(string $webhook_url): array {
    if (!$this->webhookIsRegistered($this->settings->getWebhook('url'))) {
      return $this->registerWebhook($webhook_url);
    }

    $saved_webhook_url = $this->settings->getWebhook('url');
    if ($saved_webhook_url === $webhook_url && $this->webhookIsRegistered($webhook_url)) {
      $this->logger->info('The webhook @webhook did not change. Please update the webhook and try again.', ['@webhook' => $webhook_url]);
      return [];
    }

    if ($this->webhookIsRegistered($webhook_url)) {
      $this->logger->error('The webhook @webhook has already been registered!', ['@webhook' => $webhook_url]);
      return [];
    }

    $options['url'] = $webhook_url;
    $response = $this->handleResponse($this->client->updateWebhook($this->settings->getWebhook('uuid'), $options));
    if (!isset($response['success'])) {
      $this->logger->error('Unexpected error occurred during webhook update. Response: @resp', ['@resp' => print_r($response, TRUE)]);
      return [];
    }

    if ($response['success'] === FALSE) {
      if (!isset($response['error']['message'])) {
        $this->logger->error('Unable to update URL %url, Unable to connect to Content Hub.', [
          '%url' => $webhook_url,
        ]);
        return [];
      }

      $this->logger->error('Unable to update URL %url, Error %error: %error_message.', [
        '%url' => $webhook_url,
        '%error' => $response['error']['code'],
        '%error_message' => $response['error']['message'],
      ]);
      return [];
    }

    $this->logger->notice('Webhook url @old has been successfully updated to @new',
      [
        '@old' => $this->settings->getWebhook('settings_url'),
        '@new' => $webhook_url,
      ]);

    $this->saveWebhookConfig($response['uuid'], $webhook_url);
    return $response['data'] ?? $response;
  }

  /**
   * Unregisters the client.
   *
   * @param \Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent $event
   *   ACH unregister event.
   *
   * @return bool
   *   TRUE if unregister is successful, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function unregister(AcquiaContentHubUnregisterEvent $event): bool {
    $this->settings = $this->client->getSettings();

    $success = $this->unregisterWebhook($event, TRUE);
    if (!$success) {
      $this->logger->error('Some error occurred during webhook deletion.');
      return FALSE;
    }

    $client_uuid = empty($event->getOriginUuid()) ? $this->settings->getUuid() : $event->getOriginUuid();

    $client_name = $event->getClientName();
    $resp = $this->client->deleteClient($client_uuid);
    if ($resp instanceof ResponseInterface && $resp->getStatusCode() !== Response::HTTP_OK) {
      $this->logger->error('Could not delete client: @e_message', ['@e_message' => $resp->getReasonPhrase()]);
      return FALSE;
    }

    $this->logger->notice('Successfully unregistered client @client', ['@client' => $client_name]);

    // If origin is set, then we unregister a different site, do not delete
    // the config on this.
    if (!$event->getOriginUuid()) {
      $this->getContentHubConfig()->delete();
    }

    return TRUE;
  }

  /**
   * Unregisters the webhook url assigned to this site.
   *
   * @param \Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent $event
   *   AcquiaContentHubUnregisterEvent instance.
   * @param bool $delete_orphaned_filters
   *   TRUE if orphaned filters should be deleted, FALSE otherwise.
   *
   * @return bool
   *   TRUE, if un-registration is successful, FALSE otherwise.
   */
  public function unregisterWebhook(AcquiaContentHubUnregisterEvent $event, bool $delete_orphaned_filters = FALSE): bool {
    $resp = $this->client->deleteWebhook($event->getWebhookUuid());
    if ($resp instanceof ResponseInterface && $resp->getStatusCode() !== Response::HTTP_OK) {
      $this->logger->error('Could not unregister webhook: @e_message', ['@e_message' => $resp->getReasonPhrase()]);
      return FALSE;
    }

    // Clears the webhook configuration.
    $this->getContentHubConfig()->clear('webhook')->save();

    $resp = $this->client->deleteFilter($event->getDefaultFilter());
    if ($resp instanceof ResponseInterface && $resp->getStatusCode() !== Response::HTTP_OK) {
      $this->logger->error('Could not delete default filter for webhook: @e_message', ['@e_message' => $resp->getReasonPhrase()]);
      return FALSE;
    }

    if ($delete_orphaned_filters) {
      foreach ($event->getOrphanedFilters() as $filter_id) {
        if ($this->client->deleteFilter($filter_id) instanceof ResponseInterface && $resp->getStatusCode() !== Response::HTTP_OK) {
          $this->logger->error('
            Could not delete orphaned filter (@filter) for webhook: @e_message',
            [
              '@e_message' => $resp->getReasonPhrase(),
              '@filter' => $filter_id,
            ]);

          return FALSE;
        }
      }
    }

    return TRUE;
  }

  /**
   * Check if client successfully registered.
   *
   * Check client first if needed before any action.
   *
   * @return $this
   *   Returns itself for the sake of chainability.
   *
   * @throws \RuntimeException
   * @throws \Exception
   */
  public function checkClient(): self {
    if (!$this->client instanceof ContentHubClient) {
      throw new \RuntimeException('Client is not configured.');
    }

    $resp = $this->client->ping();
    if ($resp->getStatusCode() !== 200) {
      throw new \RuntimeException('Client could not reach Content Hub.');
    }

    return $this;
  }

  /**
   * Creates default filter for the site.
   *
   * @param string $filter_name
   *   The name of the filter.
   *
   * @return array|null
   *   The response of the attempt.
   *
   * @throws \Exception
   */
  protected function createDefaultFilter(string $filter_name): ?array {
    $filter = $this->client->getFilterByName($filter_name);
    // Only create default filter if it does not exist yet for the current
    // client.
    if (empty($filter['uuid'])) {
      $site_origin = $this->client->getSettings()->getUuid();
      $filter_query = [
        'bool' => [
          'should' => [
            [
              'match' => [
                'data.attributes.channels.value.und' => $site_origin,
              ],
            ],
            [
              'match' => [
                'data.origin' => $site_origin,
              ],
            ],
          ],
        ],
      ];
      $filter = $this->client->putFilter($filter_query, $filter_name);
    }

    return $filter;
  }

  /**
   * Checks whether the webhook has already been registered.
   *
   * @param string $webhook_url
   *   The webhook's url.
   *
   * @return bool
   *   TRUE if the webhook is registered.
   *
   * @throws \Exception
   */
  public function webhookIsRegistered(string $webhook_url): bool {
    $resp = $this->client->getWebHook($webhook_url);
    return !empty($resp);
  }

  /**
   * Remove webhook suppression.
   *
   * @param string $webhook_uuid
   *   Webhook uuid.
   *
   * @return bool
   *   TRUE if we get the response with success TRUE value.
   */
  public function removeWebhookSuppression(string $webhook_uuid): bool {
    $response_body = $this->client->unSuppressWebhook($webhook_uuid);

    if (!empty($response_body) && $response_body['success'] === TRUE) {
      return TRUE;
    }

    if (empty($response_body)) {
      $this
        ->logger
        ->error('DELETE request against webhook suppression endpoint returned with an empty body.');

      return FALSE;
    }

    $this
      ->logger
      ->error('Could not register with environment variables: @e_message', ['@e_message' => $response_body['error']['message']]);

    return FALSE;
  }

  /**
   * Suppress webhook.
   *
   * @param string $webhook_uuid
   *   Webhook uuid.
   *
   * @return bool
   *   TRUE if we get the response with success TRUE value.
   */
  public function suppressWebhook(string $webhook_uuid): bool {
    $response_body = $this->client->suppressWebhook($webhook_uuid);

    if (!empty($response_body) && $response_body['success'] === TRUE) {
      return TRUE;
    }

    if (empty($response_body)) {
      $this
        ->logger
        ->error('PUT request against webhook suppression endpoint returned with an empty body.');

      return FALSE;
    }

    $this
      ->logger
      ->error('Something went wrong during webhook suppression: @e_message', ['@e_message' => $response_body['error']['message']]);

    return FALSE;
  }

  /**
   * Handles incoming response.
   *
   * A response can either contain a json body which has the data,
   * or a reason phrase containing the error message. In some cases the latter
   * one can also come in an array structure.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response object.
   *
   * @return array
   *   The response decoded into array.
   */
  protected function handleResponse(ResponseInterface $response): array {
    $body = json_decode((string) $response->getBody(), TRUE);
    if (empty($body)) {
      return [
        'success' => FALSE,
        'error' => [
          'message' => $response->getReasonPhrase(),
        ],
      ];
    }

    return $body;
  }

  /**
   * Saves webhook modifications to configuration.
   *
   * @param string $uuid
   *   Webhook uuid.
   * @param string $url
   *   Webhook url.
   */
  protected function saveWebhookConfig(string $uuid, string $url): void {
    $wh_path = Url::fromRoute('acquia_contenthub.webhook')->toString();
    $settings_url = str_replace($wh_path, '', $url);
    $webhook = [
      'uuid' => $uuid,
      'url' => $url,
      'settings_url' => $settings_url,
    ];
    $this->getContentHubConfig()->set('webhook', $webhook)->save();
  }

  /**
   * Synchronizes this webhook's interest list with tracking table.
   */
  public function syncWebhookInterestListWithTrackingTables(): void {
    $config = $this->getContentHubConfig();
    $webhook_uuid = $config->get('webhook.uuid');
    $send_update = $config->get('send_contenthub_updates') ?? TRUE;

    if ($this->moduleHandler->moduleExists('acquia_contenthub_subscriber')) {
      $this->syncSubscriber($webhook_uuid, $send_update);
    }

    if ($this->moduleHandler->moduleExists('acquia_contenthub_publisher')) {
      $this->syncPublisher($webhook_uuid, $send_update);
    }
  }

  /**
   * Syncs subscriber tracking table and interest list.
   *
   * @param string $webhook_uuid
   *   Webhook uuid to send the interest list to.
   * @param bool $send_update
   *   Update control.
   */
  public function syncSubscriber(string $webhook_uuid, bool $send_update): void {
    $uuids = $this->getTrackedItemsFromSubscriber();
    if (empty($uuids) || !$send_update) {
      return;
    }

    $interest_list = $this->buildInterestList(
      $uuids,
      SyndicationStatus::IMPORT_SUCCESSFUL,
      'manual'
    );
    $this->client->addEntitiesToInterestListBySiteRole($webhook_uuid, 'SUBSCRIBER', $interest_list);
    $this->logger->notice(sprintf(
      'Added %d imported entities to interest list for webhook uuid = "%s".',
      count($uuids),
      $webhook_uuid
    ));
  }

  /**
   * Syncs publisher tracking table and interest list.
   *
   * @param string $webhook_uuid
   *   Webhook uuid to send the interest list to.
   * @param bool $send_update
   *   Update control.
   */
  public function syncPublisher(string $webhook_uuid, bool $send_update): void {
    $uuids = $this->getConfirmedTrackedItemsFromPublisher();
    if (empty($uuids) || !$send_update) {
      return;
    }

    $interest_list = $this->buildInterestList(
      $uuids,
      SyndicationStatus::EXPORT_SUCCESSFUL,
      'manual'
    );
    $this->client->addEntitiesToInterestListBySiteRole($webhook_uuid, 'PUBLISHER', $interest_list);
    $this->logger->notice(sprintf(
      'Added %d exported entities to interest list for webhook uuid = "%s".',
      count($uuids),
      $webhook_uuid
    ));
  }

  /**
   * Returns an array of tracked items from subscriber tracking table.
   *
   * @return array
   *   Entity uuids.
   */
  public function getTrackedItemsFromSubscriber(): array {
    $database = \Drupal::database();
    $query = $database->select('acquia_contenthub_subscriber_import_tracking', 't')
      ->fields('t', ['entity_uuid']);
    $query->condition('status', 'imported');
    $results = $query->execute()->fetchAllAssoc('entity_uuid');
    return array_keys($results);
  }

  /**
   * Returns an array of tracked items from publisher tracking table.
   *
   * @return array
   *   Entity uuids.
   */
  public function getConfirmedTrackedItemsFromPublisher(): array {
    $database = \Drupal::database();
    $query = $database->select('acquia_contenthub_publisher_export_tracking', 't')
      ->fields('t', ['entity_uuid']);
    $query->condition('status', ['imported', 'confirmed'], 'IN');
    $results = $query->execute()->fetchAllAssoc('entity_uuid');
    return array_keys($results);
  }

}
