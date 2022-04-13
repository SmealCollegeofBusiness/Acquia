<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\Traits;

/**
 * Formats strings.
 */
trait StringFormatterTrait {

  /**
   * Improves sprintf functionality by converting values to string.
   *
   * @param string $format
   *   The string to format.
   * @param mixed ...$values
   *   The values to pass to format in the resulting string.
   *
   * @return string
   *   The formatted string.
   */
  protected function stringPrintFormat(string $format, ...$values): string {
    foreach ($values as $i => $val) {
      if (is_array($val)) {
        $values[$i] = print_r($val, TRUE);
      }
      if (is_null($val)) {
        $values[$i] = 'NULL';
      }
      if (is_object($val)) {
        $values[$i] = get_class($val);
      }
    }

    return vsprintf($format, $values);
  }

}
