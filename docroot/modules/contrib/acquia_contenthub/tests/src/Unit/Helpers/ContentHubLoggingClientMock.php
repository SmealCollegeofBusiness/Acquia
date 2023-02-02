<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Helpers;

use Acquia\ContentHubClient\ContentHubLoggingClient;

/**
 * Class created to mock ContentHubLoggingClient for easier logs assertion.
 */
class ContentHubLoggingClientMock extends ContentHubLoggingClient {

  /**
   * ContentHubLoggingClientMock constructor.
   */
  public function __construct() {
  }

  /**
   * In memory logs stored for assertion.
   *
   * @var array
   */
  protected $logs = [];

  /**
   * {@inheritDoc}
   */
  public function sendLogs(array $logs) {
    $this->logs = array_merge($this->logs, $logs);
    return [
      'success' => TRUE,
      'request_id' => 'some-uuid',
    ];
  }

  /**
   * Returns array of logged events.
   *
   * @return array
   *   Array of logs.
   */
  public function getLogs(): array {
    return $this->logs;
  }

}
