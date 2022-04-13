<?php

namespace Drupal\acquia_contenthub_subscriber\Form;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_subscriber\ContentHubImportQueue;
use Drupal\acquia_contenthub_subscriber\ContentHubImportQueueByFilter;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The form for content hub import queues.
 *
 * @package Drupal\acquia_contenthub\Form
 */
class ContentHubImportQueueForm extends FormBase {

  /**
   * The Import Queue Service.
   *
   * @var \Drupal\acquia_contenthub_subscriber\ContentHubImportQueue
   */
  protected $importQueue;

  /**
   * Content Hub import queue by filter service.
   *
   * @var \Drupal\acquia_contenthub_subscriber\ContentHubImportQueueByFilter
   */
  protected $importByFilter;

  /**
   * The Subscriber Tracker Service.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * Client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * ContentHubImportQueueForm constructor.
   *
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubImportQueue $import_queue
   *   The Import Queue Service.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   Acquia Content Hub Client factory.
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubImportQueueByFilter $import_by_filter
   *   Content Hub import queue by filter service.
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   Acquia Content Hub Subscriber Tracker.
   */
  public function __construct(ContentHubImportQueue $import_queue, ClientFactory $client_factory, ContentHubImportQueueByFilter $import_by_filter, SubscriberTracker $tracker) {
    $this->importQueue = $import_queue;
    $this->clientFactory = $client_factory;
    $this->importByFilter = $import_by_filter;
    $this->tracker = $tracker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_contenthub_subscriber.acquia_contenthub_import_queue'),
      $container->get('acquia_contenthub.client.factory'),
      $container->get('acquia_contenthub_subscriber.acquia_contenthub_import_queue_by_filter'),
      $container->get('acquia_contenthub_subscriber.tracker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub.import_queue_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => $this->t('Instruct the content hub module to manage content syndication with a queue.'),
    ];

    $form['run_import_queue'] = [
      '#type' => 'details',
      '#title' => $this->t('Run the import queue'),
      '#description' => $this->t('<strong>For development & testing use only!</strong><br /> Running the import queue from the UI can cause php timeouts for large datasets.
                         A cronjob to run the queue should be used instead.'),
      '#open' => TRUE,
    ];

    $form['run_import_queue']['actions'] = [
      '#type' => 'action',
      '#weight' => 24,
    ];

    $queue_count = $this->importQueue->getQueueCount();

    $form['run_import_queue']['queue_list'] = [
      '#type' => 'item',
      '#title' => $this->t('Number of items in the import queue'),
      '#description' => $this->t('%num @items', [
        '%num' => $queue_count,
        '@items' => $queue_count == 1 ? 'item' : 'items',
      ]),
    ];

    $form['run_import_queue']['actions']['run'] = [
      '#type' => 'submit',
      '#name' => 'run_import_queue',
      '#value' => $this->t('Import Items'),
      '#op' => 'run',
    ];

    if ($queue_count > 0) {
      $form['run_import_queue']['purge'] = [
        '#type' => 'container',
        '#weight' => 25,
      ];

      $form['run_import_queue']['purge']['details'] = [
        '#type' => 'item',
        '#title' => $this->t('Purge existing queues'),
        '#description' => $this->t('In case there are stale / stuck items in the queue, press Purge button to clear the Import Queue.'),
      ];
      $form['run_import_queue']['purge']['action'] = [
        '#type' => 'submit',
        '#value' => $this->t('Purge'),
        '#name' => 'purge_import_queue',
      ];
    }

    $title = $this->t('Enqueue from filters');
    $form['queue_from_filters'] = [
      '#type' => 'details',
      '#title' => $title,
      '#description' => $this->t('Queue entities for import based on your custom filters'),
      '#open' => TRUE,
    ];

    $form['queue_from_filters']['actions'] = [
      '#type' => 'actions',
    ];

    $form['queue_from_filters']['actions']['import'] = [
      '#type' => 'submit',
      '#name' => 'queue_from_filters',
      '#value' => $title,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $queue_count = $this->importQueue->getQueueCount();
    $trigger = $form_state->getTriggeringElement();
    $messenger = $this->messenger();

    switch ($trigger['#name']) {
      case  'queue_from_filters':
        $filter_uuids = $this->getFilterUuids();
        if (!$filter_uuids) {
          $messenger->addMessage('No filters found!', 'warning');
          break;
        }
        $this->createAndProcessFilterQueueItems($filter_uuids);
        $messenger->addMessage('Entities got queued for import.', 'status');
        break;

      case 'run_import_queue':
        if (!empty($queue_count)) {
          $this->importQueue->processQueueItems();
        }
        else {
          $messenger->addMessage('You cannot run the import queue because it is empty.', 'warning');
        }
        break;

      case 'purge_import_queue':
        $this->importQueue->purgeQueues();
        $this->tracker->delete('status', SubscriberTracker::QUEUED);
        $this->messenger()->addMessage($this->t('Successfully purged Content Hub import queue.'));
        break;
    }
  }

  /**
   * Return the cloud filters UUIDs.
   *
   * @return array
   *   Array contains UUIDs of cloud filters.
   *
   * @throws \Exception
   */
  protected function getFilterUuids(): array {
    $client = $this->clientFactory->getClient();

    $settings = $client->getSettings();
    $webhook_uuid = $settings->getWebhook('uuid');

    if (!$webhook_uuid) {
      return [];
    }

    $filters = $client->listFiltersForWebhook($webhook_uuid);

    return $filters['data'] ?? [];
  }

  /**
   * Creates queue items from passed filter uuids and starts the processing.
   *
   * @param array $filter_uuids
   *   The list of filter uuids to process.
   */
  protected function createAndProcessFilterQueueItems(array $filter_uuids): void {
    $queue = $this->importByFilter->getQueue();

    foreach ($filter_uuids as $filter_uuid) {
      $data = new \stdClass();
      $data->filter_uuid = $filter_uuid;
      $queue->createItem($data);
    }

    $this->importByFilter->processQueueItems();
  }

}
