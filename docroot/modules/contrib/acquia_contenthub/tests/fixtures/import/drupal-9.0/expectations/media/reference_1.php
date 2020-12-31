<?php

/**
 * @file
 * Media file expectation.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => '083607fb-df43-4efb-a66c-7a44fe018a62',
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
        'target_id' => '995f955b-08a9-4436-a0c7-1cde093ee174',
      ],
    ],
  ],
  'filename' => [
    'en' => [
      0 => [
        'value' => 'druplicon.png',
      ],
    ],
  ],
  'uri' => [
    'en' => [
      0 => [
        'value' => 'public://druplicon_4.png',
      ],
    ],
  ],
  'filemime' => [
    'en' => [
      0 => [
        'value' => 'image/png',
      ],
    ],
  ],
  'filesize' => [
    'en' => [
      0 => [
        'value' => '3905',
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
        'value' => '1545832314',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1545832321',
      ],
    ],
  ],
];

$expectations = ['083607fb-df43-4efb-a66c-7a44fe018a62' => new CdfExpectations($data, ['fid'])];

return $expectations;
