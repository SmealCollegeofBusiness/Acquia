<?php

namespace Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub_subscriber\Exception\ContentHubImportException;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Queue worker for importing entities.
 *
 * @QueueWorker(
 *   id = "acquia_contenthub_subscriber_import",
 *   title = "Queue Worker to import entities from contenthub."
 * )
 */
class ContentHubImportQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The common actions object.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $common;

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $factory;

  /**
   * The Subscriber Tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $achLoggerChannel;

  /**
   * ContentHubExportQueueWorker constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Dispatcher.
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common
   *   The common actions object.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   The client factory.
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   The Subscriber Tracker.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @throws \Exception
   */
  public function __construct(EventDispatcherInterface $dispatcher, ContentHubCommonActions $common, ClientFactory $factory, SubscriberTracker $tracker, LoggerChannelFactoryInterface $logger_factory, array $configuration, $plugin_id, $plugin_definition) {

    $this->common = $common;
    if (!empty($this->common->getUpdateDbStatus())) {
      throw new \Exception("Site has pending database updates. Apply these updates before importing content.");
    }
    $this->dispatcher = $dispatcher;
    $this->factory = $factory;
    $this->tracker = $tracker;
    $this->achLoggerChannel = $logger_factory->get('acquia_contenthub_subscriber');
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('acquia_contenthub_common_actions'),
      $container->get('acquia_contenthub.client.factory'),
      $container->get('acquia_contenthub_subscriber.tracker'),
      $container->get('logger.factory'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Processes acquia_contenthub_subscriber_import queue items.
   *
   * @param mixed $data
   *   The data in the queue.
   *
   * @throws \Exception
   */
  public function processItem($data): void {
    if (!$ach_client = $this->factory->getClient()) {
      $this->achLoggerChannel->error('Acquia Content Hub client cannot be initialized because connection settings are empty.');
      return;
    }

    $settings = $this->factory->getSettings();
    $webhook = $settings->getWebhook('uuid');

    try {
      $interests = $ach_client->getInterestsByWebhook($webhook);
    }
    catch (\Exception $exception) {
      $this->achLoggerChannel->error(
        sprintf(
          'Following error occurred while we were trying to get the interest list: %s',
          $exception->getMessage()
        )
      );

      return;
    }

    $process_items = explode(', ', $data->uuids);

    // Get rid of items potentially deleted from the interest list.
    $uuids = array_intersect($process_items, $interests);
    if (count($uuids) !== count($process_items)) {
      // Log the uuids no longer on the interest list for this webhook.
      $missing_uuids = array_diff($process_items, $uuids);
      $this
        ->achLoggerChannel->info(
          sprintf(
            'Skipped importing the following missing entities: %s. This occurs when entities are deleted at the Publisher before importing.',
            implode(', ', $missing_uuids))

        );
    }

    if (!$uuids) {
      $this->achLoggerChannel->info('There are no matching entities in the queues and the site interest list.');
      return;
    }

    try {
      $stack = $this->common->importEntities(...$uuids);
      $this->factory->getClient();
    }
    catch (ContentHubImportException $e) {
      // Get UUIDs.
      $e_uuids = $e->getUuids();
      if (array_diff($uuids, $e_uuids) == array_diff($e_uuids, $uuids) && $e->isEntitiesMissing()) {
        // The UUIDs can't be imported since they aren't in the Service.
        // The missing UUIDs are the same as the ones that were sent for import.
        if ($webhook) {
          foreach ($uuids as $uuid) {
            try {
              if (!$this->tracker->getEntityByRemoteIdAndHash($uuid)) {
                // If we cannot load, delete interest and tracking record.
                $ach_client->deleteInterest($uuid, $webhook);
                $this->tracker->delete($uuid);
                $this->achLoggerChannel->info(
                  sprintf(
                    'The following entity was deleted from interest list and tracking table: %s',
                    $uuid
                  )
                );
              }
            }
            catch (\Exception $ex) {
              $this
                ->achLoggerChannel
                ->error(sprintf(
                  'Entity deletion from tracking table and interest list failed. Entity: %s. Message: %s',
                  $uuid,
                  $ex->getMessage()));
            }
            return;
          }
        }
      }
      else {
        // There are import problems but probably on dependent entities.
        $this
          ->achLoggerChannel
          ->error(sprintf('Import failed: %s.', $e->getMessage()));
        throw $e;
      }
    }

    if ($webhook) {
      try {
        $ach_client->addEntitiesToInterestList($webhook, array_keys($stack->getDependencies()));

        $this->achLoggerChannel->info(
          sprintf(
            'The following imported entities have been added to the interest list on Content Hub for webhook "%s": [%s].',
            $webhook,
            implode(', ', $uuids)
          )
        );
      }
      catch (\Exception $e) {
        $this->achLoggerChannel->error(
          sprintf(
            'Error adding the following entities to the interest list for webhook "%s": [%s]. Error message: "%s".',
            $webhook,
            implode(', ', $uuids),
            $e->getMessage()
          )
        );
      }
    }
  }

}
