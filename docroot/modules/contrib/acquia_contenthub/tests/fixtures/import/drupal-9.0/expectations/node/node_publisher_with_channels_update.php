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
        'value' => 'aa40053e-d9fe-420b-a439-a9a550e90568',
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
        'target_id' => '2e2c6696-4441-480c-9708-8d9802a878ad',
      ],
    ],
  ],
  'revision_timestamp' => [
    'en' => [
      0 => [
        'value' => '1571598495',
      ],
    ],
  ],
  'revision_uid' => [
    'en' => [
      0 => [
        'target_id' => 'e2c2cca5-49b7-4a94-a30c-945bc8888d07',
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
        'value' => 'Test hungarian content - channel specific',
      ],
    ],
  ],
  'uid' => [
    'en' => [
      0 => [
        'target_id' => 'e2c2cca5-49b7-4a94-a30c-945bc8888d07',
      ],
    ],
  ],
  'created' => [
    'en' => [
      0 => [
        'value' => '1571236726',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1545339655',
      ],
    ],
  ],
  'promote' => [
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
        'value' => "Test english label",
        'summary' => '',
        'format' => 'plain_text',
      ],
    ],
  ],
];

$expectations = ['aa40053e-d9fe-420b-a439-a9a550e90568' => new CdfExpectations($data, ['nid', 'vid', 'changed', 'created', 'path', 'content_translation_source', 'content_translation_outdated'])];

return $expectations;
