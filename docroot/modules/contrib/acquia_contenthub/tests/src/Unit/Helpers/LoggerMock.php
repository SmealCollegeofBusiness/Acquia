<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Helpers;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\LogMessageParser;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Mock Logger created for easy assertions of log messages.
 */
class LoggerMock implements LoggerChannelInterface {
  use RfcLoggerTrait;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * Constructs a LoggerMock object.
   */
  public function __construct() {
    $this->parser = new LogMessageParser();
  }

  /**
   * Log messages.
   *
   * @var array
   */
  protected $logMessages = [];

  /**
   * {@inheritDoc}
   */
  public function log($level, $message, array $context = []) {
    if (!empty($context)) {
      $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
      $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);
    }
    $this->logMessages[$level][] = strip_tags($message);
  }

  /**
   * Helper method that can be used for assertions.
   *
   * @return array
   *   Log messages.
   */
  public function getLogMessages(): array {
    return $this->logMessages;
  }

  /**
   * Returns only emergency messages for assertion.
   *
   * @return array
   *   Log messages.
   */
  public function getEmergencyMessages(): array {
    return $this->logMessages[RfcLogLevel::EMERGENCY] ?? [];
  }

  /**
   * Returns only alert messages for assertion.
   *
   * @return array
   *   Log messages.
   */
  public function getAlertMessages(): array {
    return $this->logMessages[RfcLogLevel::ALERT] ?? [];
  }

  /**
   * Returns only critical messages for assertion.
   *
   * @return array
   *   Log messages.
   */
  public function getCriticalMessages(): array {
    return $this->logMessages[RfcLogLevel::CRITICAL] ?? [];
  }

  /**
   * Returns only error messages for assertion.
   *
   * @return array
   *   Log messages.
   */
  public function getErrorMessages(): array {
    return $this->logMessages[RfcLogLevel::ERROR] ?? [];
  }

  /**
   * Returns only warning messages for assertion.
   *
   * @return array
   *   Log messages.
   */
  public function getWarningMessages(): array {
    return $this->logMessages[RfcLogLevel::WARNING] ?? [];
  }

  /**
   * Returns only notice messages for assertion.
   *
   * @return array
   *   Log messages.
   */
  public function getNoticeMessages(): array {
    return $this->logMessages[RfcLogLevel::NOTICE] ?? [];
  }

  /**
   * Returns only info messages for assertion.
   *
   * @return array
   *   Log messages.
   */
  public function getInfoMessages(): array {
    return $this->logMessages[RfcLogLevel::INFO] ?? [];
  }

  /**
   * Returns only debug messages for assertion.
   *
   * @return array
   *   Log messages.
   */
  public function getDebugMessages(): array {
    return $this->logMessages[RfcLogLevel::DEBUG] ?? [];
  }

  /**
   * {@inheritDoc}
   */
  public function setRequestStack(RequestStack $requestStack = NULL) {
  }

  /**
   * {@inheritDoc}
   */
  public function setCurrentUser(AccountInterface $current_user = NULL) {
  }

  /**
   * {@inheritDoc}
   */
  public function setLoggers(array $loggers) {
  }

  /**
   * {@inheritDoc}
   */
  public function addLogger(LoggerInterface $logger, $priority = 0) {
  }

}
