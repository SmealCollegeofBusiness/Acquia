<?php

namespace Drupal\acquia_contenthub_translations\EntityHandler;

use Acquia\ContentHubClient\CDFDocument;

/**
 * Represents the context of non-translatable entities.
 *
 * Holds information that is shared across the handlers.
 */
class Context {

  /**
   * The CDF document.
   *
   * @var \Acquia\ContentHubClient\CDFDocument
   */
  private $document;

  /**
   * The list of removable languages.
   *
   * @var array
   */
  private $removableLanguages;

  /**
   * A list of uuids marked for removal from the CDF document.
   *
   * @var array
   */
  private $removableCdfs = [];

  /**
   * A list of entities marked for tracking.
   *
   * @var array
   */
  private $trackableEntities = [];

  /**
   * Constructs a new object.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $document
   *   The CDF document.
   * @param array $removable_languages
   *   A list of removable languages. It is expected to be modified by the
   *   handlers.
   */
  public function __construct(CDFDocument $document, array $removable_languages = []) {
    $this->document = $document;
    $this->removableLanguages = $removable_languages;
  }

  /**
   * Returns the CDF document.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   The modifiable CDF document.
   */
  public function getCdfDocument(): CDFDocument {
    return $this->document;
  }

  /**
   * Returns a reference to the removable language array.
   *
   * @return array
   *   The reference to the array.
   */
  public function &getRemovableLanguages(): array {
    return $this->removableLanguages;
  }

  /**
   * Adds a uuid to the removable CDF list.
   *
   * @param string $uuid
   *   The identifier of the CDF.
   */
  public function addToRemovables(string $uuid): void {
    $this->removableCdfs[] = $uuid;
  }

  /**
   * Returns a list of removable CDFs.
   *
   * @return array
   *   The removable CDFs.
   */
  public function getRemovableCdfs(): array {
    return $this->removableCdfs;
  }

  /**
   * Adds a new element to the trackable entities.
   *
   * @param array $data
   *   The data for the trackable.
   */
  public function addTrackableEntity(array $data): void {
    $this->trackableEntities[] = $data;
  }

  /**
   * Returns entities that need to be tracked for translations.
   *
   * @return array
   *   The list of entities.
   */
  public function getTrackableEntities(): array {
    return $this->trackableEntities;
  }

}
