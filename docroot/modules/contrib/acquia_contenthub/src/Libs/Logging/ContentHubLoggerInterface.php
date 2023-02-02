<?php

namespace Drupal\acquia_contenthub\Libs\Logging;

use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Content Hub unified logger.
 *
 * Logs to the specified arbitrary channel and the event service. Meant to
 * minimise code duplicates around dual logging.
 *
 * @package Drupal\acquia_contenthub\Libs\Logging
 */
interface ContentHubLoggerInterface {

  /**
   * Returns the logger channel.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger channel instance.
   */
  public function getChannel(): LoggerChannelInterface;

  /**
   * Returns the event logger service.
   *
   * @return \Drupal\acquia_contenthub\Libs\Logging\ContentHubEventLogger
   *   The event logger service instance.
   */
  public function getEvent(): ContentHubEventLogger;

  /**
   * Logs the message as error and failure in event service.
   *
   * Both in event service and watchdog. Parameter semantics is the same as in
   * case of translatables. Returns the event reference.
   *
   * @param string $message
   *   The error message to log.
   * @param array $context
   *   The string context.
   *
   * @return string|null
   *   The object id of the event or null.
   */
  public function logError(string $message, array $context): ?string;

  /**
   * Logs the message as warning and failure in event service.
   *
   * Both in event service and watchdog. Parameter semantics is the same as in
   * case of translatables. Returns the event reference.
   *
   * @param string $message
   *   The error message to log.
   * @param array $context
   *   The string context.
   *
   * @return string|null
   *   The object id of the event or null.
   */
  public function logWarning(string $message, array $context): ?string;

}
