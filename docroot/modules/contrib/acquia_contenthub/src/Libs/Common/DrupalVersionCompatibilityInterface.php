<?php

namespace Drupal\acquia_contenthub\Libs\Common;

use Drupal\file\Entity\File;

/**
 * Represents a drupal version compatibility bridge.
 */
interface DrupalVersionCompatibilityInterface {

  /**
   * Maps a file extensions to a mimetype.
   *
   * @param string $extension
   *   The file extension.
   *
   * @return string|null
   *   The mime type.
   */
  public function getMimeTypeFromExtension(string $extension): ?string;

  /**
   * Sets file status to permanent.
   *
   * @param \Drupal\file\Entity\File $file
   *   Name of the file.
   */
  public function setFileStatusPermanent(File $file): void;

}
