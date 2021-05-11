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
        'value' => '660e1f94-2422-4ccd-af03-6c19abfe62f5',
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
        'value' => 'drupal.svg',
      ],
    ],
  ],
  'uri' => [
    'en' => [
      0 => [
        'value' => 'path/to/file/drupal.svg',
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
        'value' => '951',
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
        'value' => '1612460928',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1612460928',
      ],
    ],
  ],
];

$expectations = ['660e1f94-2422-4ccd-af03-6c19abfe62f5' => new CdfExpectations($data, ['fid'])];

return $expectations;
