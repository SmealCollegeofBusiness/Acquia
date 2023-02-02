<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Helpers;

/**
 * Trait created for event log assertion.
 */
trait EventLogAssertionTrait {

  /**
   * Helper method which asserts the log context.
   *
   * @param array $expected
   *   Expected log array.
   * @param array $actual
   *   Actual log array recieved by logging client.
   * @param string|null $object_type
   *   Object type to be asserted.
   * @param string|null $severity
   *   Severity to be asserted.
   * @param string|null $event_name
   *   Event name to be asserted.
   */
  private function assertLogs(array $expected, array $actual, ?string $object_type = NULL, ?string $severity = NULL, ?string $event_name = NULL): void {
    static::assertEquals($severity ?? $expected['severity'], $actual['severity']);
    static::assertEquals($expected['content'], $actual['content']);
    static::assertEquals($expected['object_id'], $actual['object_id']);
    static::assertEquals($object_type ?? $expected['object_type'], $actual['object_type']);
    static::assertEquals($event_name ?? $expected['event_name'], $actual['event_name']);
  }

}
