<?php

namespace Drupal\acquia_contenthub\Libs\Logging;

use Acquia\ContentHubClient\Syndication\SyndicationEvents;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Base class for Content Hub logging implementations.
 *
 * @package Drupal\acquia_contenthub\Libs\Logging
 */
abstract class ContentHubLoggerBase implements ContentHubLoggerInterface {

  /**
   * An arbitrary logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $channel;

  /**
   * The event service logger.
   *
   * Sends logs to the Content Hub event service.
   *
   * @var \Drupal\acquia_contenthub\Libs\Logging\ContentHubEventLogger
   */
  protected $event;

  /**
   * ContentHubLogger constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   The logger channel.
   * @param \Drupal\acquia_contenthub\Libs\Logging\ContentHubEventLogger $event_logger
   *   The event service logger.
   */
  public function __construct(LoggerChannelInterface $logger_channel, ContentHubEventLogger $event_logger) {
    $this->channel = $logger_channel;
    $this->event = $event_logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getChannel(): LoggerChannelInterface {
    return $this->channel;
  }

  /**
   * {@inheritdoc}
   */
  public function getEvent(): ContentHubEventLogger {
    return $this->event;
  }

  /**
   * {@inheritdoc}
   */
  public function logError(string $message, array $context = []): ?string {
    $this->channel->error($message, $context);
    return $this->logEventFailure($message, SyndicationEvents::SEVERITY_ERROR, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function logWarning(string $message, array $context = []): ?string {
    $this->channel->warning($message, $context);
    return $this->logEventFailure($message, SyndicationEvents::SEVERITY_WARN, $context);
  }

  /**
   * Logs event failures with a given severity.
   *
   * @param string $message
   *   The message to log.
   * @param string $severity
   *   The severity of the event.
   * @param array $context
   *   (Optional) The context of the message.
   *
   * @return string|null
   *   The event reference or NULL if the client couldn't be initialised.
   */
  protected function logEventFailure(string $message, string $severity, array $context = []): ?string {
    return $this->event->logEntityEvent(
      $severity,
      vsprintf(preg_replace('/@\w*|%\w*/', '%s', $message), array_values($context)),
      $this->getEventErrorName()
    );
  }

  /**
   * Sends the given log message to the event service.
   *
   * @return string
   *   The event name in case of an error.
   */
  abstract protected function getEventErrorName(): string;

}
