<?php

/**
 * @file
 * Expectation for node page user scenario.
 */

use Drupal\Tests\acquia_contenthub\Kernel\Stubs\CdfExpectations;

$data = [
  'uuid' => [
    'en' => [
      0 => [
        'value' => '995f955b-08a9-4436-a0c7-1cde093ee174',
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
  'preferred_langcode' => [
    'en' => [
      0 => [
        'value' => 'en',
      ],
    ],
  ],
  'preferred_admin_langcode' => [
    'en' => [],
  ],
  'name' => [
    'en' => [
      0 => [
        'value' => 'admin',
      ],
    ],
  ],
  'pass' => [
    'en' => [
      0 => [
        'value' => '$S$EV9u1jOqu4/m8OG1cQHgnZUZXc7DlWqKz/STcN3r22lf/pYHjS/u',
      ],
    ],
  ],
  'mail' => [
    'en' => [
      0 => [
        'value' => 'admin@example.com',
      ],
    ],
  ],
  'timezone' => [
    'en' => [
      0 => [
        'value' => 'UTC',
      ],
    ],
  ],
  'status' => [
    'en' => [
      0 => [
        'value' => TRUE,
      ],
    ],
  ],
  'created' => [
    'en' => [
      0 => [
        'value' => '1585315609',
      ],
    ],
  ],
  'changed' => [
    'en' => [
      0 => [
        'value' => '1585315681',
      ],
    ],
  ],
  'access' => [
    'en' => [
      0 => [
        'value' => '1585394321',
      ],
    ],
  ],
  'login' => [
    'en' => [
      0 => [
        'value' => '1585394126',
      ],
    ],
  ],
  'init' => [
    'en' => [
      0 => [
        'value' => 'admin@example.com',
      ],
    ],
  ],
  'default_langcode' => [
    'en' => [
      0 => [
        'value' => TRUE,
      ],
    ],
  ],
];

$expectations = [
  '995f955b-08a9-4436-a0c7-1cde093ee174' => new CdfExpectations($data, [
    'uid',
    'user_picture',
    'roles',
  ]),
];

return $expectations;
