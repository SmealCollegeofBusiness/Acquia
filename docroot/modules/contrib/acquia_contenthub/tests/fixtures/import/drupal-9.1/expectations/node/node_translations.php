<?php

/**
 * @file
 * Expectation for node translations scenario.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => 'b0137bab-a80e-4305-84fe-4d99ffd906c5',
      ],
    ],
    'be' => [
      0 => [
        'value' => 'b0137bab-a80e-4305-84fe-4d99ffd906c5',
      ],
    ],
  ],
  'langcode' => [
    'en' => [
      0 => [
        'value' => 'en',
      ],
    ],
    'be' => [
      0 => [
        'value' => 'be',
      ],
    ],
  ],
  'type' => [
    'en' => [
      0 => [
        'target_id' => 'd94c6335-6276-478a-90ff-64a5741c3bbc',
      ],
    ],
    'be' => [
      0 => [
        'target_id' => 'd94c6335-6276-478a-90ff-64a5741c3bbc',
      ],
    ],
  ],
  'revision_timestamp' => [
    'en' => [
      0 => [
        'value' => '1578405551',
      ],
    ],
    'be' => [
      0 => [
        'value' => '1578405551',
      ],
    ],
  ],
  'revision_uid' => [
    'en' => [
      0 => [
        'target_id' => 'a86b814f-3fa8-4587-b9f5-f2ff3dbb38ca',
      ],
    ],
    'be' => [
      0 => [
        'target_id' => 'a86b814f-3fa8-4587-b9f5-f2ff3dbb38ca',
      ],
    ],
  ],
  'revision_log' => [
    'en' => [],
    'be' => [],
  ],
  'status' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
    'be' => [
      0 => [
        'value' => '1',
      ],
    ],
  ],
  'title' => [
    'en' => [
      0 => [
        'value' => 'Test page node',
      ],
    ],
    'be' => [
      0 => [
        'value' => 'Тэставая старонка',
      ],
    ],
  ],
  'uid' => [
    'en' => [
      0 => [
        'target_id' => '4eda3a4b-4f2a-4c51-bbb8-dc46b5566412',
      ],
    ],
    'be' => [
      0 => [
        'target_id' => '4eda3a4b-4f2a-4c51-bbb8-dc46b5566412',
      ],
    ],
  ],
  'created' => [
    'en' => [
      0 => [
        'value' => '1547719026',
      ],
    ],
    'be' => [
      0 => [
        'value' => '1547719101',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1578405527',
      ],
    ],
    'be' => [
      0 => [
        'value' => '1578405551',
      ],
    ],
  ],
  'field_basic_image' => [
    'en' => [
      0 => [
        'target_id' => '88ce1479-0445-4b31-9556-d24b7ecdbf70',
        'alt' => 'Alt_text_en',
        'title' => '',
        'width' => 768,
        'height' => 512,
      ],
    ],
    'be' => [
      0 => [
        'target_id' => 'aa6c74ba-4701-4341-a534-76be9462ffb8',
        'alt' => 'Alt_text_be',
        'title' => '',
        'width' => 768,
        'height' => 512,
      ],
    ],
  ],
  'content_translation_source' => [
    'en' => [
      0 => [
        'value' => 'und',
      ],
    ],
    'be' => [
      0 => [
        'value' => 'en',
      ],
    ],
  ],
  'content_translation_outdated' => [
    'en' => [
      0 => [
        'value' => 0,
      ],
    ],
    'be' => [
      0 => [
        'value' => 0,
      ],
    ],
  ],
  'promote' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
    'be' => [
      0 => [
        'value' => '1',
      ],
    ],
  ],
  'default_langcode' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
    'be' => [
      0 => [
        'value' => '0',
      ],
    ],
  ],
  'revision_default' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
    'be' => [
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
    'be' => [
      0 => [
        'value' => '1',
      ],
    ],
  ],
  'nid' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
    'be' => [
      0 => [
        'value' => '1',
      ],
    ],
  ],
  'vid' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
    'be' => [
      0 => [
        'value' => '1',
      ],
    ],
  ],
  'sticky' => [
    'en' => [
      0 => [
        'value' => '1',
      ],
    ],
    'be' => [
      0 => [
        'value' => '0',
      ],
    ],
  ],
  'path' => [
    'en' => [
      0 => [
        'langcode' => 'en',
      ],
    ],
    'be' => [
      0 => [
        'langcode' => 'be',
      ],
    ],
  ],
  'body' => [
    'en' => [
      0 => [
        'value' => "<p>A test page node</p>\r\n",
        'summary' => '',
        'format' => 'basic_html',
      ],
    ],
    'be' => [
      0 => [
        'value' => "<p>Гэта тэст</p>\r\n",
        'summary' => '',
        'format' => 'basic_html',
      ],
    ],
  ],
];

$expectations = [
  'b0137bab-a80e-4305-84fe-4d99ffd906c5' => new CdfExpectations($data),
];

return $expectations;
