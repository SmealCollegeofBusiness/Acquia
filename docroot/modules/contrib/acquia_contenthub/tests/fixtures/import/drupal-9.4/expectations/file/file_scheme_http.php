<?php

/**
 * @file
 * Http scheme file expectation.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => '211da662-acec-4d6c-87f6-c1d7b77a098e',
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
        'value' => 'http.svg',
      ],
    ],
  ],
  'uri' => [
    'en' => [
      0 => [
        'value' => 'http://example.com/http.svg',
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
  'filesize' => [
    'en' => [
      0 => [
        'value' => '1900',
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
        'value' => '1612543431',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1612543431',
      ],
    ],
  ],
];

$expectations = ['211da662-acec-4d6c-87f6-c1d7b77a098e' => new CdfExpectations($data, ['fid'])];

return $expectations;
