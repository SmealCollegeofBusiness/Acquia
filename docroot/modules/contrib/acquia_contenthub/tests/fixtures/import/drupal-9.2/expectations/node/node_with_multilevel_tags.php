<?php

/**
 * @file
 * Text Expectations.
 *
 * This file outlines test expectations for the node-partial-fetch.json
 * fixture.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'nid' => [
    'en' => [
      0 => [
        'value' => '112',
      ],
    ],
  ],
  'uuid' => [
    'en' => [
      0 => [
        'value' => '35ebd04f-da34-4804-affb-ca4bd79c1536',
      ],
    ],
  ],
  'vid' => [
    'en' => [
      0 => [
        'value' => '169',
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
        'target_id' => 'article',
      ],
    ],
  ],
  'revision_timestamp' => [
    'en' => [
      0 => [
        'value' => '1654609728',
      ],
    ],
  ],
  'revision_uid' => [
    'en' => [
      0 => [
        'target_id' => '1',
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
  'uid' => [
    'en' => [
      0 => [
        'target_id' => '1',
      ],
    ],
  ],
  'title' => [
    'en' => [
      0 => [
        'value' => 'Article with multiple tags in heirarchy v2',
      ],
    ],
  ],
  'created' => [
    'en' => [
      0 => [
        'value' => '1654609649',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1654609728',
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
  'path' => [
    'en' => [
      0 => [
        'alias' => '/article-wuth-multiple-tags-heirarchy-v2',
        'pid' => '10',
        'langcode' => 'en',
      ],
    ],
  ],
  'content_translation_source' => [
    'en' => [
      0 => [
        'value' => 'und',
      ],
    ],
  ],
  'content_translation_outdated' => [
    'en' => [
      0 => [
        'value' => '0',
      ],
    ],
  ],
  'body' => [
    'en' => [
      0 => [
        'value' => '<p>blahbdfdsfsdf</p>
',
        'summary' => '',
        'format' => 'basic_html',
      ],
    ],
  ],
  'comment' => [
    'en' => [
      0 => [
        'status' => '2',
      ],
    ],
  ],
  'field_image' => [
    'en' => [],
  ],
  'field_tags' => [
    'en' => [
      0 => [
        'target_id' => '21',
      ],
      1 => [
        'target_id' => '22',
      ],
      2 => [
        'target_id' => '20',
      ],
    ],
  ],
];

$expectations['35ebd04f-da34-4804-affb-ca4bd79c1536'] = new CdfExpectations($data, []);

return $expectations;
