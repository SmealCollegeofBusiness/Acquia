<?php

/**
 * @file
 * Expectation for view configuration entity translation scenario.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'label' => [
    'en' => 'Content',
    'be' => 'BE: Content',
    'ru' => 'RU: Content',
  ],
  'description' => [
    'en' => 'Find and manage content.',
    'be' => 'BE: Find and manage content.',
    'ru' => 'RU: Find and manage content.',
  ],
];

$expectation = new CdfExpectations($data);
$expectation->setLangcodes(['en', 'be', 'ru']);
$expectation->setEntityLoader('acquia_contenthub_test_load_view_config_translation');

function acquia_contenthub_test_load_view_config_translation() {
  return \Drupal::service('entity_type.manager')->getStorage('view')->load('content');
}

return [
  '0204f032-73dd-4d0f-83df-019631d86563' => $expectation,
];
