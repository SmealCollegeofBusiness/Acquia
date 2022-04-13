<?php

namespace Drupal\acquia_contenthub_publisher\Form;

use Drupal\acquia_contenthub_publisher\ContentHubExportQueue;
use Drupal\acquia_contenthub_publisher\PublisherTracker;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a form to Process items from the Content Hub Export Queue.
 */
class ContentHubExportQueueForm extends FormBase {

  /**
   * The Export Queue Service.
   *
   * @var \Drupal\acquia_contenthub_publisher\ContentHubExportQueue
   */
  protected $exportQueue;

  /**
   * The Publisher Tracker Service.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherTracker
   */
  protected $tracker;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub.export_queue_settings';
  }

  /**
   * ContentHubExportQueueForm constructor.
   *
   * @param \Drupal\acquia_contenthub_publisher\ContentHubExportQueue $export_queue
   *   The Import Queue Service.
   * @param \Drupal\acquia_contenthub_publisher\PublisherTracker $tracker
   *   Acquia Content Hub Publisher Tracker.
   */
  public function __construct(ContentHubExportQueue $export_queue, PublisherTracker $tracker) {
    $this->exportQueue = $export_queue;
    $this->tracker = $tracker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_contenthub_publisher.acquia_contenthub_export_queue'),
      $container->get('acquia_contenthub_publisher.tracker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => $this->t('Instruct the content hub module to manage content export with a queue.'),
    ];

    $queue_count = $this->exportQueue->getQueueCount();

    $form['run_export_queue'] = [
      '#type' => 'details',
      '#title' => $this->t('Run Export Queue'),
      '#description' => $this->t('<strong>For development & testing use only!</strong><br /> Running the export queue from the UI can cause php timeouts for large datasets.
                         A cronjob to run the queue should be used instead.'),
      '#open' => TRUE,
    ];
    $form['run_export_queue']['queue-list'] = [
      '#type' => 'item',
      '#title' => $this->t('Number of queue items in the Export Queue'),
      '#description' => $this->t('%num @items.', [
        '%num' => $queue_count,
        '@items' => $queue_count === 1 ? $this->t('item') : $this->t('items'),
      ]),
    ];
    $form['run_export_queue']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export Items'),
      '#name' => 'run_export_queue',
    ];
    if ($queue_count > 0) {
      $form['run_export_queue']['purge_queue'] = [
        '#type' => 'item',
        '#title' => $this->t('Purge existing queues'),
        '#description' => $this->t('In case there are stale / stuck items in the queue press Purge button to clear the Export Queue.'),
      ];
      $form['run_export_queue']['purge'] = [
        '#type' => 'submit',
        '#value' => $this->t('Purge'),
        '#name' => 'purge_export_queue',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $queue_count = $this->exportQueue->getQueueCount();
    $trigger = $form_state->getTriggeringElement();
    switch ($trigger['#name']) {
      case 'run_export_queue':
        if (!empty($queue_count)) {
          $this->exportQueue->processQueueItems();
        }
        else {
          $this->messenger()->addWarning($this->t('You cannot run the export queue because it is empty.'));
        }
        break;

      case 'purge_export_queue':
        $this->exportQueue->purgeQueues();
        $this->tracker->delete('status', PublisherTracker::QUEUED);
        $this->messenger()->addMessage($this->t('Purged all contenthub export queues.'));
        break;

      default:
        break;
    }
  }

}
