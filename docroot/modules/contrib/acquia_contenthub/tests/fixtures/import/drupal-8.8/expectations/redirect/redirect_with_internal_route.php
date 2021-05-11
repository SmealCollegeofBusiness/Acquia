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
        'value' => '31',
      ],
    ],
  ],
  'uuid' => [
    'und' => [
      0 => [
        'value' => '73cc40e6-af4a-45d4-915d-26503d416bf2',
      ],
    ],
  ],
  'hash' => [
    'und' => [
      0 => [
        'value' => 'cd6N4DbawGkGEsRAvht_w1zxznrtXNbFiqBdhNWMpA8',
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
        'path' => 'check-internal-route',
        'query' => NULL,
      ],
    ],
  ],
  'redirect_redirect' => [
    'und' => [
      0 => [
        'uri' => 'internal:/node/add',
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
        'value' => '1615812364',
      ],
    ],
  ],
];

return ['73cc40e6-af4a-45d4-915d-26503d416bf2' => new CdfExpectations($data, ['rid'])];
