<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub\Libs\Traits\HandleResponseTrait;
use Drupal\acquia_contenthub_publisher\PublisherActions;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Re-exports an entity and all its dependencies on a webhook request.
 *
 * @package Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook
 */
class ReExport implements EventSubscriberInterface {

  use HandleResponseTrait;

  /**
   * The Publisher Actions Service.
   *
   * @var \Drupal\acquia_contenthub_publisher\PublisherActions
   */
  protected $publisherActions;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The acquia_contenthub_moderation logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $channel;

  /**
   * ReExport constructor.
   *
   * @param \Drupal\acquia_contenthub_publisher\PublisherActions $publisher_actions
   *   The Publisher Actions Service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The Entity Repository service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(PublisherActions $publisher_actions, EntityRepositoryInterface $entity_repository, LoggerChannelFactoryInterface $logger_factory) {
    $this->publisherActions = $publisher_actions;
    $this->entityRepository = $entity_repository;
    $this->channel = $logger_factory->get('acquia_contenthub_publisher');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::HANDLE_WEBHOOK][] =
      ['onHandleWebhook', 1000];
    return $events;
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
    $payload = $event->getPayload();
    $client = $event->getClient();
    $settings = $client->getSettings();
    $client_uuid = $settings->getUuid();

    if ('successful' !== $payload['status'] || 'republish' !== $payload['crud'] || $payload['initiator'] === $client_uuid || empty($payload['entities'])) {
      return;
    }

    // Obtaining Entities from Webhook message.
    $body = '';
    $entities_not_found = [];
    $entities_enqueued = [];
    foreach ($payload['entities'] as $entity) {
      $uuid = $entity['uuid'];
      $type = $entity['type'];
      $dependencies = $entity['dependencies'] ?? [];
      try {
        $entity = $this->entityRepository->loadEntityByUuid($type, $uuid);
      }
      catch (\Exception $exception) {
        $entity = FALSE;
      }
      if (!$entity) {
        $entities_not_found[] = "$type/$uuid";
        continue;
      }

      $entities_enqueued[] = "$type/$uuid";
      $this->publisherActions->reExportEntityFull($entity, $dependencies);
    }

    if (!empty($entities_enqueued)) {
      $body = sprintf('Entities have been successfully enqueued by origin = %s. Entities: %s.' . PHP_EOL,
        $payload['initiator'], implode(', ', $entities_enqueued));
      $this->channel->info($body);
    }

    if (!empty($entities_not_found)) {
      $body .= sprintf('The entities could not be re-exported. Requesting client: %s. Entities: %s.', $payload['initiator'], implode(', ', $entities_not_found));
      $this->channel->error($body);
    }

    $response = $this->getResponse($event, $body);
    $event->setResponse($response);
    $event->stopPropagation();
  }

}
