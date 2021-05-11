<?php

namespace Drupal\acquia_contenthub_publisher\Controller;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Status Reports.
 *
 * @package Drupal\acquia_contenthub_publisher\Controller
 */
class StatusReportController extends ControllerBase {

  /**
   * The Acquia ContentHub Client object.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * The results per page to show.
   *
   * @var int
   */
  const LIMIT = 50;

  /**
   * The page manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * StatusReportController constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   */
  public function __construct(ClientFactory $client_factory, PagerManagerInterface $pager_manager = NULL) {
    $this->client = $client_factory->getClient();
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_contenthub.client.factory'),
      $container->has('pager.manager') ? $container->get('pager.manager') : NULL
    );
  }

  /**
   * Renders "Status Report" page.
   *
   * @return array
   *   Renderable array.
   *
   * @throws \Exception
   */
  public function statusReportPage(Request $request) {
    $page = $request->query->has('page') ? $request->query->get('page') : 0;
    return [
      $this->getWebhooksPageSection($page),
    ];
  }

  /**
   * Returns the Webhooks page section.
   *
   * @return array
   *   Renderable array.
   *
   * @throws \Exception
   */
  protected function getWebhooksPageSection($page) {
    $client_entities = $this->getClientEntities($page);
    $returned_subscribers = $client_entities['results'];
    $total_subscribers = $client_entities['total'];
    $total_pages = $client_entities['total_pages'];
    $current_start = ($page * self::LIMIT) + 1;

    // @todo Remove condition once 8.8 is lowest supported version.
    // Keep the logic within the if statement, remove the else.
    if (!is_null($this->pagerManager)) {
      $this->pagerManager->createPager($total_subscribers, self::LIMIT);
    }
    else {
      // Global function needed for pager.
      pager_default_initialize($total_subscribers, self::LIMIT);
    }

    $content['#attached']['library'][] = 'acquia_contenthub_publisher/acquia_contenthub_publisher';

    if ($total_subscribers > 0) {
      $content['clients_subheader'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Showing @start - @current_total of @total @label',
          [
            '@start' => $current_start,
            '@current_total' => (count($returned_subscribers) - 1) + $current_start,
            '@total' => $total_subscribers,
            '@label' => ($total_subscribers > 1) ? 'clients' : 'client',
          ]),
      ];
    }

    $content['pager'] = [
      '#type' => 'pager',
      '#quantity' => $total_pages,
    ];

    $content['clients_table'] = [
      '#type' => 'table',
      '#header' => [
        'name' => $this->t('Client'),
        'type' => $this->t('Type'),
        'uuid' => $this->t('Webhook Domain'),
        'status' => $this->t('Status'),
        'percent_exported' => $this->t('Percent Exported'),
        'percent_imported' => $this->t('Percent Imported'),
        'updated' => $this->t('Last Updated'),
        'details' => $this->t('Details'),
      ],
      '#empty' => $this->t('No clients found.'),
    ];

    foreach ($returned_subscribers as $key => $client) {
      $type = $this->getClientType($client['attributes']);
      $settings = $client['metadata']['settings'] ?? [];
      $webhook_uuid = $settings['webhook']['uuid'] ?? 'Not Registered';
      $webhook_url = $settings['webhook']['settings_url'] ?? 'Not Registered';
      $status = $this->t('<span title="@status_title">Not Available</span>', [
        '@status_title' => 'No syndication data found.',
      ]);
      $percent_exported = $this->t('<span title="@export_title">Not Available</span>', [
        '@export_title' => 'No data found. Only sites with the acquia_contenthub_publisher module enabled can export content.',
      ]);
      $percent_imported = $this->t('<span title="@import_title">Not Available</span>', [
        '@import_title' => 'No data found. Only sites with the acquia_contenthub_subscriber module enabled can import content.',
      ]);
      $last_updated = $this->t('<span title="@updated_title">Not Available</span>', [
        '@updated_title' => 'No syndication data found.',
      ]);

      // If this client has no interests, stop processing.
      try {
        $interests = $this->client->getInterestsByWebhook($webhook_uuid);
      }
      catch (\Exception $e) {
        $interests = [];
      }

      if (!empty($interests)) {
        $status = !empty($client['metadata']['metrics']) ? $this->getClientStatus($client['metadata']['metrics']) : 'Not Found';

        $client_progress = $this->calculateProgress($client, $interests,
          $type);

        if (!empty($client_progress['publisher'])) {
          $percent_exported = $client_progress['publisher'];
        }
        if (!empty($client_progress['subscriber'])) {
          $percent_imported = $client_progress['subscriber'];
        }

        $last_updated = !empty($client['metadata']['metrics']) ? $this->getLastUpdatedTime($client['metadata']['metrics']) : 'Not Found';
      }
      $url = Url::fromRoute(
        'acquia_contenthub_publisher.single_status',
        ['uuid' => $client['uuid']],
        []
      );
      $link = Link::fromTextAndUrl('More Details', $url)->toString();

      // Build the row.
      $content['clients_table'][] = $this->buildClientEntityTableRow(
        $client,
        $settings['name'],
        $type,
        $webhook_uuid,
        $webhook_url,
        $status,
        $percent_exported,
        $percent_imported,
        $last_updated,
        $link
      );
    }
    return $content;
  }

  /**
   * Returns only subscribers with interests and metric data.
   *
   * @return array
   *   All subscriber cdf data that have interests in the service
   *
   * @throws \Exception
   */
  protected function getClientEntities($page) {
    $subscribers = [];

    // Search only client entities that are not the current client.
    $options = [
      "from" => $page * self::LIMIT,
      "query" => [
        "bool" => [
          "filter" => [
            [
              "term" => ["data.type" => 'client'],
            ],
          ],
        ],
      ],
      "size" => self::LIMIT,
      "sort" => [
        "data.modified" => "desc",
      ],
    ];

    $client_entities = $this->client->searchEntity($options) ?? [];
    $subscribers['data'] = [];
    $subscribers['total'] = 0;
    $subscribers['total_pages'] = 1;
    $subscribers['results'] = [];

    if (!empty($client_entities['hits']['hits'])) {
      foreach ($client_entities['hits']['hits'] as $key => $client_entity) {
        if (!isset($client_entity['_source']['data']['metadata']['settings']['uuid'])) {
          continue;
        }

        $subscribers['data'][] = $client_entity['_source']['data'];
      }

      $subscribers['total'] = $client_entities['hits']['total'];
      $subscribers['total_pages'] = ceil($subscribers['total'] / self::LIMIT);
      $subscribers['results'] = $subscribers['data'];
    }

    return $subscribers;
  }

  /**
   * Builds a single row in the client table.
   *
   * @param array $client
   *   Client CDF.
   * @param string $name
   *   Client's name.
   * @param array $type
   *   Pubisher or subscriber or both.
   * @param string $webhook_uuid
   *   Webhook UUID, could be unregistered.
   * @param string $webhook_url
   *   Webhook URL, could be unregistered.
   * @param string $status
   *   Progress status, see self::getClientStatus() for logic.
   * @param string $percent_exported
   *   Percent of entities exported.
   * @param string $percent_imported
   *   Percent of entities imported.
   * @param string $last_updated
   *   Last updated time, see self::getLastUpdatedTime() for logic.
   * @param mixed $link
   *   Drupal-formatted link.
   *
   * @return array
   *   Drupal-formatted render array.
   */
  protected function buildClientEntityTableRow(array $client, $name, array $type, $webhook_uuid, $webhook_url, $status, $percent_exported, $percent_imported, $last_updated, $link) {
    return [
      'name' => [
        '#markup' => $this->t('<span title="@uuid">@name</span><span class="current">@current</span>', [
          '@uuid' => $client['uuid'],
          '@name' => $name,
          '@current' => ($client['uuid'] === $this->client->getSettings()->getUuid()) ? '(current)' : '',
        ]),
      ],
      'type' => [
        '#markup' => $this->t('@types', [
          '@types' => empty($type) ? 'Unknown' : implode(', ', $type),
        ]),
      ],
      'uuid' => [
        '#markup' => $this->t('<span title="@uuid">@url</span>', [
          '@uuid' => $webhook_uuid,
          '@url' => $webhook_url,
        ]),
      ],
      'status' => [
        '#markup' => $this->t('@status', [
          '@status' => $status,
        ]),
      ],
      'percent_exported' => [
        '#markup' => $percent_exported,
      ],
      'percent_imported' => [
        '#markup' => $percent_imported,
      ],
      'updated' => [
        '#markup' => $this->t('@last_updated', [
          '@last_updated' => $last_updated,
        ]),
      ],
      'details' => [
        '#markup' => $link,
      ],
    ];
  }

  /**
   * Get types of current client.
   *
   * @param array $attributes
   *   Client attributes.
   *
   * @return array
   *   Array of client types.
   */
  protected function getClientType(array $attributes = []) {
    $type = [];
    if (!empty($attributes)) {
      if (isset($attributes['publisher']['value']['und']) && $attributes['publisher']['value']['und']) {
        $type[] = 'Publisher';
      }
      if (isset($attributes['subscriber']['value']['und']) && $attributes['subscriber']['value']['und']) {
        $type[] = 'Subscriber';
      }
    }
    return $type;
  }

  /**
   * Calculate import and export progress of current client.
   *
   * @param array $client
   *   Current client.
   * @param array $interests
   *   Webhook uuid of client.
   * @param array $client_type
   *   Array of client types.
   *
   * @return array
   *   Statuses for publisher and subscriber.
   *
   * @throws \Exception
   */
  protected function calculateProgress(array $client, array $interests, array $client_type) {
    $client_status = [];
    foreach ($client_type as $type) {
      $type = strtolower($type);
      $data_type = 'data';
      if (
        isset($client['metadata']['metrics'][$type]) &&
        !empty($interests) &&
        !empty($client['metadata']['metrics'][$type][$data_type])
      ) {
        $client_metrics = $client['metadata']['metrics'][$type];
        $percent = $this->getPercentByTotals($client_metrics[$data_type] ?? [], $type);
        $client_status[$type] = $this->t('<span class="percent">@percent@label</span><progress max="100" value="@percent"></progress>',
          [
            '@percent' => $percent,
            '@label' => '%',
          ]);
      };
    }

    return $client_status;
  }

  /**
   * Determine progress of publisher and subscriber.
   *
   * @param array $metrics
   *   Current client metrics.
   *
   * @return string
   *   Not Found, Queued, In Progress, Complete
   */
  protected function getClientStatus(array $metrics) {
    $status = (
      empty($metrics['publisher']['data']) &&
      empty($metrics['subscriber']['data'])
    ) ? 'Not Found' : 'Complete';

    if (
      isset($metrics['publisher']['data']['queued']) ||
      isset($metrics['publisher']['data']['exported']) ||
      isset($metrics['subscriber']['data']['queued'])
    ) {
      $status = 'In Progress';
    }

    return $status;
  }

  /**
   * Determines most recent updated time.
   *
   * @param array $metrics
   *   Current client metrics.
   *
   * @return string
   *   Last updated time in 'time ago' format.
   *
   * @throws \Exception
   */
  protected function getLastUpdatedTime(array $metrics) {
    if (
      !isset($metrics['publisher']['last_updated']) &&
      !isset($metrics['subscriber']['last_updated'])
    ) {
      return 'Not Found';
    }
    $times = [];

    $times['publisher_updated'] = $metrics['publisher']['last_updated'] ?? 0;
    $times['subscriber_updated'] = $metrics['subscriber']['last_updated'] ?? 0;

    $times = array_filter($times);

    return $this->getTimeAgo(max($times));
  }

  /**
   * Returns percentage of imported entities over the total entities in the CDF.
   *
   * @param array $data
   *   The metrics data array of entities in various states from the CDF.
   * @param string $type
   *   The type of metrics to get.
   *
   * @return string
   *   Percentage of imported entities
   *
   * @throws \Exception
   */
  protected function getPercentByTotals(array $data, string $type = 'subscriber') {
    if (empty($data)) {
      return FALSE;
    }
    $type = ($type === 'subscriber') ? 'imported' : 'confirmed';
    if (empty($data[$type])) {
      return 0;
    }
    $numerator = $data[$type];
    $total = array_sum($data);
    $percent = ($total > 0) ? round(($numerator / $total) * 100) : '0';
    return $percent;
  }

  /**
   * Time ago formatting.
   *
   * @param int $time
   *   Timestamp of last update.
   *
   * @return string
   *   Time ago as formatting.
   *
   * @throws \Exception;
   */
  protected function getTimeAgo($time) {
    if (!$time) {
      return 'Not Found';
    }
    $date_formatter = \Drupal::service('date.formatter');
    $time_ago = $date_formatter->formatDiff($time, \Drupal::time()->getRequestTime(), [
      'granularity' => 2,
    ]);
    return $time_ago;
  }

}
