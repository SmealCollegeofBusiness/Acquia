<?php

namespace Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry;

/**
 * Interface defined to mark languages undesired for import.
 *
 * Undesired Languages are imported only because of hard dependency.
 *
 * But such translations will be excluded from syndication where possible.
 */
interface UndesiredLanguageRegistryInterface {

  /**
   * Marks a set of langcodes as undesired for import.
   *
   * @param string ...$languages
   *   Array of languages to mark undesired.
   */
  public function markLanguagesUndesired(string ...$languages): void;

  /**
   * Returns array of undesired langcodes.
   *
   * @return array
   *   Array of undesired langcodes.
   */
  public function getUndesiredLanguages(): array;

  /**
   * Checks if a langcode is marked as undesired or not.
   *
   * @param string $language
   *   Langcode.
   *
   * @return bool
   *   True if undesired otherwise false.
   */
  public function isLanguageUndesired(string $language): bool;

  /**
   * Removes a language from undesired list.
   *
   * @param string ...$languages
   *   Langcodes to delete.
   */
  public function removeLanguageFromUndesired(string ...$languages): void;

}
