<?php

namespace Drupal\acquia_contenthub\Libs\Common;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use GuzzleHttp\Psr7\MimeType;

/**
 * Bridge for drupal compatibility.
 */
class DrupalVersionCompatibilityBridge implements DrupalVersionCompatibilityInterface {

  /**
   * {@inheritdoc}
   */
  public function getMimeTypeFromExtension(string $extension): ?string {
    if (class_exists('GuzzleHttp\Psr7\MimeType')) {
      $filemime = MimeType::fromExtension($extension);
    }
    else {
      /* @phpstan-ignore-next-line */
      $filemime = mimetype_from_extension($extension);
    }
    return $filemime;
  }

  /**
   * {@inheritdoc}
   */
  public function setFileStatusPermanent(File $file): void {
    if (defined('\Drupal\file\FileInterface::STATUS_PERMANENT')) {
      $file->set('status', FileInterface::STATUS_PERMANENT);
    }
    else {
      /* @phpstan-ignore-next-line */
      $file->set('status', FILE_STATUS_PERMANENT);
    }
  }

}
