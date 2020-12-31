<?php

/**
 * @file
 * Expectation for node page scenario.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => '3405e162-4b3e-42ca-aa7b-9ba9fc78eb02',
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
        'target_id' => '4f5e0796-5441-452a-93bf-6dfae35c4a22',
      ],
    ],
  ],
  'revision_timestamp' => [
    'en' => [
      0 => [
        'value' => '1571949727',
      ],
    ],
  ],
  'revision_uid' => [
    'en' => [
      0 => [
        'target_id' => '37fa10c6-a0be-419d-ad7e-69cfb9cca0ff',
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
        'value' => 'Testing Moderation',
      ],
    ],
  ],
  'field_related_article' => [
    'en' => [
      0 => [
        'target_id' => '200ba060-c666-4bb2-a0cc-6347cc1b969e',
      ],
    ],
  ],
  'uid' => [
    'en' => [
      0 => [
        'target_id' => '37fa10c6-a0be-419d-ad7e-69cfb9cca0ff',
      ],
    ],
  ],
  'created' => [
    'en' => [
      0 => [
        'value' => '1571231354',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1571949727',
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
        'value' => 'review',
      ],
    ],
  ],
  'body' => [
    'en' => [
      0 => [
        'value' => "<p>Testing moderation state in-progress</p>\n",
        'summary' => '',
        'format' => 'basic_html',
      ],
    ],
  ],
];

$expectations = ['3405e162-4b3e-42ca-aa7b-9ba9fc78eb02' => new CdfExpectations($data, ['nid', 'vid', 'path', 'changed', 'comment', 'revision_log'])];

return $expectations;
