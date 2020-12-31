<?php

/**
 * @file
 * Expectation for node type basic page scenario.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => '959b13f5-10b5-403b-b23d-f3e49aaa8776',
  'langcode' => 'en',
  'status' => TRUE,
  'dependencies' =>
    [],
  '_core' =>
    [
      'default_config_hash' => 'KuyA4NHPXcmKAjRtwa0vQc2ZcyrUJy6IlS2TAyMNRbc',
    ],
  'name' => 'Basic page',
  'type' => 'page',
  'description' => 'Use <em>basic pages</em> for your static content, such as an \'About us\' page.',
  'help' => '',
  'new_revision' => TRUE,
  'preview_mode' => 1,
  'display_submitted' => FALSE,
];

$expectations = ['959b13f5-10b5-403b-b23d-f3e49aaa8776' => new CdfExpectations($data)];

return $expectations;
