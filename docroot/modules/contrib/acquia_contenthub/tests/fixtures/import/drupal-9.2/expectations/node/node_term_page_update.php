<?php

/**
 * @file
 * Expectation for node term page update scenario.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => '40253012-2a03-47c1-86b8-87e4d0adf091',
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
        'target_id' => '69f7efaf-cbd7-412e-a717-4f5a1603fe65',
      ],
    ],
  ],
  'revision_timestamp' => [
    'en' => [
      0 => [
        'value' => '1585400064',
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
        'value' => '1',
      ],
    ],
  ],
  'title' => [
    'en' => [
      0 => [
        'value' => 'Test Node',
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
        'value' => '1585398987',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1585400064',
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
    'en' => [],
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
        'value' => "<p>Test node body</p>\n",
        'summary' => '',
        'format' => 'basic_html',
      ],
    ],
  ],
  'field_custom_category' => [
    'en' => [],
  ],
];

$expectations = ['40253012-2a03-47c1-86b8-87e4d0adf091' => new CdfExpectations($data, ['nid', 'vid'])];

return $expectations;
