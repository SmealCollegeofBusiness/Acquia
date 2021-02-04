<?php

namespace Drupal\acquia_contenthub_publisher\Controller;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for managing the subscription.
 *
 * @package Drupal\acquia_contenthub_publisher\Controller
 */
class SubscriptionManagerController extends ControllerBase {

  /**
   * The Acquia ContentHub Client object.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * SubscriptionManagerController constructor.
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
   * Renders "Subscription Settings" page.
   *
   * @return array
   *   Renderable array.
   *
   * @throws \Exception
   */
  public function subscriptionSettingsPage() {
    return [
      $this->getWebhookPageSection(),
      $this->getClientsPageSection(),
    ];
  }

  /**
   * Returns the "Administer Webhooks" page sections.
   *
   * @return array
   *   Renderable array.
   *
   * @throws \Exception
   */
  protected function getWebhookPageSection() {
    $content['webhooks_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Administer Webhooks'),
    ];
    $content['webhooks_table'] = [
      '#type' => 'table',
      '#header' => [
        'uuid' => $this->t('UUID'),
        'url' => $this->t('URL'),
        'operations' => $this->t('Operations'),
      ],
      '#empty' => $this->t('No webhooks found.'),
    ];

    foreach ($this->client->getWebHooks() as $key => $webhook) {
      $links = [];
      $webhook_uuid = $webhook->getUuid();
      if (!$webhook->isEnabled()) {
        $links['enable'] = [
          'title' => $this->t('re-enable'),
          'url' => Url::fromRoute('acquia_contenthub_publisher.enable_webhook',
            ['uuid' => $webhook_uuid]),
        ];
      }
      else {
        $links['edit'] = [
          'title' => $this->t('edit'),
          'url' => Url::fromRoute('acquia_contenthub_publisher.edit_webhook',
            ['uuid' => $webhook_uuid]),
        ];
        $links['delete'] = [
          'title' => $this->t('delete'),
          'url' => Url::fromRoute('acquia_contenthub_publisher.delete_webhook',
            ['uuid' => $webhook_uuid]),
        ];
      }

      $content['webhooks_table'][] = [
        'uuid' => [
          '#markup' => $webhook_uuid,
        ],
        'url' => [
          '#markup' => $webhook->getUrl(),
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => $links,
        ],
      ];
    }

    return $content;
  }

  /**
   * Returns the "Administer Clients" page sections.
   *
   * @return array
   *   Renderable array.
   *
   * @throws \Exception
   */
  protected function getClientsPageSection() {
    $content['clients_header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Administer Clients'),
    ];
    $content['clients_table'] = [
      '#type' => 'table',
      '#header' => [
        'uuid' => $this->t('UUID'),
        'name' => $this->t('Name'),
        'operations' => $this->t('Operations'),
      ],
      '#empty' => $this->t('No clients found.'),
    ];

    foreach ($this->client->getClients() as $key => $client) {
      $links = [];
      $links['edit'] = [
        'title' => $this->t('edit'),
        'url' => Url::fromRoute('acquia_contenthub_publisher.edit_client',
          ['uuid' => $client['uuid']]),
      ];
      if ($client['uuid'] !== $this->client->getSettings()->getUuid()) {
        $links['delete'] = [
          'title' => $this->t('delete'),
          'url' => Url::fromRoute('acquia_contenthub_publisher.delete_client',
            ['uuid' => $client['uuid']]),
        ];
      }

      $content['clients_table'][] = [
        'uuid' => [
          '#markup' => $client['uuid'],
        ],
        'name' => [
          '#markup' => $client['name'],
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => $links,
        ],
      ];
    }

    return $content;
  }

}
