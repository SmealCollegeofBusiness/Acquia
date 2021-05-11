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
        'value' => '19',
      ],
    ],
  ],
  'uuid' => [
    'und' => [
      0 => [
        'value' => 'a1d183ff-f1de-433c-8a75-21450a9c868b',
      ],
    ],
  ],
  'hash' => [
    'und' => [
      0 => [
        'value' => 'yT1CWZD7AVmbIQW_0DqPnVjav0O_T7qC4-eSbELGX_w',
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
        'path' => 'ghgj',
        'query' => NULL,
      ],
    ],
  ],
  'redirect_redirect' => [
    'und' => [
      0 => [
        'uri' => 'internal:/simplify',
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
        'value' => '1612279729',
      ],
    ],
  ],
];

return ['a1d183ff-f1de-433c-8a75-21450a9c868b' => new CdfExpectations($data, ['rid'])];
