<?php

namespace Drupal\acquia_contenthub_s3;

use Drupal\s3fs\StreamWrapper\S3fsStream;

/**
 * Overrides S3fsStream.
 *
 * @see \Drupal\s3fs\StreamWrapper\S3fsStream
 */
class S3fsStreamDecorator extends S3fsStream {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function getExternalUrl() {
    // phpcs:ignore
    $source = \Drupal::service('acquia_contenthub_s3.origin_locator')->getS3FileSource($this->uri);
    if ($source) {
      $this->config = array_merge($this->config, $source);
    }

    return parent::getExternalUrl();
  }

}
