<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Helpers;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\LogMessageParser;
use Drupal\Core\Logger\RfcLoggerTrait;
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
