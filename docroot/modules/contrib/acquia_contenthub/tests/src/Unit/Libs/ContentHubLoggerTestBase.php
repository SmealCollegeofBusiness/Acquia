<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Libs;

use Acquia\ContentHubClient\Settings;
use Acquia\ContentHubClient\Syndication\SyndicationEvents;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Libs\Logging\ContentHubEventLogger;
use Drupal\Component\Uuid\Php;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\ContentHubLoggingClientMock;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\EventLogAssertionTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\UnitTestCase;

/**
 * Base class for Content Hub logger tests.
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\Libs
 */
abstract class ContentHubLoggerTestBase extends UnitTestCase {

  use EventLogAssertionTrait;

  /**
   * The event service logger.
   *
   * @var \Drupal\acquia_contenthub\Libs\Logging\ContentHubEventLogger
   */
  protected $eventLogger;

  /**
   * The logger channel mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $loggerChannel;

  /**
   * The Content Hub logger.
   *
   * @var \Drupal\acquia_contenthub\Libs\Logging\ContentHubLoggerInterface
   */
  protected $chLogger;

  /**
   * The logging client mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\ContentHubLoggingClientMock
   */
  protected $loggingClient;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $client_factory = $this->prophesize(ClientFactory::class);
    $this->loggingClient = new ContentHubLoggingClientMock();
    $settings = $this->prophesize(Settings::class);
    $settings
      ->getUuid()
      ->willReturn('some-origin');
    $client_factory
      ->getSettings()
      ->willReturn($settings->reveal());
    $client_factory
      ->getLoggingClient()
      ->willReturn($this->loggingClient);

    $this->loggerChannel = new LoggerMock();
    $this->eventLogger = new ContentHubEventLogger($client_factory->reveal(), $this->loggerChannel, new Php());
  }

  /**
   * Tests logWarning method.
   *
   * @param string $message
   *   The error message.
   * @param array $context
   *   The context used to resolve placeholders.
   * @param string $expected_message
   *   Expected error message after resolving placeholders.
   *
   * @dataProvider logRecordsDataProvider
   */
  public function testLogErrorWithAndWithoutContext(string $message, array $context, string $expected_message): void {
    // Object id was not provided, therefore it should be random generated.
    $event_ref = $this->chLogger->logError($message, $context);
    $this->assertSeverities(
      $expected_message,
      $event_ref,
      RfcLogLevel::ERROR,
      SyndicationEvents::SEVERITY_ERROR
    );
  }

  /**
   * Tests logWarning method.
   *
   * @param string $message
   *   The error message.
   * @param array $context
   *   The context used to resolve placeholders.
   * @param string $expected_message
   *   Expected error message after resolving placeholders.
   *
   * @dataProvider logRecordsDataProvider
   */
  public function testLogWarningWithAndWithoutContext(string $message, array $context, string $expected_message): void {
    // Object id was not provided, therefore it should be random generated.
    $event_ref = $this->chLogger->logWarning($message, $context);
    $this->assertSeverities(
      $expected_message,
      $event_ref,
      RfcLogLevel::WARNING,
      SyndicationEvents::SEVERITY_WARN
    );
  }

  /**
   * Tests if getChannel will return the logger channel.
   */
  public function testGetChannelReturnsChannel() {
    $this->assertTrue($this->chLogger->getChannel() instanceof LoggerChannelInterface);
  }

  /**
   * Tests if getEvent will return the event logger service.
   */
  public function testGetEventReturnsEventLoggerService() {
    $this->assertTrue($this->chLogger->getEvent() instanceof ContentHubEventLogger);
  }

  /**
   * Base assertions for logging tests.
   *
   * @param string $expected_message
   *   Expected error message after resolving placeholders.
   * @param string $event_ref
   *   The object_id of an event.
   * @param string $rfc_severity
   *   The RFC log level.
   * @param string $event_severity
   *   The event severity.
   */
  protected function assertSeverities(string $expected_message, string $event_ref, string $rfc_severity, string $event_severity) {
    $logs = $this->loggerChannel->getLogMessages();
    $this->assertTrue(count($logs) === 1,
      'There is exactly 1 log record in the list'
    );
    $this->assertTrue(isset($logs[$rfc_severity]), 'There is an error message');
    $this->assertTrue($logs[$rfc_severity][0] === $expected_message,
      'The log messages match'
    );

    $logs = $this->loggingClient->getLogs();
    $this->assertTrue(count($logs) === 1, 'There is exactly 1 log record in the list');
    $expected = [
      'severity' => $event_severity,
      'content' => $expected_message,
      'object_id' => $event_ref,
      'object_type' => 'Entity',
      'event_name' => $this->getExpectedFailureEventName(),
    ];
    $this->assertLogs($expected, $logs[0]);
  }

  /**
   * Provides test data set for error logs.
   *
   * @return array[]
   *   Test data set.
   */
  public function logRecordsDataProvider(): array {
    return [
      [
        'An error message', [], 'An error message',
      ],
      [
        'An error message @with @context',
        ['@with' => 'random', '@context' => 'value'],
        'An error message random value',
      ],
      [
        'An error message %with other %notation',
        ['%with' => 'random', '%notation' => 'value'],
        'An error message random other value',
      ],
      [
        'Error with @underscored_placeholder',
        ['@underscored_placeholder' => 'works with spaces too'],
        'Error with works with spaces too',
      ],
    ];
  }

  /**
   * Returns the expected event name on failure.
   *
   * @return string
   *   The name of the event.
   */
  abstract protected function getExpectedFailureEventName(): string;

}
