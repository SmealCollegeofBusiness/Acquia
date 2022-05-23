<?php

namespace Drupal\acquia_contenthub\EventSubscriber\HandleWebhook;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\CdfMetricsManager;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class PurgeBase.
 *
 * Provides the base event subscriber class for "purge successful" webhook
 * handler for acquia_contenthub_publisher and acquia_contenthub_subscriber
 * modules.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\HandleWebhook
 */
abstract class PurgeBase implements EventSubscriberInterface {

  /**
   * The webhook's "purge" event name.
   */
  protected const PURGE = 'purge';

  /**
   * The Queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Content Hub metrics manager.
   *
   * @var \Drupal\acquia_contenthub\Client\CdfMetricsManager
   */
  protected $chMetrics;

  /**
   * PurgeBase constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   The logger factory.
   * @param \Drupal\acquia_contenthub\Client\CdfMetricsManager $cdf_metrics_manager
   *   The Content Hub metrics manager.
   */
  public function __construct(QueueFactory $queue_factory, LoggerChannelInterface $logger_channel, CdfMetricsManager $cdf_metrics_manager) {
    $this->queue = $queue_factory->get($this->getQueueName());
    $this->logger = $logger_channel;
    $this->chMetrics = $cdf_metrics_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::HANDLE_WEBHOOK][] = 'onHandleWebhook';
    return $events;
  }

  /**
   * On handle webhook event.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   The handle webhook event.
   */
  public function onHandleWebhook(HandleWebhookEvent $event) {
    $payload = $event->getPayload();
    if (empty($payload['crud']) || self::PURGE !== $payload['crud']) {
      return;
    }

    if ('successful' !== $payload['status']) {
      $this->logger->error('Failed to react on @webhook webhook (@payload).',
        [
          '@webhook' => self::PURGE,
          '@payload' => print_r($payload, TRUE),
        ]);
      return;
    }

    $this->onPurgeSuccessful();
    $this->createClientCdf();
  }

  /**
   * Reacts on "purge successful" webhook.
   */
  protected function onPurgeSuccessful() {
    // Delete queue.
    $this->queue->deleteQueue();
    $this->logger->info(
      'Queue @queue has been purged successfully.',
      ['@queue' => $this->getQueueName()]);
  }

  /**
   * Registers deleted client CDFs on purge.
   */
  protected function createClientCdf(): void {
    try {
      $this->chMetrics->sendClientCdfUpdates();
    }
    catch (\Exception $e) {
      $this->logger->warning(
        sprintf('Could not recreate client CDF after purge operation. Error: %s', $e->getMessage())
      );
    }
  }

  /**
   * Returns the queue name to delete.
   *
   * @return string
   *   Queue name.
   */
  abstract protected function getQueueName(): string;

}
