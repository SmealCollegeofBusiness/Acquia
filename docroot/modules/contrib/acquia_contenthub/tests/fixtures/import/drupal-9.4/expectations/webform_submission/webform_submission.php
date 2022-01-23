<?php

/**
 * @file
 * Webform submission expectation.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => 'edd0127d-3cf0-49a5-9661-012449128145',
      ],
    ],
  ],
  'uri' => [
    'en' => [
      0 => [
        'value' => '/form/test-webform',
      ],
    ],
  ],
  'created' => [
    'en' => [
      0 => [
        'value' => '1614461270',
      ],
    ],
  ],
  'completed' => [
    'en' => [
      0 => [
        'value' => '1614461279',
      ],
    ],
  ],
  'in_draft' => [
    'en' => [
      0 => [
        'value' => '0',
      ],
    ],
  ],
  'serial' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
  ],
  'token' => [
    'en' => [
      0 => [
        'value' => '-r2co5nX_vpHn6YA0Adi76IArX6m6HWxjNCMjsLesXE',
      ],
    ],
  ],
  'locked' => [
    'en' => [
      0 => [
        'value' => '0',
      ],
    ],
  ],
  'sticky' => [
    'en' => [
      0 => [
        'value' => '0',
      ],
    ],
  ],
  'remote_addr' => [
    'en' => [
      0 => [
        'value' => '127.0.0.1',
      ],
    ],
  ],
  'uid' => [
    'en' => [
      0 => [
        'target_id' => '3221d80e-0721-4867-8afd-6c2a7512a1a7',
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
  'webform_id' => [
    'en' => [
      0 => [
        'target_id' => '035fd1a3-c6a4-449e-937b-e333eb4693b7',
      ],
    ],
  ],
];

$expectations = ['edd0127d-3cf0-49a5-9661-012449128145' => new CdfExpectations($data, ['sid', 'changed'])];

return $expectations;
