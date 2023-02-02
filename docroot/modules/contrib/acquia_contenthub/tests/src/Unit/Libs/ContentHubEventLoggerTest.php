<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Libs;

use Acquia\ContentHubClient\ContentHubLoggingClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Exception\EventServiceUnreachableException;
use Drupal\acquia_contenthub\Libs\Logging\ContentHubEventLogger;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\ContentHubLoggingClientMock;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\EventLogAssertionTrait;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Test for ContentHubEventLogger.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\Libs\Logging\ContentHubEventLogger
 */
class ContentHubEventLoggerTest extends UnitTestCase {

  use EventLogAssertionTrait;

  /**
   * Client factory mock.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Mock Logger.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $loggerMock;

  /**
   * Mock logging client for assertions.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\ContentHubLoggingClientMock
   */
  protected $loggingClient;

  /**
   * Uuid generator service mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $uuid;

  /**
   * Array of logs to assert.
   *
   * @var array
   */
  protected static $logs = [];

  /**
   * {@inheritDoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    for ($x = 0; $x < 2 * ContentHubEventLogger::CHUNK_SIZE; $x++) {
      self::$logs[$x] = [
        'severity' => 'random-severity' . $x,
        'content' => 'random-message' . $x,
        'object_id' => 'random-object-id' . $x,
        'object_type' => 'random-object-type' . $x,
        'event_name' => 'random-event-name' . $x,
      ];
    }
  }

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->clientFactory = $this->prophesize(ClientFactory::class);
    $this->loggerMock = new LoggerMock();
    $this->loggingClient = new ContentHubLoggingClientMock();
    $this->clientFactory
      ->getLoggingClient()
      ->shouldBeCalled()
      ->willReturn($this->loggingClient);
    $settings = $this->prophesize(Settings::class);
    $settings
      ->getUuid()
      ->willReturn('origin-uuid');
    $this->clientFactory
      ->getSettings()
      ->willReturn($settings->reveal());
    $this->uuid = $this->prophesize(UuidInterface::class);
  }

  /**
   * Tests getLoggingClient method.
   *
   * @covers ::getLoggingClient
   */
  public function testGetLoggingClient(): void {
    $this->clientFactory
      ->getLoggingClient()
      ->shouldBeCalled()
      ->willThrow(new EventServiceUnreachableException('Event service missing.'));
    $ch_event_logger = new ContentHubEventLogger($this->clientFactory->reveal(), $this->loggerMock, $this->uuid->reveal());
    $this->assertEmpty($this->loggerMock->getLogMessages());
    $ch_event_logger->getLoggingClient();
    // Assert there was EventServiceUnreachableException and logged.
    $log_messages = $this->loggerMock->getLogMessages();
    $this->assertNotEmpty($log_messages);
    // Assert there are errors in log messages.
    $this->assertNotEmpty($log_messages[RfcLogLevel::ERROR]);
    $this->assertEquals('Event service is unreachable. Error: Event service missing.', $log_messages[RfcLogLevel::ERROR][0]);
    // Assert there was a runtime exception and logged.
    $this->clientFactory
      ->getLoggingClient()
      ->shouldBeCalled()
      ->willThrow(new \RuntimeException('Event logging Client missing.'));
    $ch_event_logger = new ContentHubEventLogger($this->clientFactory->reveal(), $this->loggerMock, $this->uuid->reveal());
    $ch_event_logger->getLoggingClient();
    $log_messages = $this->loggerMock->getLogMessages();
    $this->assertEquals('Trying to access event logging client before initializing it. Error: Event logging Client missing.', $log_messages[RfcLogLevel::ERROR][1]);
    $this->clientFactory
      ->getLoggingClient()
      ->shouldBeCalled()
      ->willReturn($this->prophesize(ContentHubLoggingClient::class)->reveal());
    $ch_event_logger = new ContentHubEventLogger($this->clientFactory->reveal(), $this->loggerMock, $this->uuid->reveal());
    $this->assertNotNull($ch_event_logger->getLoggingClient());
  }

  /**
   * Tests logEvent method.
   *
   * @covers ::logEvent
   */
  public function testLogEvent(): void {
    $event_log = self::$logs[0];
    $ch_event_logger = new ContentHubEventLogger($this->clientFactory->reveal(), $this->loggerMock, $this->uuid->reveal());
    $ch_event_logger->logEvent(
      $event_log['severity'],
      $event_log['content'],
      $event_log['object_type'],
      $event_log['event_name'],
      $event_log['object_id']
    );
    $logs = $this->loggingClient->getLogs();
    $this->assertLogs($event_log, $logs[0]);
  }

  /**
   * Tests logEntityEvent method.
   *
   * @covers ::logEntityEvent
   */
  public function testLogEntityEvent(): void {
    $event_log = self::$logs[0];
    $ch_event_logger = new ContentHubEventLogger($this->clientFactory->reveal(), $this->loggerMock, $this->uuid->reveal());
    $ch_event_logger->logEntityEvent(
      $event_log['severity'],
      $event_log['content'],
      $event_log['event_name'],
      $event_log['object_id']
    );
    $logs = $this->loggingClient->getLogs();
    $this->assertLogs($event_log, $logs[0], 'Entity');
  }

  /**
   * Tests logMultipleEvents.
   *
   * @covers ::logMultipleEvents
   *
   * @throws \Exception
   */
  public function testLogMultipleEvents(): void {
    $ch_event_logger = new ContentHubEventLogger($this->clientFactory->reveal(), $this->loggerMock, $this->uuid->reveal());
    $ch_event_logger->logMultipleEvents(
      'random-severity',
      $this->getStructuredLogs(),
      'random-object-type',
      'random-event-name'
    );
    $logs = $this->loggingClient->getLogs();
    $length = count(self::$logs);
    for ($i = 0; $i < $length; $i++) {
      $this->assertLogs(self::$logs[$i], $logs[$i], 'random-object-type', 'random-severity', 'random-event-name');
    }
  }

  /**
   * Tests logMultipleEntityEvents.
   *
   * @covers ::logMultipleEntityEvents
   *
   * @throws \Exception
   */
  public function testLogMultipleEntityEvents(): void {
    $ch_event_logger = new ContentHubEventLogger($this->clientFactory->reveal(), $this->loggerMock, $this->uuid->reveal());
    $ch_event_logger->logMultipleEntityEvents(
      'random-severity',
      $this->getStructuredLogs(),
      'random-event-name'
    );
    $logs = $this->loggingClient->getLogs();
    $length = count(self::$logs);
    for ($i = 0; $i < $length; $i++) {
      $this->assertLogs(self::$logs[$i], $logs[$i], 'Entity', 'random-severity', 'random-event-name');
    }
  }

  /**
   * Tests event was not logged and exception was raised.
   *
   * @covers ::sendLogsToService
   */
  public function testUnsuccessfulLogging(): void {
    $logging_client = $this->prophesize(ContentHubLoggingClient::class);
    $logging_client
      ->sendLogs(Argument::type('array'))
      ->shouldBeCalled()
      ->willThrow(new \Exception('Something went wrong while logging.'));
    $this->clientFactory
      ->getLoggingClient()
      ->shouldBeCalled()
      ->willReturn($logging_client->reveal());
    $ch_event_logger = new ContentHubEventLogger($this->clientFactory->reveal(), $this->loggerMock, $this->uuid->reveal());
    $ch_event_logger->logEvent(
      'random-status',
      'random-message',
      'random_uuid',
      'random-object-type',
      'random-event-name'
    );
    $log_messages = $this->loggerMock->getLogMessages();
    $this->assertEquals('Something went wrong while sending logs to event microservice. Error: Something went wrong while logging.',
      $log_messages[RfcLogLevel::ERROR][0]
    );
  }

  /**
   * Returns mock log array for logging multiple logs.
   *
   * @return array
   *   Array of logs.
   */
  protected function getStructuredLogs(): array {
    $logs = [];
    foreach (self::$logs as $log) {
      $logs[$log['object_id']] = $log['content'];
    }
    return $logs;
  }

}
