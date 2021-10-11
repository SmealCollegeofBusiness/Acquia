<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Traits;

/**
 * Contains dblog related asserts.
 *
 * @package Drupal\Tests\acquia_contenthub\Traits
 */
trait WatchdogAssertsTrait {

  /**
   * Verify a log entry was entered into watchdog table.
   *
   * @param string $type
   *   The channel to which this message belongs.
   * @param string $message
   *   The message to check in the log.
   */
  public function assertLogMessage(string $type, string $message) {
    $count = \Drupal::database()->select('watchdog', 'w')
      ->condition('type', $type)
      ->condition('message', '%' . $message . '%', 'LIKE')
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertTrue($count > 0, sprintf(
        'watchdog table contains %s rows for %s',
        $count, $message
      )
    );
  }

}
