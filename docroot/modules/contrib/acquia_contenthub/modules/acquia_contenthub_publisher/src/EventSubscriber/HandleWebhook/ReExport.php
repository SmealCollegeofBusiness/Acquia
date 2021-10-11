<?php

namespace Drupal\acquia_contenthub_publisher\EventSubscriber\HandleWebhook;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_publisher\PublisherActions;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Re-exports an entity and all its dependencies on a webhook request.
 *
 * @package Drupal\acquia_contenthub_preview\EventSubscriber\HandleWebhook
 */
class ReExport implements EventSubscriberInterface {

  use HandleWebhookTrait;

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

    if ('successful' !== $payload['status'] || empty($payload['uuid']) || 'republish' !== $payload['crud'] || $payload['initiator'] === $client_uuid || empty($payload['cdf'])) {
      return;
    }

    // Obtaining Entity from Webhook message.
    $uuid = $payload['cdf']['uuid'];
    $type = $payload['cdf']['type'];
    $dependencies = $payload['cdf']['dependencies'] ?? [];

    try {
      $entity = $this->entityRepository->loadEntityByUuid($type, $uuid);
    }
    catch (\Exception $exception) {
      $entity = FALSE;
    }
    if (!$entity) {
      $body = sprintf('The entity "%s:%s" could not be found and thus cannot be re-exported from a webhook request by origin = %s.', $type, $uuid, $payload['initiator']);
      $this->channel->error($body);
      $response = $this->getResponse($event, $body, 404);
    }
    else {
      $this->publisherActions->reExportEntityFull($entity, $dependencies);
      $body = sprintf('Entity "%s/%s" successfully enqueued for export from webhook UUID = %s by origin = %s.', $type, $uuid, $payload['uuid'], $payload['initiator']);
      $this->channel->info($body);
      $response = $this->getResponse($event, $body, 200);
    }
    $event->setResponse($response);
    $event->stopPropagation();
  }

}
