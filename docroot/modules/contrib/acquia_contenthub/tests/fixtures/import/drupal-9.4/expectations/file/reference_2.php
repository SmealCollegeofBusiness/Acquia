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
        'value' => 'a5b12985-69f7-46d0-8e3f-a9e940eab99f',
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
        'value' => 'public://1.txt',
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
        'value' => '1585658200',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1585658207',
      ],
    ],
  ],
];

$expectations = ['a5b12985-69f7-46d0-8e3f-a9e940eab99f' => new CdfExpectations($data, ['fid'])];

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => 'f8a90411-8bc0-4e49-a4e5-211add30c654',
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
        'value' => '2.txt',
      ],
    ],
  ],
  'uri' => [
    'en' => [
      0 => [
        'value' => 'public://2.txt',
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
        'value' => '1585658205',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1585658207',
      ],
    ],
  ],
];
$expectations['f8a90411-8bc0-4e49-a4e5-211add30c654'] = new CdfExpectations($data, ['fid']);

return $expectations;
