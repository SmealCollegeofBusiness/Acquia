<?php

namespace Drupal\acquia_contenthub_translations\EventSubscriber\HandleWebhook;

use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\Client\CdfMetricsManager;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_subscriber\EventSubscriber\HandleWebhook\ImportUpdateAssets;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\acquia_contenthub_translations\Helpers\SubscriberLanguagesTrait;
use Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Imports and updates assets based on available languages.
 *
 * @package Drupal\acquia_contenthub_translations\EventSubscriber\HandleWebhook
 */
class ImportUpdateTranslatableAssets extends ImportUpdateAssets {

  use SubscriberLanguagesTrait;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The subscription tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * The acquia_contenthub_translations logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Acquia ContentHub Admin Settings Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Cdf Metrics Manager.
   *
   * @var \Drupal\acquia_contenthub\Client\CdfMetricsManager
   */
  protected $cdfMetricsManager;

  /**
   * Content Hub translations config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $translationConfig;

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Undesired language registrar.
   *
   * @var \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface
   */
  protected $registrar;

  /**
   * ImportUpdateAssets constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue factory.
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   The subscription tracker.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $subscriber_logger
   *   The logger channel factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\acquia_contenthub\Client\CdfMetricsManager $cdf_metrics_manager
   *   Cdf metrics manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $translations_logger
   *   Translations logger.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language Manager.
   * @param \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface $registrar
   *   Undesired language registrar.
   */
  public function __construct(
    QueueFactory $queue,
    SubscriberTracker $tracker,
    LoggerChannelInterface $subscriber_logger,
    ConfigFactoryInterface $config_factory,
    CdfMetricsManager $cdf_metrics_manager,
    LoggerChannelInterface $translations_logger,
    LanguageManagerInterface $language_manager,
    UndesiredLanguageRegistryInterface $registrar
  ) {
    parent::__construct($queue, $tracker, $subscriber_logger, $config_factory, $cdf_metrics_manager);
    $this->queue = $queue->get('acquia_contenthub_subscriber_import');
    $this->tracker = $tracker;
    $this->logger = $translations_logger;
    $this->config = $config_factory->get('acquia_contenthub.admin_settings');
    $this->translationConfig = $config_factory->get('acquia_contenthub_translations.settings');
    $this->cdfMetricsManager = $cdf_metrics_manager;
    $this->languageManager = $language_manager;
    $this->registrar = $registrar;
  }

  /**
   * Handles webhook events.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   The HandleWebhookEvent object.
   *
   * @throws \Exception
   */
  public function onHandleWebhook(HandleWebhookEvent $event): void {
    if (!$this->translationConfig->get('selective_language_import')) {
      parent::onHandleWebhook($event);
      return;
    }
    $payload = $event->getPayload();
    $client = $event->getClient();

    // Nothing to do or log here.
    if (!isset($payload['crud']) || $payload['crud'] !== 'update') {
      return;
    }

    if ($payload['status'] !== 'successful' || !isset($payload['assets']) || !count($payload['assets'])) {
      $this->logger
        ->info('Payload will not be processed because it is not successful or it does not have assets.
        Payload data: @payload', ['@payload' => print_r($payload, TRUE)]);
      return;
    }

    if ($payload['initiator'] === $client->getSettings()->getUuid()) {
      // Only log if we're trying to update something other than client objects.
      if ($payload['assets'][0]['type'] !== 'client') {
        $this->logger
          ->info('Payload will not be processed because its initiator is the existing client.
        Payload data: @payload', ['@payload' => print_r($payload, TRUE)]);
      }

      return;
    }

    $uuids = [];
    $types = ['drupal8_content_entity', 'drupal8_config_entity'];
    foreach ($payload['assets'] as $asset) {
      $uuid = $asset['uuid'];
      $type = $asset['type'];
      if (!in_array($type, $types, FALSE)) {
        $this->logger
          ->info('Entity with UUID @uuid was not added to the import queue because it has an unsupported type: @type',
            ['@uuid' => $uuid, '@type' => $type]
          );
        continue;
      }
      $uuids[] = $uuid;
    }

    $uuids_to_queue = [];
    $untracked_uuids = $this->tracker->getUntracked($uuids);
    $tracked_uuids = empty($untracked_uuids) ? $uuids : array_diff($uuids, $untracked_uuids);
    $uuids = empty($untracked_uuids) ? $uuids : $this->pruneEntities($untracked_uuids, $client, $uuids);
    foreach ($uuids as $uuid) {
      if (in_array($uuid, $tracked_uuids, TRUE)) {
        $status = $this->tracker->getStatusByUuid($uuid);
        if ($status === SubscriberTracker::AUTO_UPDATE_DISABLED) {
          $this->logger
            ->info('Entity with UUID @uuid was not added to the import queue because it has auto update disabled.',
              ['@uuid' => $uuid]
            );
          continue;
        }
      }
      $uuids_to_queue[] = $uuid;
      $this->tracker->queue($uuid);
      $this->logger
        ->info('Attempting to add entity with UUID @uuid to the import queue.',
          ['@uuid' => $uuid]
        );
    }
    if (empty($uuids_to_queue)) {
      return;
    }
    $item = new \stdClass();
    $item->uuids = implode(', ', $uuids_to_queue);
    $queue_id = $this->queue->createItem($item);
    if (empty($queue_id)) {
      return;
    }
    $this->tracker->setQueueItemByUuids($uuids_to_queue, $queue_id);
    $this->logger
      ->info('Entities with UUIDs @uuids added to the import queue and to the tracking table.',
        ['@uuids' => print_r($uuids, TRUE)]);
    $send_contenthub_updates = $this->config->get('send_contenthub_updates') ?? TRUE;
    if ($send_contenthub_updates) {
      $client->addEntitiesToInterestList($client->getSettings()->getWebhook('uuid'), $uuids_to_queue);
    }

    $this->cdfMetricsManager->sendClientCdfUpdates();
    // Essential so that subscriber module doesn't override this logic.
    $event->stopPropagation();
  }

  /**
   * Prunes entities on basis of languages.
   *
   * @param array $untracked_uuids
   *   Entities which are not tracked.
   * @param \Acquia\ContentHubClient\ContentHubClient $client
   *   Content hub client.
   * @param array $uuids
   *   Uuids to filter out.
   *
   * @return array
   *   Filtered out uuids based on subscriber languages.
   *
   * @throws \Exception
   */
  public function pruneEntities(array $untracked_uuids, ContentHubClient $client, array $uuids): array {
    $untracked_cdf_document = $client->getEntities($untracked_uuids);
    $enabled_languages = $this->getOriginalEnabledLanguages($this->languageManager, $this->registrar);
    $deletable_uuids = [];
    $logs = [];
    foreach ($untracked_cdf_document->getEntities() as $untracked_cdf) {
      $languages = $untracked_cdf->getMetadata()['languages'] ?? [$untracked_cdf->getMetadata()['default_language']];
      if (empty(array_intersect($languages, $enabled_languages))) {
        $deletable_uuids[] = $untracked_cdf->getUuid();
        $logs[] = $untracked_cdf->getAttribute('entity_type')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED] . ' : ' . $untracked_cdf->getUuid();
      }
    }
    if (empty($deletable_uuids)) {
      return $uuids;
    }
    $this->logger->info('These entities (@entities) were not added to import queue as these are in foreign languages.',
      ['@entities' => implode(', ', $logs)]
    );
    return array_diff($uuids, $deletable_uuids);
  }

}
