<?php

/**
 * @file
 * Expectation for node page update scenario.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => '5d1ba3c3-d527-4328-8fce-a6b714c5ef79',
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
  'type' => [
    'en' => [
      0 => [
        'target_id' => 'b7b40bf9-97b4-4c60-873e-0602135a4861',
      ],
    ],
  ],
  'revision_timestamp' => [
    'en' => [
      0 => [
        'value' => '1585396514',
      ],
    ],
  ],
  'revision_uid' => [
    'en' => [
      0 => [
        'target_id' => '995f955b-08a9-4436-a0c7-1cde093ee174',
      ],
    ],
  ],
  'revision_log' => [
    'en' => [],
  ],
  'status' => [
    'en' => [
      0 => [
        'value' => '0',
      ],
    ],
  ],
  'title' => [
    'en' => [
      0 => [
        'value' => 'Test English Page',
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
  'created' => [
    'en' => [
      0 => [
        'value' => '1585394438',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1547590795',
      ],
    ],
  ],
  'promote' => [
    'en' => [
      0 => [
        'value' => '1',
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
  'default_langcode' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
  ],
  'revision_default' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
  ],
  'revision_translation_affected' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
  ],
  'moderation_state' => [
    'en' => [
      0 => [
        'value' => 'published',
      ],
    ],
  ],
  'path' => [
    'en' => [
      0 => [
        'langcode' => 'en',
      ],
    ],
  ],
  'body' => [
    'en' => [
      0 => [
        'value' => "<p>Test body</p>\n",
        'summary' => '',
        'format' => 'basic_html',
      ],
    ],
  ],
];

$expectations = ['5d1ba3c3-d527-4328-8fce-a6b714c5ef79' => new CdfExpectations($data, ['nid', 'vid', 'changed'])];

return $expectations;
