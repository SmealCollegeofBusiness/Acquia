<?php

/**
 * @file
 * Contains post update hooks.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\workbench_email\TemplateInterface;
use Drupal\workbench_email\Update\UpdateHelper;

/**
 * Implements hook_removed_post_updates().
 */
function workbench_email_removed_post_updates() {
  return [
    'workbench_email_post_update_move_to_recipient_plugins' => '2.3.0',
    'workbench_email_post_update_add_reply_to' => '2.3.0',
  ];
}
