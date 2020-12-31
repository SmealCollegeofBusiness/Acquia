<?php

/**
 * @file
 * Media file expectation.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$expectations = [];

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => '0f353016-de0f-4268-859c-9ed58a4d6f36',
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
  'uid' => [
    'en' => [
      0 => [
        'target_id' => '995f955b-08a9-4436-a0c7-1cde093ee174',
      ],
    ],
  ],
  'bundle' => [
    'en' => [
      0 => [
        'target_id' => 'bf33b1a3-4f20-4818-ba03-50cea4d09ac4',
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
        'value' => 'Media item#1',
      ],
    ],
  ],
  'thumbnail' => [
    'en' => [
      0 => [
        'target_id' => '083607fb-df43-4efb-a66c-7a44fe018a62',
        'alt' => 'Thumbnail',
        'title' => 'Media item#1',
        'width' => '637',
        'height' => '848',
      ],
    ],
  ],
  'created' => [
    'en' => [
      0 => [
        'value' => '1545832296',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1545832321',
      ],
    ],
  ],
  'default_langcode' => [
    'en' => [
      0 => [
        'value' => 1,
      ],
    ],
  ],
  'revision_default' => [
    'en' => [
      0 => [
        'value' => 1,
      ],
    ],
  ],
  'revision_translation_affected' => [
    'en' => [
      0 => [
        'value' => 1,
      ],
    ],
  ],
  'field_media_image' => [
    'en' => [
      0 => [
        'target_id' => '083607fb-df43-4efb-a66c-7a44fe018a62',
        'alt' => 'Thumbnail',
        'title' => 'Media item#1',
        'width' => '637',
        'height' => '848',
      ],
    ],
  ],
];

// 'revision_created' changes dynamically. Skip this field.
$expectations['0f353016-de0f-4268-859c-9ed58a4d6f36'] = new CdfExpectations($data, [
  'revision_created',
  'mid',
  'vid',
  'path',
]);

return $expectations;
