<?php

namespace Drupal\acquia_contenthub_publisher\Controller;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StatusReportDetailsController.
 *
 * @package Drupal\acquia_contenthub_publisher\Controller
 */
class StatusReportDetailsController extends ControllerBase {

  /**
   * The Acquia ContentHub Client object.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * Graph colors mapped to statuses.
   *
   * @var array
   */
  protected $graphColors = [
    'queued' => '#555555',
    'exported' => '#29A8E1',
    'confirmed' => '#7CD7F2',
    'imported' => '#33D1FF',
    'pending' => '#56B7FF',
  ];

  /**
   * StatusReportDetailsController constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   */
  public function __construct(ClientFactory $client_factory) {
    $this->client = $client_factory->getClient();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_contenthub.client.factory')
    );
  }

  /**
   * Returns the "Webhook Details" Section.
   *
   * @return array
   *   Renderable array.
   *
   * @throws \Exception
   */
  public function getWebhookDetails(string $uuid) {
    $client_entity = $this->client->getEntity($uuid);
    if (!$client_entity instanceof ClientCDFObject) {
      return [];
    }
    if ($client_entity->getType() != 'client') {
      return [];
    }
    $webhook = $client_entity->getMetadata()['settings']['webhook'];
    $webhook_uuid = $webhook['uuid'];
    $webhook_url = $webhook['settings_url'];

    $content['single_details_page'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['id' => 'single_details_page'],
      '#value' => '',
    ];

    $content['single_details_page']['details'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['id' => 'single_details'],
    ];

    $content['single_details_page']['details']['webhook_subheader'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Webhook Details'),
    ];

    $content['single_details_page']['details']['webhook_url'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<label>Domain</label>@url', [
        '@url' => $webhook_url,
      ]),
    ];

    $content['single_details_page']['details']['webhook_uuid'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<label>UUID</label>@uuid', [
        '@uuid' => $webhook_uuid,
      ]),
    ];

    // If this client has no interests, stop processing.
    try {
      $interests = $this->client->getInterestsByWebhook($webhook_uuid);
    }
    catch (\Exception $e) {
      $interests = [];
    }
    if (empty($interests)) {
      $content['single_details_page']['details']['no_details'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('<label>Status</label>No syndication data found'),
      ];

      return $content;
    }

    foreach ($client_entity->getMetadata()['metrics'] as $type => $metrics) {
      $data_type = 'data';
      $data = $metrics[$data_type] ?? [];

      if (empty($data) || !is_array($data)) {
        continue;
      }

      $total = array_sum($data);
      $status = implode(', ', array_map(
        function ($v, $k) {
          return sprintf("%s %s", $v, $k);
        },
        $data,
        array_keys($data)
      ));

      $content['single_details_page']['details'][$type . '_details'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('<label>@type Status</label>@status out of @total entities',
          [
            '@type' => ucfirst($type),
            '@status' => $status,
            '@total' => $total,
          ]),
      ];

      $content['single_details_page']['details'][$type . '_updated'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('<label>Last Updated</label>@date', [
          '@date' => $this->getTime($client_entity->getMetadata()['metrics'][$type]['last_updated']),
          '@total' => $total,
        ]),
      ];

      $content['single_details_page'][$type . '_graph'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['id' => $type . '_chart'],
        '#value' => '',
      ];

      foreach ($data as $k => $v) {
        $content['#attached']['drupalSettings']['acquia_contenthub_' . $type . '_status'][$k] = $v;
        $content['#attached']['drupalSettings']['acquia_contenthub_' . $type . '_color'][$k] = $this->graphColors[$k];
      }
      if ($total > 0) {
        $content['#attached']['drupalSettings']['acquia_contenthub_' . $type . '_status']['total'] = $total;
      }
    }

    $content['#attached']['library'][] = 'acquia_contenthub_publisher/acquia_contenthub_publisher_details';
    $content['#attached']['library'][] = 'acquia_contenthub_publisher/google_charts';

    return $content;
  }

  /**
   * Time formatting.
   *
   * @param int $time
   *   Timestamp of last update.
   *
   * @return string
   *   Time as formatted.
   *
   * @throws \Exception;
   */
  protected function getTime(int $time) {
    if ($time === 0) {
      return 'Not Found';
    }
    $formatted_time = \Drupal::service('date.formatter')->format($time, 'long');
    return $formatted_time;
  }

}
