<?php

/**
 * @file
 * Https scheme file expectation.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => '5ccc339c-2225-4354-9ae5-82e1244ca434',
      ],
    ],
  ],
  'langcode' => [
    'en' => [
      0 => [
        'value' => 'en',
      ],
    ],
  ],
  'uid' => [
    'en' => [
      0 => [
        'target_id' => '831b17ff-0f91-4afc-bd9a-2efb9d9b76a6',
      ],
    ],
  ],
  'filename' => [
    'en' => [
      0 => [
        'value' => 'https.svg',
      ],
    ],
  ],
  'uri' => [
    'en' => [
      0 => [
        'value' => 'https://www.example.com/https.svg',
      ],
    ],
  ],
  'filemime' => [
    'en' => [
      0 => [
        'value' => 'image/svg+xml',
      ],
    ],
  ],
  'status' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
  ],
  'created' => [
    'en' => [
      0 => [
        'value' => '1612548096',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1612548096',
      ],
    ],
  ],
];

$expectations = ['5ccc339c-2225-4354-9ae5-82e1244ca434' => new CdfExpectations($data, ['fid'])];

return $expectations;
