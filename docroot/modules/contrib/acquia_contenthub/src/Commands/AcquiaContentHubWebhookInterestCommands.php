<?php

namespace Drupal\acquia_contenthub\Commands;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Syndication\SyndicationStatus;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubConnectionManager;
use Drupal\acquia_contenthub\Libs\InterestList\InterestListTrait;
use Drupal\acquia_contenthub\PubSubModuleStatusChecker;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\Table;

/**
 * Tests commands that interact with webhook interests.
 *
 * @package Drupal\acquia_contenthub\Commands
 */
class AcquiaContentHubWebhookInterestCommands extends DrushCommands {

  use InterestListTrait;

  /**
   * Allowed site Roles.
   */
  public const SITE_ROLES = [
    'PUBLISHER',
    'SUBSCRIBER',
  ];

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * The Content Hub client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * The webhook.
   *
   * @var array
   */
  protected $webhook;

  /**
   * The webhook url.
   *
   * @var string
   */
  protected $webhookUrl;

  /**
   * The webhook uuid.
   *
   * @var string
   */
  protected $webhookUuid;

  /**
   * The Content Hub Connection Manager.
   *
   * @var \Drupal\acquia_contenthub\ContentHubConnectionManager
   */
  protected $connectionManager;

  /**
   * Acquia ContentHub Admin Settings Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $chConfig;

  /**
   * Status Checker.
   *
   * @var \Drupal\acquia_contenthub\PubSubModuleStatusChecker
   */
  protected $checker;

  /**
   * AcquiaContentHubWebhookInterestCommands constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   * @param \Drupal\acquia_contenthub\ContentHubConnectionManager $connection_manager
   *   The Content Hub Connection Manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config Factory.
   * @param \Drupal\acquia_contenthub\PubSubModuleStatusChecker $checker
   *   Status checker.
   */
  public function __construct(ClientFactory $client_factory, ContentHubConnectionManager $connection_manager, ConfigFactoryInterface $config_factory, PubSubModuleStatusChecker $checker) {
    $this->clientFactory = $client_factory;
    $this->connectionManager = $connection_manager;
    $this->chConfig = $config_factory->getEditable('acquia_contenthub.admin_settings');
    $this->checker = $checker;
  }

  /**
   * Find webhook information.
   *
   * @throws \Exception
   */
  private function findWebhook():void {
    $this->client = $this->clientFactory->getClient();
    if (!$this->client) {
      throw new \Exception(dt('The Content Hub client is not connected so the webhook operations could not be performed.'));
    }

    $this->webhookUrl = $this->formatWebhookUrl($this->input()->getOptions()['webhook_url'] ?? $this->client->getSettings()->getWebhook('url'));
    $this->webhook = $this->client->getWebHook($this->webhookUrl);
    if (!$this->webhook) {
      throw new \Exception(dt('The webhook is not available so the operation could not complete.'));
    }
  }

  /**
   * Perform a webhook interest management operation.
   *
   * @command acquia:contenthub-webhook-interests-list
   * @aliases ach-wi-list
   *
   * @option webhook_url
   *   The webhook URL to use.
   * @default webhook_url null
   *
   * @usage acquia:contenthub-webhook-interests-list
   *   Displays list of registered interests for the webhook
   *
   * @throws \Exception
   */
  public function contenthubWebhookInterestsList() {
    $this->findWebhook();

    $message = sprintf('Listing Interests for webhook %s', $this->webhookUrl);
    $interests = $this->client->getInterestsByWebhook($this->webhookUuid);

    if (empty($interests)) {
      $this->output()->writeln(dt('<fg=white;bg=red;options=bold;>No interests found for webhook @url</>', ['@url' => $this->webhookUrl]));
      return;
    }

    $interests = ['Index' => 'Interest'] + $interests;

    $webhooks = array_merge(['Webhook'], array_fill(0, count($interests) - 1, $this->webhookUrl));
    $this->renderTable($message, TRUE, array_keys($interests), $interests, $webhooks);
  }

  /**
   * Perform a webhook interest management operation.
   *
   * @command acquia:contenthub-webhook-interests-add
   * @aliases ach-wi-add
   *
   * @option webhook_url
   *   The webhook URL to use.
   * @default webhook_url null
   * @option uuids
   *   The entities against which to perform the operation. Comma-separated.
   * @default uuids null
   * @option site_role
   *   The site against which to perform the operation.
   * @default site_role null
   *
   * @usage acquia:contenthub-webhook-interests-add
   *   Add the interests to the webhook
   *
   * @throws \Exception
   */
  public function contenthubWebhookInterestsAdd() {
    $this->findWebhook();

    $uuids = $this->input()->getOptions()['uuids'];
    if (empty($uuids)) {
      $this->output()->writeln(dt('<fg=white;bg=red;options=bold;>[error] Uuids are required to add interests.</>'));
      return;
    }

    $site_role = strtoupper($this->input()->getOptions()['site_role']);
    if (empty($site_role)) {
      $this->output()->writeln(dt('<fg=white;bg=red;options=bold;>[error] Site role is required to add interests.</>'));
      return;
    }

    if (!in_array($site_role, self::SITE_ROLES, TRUE)) {
      $this->output()->writeln(dt('<fg=white;bg=red;options=bold;>[error]Invalid site role.</>'));
      return;
    }

    if ($site_role === 'PUBLISHER' && !$this->checker->isPublisher()) {
      $this->output()->writeln(dt('<fg=white;bg=red;options=bold;>[error]Current site is not a publisher.</>'));
      return;
    }

    if ($site_role === 'SUBSCRIBER' && !$this->checker->isSubscriber()) {
      $this->output()->writeln(dt('<fg=white;bg=red;options=bold;>[error]Current site is not a subscriber.</>'));
      return;
    }

    $uuids = explode(',', $uuids);
    $send_update = $this->chConfig->get('send_contenthub_updates') ?? TRUE;
    if ($send_update) {

      $reason = $site_role === 'PUBLISHER' ? '' : 'manual';
      $interest_list = $this->buildInterestList($uuids, SyndicationStatus::EXPORT_SUCCESSFUL, $reason);
      $response = $this->client->addEntitiesToInterestListBySiteRole($this->webhookUuid, $site_role, $interest_list);

      if (!$response) {
        $this->output()->writeln(dt('Empty response after attempting to add entities to interest list.'));
        return;
      }

      if (200 !== $response->getStatusCode()) {
        $resp = ContentHubClient::getResponseJson($response);
        $this->output()->writeln(dt(
          'An error occurred and interests were not updated. Error message: @error',
          ['@error' => $resp['error']['message'] ?? 'Unknown error']
        ));
        return;
      }
      $this->output()->writeln(dt("\nInterests updated successfully.\n"));
    }

    $this->contenthubWebhookInterestsList();
  }

  /**
   * Perform a webhook interest management operation.
   *
   * @command acquia:contenthub-webhook-interests-delete
   * @aliases ach-wi-del
   *
   * @option webhook_url
   *   The webhook URL to use.
   * @default webhook_url null
   * @option uuids
   *   The entities against which to perform the operation. Comma-separated.
   * @default uuids null
   *
   * @usage acquia:contenthub-webhook-interests-delete
   *   Delete the interest from the webhook
   *
   * @throws \Exception
   */
  public function contenthubWebhookInterestsDelete() {
    $this->findWebhook();
    if (empty($this->input()->getOptions()['uuids'])) {
      $this->output()->writeln(dt('<fg=white;bg=red;options=bold;>[error] Uuids are required to delete interests.</>'));
      return;
    }
    $uuids = explode(',', $this->input()->getOptions()['uuids']);
    $this->output()->writeln("\n");
    $send_update = $this->chConfig->get('send_contenthub_updates') ?? TRUE;
    if ($send_update) {
      foreach ($uuids as $uuid) {
        $response = $this->client->deleteInterest($uuid, $this->webhookUuid);

        if (!$response) {
          continue;
        }

        if (200 !== $response->getStatusCode()) {
          $this->output()->writeln(
            dt('An error occurred and the interest @uuid was not removed.',
              ['@uuid' => $uuid]
            )
          );
          continue;
        }

        $this->output()->writeln(
          dt('Interest @uuid removed from webhook @webhook.', [
            '@uuid' => $uuid,
            '@webhook' => $this->webhookUrl,
          ])
        );
      }
    }

    $this->output()->writeln("\n");
    $this->contenthubWebhookInterestsList();
  }

  /**
   * Synchronizes Webhook's interest list with Entity Tracking tables.
   *
   * @command acquia:sync-interests
   * @aliases ach-wi-sync
   */
  public function syncInterestListWithTrackingTable() {
    $this->connectionManager->syncWebhookInterestListWithTrackingTables();
  }

  /**
   * Render a message and table with headers.
   *
   * @param string $message
   *   String to display above the table. Pass empty string to display nothing.
   * @param bool $use_headers
   *   Show Individual column headers?
   * @param mixed ... $cols // @codingStandardsIgnoreLine
   *   Columns of data to render into rows. Variable length.
   */
  private function renderTable(string $message, bool $use_headers = FALSE, ...$cols) {
    $rows_mapper = function (...$items) {
      return $items;
    };

    if (!empty($message)) {
      $this->output()->writeln($message);
    }

    $table = new Table($this->output());

    $headers = [];
    if ($use_headers) {
      foreach ($cols as &$col) {
        $keys = array_keys($col);
        $headers[] = $col[$keys[0]];
        unset($col[$keys[0]]);
      }
      $table->setHeaders($headers);
    }

    $rows = array_map($rows_mapper, ...$cols);

    $table->setRows($rows)
      ->render();
  }

  /**
   * Format webhook url in case of missing acquia-contenthub/webhook.
   *
   * @param string $webhook_url
   *   Webhook url to format.
   *
   * @return string
   *   Webhook url in proper format.
   */
  private function formatWebhookUrl(string $webhook_url) {
    if (!strpos($webhook_url, 'acquia-contenthub/webhook')) {
      $webhook_url .= 'acquia-contenthub/webhook';
    }
    return $webhook_url;
  }

}
