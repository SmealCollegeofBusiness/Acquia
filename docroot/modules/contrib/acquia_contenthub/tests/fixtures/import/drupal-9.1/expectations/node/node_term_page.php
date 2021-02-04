<?php

/**
 * @file
 * Expectation for node term page scenario.
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
        'value' => '1585399034',
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
        'value' => '1585399034',
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
    'en' => [
      0 => [
        'target_id' => '20b902fa-e233-4cfc-9012-6824a1d256ea',
      ],
    ],
  ],
];

$expectations = ['40253012-2a03-47c1-86b8-87e4d0adf091' => new CdfExpectations($data, ['nid', 'vid'])];

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => '20b902fa-e233-4cfc-9012-6824a1d256ea',
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
        'value' => 'taxonomy_term',
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
  'name' => [
    'en' => [
      0 => [
        'value' => 'Category 1 - 1 - 1',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1546545959',
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
  'parent' => [
    'en' => [
      0 => [
        'target_id' => 'e07f1e2a-83ec-44ba-b874-9cbb00140675',
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
  'description' => [
    'en' => [
      0 => [
        'value' => NULL,
        'format' => NULL,
      ],
    ],
  ],
];

$expectations['20b902fa-e233-4cfc-9012-6824a1d256ea'] = new CdfExpectations($data, ['tid', 'vid', 'weight', 'revision_id', 'revision_created', 'revision_default', 'revision_log_message', 'revision_translation_affected']);

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => 'e07f1e2a-83ec-44ba-b874-9cbb00140675',
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
        'value' => 'taxonomy_term',
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
  'name' => [
    'en' => [
      0 => [
        'value' => 'Category 1 - 1',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1546545945',
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
  'parent' => [
    'en' => [
      0 => [
        'target_id' => '17ce8cc4-edfe-4ca7-809d-93abaf09960c',
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
  'description' => [
    'en' => [
      0 => [
        'value' => NULL,
        'format' => NULL,
      ],
    ],
  ],
];

$expectations['e07f1e2a-83ec-44ba-b874-9cbb00140675'] = new CdfExpectations($data, ['tid', 'vid', 'weight', 'revision_id', 'revision_created', 'revision_default', 'revision_log_message', 'revision_translation_affected']);

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => '17ce8cc4-edfe-4ca7-809d-93abaf09960c',
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
        'value' => 'taxonomy_term',
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
  'name' => [
    'en' => [
      0 => [
        'value' => 'Category 1',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1546545932',
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
  'path' => [
    'en' => [
      0 => [
        'langcode' => 'en',
      ],
    ],
  ],
  'description' => [
    'en' => [
      0 => [
        'value' => NULL,
        'format' => NULL,
      ],
    ],
  ],
];

$expectations['17ce8cc4-edfe-4ca7-809d-93abaf09960c'] = new CdfExpectations($data, ['tid', 'vid', 'weight', 'revision_id', 'revision_created', 'revision_default', 'revision_log_message', 'revision_translation_affected']);

return $expectations;
