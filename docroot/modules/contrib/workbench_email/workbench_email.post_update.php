<?php

/**
 * @file
 * Contains post update hooks.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\workbench_email\TemplateInterface;

/**
 * Implements hook_removed_post_updates().
 */
function workbench_email_removed_post_updates() {
  return [
    'workbench_email_post_update_move_to_recipient_plugins' => '2.2.2',
    'workbench_email_post_update_add_reply_to' => '2.2.2',
  ];
}

/**
 * Update config entities with the new template format option.
 */
function workbench_email_post_update_add_template_format(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'workbench_email_template', function (TemplateInterface $template): bool {
      // Previously, we sent all emails in HTML if swiftmailer was installed.
      // For BC, update each template to HTML only if swiftmailer is installed.
      $format = \Drupal::moduleHandler()->moduleExists('swiftmailer') ? 'html' : 'plain_text';
      $template->set('format', $format);
      return TRUE;
    }
  );
}

/**
 * Update the config schema to make email body translatable.
 */
function workbench_email_post_update_make_email_body_translatable(&$sandbox = NULL) { }
