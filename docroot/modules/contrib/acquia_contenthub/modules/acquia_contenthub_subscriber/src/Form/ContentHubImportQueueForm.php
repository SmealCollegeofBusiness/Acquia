<?php

namespace Drupal\acquia_contenthub_subscriber\Form;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_subscriber\ContentHubImportQueue;
use Drupal\acquia_contenthub_subscriber\ContentHubImportQueueByFilter;
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
  protected $contentHubImportQueue;

  /**
   * Acquia Content Hub Client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Content Hub import queue by filter service.
   *
   * @var \Drupal\acquia_contenthub_subscriber\ContentHubImportQueueByFilter
   */
  protected $importByFilter;

  /**
   * ContentHubImportQueueForm constructor.
   *
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubImportQueue $import_queue
   *   The Import Queue Service.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   Acquia Content Hub Client factory.
   * @param \Drupal\acquia_contenthub_subscriber\ContentHubImportQueueByFilter $import_by_filter
   *   Content Hub import queue by filter service.
   */
  public function __construct(ContentHubImportQueue $import_queue, ClientFactory $client_factory, ContentHubImportQueueByFilter $import_by_filter) {
    $this->contentHubImportQueue = $import_queue;
    $this->clientFactory = $client_factory;
    $this->importByFilter = $import_by_filter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_contenthub_subscriber.acquia_contenthub_import_queue'),
      $container->get('acquia_contenthub.client.factory'),
      $container->get('acquia_contenthub_subscriber.acquia_contenthub_import_queue_by_filter')
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
      '#description' => '<strong>For development & testing use only!</strong><br /> Running the import queue from the UI can cause php timeouts for large datasets.
                         A cronjob to run the queue should be used instead.',
      '#open' => TRUE,
    ];

    $form['run_import_queue']['actions'] = [
      '#type' => 'actions',
    ];

    $queue_count = $this->contentHubImportQueue->getQueueCount();

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
      '#value' => $this->t('Run import queue'),
      '#op' => 'run',
    ];

    $title = $this->t('Queue from filters');
    $form['queue_from_filters'] = [
      '#type' => 'details',
      '#title' => $title,
      '#description' => 'Queue entities for import based on your custom filters',
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
    $queue_count = intval($this->contentHubImportQueue->getQueueCount());
    $trigger = $form_state->getTriggeringElement();
    $messenger = $this->messenger();

    switch ($trigger['#name']) {
      case  'queue_from_filters':
        $filter_uuids = $this->getFilterUuids();
        if (!$filter_uuids) {
          $messenger->addMessage('No filters found!', 'warning');
          break;
        }

        $this->importByFilter->process($filter_uuids);
        $messenger->addMessage('Entities got queued for import.', 'status');
        break;

      case 'run_import_queue':
        if (!empty($queue_count)) {
          $this->contentHubImportQueue->process();
        }
        else {
          $messenger->addMessage('You cannot run the import queue because it is empty.', 'warning');
        }
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

}
