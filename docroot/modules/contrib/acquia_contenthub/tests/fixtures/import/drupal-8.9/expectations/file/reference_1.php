<?php

/**
 * @file
 * File file expectation.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => 'b88f7854-0b14-4993-88ab-f14f9e24c4b3',
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
        'value' => '1.txt',
      ],
    ],
  ],
  'uri' => [
    'en' => [
      0 => [
        'value' => 'public://2020-03/1.txt',
      ],
    ],
  ],
  'filemime' => [
    'en' => [
      0 => [
        'value' => 'text/plain',
      ],
    ],
  ],
  'filesize' => [
    'en' => [
      0 => [
        'value' => '880',
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
        'value' => '1585656547',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1585656549',
      ],
    ],
  ],
];

$expectations = ['b88f7854-0b14-4993-88ab-f14f9e24c4b3' => new CdfExpectations($data, ['fid'])];

return $expectations;
