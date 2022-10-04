<?php

namespace Drupal\acsf\Event;

use Drupal\acsf\AcsfCompatibilityHelper;

/**
 * Handles the scrubbing of Drupal temporary files.
 */
class AcsfDuplicationScrubTemporaryFilesHandler extends AcsfEventHandler {

  /**
   * Implements AcsfEventHandler::handle().
   */
  public function handle() {
    $this->consoleLog(dt('Entered @class', ['@class' => get_class($this)]));

    $file_storage = \Drupal::entityTypeManager()->getStorage('file');

    $status = AcsfCompatibilityHelper::getFileStatusPermanent();

    // Remove all temporary files. As in file_cron(), temporary is encoded as
    // "<> FILE_STATUS_PERMANENT".
    $fids = $file_storage->getQuery()
      ->condition('status', $status, '<>')
      ->range(0, 1000)
      ->execute();

    $files = $file_storage->loadMultiple($fids);
    foreach ($files as $file) {
      $file->delete();
    }
  }

}
