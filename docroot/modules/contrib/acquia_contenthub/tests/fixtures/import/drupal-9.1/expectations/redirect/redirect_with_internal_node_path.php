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
        'value' => '30',
      ],
    ],
  ],
  'uuid' => [
    'und' => [
      0 => [
        'value' => '610bdde1-f19a-4afa-825b-d32a0147d87c',
      ],
    ],
  ],
  'hash' => [
    'und' => [
      0 => [
        'value' => 'U6kbWOpaewomhJnWHzoX-GNZ7KvxB3_Rr-UOr3cHHYc',
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
        'path' => 'check-existing-node-id',
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
        'value' => '1615806597',
      ],
    ],
  ],
];

return ['610bdde1-f19a-4afa-825b-d32a0147d87c' => new CdfExpectations($data, ['rid'])];
