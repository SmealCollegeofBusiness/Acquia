<?php

/**
 * @file
 * Expectation for redirect entity.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'rid' => [
    'und' => [
      0 => [
        'value' => '29',
      ],
    ],
  ],
  'uuid' => [
    'und' => [
      0 => [
        'value' => '0612f69c-5968-4b40-9c1d-48a549b56325',
      ],
    ],
  ],
  'hash' => [
    'und' => [
      0 => [
        'value' => 'GTjAVmllaDZVWG3b0vNVnKeDAXam8Vtgz2CLHfyyU6I',
      ],
    ],
  ],
  'type' => [
    'und' => [
      0 => [
        'value' => 'redirect',
      ],
    ],
  ],
  'uid' => [
    'und' => [
      0 => [
        'target_id' => 'dc916071-4ecb-41ad-81c5-2a7be06cb782',
      ],
    ],
  ],
  'redirect_source' => [
    'und' => [
      0 => [
        'path' => 'check-existing',
        'query' => NULL,
      ],
    ],
  ],
  'redirect_redirect' => [
    'und' => [
      0 => [
        'uri' => '03cf6ebe-f0b2-4217-9783-82d7125ef460',
        'title' => '',
        'options' => [],
      ],
    ],
  ],
  'language' => [
    'und' => [
      0 => [
        'value' => 'und',
      ],
    ],
  ],
  'status_code' => [
    'und' => [
      0 => [
        'value' => '301',
      ],
    ],
  ],
  'created' => [
    'und' => [
      0 => [
        'value' => '1615806438',
      ],
    ],
  ],
];

return ['0612f69c-5968-4b40-9c1d-48a549b56325' => new CdfExpectations($data, ['rid'])];
