<?php

namespace Drupal\Tests\acquia_contenthub_translations\Traits;

use Drupal\acquia_contenthub_translations\Data\EntityTranslations;
use Drupal\acquia_contenthub_translations\Data\EntityTranslationsTracker;
use PHPUnit\Framework\Assert;

/**
 * Provides common methods for db assertions.
 */
trait EntityTranslationDbAssertions {

  /**
   * Asserts translations table rows.
   *
   * Expectation format:
   * @code
   *   [
   *     'some_uuid' => [
   *       [
   *         'field' => 'value',
   *       ],
   *       [
   *         'field2 => 'value2',
   *       ],
   *     ],
   *   ]
   * @endcode
   *
   * @param array $expectation
   *   The expected values.
   * @param int $expected_count
   *   The expected number of records.
   */
  public function assertTranslationsRows(array $expectation, int $expected_count): void {
    $assertions = ['langcode', 'operation_flag'];
    $this->assertRows(EntityTranslations::TABLE, $assertions, $expectation, $expected_count);
  }

  /**
   * Asserts translation tracker table rows.
   *
   * Expectation format:
   * @code
   *   [
   *     'some_uuid' => [
   *       [
   *         'field' => 'value',
   *       ],
   *       [
   *         'field2 => 'value2',
   *       ],
   *     ],
   *   ]
   * @endcode
   *
   * @param array $expectation
   *   The expected values.
   * @param int $expected_count
   *   The expected number of records.
   */
  public function assertTrackerRows(array $expectation, int $expected_count): void {
    $assertions = [
      'entity_type', 'original_default_language', 'default_language',
    ];
    $this->assertRows(EntityTranslationsTracker::TABLE, $assertions, $expectation, $expected_count);
  }

  /**
   * Assert rows based on the table name.
   *
   * @param string $table
   *   The table's name.
   * @param array $assertions
   *   Additional assertions.
   * @param array $expectation
   *   The expected values.
   * @param int $expected_count
   *   The expected number of records.
   */
  private function assertRows(string $table, array $assertions, array $expectation, int $expected_count): void {
    $time = time();
    $db = \Drupal::database();
    $res = $db->select($table, 't')
      ->fields('t')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    Assert::assertCount($expected_count, $res);

    if (empty($expectation)) {
      Assert::assertEmpty($res);
      return;
    }

    $i = 0;
    foreach ($expectation as $values) {
      foreach ($values as $j => $value) {
        $translation = $res[$i + $j];
        Assert::assertEquals($value['entity_uuid'], $translation['entity_uuid']);
        if (!empty($assertions)) {
          foreach ($assertions as $assertion) {
            Assert::assertEquals($value[$assertion], $translation[$assertion]);
          }
        }
        Assert::assertTrue($time >= $translation['created'] && $translation['created'] > 0);
        Assert::assertTrue($time >= $translation['changed'] && $translation['created'] > 0);
      }
      $i++;
    }
  }

}
