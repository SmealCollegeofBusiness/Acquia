<?php

namespace Drupal\acquia_contenthub_subscriber\Commands;

use Acquia\ContentHubClient\Webhook;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Commands\Traits\ColorizedOutputTrait;
use Drupal\acquia_contenthub_subscriber\ContentHubImportQueue;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Config\Config;
use Drush\Commands\DrushCommands;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drush command for Acquia ContentHub Subscriber Interest List Purge.
 */
class AcquiaContentHubSubscriberInterestListCommands extends DrushCommands {

  use ColorizedOutputTrait;

  /**
   * Content Hub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient|bool
   */
  protected $client;

  /**
   * CH settings.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $settings;

  /**
   * Acquia ContentHub Admin Settings Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $chConfig;

  /**
   * The Import Queue Service.
   *
   * @var \Drupal\acquia_contenthub_subscriber\ContentHubImportQueue
   */
  protected $importQueue;

  /**
   * The Subscriber Tracker Service.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * Settings provider.
   *
   * @var string
   */
  protected $provider;

  /**
   * AcquiaContentHubSubscriberInterestListCommands constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   Client Factory.
   * @param \Drupal\Core\Config\Config $config
   *   CH Admin settings Config.
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubImportQueue $import_queue
   *   Import queue service.
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   Acquia Content Hub Subscriber Tracker.
   */
  public function __construct(
    ClientFactory $factory,
    Config $config,
    ContentHubImportQueue $import_queue,
    SubscriberTracker $tracker
  ) {
    $this->client = $factory->getClient();
    $this->settings = $factory->getSettings();
    $this->provider = $factory->getProvider();
    $this->chConfig = $config;
    $this->importQueue = $import_queue;
    $this->tracker = $tracker;
  }

  /**
   * Perform a webhook interest list purge operation for current site.
   *
   * Deletes the current webhook and reassigns all the existing filters
   * of current webhook to the newly created webhook.
   * Webhook uuid changes so it needs to be updated in the configuration.
   * If config is saved in the database, then no further action is needed
   * otherwise it needs to be updated in settings.php or settings.local.php
   * or environment variables depending on how configuration is being managed.
   * Deletes everything from import tracking table.
   * Deletes all the items in import queue.
   *
   * @command acquia:contenthub-webhook-interests-purge
   * @aliases ach-wi-purge
   *
   * @usage drush acquia:contenthub-webhook-interests-purge
   *   Purges interest list of current site's webhook.
   *
   * @throws \Exception
   */
  public function purgeInterestList(): int {
    $warning_message = dt("This operation will reinitialize the webhook changing its uuid. You will have to change the uuid in the Content Hub Settings if it's being managed in environment variables or settings.php. Are you sure?");
    if (!$this->io()->confirm($warning_message)) {
      return 1;
    }
    $webhook_uuid = $this->settings->getWebhook('uuid');
    if (!$webhook_uuid) {
      $this->stderr()->writeln($this->error(dt('Webhook does not exist. Exiting...')));
      return 2;
    }

    $webhook_url = $this->getWebhookUrl($webhook_uuid);
    $filters_list = $this->client->listFiltersForWebhook($webhook_uuid);
    $filter_uuids = $filters_list['data'] ?? [];

    if (($code = $this->deleteWebhook($webhook_uuid)) > 0) {
      return $code;
    }

    $new_webhook_uuid = $this->addNewWebhook($webhook_url);
    if (is_int($new_webhook_uuid)) {
      return $new_webhook_uuid;
    }

    if (($code = $this->attachFiltersToNewWebhook($filter_uuids, $new_webhook_uuid)) > 0) {
      return $code;
    }

    $this->purgeImportQueue();
    return 0;
  }

  /**
   * Deletes the webhook.
   *
   * @param string $webhook_uuid
   *   Webhook uuid.
   *
   * @return int
   *   Return code.
   */
  protected function deleteWebhook(string $webhook_uuid): int {
    $resp = $this->client->deleteWebhook($webhook_uuid);
    if ($resp instanceof ResponseInterface && $resp->getStatusCode() !== Response::HTTP_OK) {
      $this->stderr()->writeln($this->error(
        dt('Could not unregister webhook: @e_message',
          [
            '@e_message' => $resp->getReasonPhrase(),
          ])));
      return 3;
    }
    return 0;
  }

  /**
   * Adds new webhook and saves it in the configuration.
   *
   * @param string $webhook_url
   *   Webhook url.
   *
   * @return int|string
   *   Integer if process fails else new webhook uuid.
   *
   * @throws \Exception
   */
  protected function addNewWebhook(string $webhook_url) {
    $response = $this->client->addWebhook($webhook_url);
    $message = '';
    if (empty($response)) {
      $message = dt('Unable to add webhook %url.',
        [
          '%url' => $webhook_url,
        ]);
    }
    if (isset($response['error'])) {
      $message = dt('Unable to add webhook %url. Error %code: %message',
        [
          '%url' => $webhook_url,
          '%code' => $response['error']['code'] ?? dt('n/a'),
          '%message' => $response['error']['message'] ?? dt('n/a'),
        ]);
    }
    if (!empty($message)) {
      $this->stderr()->writeln($this->error($message));
      return 4;
    }
    $new_webhook_uuid = $response['uuid'];
    if ($this->provider === 'core_config') {
      $this->chConfig->set('webhook.uuid', $new_webhook_uuid)->save();
    }
    else {
      $this->output()->writeln($this->info(dt('Webhook uuid has been changed to %webhook_uuid. Please update the webhook uuid in %provider.',
        [
          '%webhook_uuid' => $new_webhook_uuid,
          '%provider' => $this->provider,
        ])));
    }
    return $new_webhook_uuid;
  }

  /**
   * Attaches existing filters to new webhook.
   *
   * @param array $filter_uuids
   *   Filter uuids.
   * @param string $new_webhook_uuid
   *   New webhook uuid.
   *
   * @return int
   *   Return code.
   */
  protected function attachFiltersToNewWebhook(array $filter_uuids, string $new_webhook_uuid): int {
    foreach ($filter_uuids as $filter_uuid) {
      try {
        $this->client->addFilterToWebhook($filter_uuid, $new_webhook_uuid);
      }
      catch (\Exception $e) {
        $this->stderr()->writeln($this->error(dt('Something went wrong while attaching filters to new webhook. Error: %error',
          [
            '%error' => $e->getMessage(),
          ])));
        return 5;
      }
    }
    return 0;
  }

  /**
   * Purges import queue and deletes all the rows from import tracking table.
   */
  protected function purgeImportQueue(): void {
    $this->importQueue->purgeQueues();
    $this->output()->writeln($this->info(dt('All the items in the import queue have been purged.')));
    $this->tracker->deleteAll();
    $this->output()->writeln($this->info(dt('Tracking table has been reset.')));
  }

  /**
   * Returns formatted webhook url.
   *
   * @param string $webhook_uuid
   *   Webhook uuid for which to find webhook url.
   *
   * @return string
   *   Webhook url.
   *
   * @throws \Exception
   */
  protected function getWebhookUrl(string $webhook_uuid): string {
    $webhook_url = $this->settings->getWebhook('url');
    if (!empty($webhook_url)) {
      return $webhook_url;
    }
    $webhooks = $this->client->getWebHooks();
    /** @var \Acquia\ContentHubClient\Webhook|array $webhook */
    $webhook = current(array_filter($webhooks,
      function (Webhook $webhook) use ($webhook_uuid) {
        return $webhook->getUuid() === $webhook_uuid;
      }));
    return !empty($webhook) ? $webhook->getUrl() : '';
  }

}
