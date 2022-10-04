<?php

namespace Drupal\acquia_contenthub_translations\Data;

use Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface as ETMInterface;
use Drupal\acquia_contenthub_translations\Exceptions\InvalidAttributeException;

/**
 * Representation of a tracked entity.
 *
 * Exposes a public API which can be used to modify underlying data. Intended
 * to be used together with the translation manager.
 *
 * @see \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
 */
class TrackedEntity {

  /**
   * Holds values about the tracked entity.
   *
   * @var array
   */
  private $values = [];

  /**
   * Holds changed values.
   *
   * @var array
   */
  private $newValues = [];

  /**
   * The EntityTranslationManager service.
   *
   * @var \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface
   */
  private $manager;

  /**
   * Constructs a new object.
   *
   * Required fields:
   * 'uuid', 'type', 'original_default_language', 'default_language'.
   *
   * @param \Drupal\acquia_contenthub_translations\EntityTranslationManagerInterface $manager
   *   The EntityTranslationManagerInterface service.
   * @param array $values
   *   The values of the tracked entity.
   *
   * @throws \Drupal\acquia_contenthub_translations\Exceptions\InvalidAttributeException
   */
  public function __construct(ETMInterface $manager, array $values) {
    $required_fields = [
      'uuid', 'type', 'original_default_language', 'default_language',
    ];
    if ($diff = array_diff_key(array_flip($required_fields), $values)) {
      throw new InvalidAttributeException(sprintf('required fields are missing: %s', print_r($diff, TRUE)));
    }

    foreach ($values as $attr => $datum) {
      $this->values[$attr] = $datum;
    }

    $this->manager = $manager;
  }

  /**
   * Returns the uuid.
   *
   * @return string
   *   The entity's uuid.
   */
  public function uuid(): string {
    return $this->values['uuid'];
  }

  /**
   * Returns the type.
   *
   * @return string
   *   The entity's type.
   */
  public function type(): string {
    return $this->values['type'];
  }

  /**
   * The created date in unix format.
   *
   * @return int
   *   The changed date.
   */
  public function created(): int {
    return $this->values['created'];
  }

  /**
   * The changed date in unix format.
   *
   * @return int
   *   The changed date.
   */
  public function changed(): int {
    return $this->values['changed'];
  }

  /**
   * Returns the tracked translations.
   *
   * Returned structure:
   * [
   *   'en' => 3,
   *   'de' => 0,
   * ]
   *
   * @return array
   *   The langcode array.
   */
  public function languages(): array {
    return $this->values['languages'] ?? [];
  }

  /**
   * Sets the entire array of langcodes.
   *
   * Expected structure is the same as described in ::languages().
   *
   * @param array $langcodes
   *   The array of langcodes to override the original with.
   */
  public function setLanguages(array $langcodes): void {
    $this->newValues['languages'] = $langcodes;
  }

  /**
   * Adds new langcode to the original list.
   *
   * Expected structure is the same as described in ::languages().
   *
   * @param array $langcodes
   *   The new langcodes.
   */
  public function addLanguages(array $langcodes): void {
    $this->newValues['languages'] = array_merge(
      $this->languages(),
      $langcodes
    );
  }

  /**
   * Returns the operation flag of a given langcode.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return int
   *   The operation flag.
   */
  public function getOperation(string $langcode): int {
    return $this->languages()[$langcode] ?? ETMInterface::NO_ACTION;
  }

  /**
   * Removes languages from the original list.
   *
   * Expected structure is the same as described in ::languages().
   *
   * @param string ...$langcodes
   *   The langcodes.
   */
  public function removeLanguage(string ...$langcodes): void {
    $langs = $this->languages();
    foreach ($langcodes as $langcode) {
      if (!isset($langs[$langcode])) {
        continue;
      }
      unset($langs[$langcode]);
    }
    $this->setLanguages($langs);
  }

  /**
   * Checks if the translation is deletable.
   *
   * @param string $language
   *   The translation to check.
   *
   * @return bool
   *   True if the tracked entity is deletable.
   */
  public function isTranslationDeletable(string $language): bool {
    if (!isset($this->languages()[$language])) {
      return FALSE;
    }

    return !($this->languages()[$language] & ETMInterface::NO_DELETION);
  }

  /**
   * Checks if the translation is updatable.
   *
   * If there's no registered translation, the object should be updatable still.
   *
   * @param string $language
   *   The translation to check.
   *
   * @return bool
   *   True if the tracked entity is updatable.
   */
  public function isTranslationUpdatable(string $language): bool {
    if (!isset($this->languages()[$language])) {
      return TRUE;
    }

    return !($this->languages()[$language] & ETMInterface::NO_UPDATE);
  }

  /**
   * Returns the original default langcode.
   *
   * @return string
   *   The langcode; e.g. 'en'.
   */
  public function originalDefaultLanguage(): string {
    return $this->values['original_default_language'];
  }

  /**
   * Returns the default language.
   *
   * The langcode the entity saved as on the subscriber.
   *
   * @return string
   *   The default langcode; e.g. 'en'.
   */
  public function defaultLanguage(): string {
    return $this->values['default_language'];
  }

  /**
   * Sets the default language.
   *
   * @param string $langcode
   *   The langcode to use.
   */
  public function setDefaultLanguage(string $langcode): void {
    $this->newValues['default_language'] = $langcode;
  }

  /**
   * Returns changed values.
   *
   * Stored for simplicity and performance reasons.
   *
   * @return array
   *   The array of new values structured similarly to values attribute.
   */
  public function getChangedValues(): array {
    return $this->newValues;
  }

  /**
   * Checks if the tracked entity is changed.
   *
   * @return bool
   *   Whether the entity is changed.
   */
  public function isChanged(): bool {
    return (bool) $this->newValues;
  }

  /**
   * Saves the entity and returns a new, updated one.
   *
   * Uses the changed values attribute.
   *
   * @return \Drupal\acquia_contenthub_translations\Data\TrackedEntity
   *   Returns the updated entity.
   */
  public function save(): TrackedEntity {
    $this->manager->updateTrackedEntity($this);
    $this->values = array_replace($this->values, $this->newValues);
    return $this;
  }

  /**
   * Deletes the entity.
   */
  public function delete(): void {
    $this->manager->deleteTrackedEntity($this->uuid());
  }

}
