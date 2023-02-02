<?php

namespace Drupal\acquia_contenthub\Libs\Logging;

use Acquia\ContentHubClient\ContentHubLoggingClient;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Exception\EventServiceUnreachableException;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Service for event logging.
 */
class ContentHubEventLogger {

  /**
   * Content Hub client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Content Hub logging client.
   *
   * @var \Acquia\ContentHubClient\ContentHubLoggingClient
   */
  protected $loggingClient;

  /**
   * ACH logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Uuid generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * Origin uuid of this site.
   *
   * @var string
   */
  protected $origin;

  /**
   * Chunk size for sending logs to event microservice.
   */
  public const CHUNK_SIZE = 1000;

  /**
   * ContentHubEventLogger constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   Content Hub Client Factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   ACH logger channel.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_gen
   *   Uuid generator.
   */
  public function __construct(ClientFactory $client_factory, LoggerChannelInterface $logger, UuidInterface $uuid_gen) {
    $this->clientFactory = $client_factory;
    $this->logger = $logger;
    $this->uuidGenerator = $uuid_gen;
    $this->origin = $this->clientFactory->getSettings()->getUuid();
  }

  /**
   * Returns logging client after initialization.
   *
   * Logs errors if event microservice is unreachable.
   *
   * @return \Acquia\ContentHubClient\ContentHubLoggingClient|null
   *   Event Logging client.
   */
  public function getLoggingClient(): ?ContentHubLoggingClient {
    if (empty($this->loggingClient)) {
      try {
        $this->loggingClient = $this->clientFactory->getLoggingClient();
      }
      catch (EventServiceUnreachableException $e) {
        $this->logger
          ->error(
            'Event service is unreachable. Error: @error',
            [
              '@error' => $e->getMessage(),
            ]
          );
      }
      catch (\RuntimeException $exc) {
        $this->logger
          ->error(
            'Trying to access event logging client before initializing it. Error: @error',
            [
              '@error' => $exc->getMessage(),
            ]
          );
      }
      catch (\Exception $exec) {
        $this->logger
          ->error(
            'Something went wrong while trying to access event logging client. Error: @error',
            [
              '@error' => $exec->getMessage(),
            ]
          );
      }
    }
    return $this->loggingClient ?? NULL;
  }

  /**
   * Logs an event by sending it to the event endpoint.
   *
   * @param string $object_id
   *   Event uuid.
   * @param string $object_type
   *   Event type: Entity, Webhook etc.
   * @param string $event_name
   *   The name of the event.
   * @param string $severity
   *   Severity for the event: error, warning etc.
   * @param string $message
   *   Error message to display.
   *
   * @return array
   *   Array of single event log with all the attributes set.
   */
  protected function getLogArray(string $object_id, string $object_type, string $event_name, string $severity, string $message): array {
    return [
      'object_id' => $object_id,
      'event_name' => $event_name,
      'object_type' => $object_type,
      'severity' => $severity,
      'content' => $message,
      'origin' => $this->origin,
    ];
  }

  /**
   * Send single event log.
   *
   * @param string $severity
   *   Severity for the event: error, warning etc.
   * @param string $message
   *   Message to display.
   * @param string $object_type
   *   Event type: Entity, Webhook etc.
   * @param string $event_name
   *   Name of the event.
   * @param string|null $object_id
   *   Event uuid. If left NULL a new uuid will be generated.
   *
   * @return string|null
   *   The object id of the event or NULL if the client is not available.
   */
  public function logEvent(string $severity, string $message, string $object_type, string $event_name, ?string $object_id = NULL): ?string {
    if (empty($this->getLoggingClient())) {
      return NULL;
    }
    $object_id = $object_id ?? $this->uuidGenerator->generate();
    $log = $this->getLogArray(
      $object_id,
      $object_type,
      $event_name,
      $severity,
      $message
    );
    $this->sendLogsToService([$log]);
    return $object_id;
  }

  /**
   * Logs an event related to entities.
   *
   * @param string $severity
   *   Severity for the event: error, warning etc.
   * @param string $message
   *   Message to display.
   * @param string $event_name
   *   Name of the event.
   * @param string|null $object_id
   *   Event uuid. If left NULL a new uuid will be generated.
   *
   * @return string|null
   *   The object id of the event or NULL if the client is not available.
   */
  public function logEntityEvent(string $severity, string $message, string $event_name, ?string $object_id = NULL): ?string {
    return $this->logEvent($severity, $message, 'Entity', $event_name, $object_id);
  }

  /**
   * Sends multiple event logs.
   *
   * E.g. sending event logs for multiple successful imports/exports.
   *
   * @param string $severity
   *   Severity for the event: error, warning, info etc.
   * @param array $logs
   *   Array of events: event uuid => message.
   * @param string $object_type
   *   Event type: Entity, Webhook etc.
   * @param string $event_name
   *   Name of the event.
   */
  public function logMultipleEvents(string $severity, array $logs, string $object_type, string $event_name): void {
    if (empty($this->getLoggingClient())) {
      return;
    }
    // Sending in chunks of 1000 to decrease payload size.
    $chunks = array_chunk($logs, self::CHUNK_SIZE, TRUE);
    foreach ($chunks as $chunk) {
      $event_logs = [];
      foreach ($chunk as $object_id => $message) {
        $event_logs[] = $this->getLogArray($object_id, $object_type, $event_name, $severity, $message);
      }
      $this->sendLogsToService($event_logs);
    }
  }

  /**
   * Logs multiple events related to entities for given set of uuids.
   *
   * @param string $severity
   *   Severity for the event: error, warning etc.
   * @param array $logs
   *   Array of events: event uuid => message.
   * @param string $event_name
   *   Name of the event.
   */
  public function logMultipleEntityEvents(string $severity, array $logs, string $event_name): void {
    $this->logMultipleEvents($severity, $logs, 'Entity', $event_name);
  }

  /**
   * Helper method to send individual log to event microservice.
   *
   * @param array $logs
   *   Array of logs.
   */
  protected function sendLogsToService(array $logs): void {
    // At this point logging client will definitely be
    // not null so not applying the same check here
    // as applied in logEvent and logMultipleEvents.
    try {
      $this->loggingClient
        ->sendLogs($logs);
    }
    catch (\Exception $e) {
      $this->logger
        ->error(
          'Something went wrong while sending logs to event microservice. Error: @error',
          [
            '@error' => $e->getMessage(),
          ]
        );
    }
  }

}
