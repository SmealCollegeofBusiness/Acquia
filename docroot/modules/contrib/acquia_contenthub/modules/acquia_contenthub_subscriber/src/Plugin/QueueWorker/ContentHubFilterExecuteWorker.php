<?php

namespace Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker;

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
   * Logger channel of acquia_contenthub channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * ContentHubFilterExecuteWorker constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Event dispatcher interface.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   Acquia Content Hub client factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   Logger channel interface acquia_contenthub.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(EventDispatcherInterface $dispatcher, ClientFactory $factory, LoggerChannelInterface $logger_channel, array $configuration, $plugin_id, $plugin_definition) {
    $this->dispatcher = $dispatcher;
    $this->factory = $factory;
    $this->loggerChannel = $logger_channel;
    $this->client = $factory->getClient();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('acquia_contenthub.client.factory'),
      $container->get('acquia_contenthub.logger_channel'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Initiates Scroll API request chain.
   *
   * @param string $filter_uuid
   *   Filter uuid to execute by.
   * @param int $scroll_time_window
   *   How long the scroll cursor will be retained inside memory.
   *
   * @return mixed
   *   Response from backend call.
   *
   * @throws \Exception
   */
  private function startScrollByFilter($filter_uuid, $scroll_time_window) {
    return $this->client->getResponseJson($this->client->post("filters/$filter_uuid/scroll?scroll=$scroll_time_window"));
  }

  /**
   * Continue Scroll API request chain.
   *
   * Notice: scroll id is changing continuously once you make a call.
   *
   * @param string $scroll_id
   *   Scroll id.
   * @param int $scroll_time_window
   *   How long the scroll cursor will be retained inside memory.
   *
   * @return mixed
   *   Response from backend call.
   *
   * @throws \Exception
   */
  private function continueScroll($scroll_id, $scroll_time_window) {
    $options = [
      'body' => json_encode([
        'scroll_id' => $scroll_id,
        'scroll' => $scroll_time_window,
      ]),
    ];

    return $this->client->getResponseJson($this->client->post('scroll/continue', $options));
  }

  /**
   * Cancel Scroll API request chain.
   *
   * @param string $scroll_id
   *   Scroll id.
   *
   * @return mixed
   *   Response from backend call.
   *
   * @throws \Exception
   */
  private function cancelScroll($scroll_id) {
    $options = [
      'body' => json_encode([
        'scroll_id' => [$scroll_id],
      ]),
    ];

    return $this->client->getResponseJson($this->client->delete("scroll", $options));
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
    return empty($filter_response['hits']['hits']);
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
    if (!isset($data->filter_uuid)) {
      throw new \Exception('Filter uuid not found');
    }
    $client = $this->client;
    $settings = $client->getSettings();
    $webhook_uuid = $settings->getWebhook('uuid');
    if (!$webhook_uuid) {
      $this->loggerChannel->critical('Your webhook is not properly setup. ContentHub cannot execute filters without an active webhook. Please set your webhook up properly and try again.');
      return;
    }

    $uuids = [];
    $interest_list = $client->getInterestsByWebhook($webhook_uuid);
    $scroll_time_window = 10;
    $matched_data = $this->startScrollByFilter($data->filter_uuid, $scroll_time_window);
    $is_final_page = $this->isFinalPage($matched_data);
    $uuids = array_merge($uuids, $this->extractEntityUuids($matched_data, $interest_list));

    $scroll_id = $matched_data['_scroll_id'];
    try {
      while (!$is_final_page) {
        $matched_data = $this->continueScroll($scroll_id, $scroll_time_window);
        $uuids = array_merge($uuids, $this->extractEntityUuids($matched_data, $interest_list));

        $scroll_id = $matched_data['_scroll_id'];
        $is_final_page = $this->isFinalPage($matched_data);
      }
    } finally {
      $this->cancelScroll($scroll_id);
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
      $event = new HandleWebhookEvent($request, $payload, $key, $client);
      $this->dispatcher->dispatch(AcquiaContentHubEvents::HANDLE_WEBHOOK, $event);
    }
  }

}
