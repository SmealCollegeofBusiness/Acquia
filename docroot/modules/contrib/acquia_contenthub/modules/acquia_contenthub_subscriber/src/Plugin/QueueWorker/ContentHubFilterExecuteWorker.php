<?php

namespace Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Queue worker for entities from Content Hub filters.
 *
 * @QueueWorker(
 *   id = "acquia_contenthub_import_from_filters",
 *   title = "Queue Worker to import entities identified by cloud filters."
 * )
 */
class ContentHubFilterExecuteWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Amount of entities to fetch in a scroll.
   */
  protected const SCROLL_SIZE = 100;

  /**
   * How long the scroll cursor will be retained inside memory.
   */
  protected const SCROLL_TIME_WINDOW = '10';

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * Acquia Content Hub Client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $factory;

  /**
   * Acquia Content Hub client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * Logger channel of acquia_contenthub_subscriber channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * Whether the account in hand is featured or not.
   *
   * This flag is configured in Content Hub Service.
   *
   * @var bool
   */
  private $isFeatured;

  /**
   * ContentHubFilterExecuteWorker constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Event dispatcher interface.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   Acquia Content Hub client factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   Logger channel interface acquia_contenthub_subscriber.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(EventDispatcherInterface $dispatcher, ClientFactory $factory, LoggerChannelInterface $logger_channel, array $configuration, string $plugin_id, $plugin_definition) {
    $this->dispatcher = $dispatcher;
    $this->factory = $factory;
    $this->loggerChannel = $logger_channel;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('acquia_contenthub.client.factory'),
      $container->get('acquia_contenthub_subscriber.logger_channel'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Extract entity uuids from filter response.
   *
   * @param array $filter_response
   *   The response from startScrollByFilter or continueScroll.
   *
   * @return array
   *   Returns entity UUIDs from filter response.
   */
  private function extractEntityUuids(array $filter_response): array {
    $uuids = [];

    if ($this->isFinalPage($filter_response)) {
      return [];
    }

    foreach ($filter_response['hits']['hits'] as $item) {
      $uuids[$item['_source']['uuid']] = [
        'uuid' => $item['_source']['uuid'],
        'type' => $item['_source']['data']['type'],
      ];
    }

    return $uuids;
  }

  /**
   * Checks whether filter response reach the final page.
   *
   * @param array $filter_response
   *   The response from startScrollByFilter or continueScroll.
   *
   * @return bool
   *   If this is the final return TRUE, FALSE otherwise.
   */
  private function isFinalPage(array $filter_response): bool {
    return empty($filter_response['hits']['hits']) || !isset($filter_response['_scroll_id']);
  }

  /**
   * Processes acquia_contenthub_import_from_filters queue items.
   *
   * @param mixed $data
   *   The data in the queue.
   *
   * @throws \Exception
   */
  public function processItem($data): void {
    if (empty($data->filter_uuid)) {
      $this->loggerChannel->error('Filter uuid not found.');
      return;
    }
    $this->initialiseClient();

    $settings = $this->client->getSettings();
    $webhook_uuid = $settings->getWebhook('uuid');
    if (!$webhook_uuid) {
      $this->loggerChannel->critical('Your webhook is not properly setup. ContentHub cannot execute filters without an active webhook. Please set your webhook up properly and try again.');
      return;
    }

    $scroll_time_window = $this->getNormalizedScrollTimeWindowValue($this->client);
    $uuids = [];
    $interest_list = $this->client->getInterestsByWebhook($webhook_uuid);
    $matched_data = $this->client->startScrollByFilter($data->filter_uuid, $scroll_time_window, self::SCROLL_SIZE);
    $is_final_page = $this->isFinalPage($matched_data);
    $uuids = array_merge($uuids, $this->extractEntityUuids(array_merge($matched_data, $interest_list)));

    $previous_scroll_id = $scroll_id = $matched_data['_scroll_id'];
    try {
      while (!$is_final_page) {
        $matched_data = $this->client->continueScroll($scroll_id, $scroll_time_window);
        $uuids = array_merge($uuids, $this->extractEntityUuids(array_merge($matched_data, $interest_list)));

        $scroll_id = $matched_data['_scroll_id'];
        if (!empty($scroll_id)) {
          $previous_scroll_id = $scroll_id;
        }
        $is_final_page = $this->isFinalPage($matched_data);
      }
    } finally {
      if (!empty($previous_scroll_id)) {
        $this->client->cancelScroll($previous_scroll_id);
      }
    }

    if (!$uuids) {
      return;
    }

    $chunks = array_chunk($uuids, 50);
    foreach ($chunks as $chunk) {
      $payload = [
        'status' => 'successful',
        'crud' => 'update',
        'assets' => $chunk,
        'initiator' => 'some-initiator',
      ];

      $request = new Request([], [], [], [], [], [], json_encode($payload));
      $key = new Key($settings->getApiKey(), $settings->getSecretKey());
      $event = new HandleWebhookEvent($request, $payload, $key, $this->client);
      $this->dispatcher->dispatch(AcquiaContentHubEvents::HANDLE_WEBHOOK, $event);
    }
  }

  /**
   * Returns the scroll window time value based on the account in hand.
   *
   * @return string
   *   The correct value for scroll query param based on whether a given account
   *   is featured.
   *
   * @throws \Exception
   */
  public function getNormalizedScrollTimeWindowValue(ContentHubClient $client): string {
    return $this->accountIsFeatured($client) ? self::SCROLL_TIME_WINDOW . 'm' : self::SCROLL_TIME_WINDOW;
  }

  /**
   * Initialises Content Hub client.
   *
   * @throws \Exception
   */
  protected function initialiseClient(): void {
    if (empty($this->client)) {
      $this->client = $this->factory->getClient();
    }
  }

  /**
   * Whether the account in hand is featured or not.
   *
   * This flag is configured in Content Hub Service.
   *
   * @return bool
   *   True if account is featured.
   *
   * @throws \Exception
   */
  protected function accountIsFeatured(ContentHubClient $client): bool {
    if (empty($this->isFeatured)) {
      $this->isFeatured = $client->isFeatured();
    }
    return $this->isFeatured;
  }

}
