<?php

/**
 * @file
 * Defines filter migrations from 1.x to 2.x.
 */

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Language\LanguageInterface;

/**
 * Converts a Legacy Content Hub Filter to a Cloud Filter.
 *
 * @param array $data
 *   An array of legacy Content Hub Filter.
 *
 * @return array
 *   The Cloud Filter ready to be saved in Content Hub.
 */
function acquia_contenthub_subscriber_migrate_filter(array $data) {
  // Test all supported languages.
  $supported_languages = array_keys(\Drupal::languageManager()->getLanguages(LanguageInterface::STATE_ALL));
  // Building ES query.
  $query = [
    'query' => [
      'bool' => [
        'filter' => [
          [
            'term' => [
              'data.type' => 'drupal8_content_entity',
            ],
          ],
        ],
      ],
    ],
    'highlight' => [
      'fields' => [
        '*' => new \stdClass(),
      ],
    ],
  ];
  // Adding search term.
  $search_term = [];
  if (!empty($data['search_term'])) {
    foreach ($supported_languages as $language) {
      $search_term[] = [
        'match' => [
          "data.attributes.label.value.{$language}" => $data['search_term'],
        ],
      ];
    }
    $query['query']['bool']['filter'][] = [
      'bool' => [
        'should' => $search_term,
        'minimum_should_match' => 1,
      ],
    ];
  }
  // Adding entity types.
  if (!empty($data['entity_types'])) {
    $query['query']['bool']['filter'][] = [
      'terms' => [
        'data.attributes.entity_type.value.und' => $data['entity_types'],
      ],
    ];
  }
  // Adding bundles.
  if (!empty($data['bundles'])) {
    $bundles = [];
    foreach ($data['bundles'] as $bundle) {
      $bundles[] = [
        'term' => [
          'data.attributes.bundle.value.und' => $bundle,
        ],
      ];
    }
    $query['query']['bool']['filter'][] = [
      'bool' => [
        'should' => $bundles,
      ],
    ];
  }
  // Adding origin.
  if (!empty($data['source'])) {
    $origins = explode(',', $data['source']);
    foreach ($origins as $origin) {
      if (Uuid::isvalid($origin)) {
        $match_origin[] = [
          'match' => [
            'data.origin' => $origin,
          ],
        ];
      }
    }
    if (isset($match_origin)) {
      $query['query']['bool']['filter'][] = [
        'bool' => [
          'should' => $match_origin,
          'minimum_should_match' => 1,
        ],
      ];
    }
  }
  // Tags.
  if (!empty($data['tags'])) {
    $tags = explode(',', $data['tags']);
    foreach ($tags as $tag) {
      if (Uuid::isvalid($tag)) {
        $match_tags[] = [
          'bool' => [
            'should' => [
              [
                'match' => [
                  'data.uuid' => $tag,
                ],
              ],
              [
                'match' => [
                  'data.attributes.tags.value.und' => $tag,
                ],
              ],
            ],
          ],
        ];
      }
    }
    if (!empty($match_tags)) {
      $query['query']['bool']['filter'][] = [
        'bool' => [
          'should' => $match_tags,
          'minimum_should_match' => 1,
        ],
      ];
    }
  }
  // Modified Date.
  $date_modified['time_zone'] = '+01:00';
  if (!empty($data['from_date'])) {
    $date_modified['gte'] = $data['from_date'];
  }
  if (!empty($data['to_date'])) {
    $date_modified['lte'] = $data['to_date'];
  }
  if (!empty($data['from_date']) || !empty($data['to_date'])) {
    $query['query']['bool']['filter'][] = [
      'range' => [
        'data.modified' => $date_modified,
      ],
    ];
  }
  // Should we include sorting in the filter?
  $query['sort'] = [
    'data.modified' => 'desc',
  ];
  return [
    'name' => $data['name'],
    'data' => $query,
    'metadata' => [
      'search_criterion' => $data,
    ],
  ];
}

/**
 * Saves a Cloud Filter in Content Hub.
 *
 * @param array $contenthub_filter
 *   The Cloud Filter ready to be saved.
 *
 * @return array|bool
 *   The migrated Cloud Filter or FALSE.
 */
function acquia_contenthub_subscriber_put_filter(array $contenthub_filter) {
  $logger = \Drupal::logger('acquia_contenthub_subscriber');
  /** @var \Drupal\acquia_contenthub\Client\ClientFactory $factory */
  $factory = \Drupal::service("acquia_contenthub.client.factory");
  $settings = $factory->getSettings();
  if ($client = $factory->getClient($settings)) {
    try {
      $filters = $client->listFilters();
      $plexus_filter = _acquia_contenthub_subscriber_find_cloud_filter($contenthub_filter, $filters);
      if (empty($plexus_filter)) {
        $plexus_filter = $client->putFilter($contenthub_filter['data']['query'], $contenthub_filter['name'], NULL, $contenthub_filter['metadata']);
        $logger->info(sprintf('Created cloud filter "%s"', $contenthub_filter['name']));
      }
      if (empty($plexus_filter['uuid'])) {
        $logger->error(sprintf('Could not migrate Filter "%s".', $contenthub_filter['name']));
        return FALSE;
      }
      // Assign Filter to Webhook.
      $webhook = \Drupal::configFactory()->get('acquia_contenthub.admin_settings')->get('webhook');
      $webhook_uuid = $webhook['uuid'] ?? NULL;
      if (Uuid::isValid($webhook_uuid)) {
        $response = $client->addFilterToWebhook($plexus_filter['uuid'], $webhook_uuid);
        if (!empty($response['success'])) {
          $logger->info(sprintf('Successfully attached Cloud filter "%s" to this webhook.', $contenthub_filter['name']));
          return $plexus_filter;
        }
      }
    }
    catch (\Exception $ex) {
      $logger->error(sprintf('Could not migrate Filter "%s": Error message: %s.', $contenthub_filter['name'], $ex->getMessage()));
    }
  }
  return FALSE;
}

/**
 * Tries to find a Cloud filter with the same name as the legacy filter passed.
 *
 * - If a filter with the same name is found then check for the query.
 * - If the query is the same then it is the same filter and return it.
 * - If the query is different, alter the name and restart the search.
 * - If none is found, return NULL, but keep last altered filter name.
 *
 * @param array $contenthub_filter
 *   A legacy Content Hub Filter.
 * @param array|null $filters
 *   An array of Cloud Filters in the 'data' key.
 *
 * @return array|null
 *   The Cloud Filter found or NULL.
 */
function _acquia_contenthub_subscriber_find_cloud_filter(array &$contenthub_filter, array $filters = NULL) {
  if (empty($filters['data'])) {
    return NULL;
  }
  $cloud_filter = [];
  foreach ($filters['data'] as $filter) {
    $name = $filter['name'];
    $cloud_filter[$name] = $filter;
  }
  $filter_name = $contenthub_filter['name'];
  $i = 0;

  while (TRUE) {
    if (in_array($contenthub_filter['name'], array_keys($cloud_filter))) {
      // A filter with the same name already exists.
      $name = $contenthub_filter['name'];
      if ($cloud_filter[$name]['data']['query'] === $contenthub_filter['data']['query']) {
        // If they have the same data query, then it is the same filter.
        return $cloud_filter[$name];
      }
      else {
        // A filter with the same name but different query already exists in
        // CH. Then it is not really the same filter. Alter the name and
        // recheck again.
        $contenthub_filter['name'] = $filter_name . '_' . $i++;
        $contenthub_filter['metadata']['search_criterion']['name'] = $contenthub_filter['name'];
      }
    }
    else {
      return NULL;
    }
  }
}
