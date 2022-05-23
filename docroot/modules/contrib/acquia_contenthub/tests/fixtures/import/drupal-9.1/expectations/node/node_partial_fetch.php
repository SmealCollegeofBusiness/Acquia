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
        'value' => '22',
      ],
    ],
  ],
  'uuid' => [
    'en' => [
      0 => [
        'value' => 'b729de7e-913d-4dc1-aacf-c40cd5fef034',
      ],
    ],
  ],
  'vid' => [
    'en' => [
      0 => [
        'value' => '49',
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
        'value' => '1646648894',
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
        'target_id' => '31',
      ],
    ],
  ],
  'title' => [
    'en' => [
      0 => [
        'value' => 'Check import optimization.',
      ],
    ],
  ],
  'created' => [
    'en' => [
      0 => [
        'value' => '1645970643',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1646648894',
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
        'alias' => '/test-import-optimisation',
        'pid' => '1',
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
        'value' => '<p>This is a test article created to check whether import optimisation is possible.</p>

<p>We\'ll create an article node, 3 tags, one path alias and import the node on subscriber.</p>

<p>Next time we\'ll change the article plus one tag and then we\'ll see if we can skip fetching the dependencies(except one changed tag) for this article on 2nd import by comparing the hashes to the import table.</p>

<p>Now change the node to update the hash.</p>

<p>2nd round of test with more optimisation.</p>

<p>Resave content. again</p>
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
        'cid' => '0',
        'last_comment_timestamp' => '1645971035',
        'last_comment_name' => NULL,
        'last_comment_uid' => '1',
        'comment_count' => '0',
      ],
    ],
  ],
  'field_image' => [
    'en' => [],
  ],
  'field_tags' => [
    'en' => [
      0 => [
        'target_id' => '13',
      ],
      1 => [
        'target_id' => '14',
      ],
      2 => [
        'target_id' => '15',
      ],
      3 => [
        'target_id' => '16',
      ],
      4 => [
        'target_id' => '17',
      ],
    ],
  ],
];

$expectations['b729de7e-913d-4dc1-aacf-c40cd5fef034'] = new CdfExpectations($data, []);

return $expectations;
