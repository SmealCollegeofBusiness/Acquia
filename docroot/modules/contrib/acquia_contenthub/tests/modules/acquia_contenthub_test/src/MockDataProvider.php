<?php

namespace Drupal\acquia_contenthub_test;

use Acquia\ContentHubClient\CDF\ClientCDFObject;

/**
 * Contains test related mock data.
 */
class MockDataProvider {

  const VALID_HOSTNAME = 'https://dev.contenhub.com';

  const VALID_WEBHOOK_URL = 'https://webhook.is-valid.com';

  const ALREADY_REGISTERED_WEBHOOK = 'https://already-registered.webhook.com';

  const VALID_SECRET = 'valid-secret-key';

  const VALID_API_KEY = 'valid-api-key';

  const VALID_CLIENT_NAME = 'valid-client-name';

  const SETTINGS_UUID = '9657377c-30e1-4a5b-9396-0fade30d90e5';

  /**
   * Provide filter mock data.
   *
   * @return array
   *   The filter.
   */
  public static function mockFilter(): array {
    return [
      'name' => 'filter_1',
      'uuid' => 'cfcd1dc9-7891-4e61-90cc-61ab43ca03c7',
    ];
  }

  /**
   * Generates random uuid.
   *
   * @return string
   *   The uuid.
   */
  public static function randomUuid(): string {
    return \Drupal::getContainer()->get('uuid')->generate();
  }

  /**
   * Create a mocked api search response.
   *
   * @return array
   *   Mock search response from api _search
   */
  public static function searchResponse(): array {
    $response =
      [
        '_shards' =>
          [
            'failed' => 0,
            'skipped' => 0,
            'successful' => 5,
            'total' => 5,
          ],
        'hits' =>
          [
            'hits' =>
              [
                0 =>
                  [
                    '_id' => '00000000-00ab-489f-52fa-404bdf8df699',
                    '_index' => 'test_entity_v11',
                    '_score' => NULL,
                    '_source' =>
                      [
                        'data' =>
                          [
                            'attributes' =>
                              [
                                'clientname' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'string',
                                    'value' =>
                                      [
                                        'und' => 'localpubsub',
                                      ],
                                  ],
                                'hash' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'keyword',
                                    'value' =>
                                      [
                                        'und' => '000000defeeefcd73177d7e93a927376279d3381',
                                      ],
                                  ],
                                'publisher' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'boolean',
                                    'value' =>
                                      [
                                        'und' => TRUE,
                                      ],
                                  ],
                                'subscriber' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'boolean',
                                    'value' =>
                                      [
                                        'und' => TRUE,
                                      ],
                                  ],
                              ],
                            'created' => '2017-01-01T14:22:45-05:00',
                            'metadata' =>
                              [
                                'languages' =>
                                  [
                                    'en' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'en',
                                        'label' => 'English',
                                        'langcode' => 'en',
                                        'locked' => FALSE,
                                        'status' => TRUE,
                                        'uuid' => '00000000-171a-4ae8-a4ca-a1a5cfe966c0',
                                        'weight' => 0,
                                      ],
                                    'und' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'und',
                                        'label' => 'Not specified',
                                        'langcode' => 'en',
                                        'locked' => TRUE,
                                        'status' => TRUE,
                                        'uuid' => '11111111-0e5e-43a9-ad87-f8485778168d',
                                        'weight' => 2,
                                      ],
                                    'zh-hans' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'zh-hans',
                                        'label' => 'Chinese, Simplified',
                                        'langcode' => 'en',
                                        'locked' => FALSE,
                                        'status' => TRUE,
                                        'uuid' => '33333333-c297-4eb6-be4f-708718c791ba',
                                        'weight' => 1,
                                      ],
                                    'zxx' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'zxx',
                                        'label' => 'Not applicable',
                                        'langcode' => 'en',
                                        'locked' => TRUE,
                                        'status' => TRUE,
                                        'uuid' => '44444444-5bc3-418d-8983-57f782bbaecd',
                                        'weight' => 3,
                                      ],
                                  ],
                                'metrics' =>
                                  [
                                    'publisher' =>
                                      [
                                        'data' =>
                                          [],
                                        'last_updated' => 0,
                                      ],
                                    'subscriber' =>
                                      [
                                        'data' =>
                                          [],
                                        'last_updated' => 0,
                                      ],
                                  ],
                                'settings' =>
                                  [
                                    'apiKey' => '00000000UAvGWYUJ1uAC',
                                    'name' => 'localpubsub',
                                    'secretKey' => '00000000XZB8TmS2UZOBiwho3uXBHmQRxJYbjZ33',
                                    'sharedSecret' => '00000000M3TuQgv3GVK4SiLxPyFPgyw8rRwFzFQoEbo=',
                                    'url' => 'https://dev.content-hub.acquia.com',
                                    'uuid' => '00000000-00ab-489f-52fa-404bdf8df699',
                                    'webhook' =>
                                      [
                                        'settings_url' => 'http://pubsub.example.com',
                                        'url' => 'http://pubsub.example.com/acquia-contenthub/webhook',
                                        'uuid' => '00000000-55aa-42f5-50d1-2e35b72ae26d',
                                      ],
                                  ],
                                'version' => 2,
                              ],
                            'modified' => '2019-09-19T14:22:45-05:00',
                            'origin' => '00000000-00ab-489f-52fa-404bdf8df699',
                            'type' => 'client',
                            'uuid' => '00000000-00ab-489f-52fa-404bdf8df699',
                          ],
                        'id' => '00000000-00ab-489f-52fa-404bdf8df699',
                        'origin' => '00000000-00ab-489f-52fa-404bdf8df699',
                        'revision' => 0,
                        'subscription' => 'TESTING',
                        'uuid' => '00000000-00ab-489f-52fa-404bdf8df699',
                      ],
                    '_type' => 'entity',
                    'sort' =>
                      [
                        0 => 1568920965000,
                      ],
                  ],
                1 =>
                  [
                    '_id' => '00000000-12bc-442f-46f5-d2694d553429',
                    '_index' => 'test_entity_v11',
                    '_score' => NULL,
                    '_source' =>
                      [
                        'data' =>
                          [
                            'attributes' =>
                              [
                                'clientname' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'string',
                                    'value' =>
                                      [
                                        'und' => 'pubsub',
                                      ],
                                  ],
                                'hash' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'keyword',
                                    'value' =>
                                      [
                                        'und' => '0000000031b94c23fdd499244fa665ec2b9e3610',
                                      ],
                                  ],
                                'publisher' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'boolean',
                                    'value' =>
                                      [
                                        'und' => TRUE,
                                      ],
                                  ],
                                'subscriber' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'boolean',
                                    'value' =>
                                      [
                                        'und' => TRUE,
                                      ],
                                  ],
                              ],
                            'created' => '2019-09-19T18:49:03+00:00',
                            'metadata' =>
                              [
                                'languages' =>
                                  [
                                    'en' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'en',
                                        'label' => 'English',
                                        'langcode' => 'en',
                                        'locked' => FALSE,
                                        'status' => TRUE,
                                        'uuid' => '00000000-171a-4ae8-a4ca-a1a5cfe966c0',
                                        'weight' => 0,
                                      ],
                                    'und' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'und',
                                        'label' => 'Not specified',
                                        'langcode' => 'en',
                                        'locked' => TRUE,
                                        'status' => TRUE,
                                        'uuid' => '11111111-0e5e-43a9-ad87-f8485778168d',
                                        'weight' => 2,
                                      ],
                                    'zh-hans' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'zh-hans',
                                        'label' => 'Chinese, Simplified',
                                        'langcode' => 'en',
                                        'locked' => FALSE,
                                        'status' => TRUE,
                                        'uuid' => '33333333-c297-4eb6-be4f-708718c791ba',
                                        'weight' => 1,
                                      ],
                                    'zxx' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'zxx',
                                        'label' => 'Not applicable',
                                        'langcode' => 'en',
                                        'locked' => TRUE,
                                        'status' => TRUE,
                                        'uuid' => '44444444-5bc3-418d-8983-57f782bbaecd',
                                        'weight' => 3,
                                      ],
                                  ],
                                'metrics' =>
                                  [
                                    'publisher' =>
                                      [
                                        'data' =>
                                          [
                                            'confirmed' => '3',
                                          ],
                                        'last_updated' => 1383228800,
                                      ],
                                    'subscriber' =>
                                      [
                                        'data' =>
                                          [
                                            'imported' => '57',
                                            'queued' => '5',
                                          ],
                                        'last_updated' => 1483228800,
                                      ],
                                  ],
                                'settings' =>
                                  [
                                    'apiKey' => '00000000UAvGWYUJ1uAC',
                                    'name' => 'pubsub',
                                    'secretKey' => '00000000XZB8TmS2UZOBiwho3uXBHmQRxJYbjZ33',
                                    'sharedSecret' => '00000000M3TuQgv3GVK4SiLxPyFPgyw8rRwFzFQoEbo=',
                                    'url' => 'https://dev.content-hub.acquia.com',
                                    'uuid' => '00000000-12bc-442f-46f5-d2694d553429',
                                    'webhook' =>
                                      [
                                        'settings_url' => 'http://pubsubonline.example.com',
                                        'url' => 'http://pubsubonline.example.com/acquia-contenthub/webhook',
                                        'uuid' => '00000000-4b0b-4c99-5b1d-0177597c2ca7',
                                      ],
                                  ],
                                'version' => 2,
                              ],
                            'modified' => '2019-09-19T18:49:03+00:00',
                            'origin' => '00000000-12bc-442f-46f5-d2694d553429',
                            'type' => 'client',
                            'uuid' => '00000000-12bc-442f-46f5-d2694d553429',
                          ],
                        'id' => '00000000-12bc-442f-46f5-d2694d553429',
                        'origin' => '00000000-12bc-442f-46f5-d2694d553429',
                        'revision' => 0,
                        'subscription' => 'TESTING',
                        'uuid' => '00000000-12bc-442f-46f5-d2694d553429',
                      ],
                    '_type' => 'entity',
                    'sort' =>
                      [
                        0 => 1568918943000,
                      ],
                  ],
                2 =>
                  [
                    '_id' => '00000000-42bf-4860-6d03-4e3411ee32b4',
                    '_index' => 'test_entity_v11',
                    '_score' => NULL,
                    '_source' =>
                      [
                        'data' =>
                          [
                            'attributes' =>
                              [
                                'clientname' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'string',
                                    'value' =>
                                      [
                                        'und' => 'sub',
                                      ],
                                  ],
                                'hash' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'keyword',
                                    'value' =>
                                      [
                                        'und' => '000000003667ad50af5b76bff12aa78393ae8479',
                                      ],
                                  ],
                                'publisher' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'boolean',
                                    'value' =>
                                      [
                                        'und' => FALSE,
                                      ],
                                  ],
                                'subscriber' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'boolean',
                                    'value' =>
                                      [
                                        'und' => TRUE,
                                      ],
                                  ],
                              ],
                            'created' => '2019-09-19T18:49:00+00:00',
                            'metadata' =>
                              [
                                'languages' =>
                                  [
                                    'en' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'en',
                                        'label' => 'English',
                                        'langcode' => 'en',
                                        'locked' => FALSE,
                                        'status' => TRUE,
                                        'uuid' => '00000000-171a-4ae8-a4ca-a1a5cfe966c0',
                                        'weight' => 0,
                                      ],
                                    'und' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'und',
                                        'label' => 'Not specified',
                                        'langcode' => 'en',
                                        'locked' => TRUE,
                                        'status' => TRUE,
                                        'uuid' => '11111111-0e5e-43a9-ad87-f8485778168d',
                                        'weight' => 2,
                                      ],
                                    'zh-hans' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'zh-hans',
                                        'label' => 'Chinese, Simplified',
                                        'langcode' => 'en',
                                        'locked' => FALSE,
                                        'status' => TRUE,
                                        'uuid' => '33333333-c297-4eb6-be4f-708718c791ba',
                                        'weight' => 1,
                                      ],
                                    'zxx' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'zxx',
                                        'label' => 'Not applicable',
                                        'langcode' => 'en',
                                        'locked' => TRUE,
                                        'status' => TRUE,
                                        'uuid' => '44444444-5bc3-418d-8983-57f782bbaecd',
                                        'weight' => 3,
                                      ],
                                  ],
                                'metrics' =>
                                  [
                                    'subscriber' =>
                                      [
                                        'data' =>
                                          [
                                            'imported' => '34',
                                            'queued' => '23',
                                          ],
                                        'last_updated' => 1483228800,
                                      ],
                                  ],
                                'settings' =>
                                  [
                                    'apiKey' => '00000000UAvGWYUJ1uAC',
                                    'name' => 'sub',
                                    'secretKey' => '00000000XZB8TmS2UZOBiwho3uXBHmQRxJYbjZ33',
                                    'sharedSecret' => '00000000M3TuQgv3GVK4SiLxPyFPgyw8rRwFzFQoEbo=',
                                    'url' => 'https://dev.content-hub.acquia.com',
                                    'uuid' => '00000000-42bf-4860-6d03-4e3411ee32b4',
                                    'webhook' =>
                                      [
                                        'settings_url' => 'http://subonline.example.com',
                                        'url' => 'http://subonline.example.com/acquia-contenthub/webhook',
                                        'uuid' => '00000000-72b6-4df8-710b-59790112588e',
                                      ],
                                  ],
                                'version' => 2,
                              ],
                            'modified' => '2019-09-19T18:49:00+00:00',
                            'origin' => '00000000-42bf-4860-6d03-4e3411ee32b4',
                            'type' => 'client',
                            'uuid' => '00000000-42bf-4860-6d03-4e3411ee32b4',
                          ],
                        'id' => '00000000-42bf-4860-6d03-4e3411ee32b4',
                        'origin' => '00000000-42bf-4860-6d03-4e3411ee32b4',
                        'revision' => 0,
                        'subscription' => 'TESTING',
                        'uuid' => '00000000-42bf-4860-6d03-4e3411ee32b4',
                      ],
                    '_type' => 'entity',
                    'sort' =>
                      [
                        0 => 1568918940000,
                      ],
                  ],
                3 =>
                  [
                    '_id' => '00000000-9987-4b2a-74b9-d758c8b60d12',
                    '_index' => 'test_entity_v11',
                    '_score' => NULL,
                    '_source' =>
                      [
                        'data' =>
                          [
                            'attributes' =>
                              [
                                'clientname' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'string',
                                    'value' =>
                                      [
                                        'und' => 'pub',
                                      ],
                                  ],
                                'hash' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'keyword',
                                    'value' =>
                                      [
                                        'und' => '0000000027dc5d3a7aa60fbc8189f34fe577f00e',
                                      ],
                                  ],
                                'publisher' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'boolean',
                                    'value' =>
                                      [
                                        'und' => TRUE,
                                      ],
                                  ],
                                'subscriber' =>
                                  [
                                    'metadata' => '',
                                    'type' => 'boolean',
                                    'value' =>
                                      [
                                        'und' => FALSE,
                                      ],
                                  ],
                              ],
                            'created' => '2019-09-19T18:47:02+00:00',
                            'metadata' =>
                              [
                                'languages' =>
                                  [
                                    'en' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'en',
                                        'label' => 'English',
                                        'langcode' => 'en',
                                        'locked' => FALSE,
                                        'status' => TRUE,
                                        'uuid' => '00000000-171a-4ae8-a4ca-a1a5cfe966c0',
                                        'weight' => 0,
                                      ],
                                    'und' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'und',
                                        'label' => 'Not specified',
                                        'langcode' => 'en',
                                        'locked' => TRUE,
                                        'status' => TRUE,
                                        'uuid' => '11111111-0e5e-43a9-ad87-f8485778168d',
                                        'weight' => 2,
                                      ],
                                    'zh-hans' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'zh-hans',
                                        'label' => 'Chinese, Simplified',
                                        'langcode' => 'en',
                                        'locked' => FALSE,
                                        'status' => TRUE,
                                        'uuid' => '33333333-c297-4eb6-be4f-708718c791ba',
                                        'weight' => 1,
                                      ],
                                    'zxx' =>
                                      [
                                        'direction' => 'ltr',
                                        'id' => 'zxx',
                                        'label' => 'Not applicable',
                                        'langcode' => 'en',
                                        'locked' => TRUE,
                                        'status' => TRUE,
                                        'uuid' => '44444444-5bc3-418d-8983-57f782bbaecd',
                                        'weight' => 3,
                                      ],
                                  ],
                                'metrics' =>
                                  [
                                    'publisher' =>
                                      [
                                        'data' =>
                                          [
                                            'confirmed' => '30',
                                            'exported' => '5',
                                          ],
                                        'last_updated' => 1483228800,
                                      ],
                                  ],
                                'settings' =>
                                  [
                                    'apiKey' => '00000000UAvGWYUJ1uAC',
                                    'name' => 'pub',
                                    'secretKey' => '00000000XZB8TmS2UZOBiwho3uXBHmQRxJYbjZ33',
                                    'sharedSecret' => '00000000M3TuQgv3GVK4SiLxPyFPgyw8rRwFzFQoEbo=',
                                    'url' => 'https://dev.content-hub.acquia.com',
                                    'uuid' => '00000000-9987-4b2a-74b9-d758c8b60d12',
                                    'webhook' =>
                                      [
                                        'settings_url' => 'http://pubonline.example.com',
                                        'url' => 'http://pubonline.example.com/acquia-contenthub/webhook',
                                        'uuid' => '00000000-5ac3-4e9f-7fe9-776b56a389c0',
                                      ],
                                  ],
                                'version' => 2,
                              ],
                            'modified' => '2019-09-19T18:47:02+00:00',
                            'origin' => '00000000-9987-4b2a-74b9-d758c8b60d12',
                            'type' => 'client',
                            'uuid' => '00000000-9987-4b2a-74b9-d758c8b60d12',
                          ],
                        'id' => '00000000-9987-4b2a-74b9-d758c8b60d12',
                        'origin' => '00000000-9987-4b2a-74b9-d758c8b60d12',
                        'revision' => 0,
                        'subscription' => 'TESTING',
                        'uuid' => '00000000-9987-4b2a-74b9-d758c8b60d12',
                      ],
                    '_type' => 'entity',
                    'sort' =>
                      [
                        0 => 1568918822000,
                      ],
                  ],
              ],
            'max_score' => NULL,
            'total' => 2,
          ],
        'timed_out' => FALSE,
        'took' => 3,
      ];

    return $response;
  }

  /**
   * Creates new local client cdf object.
   *
   * @param string $uuid
   *   UUID of clientcdf.
   *
   * @return \Acquia\ContentHubClient\CDF\ClientCDFObject
   *   Client cdf object.
   *
   * @throws \Exception
   */
  public static function getClient($uuid) {
    $search_response = self::searchResponse();
    $map = [
      '00000000-00ab-489f-52fa-404bdf8df699' => 0,
      '00000000-12bc-442f-46f5-d2694d553429' => 1,
      '00000000-42bf-4860-6d03-4e3411ee32b4' => 2,
      '00000000-9987-4b2a-74b9-d758c8b60d12' => 3,
    ];
    $cdf = $search_response['hits']['hits'][$map[$uuid]]['_source']['data'];
    return ClientCDFObject::fromArray($cdf);
  }

}
