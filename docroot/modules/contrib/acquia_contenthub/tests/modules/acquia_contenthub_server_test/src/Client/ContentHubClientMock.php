<?php

namespace Drupal\acquia_contenthub_server_test\Client;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\ContentHubDescriptor;
use Acquia\ContentHubClient\Settings;
use Acquia\ContentHubClient\Webhook;
use Acquia\Hmac\Guzzle\HmacAuthMiddleware;
use Acquia\Hmac\Key;
use Drupal\acquia_contenthub_test\MockDataProvider;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Mocks server responses.
 */
class ContentHubClientMock extends ContentHubClient {

  /**
   * Used for testing purposes.
   *
   * Based on the value custom behaviour can be added to ContentHubClientMock.
   */
  public const X_ACH_TEST_EXECUTION_RESULT = 'x-ach-test-execution-result';

  /**
   * An arbitrary message if needed for the test execution.
   *
   * For example in cases of exceptions.
   */
  public const X_ACH_TEST_EXECUTION_MSG = 'x-ach-test-execution-msg';

  /**
   * Content Hub settings.
   *
   * @var array
   */
  protected $options = [];

  /**
   * Test webhook values.
   *
   * @var array
   */
  protected $testWebhook = [
    'uuid' => '4e68da2e-a729-4c81-9c16-e4f8c05a11be',
    'url' => '',
  ];

  /**
   * {@inheritdoc}
   */
  public static function register(LoggerInterface $logger, EventDispatcherInterface $dispatcher, $name, $url, $api_key, $secret, $api_version = 'v2') {
    if ($url !== MockDataProvider::VALID_HOSTNAME) {
      throw new RequestException(
        "Could not get authorization from Content Hub to register client ${name}. Are your credentials inserted correctly?",
        new Request('POST', \Drupal::request()->getRequestUri())
      );
    }

    if ($api_key !== MockDataProvider::VALID_API_KEY) {
      self::generateErrorResponse("[4001] Not Found: Customer Key $api_key could not be found.", 401);
    }

    if ($secret !== MockDataProvider::VALID_SECRET) {
      self::generateErrorResponse('[4001] Signature for the message does not match expected signature for API key.', 401);
    }

    if ($name !== MockDataProvider::VALID_CLIENT_NAME) {
      self::generateErrorResponse('Name is already in use within subscription.', 4006);
    }

    $config = [
      'base_uri' => "$url/$api_version",
      'headers' => [
        'Content-Type' => 'application/json',
        'User-Agent' => ContentHubDescriptor::userAgent(),
      ],
    ];

    $key = new Key($api_key, $secret);
    $middleware = new HmacAuthMiddleware($key);
    $settings = new Settings($name, MockDataProvider::SETTINGS_UUID, $api_key, $secret, $url);

    return new ContentHubClientMock($logger, $settings, $middleware, $dispatcher, $config, $api_version);
  }

  /**
   * {@inheritdoc}
   */
  public function ping(): ResponseInterface {
    $headers = \Drupal::request()->headers;
    $test_exec = $headers->get(static::X_ACH_TEST_EXECUTION_RESULT);
    if ($test_exec === 'exception') {
      $msg = $headers->get(static::X_ACH_TEST_EXECUTION_MSG);
      throw new \Exception($msg);
    }

    $response_body = [
      'success' => TRUE,
      'request_id' => 'some-uuid',
    ];

    return new Response(200, [], json_encode($response_body));
  }

  /**
   * {@inheritdoc}
   */
  public function addWebhook($webhook_url) {
    $this->testWebhook['url'] = $webhook_url;
    if (strpos($webhook_url, MockDataProvider::VALID_WEBHOOK_URL) === FALSE) {
      return [
        'success' => FALSE,
        'error' => [
          'code' => 4005,
          'message' => 'The provided URL did not respond with a valid authorization.',
        ],
        'request_id' => MockDataProvider::randomUuid(),
      ];
    }

    return [
      'client_name' => $this->options['name'],
      'client_uuid' => $this->options['uuid'],
      'disable_retries' => TRUE,
      'url' => $webhook_url,
      'uuid' => $this->testWebhook['uuid'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getWebHook($webhook_url) {
    if (strpos($webhook_url, MockDataProvider::ALREADY_REGISTERED_WEBHOOK) !== FALSE) {
      return [
        'client_name' => $this->options['name'],
        'client_uuid' => $this->options['uuid'],
        'disable_retries' => TRUE,
        'url' => $webhook_url,
        'uuid' => $this->testWebhook['uuid'],
      ];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getWebHooks() {
    return [
      new Webhook([
        'uuid' => '4e68da2e-a729-4c81-9c16-e4f8c05a11be',
        'client_uuid' => 'valid_client_uuid',
        'client_name' => 'client',
        'url' => 'http://example.com/acquia-contenthub/webhook',
        'version' => 2,
        'disable_retries' => 'false',
        'filters' => [
          'valid_filter_uuid_1',
          'valid_filter_uuid_2',
          'valid_filter_uuid_3',
        ],
        'status' => 'ENABLED',
        'is_migrated' => FALSE,
        'suppressed_until' => 0,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFilter($filter_id): array {
    return [
      'data' => [
        'name' => 'default_filter_client',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateWebhook($uuid, array $options) {
    return [
      'client_name' => $this->options['name'],
      'client_uuid' => $this->options['uuid'],
      'disable_retries' => TRUE,
      'url' => $options['url'],
      'uuid' => $uuid,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteWebhook($uuid) {
    return [
      'success' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): Settings {
    return new Settings(
      'test-client',
      '00000000-0000-0001-0000-123456789123',
      '12312321312321',
      '12312321312321',
      'https://example.com',
      '12312321312321');
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteSettings(): array {
    $this->options = $this->getSettings()->toArray();
    return [
      'clients' => [
        [
          'name' => $this->options['name'],
          'uuid' => $this->options['uuid'],
        ],
      ],
      'success' => TRUE,
      'uuid' => MockDataProvider::randomUuid(),
      'webhooks' => [[
        'client_name' => $this->options['name'],
        'client_uuid' => $this->options['uuid'],
        'disable_retries' => FALSE,
        'url' => $this->testWebhook['url'],
        'uuid' => $this->testWebhook['uuid'],
        'status' => 1,
      ],
      ],
      'shared_secret' => 'kh32j32132143143276bjsdnfjdhuf3',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterByName($filter_name) {
    $filter = MockDataProvider::mockFilter();
    if ($filter['name'] !== $filter_name) {
      return [
        'success' => FALSE,
      ];
    }
    return [
      'uuid' => $filter['uuid'],
      'request_id' => MockDataProvider::randomUuid(),
      'success' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function listFiltersForWebhook($webhook_id) {
    $filter = MockDataProvider::mockFilter();
    return [
      'data' => [
        $filter['uuid'],
      ],
      'request_id' => MockDataProvider::randomUuid(),
      'success' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function putFilter($query, $name = '', $uuid = NULL, array $metadata = []) {
    $filter = MockDataProvider::mockFilter();
    return [
      'request_id' => MockDataProvider::randomUuid(),
      'success' => TRUE,
      'uuid' => $filter['uuid'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function putEntities(CDFObject ...$objects) {
    $json = json_encode([
      'request_id' => MockDataProvider::randomUuid(),
      'success' => TRUE,
    ], JSON_THROW_ON_ERROR);
    return new Response(202, [], $json);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteClient($client_uuid = NULL) {
    return [
      'success' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function listEntities(array $options = []) {
    return MockDataProvider::mockListEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function getClients() {
    $this->options = $this->getSettings()->toArray();
    return [
      [
        'name' => $this->options['name'],
        'uuid' => $this->options['uuid'],
      ],
    ];
  }

  /**
   * Generates an error response.
   *
   * @param string $message
   *   The error message.
   * @param int $status
   *   The status code of the exception.
   */
  protected static function generateErrorResponse(string $message, int $status = 0): void {
    $resp_body['error'] = [
      'message' => $message,
    ];

    if ($status !== 0) {
      $resp_body['error']['code'] = $status;
    }

    throw new RequestException(
      $message,
      new Request('POST', \Drupal::request()->getRequestUri()),
      new Response($status, [], json_encode($resp_body))
    );
  }

  /**
   * {@inheritdoc}
   */
  public function addEntitiesToInterestListBySiteRole(string $webhook_uuid, string $site_role, array $interest_list): ResponseInterface {
    return new Response();
  }

  /**
   * {@inheritdoc}
   */
  public function queryEntities(array $params = []): ?array {
    return [
      'data' => [
        [
          'metadata' => [
            'settings' => [
              'webhook' => [
                'settings_url' => 'https://www.example.com',
              ],
            ],
          ],
          'attributes' => [
            'publisher' => [
              'und' => TRUE,
            ],
          ],
        ],
      ],
    ];
  }

}
