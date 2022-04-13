<?php

namespace Drupal\acquia_contenthub\Form;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\AcquiaContentHubUnregisterHelperTrait;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubConnectionManager;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ContentHubDeleteClientConfirmForm.
 *
 * Defines a confirmation form to confirm deletion of Acquia Content Hub Client.
 *
 * @package Drupal\acquia_contenthub\Form
 */
class ContentHubDeleteClientConfirmForm extends FormBase {

  use AcquiaContentHubUnregisterHelperTrait;

  /**
   * The Content Hub connection manager.
   *
   * @var \Drupal\acquia_contenthub\ContentHubConnectionManager
   */
  protected $chConnectionManager;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * AcquiaContentHubUnregisterEvent event.
   *
   * @var \Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent
   */
  protected $event;

  /**
   * The Acquia ContentHub Client object.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * ContentHubDeleteClientConfirmForm constructor.
   *
   * @param \Drupal\acquia_contenthub\ContentHubConnectionManager $ch_connection_manager
   *   The Content Hub connection manager service.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The Client Factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Symfony event dispatcher.
   */
  public function __construct(ContentHubConnectionManager $ch_connection_manager, ClientFactory $client_factory, EventDispatcherInterface $eventDispatcher) {
    $this->chConnectionManager = $ch_connection_manager;
    $this->client = $client_factory->getClient();
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\acquia_contenthub\ContentHubConnectionManager $ch_connection_manager */
    $ch_connection_manager = $container->get('acquia_contenthub.connection_manager');
    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $client_factory */
    $client_factory = $container->get('acquia_contenthub.client.factory');

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = $container->get('event_dispatcher');

    return new static(
      $ch_connection_manager,
      $client_factory,
      $event_dispatcher
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $webhook_uuid = $this->getWebhookUuid();

    if (empty($webhook_uuid)) {
      $form['warning_message'] = [
        '#markup' => $this->t('Webhook not found for this client. Proceed?'),
      ];
      $form['delete_client_without_webhook'] = [
        '#type' => 'submit',
        '#value' => $this->t('Yes'),
        '#button_type' => 'primary',
        '#name' => 'delete_client_without_webhook',
      ];
      return $form;
    }

    $this->dispatchEvent($webhook_uuid);
    $orphaned_entities_amount = $this->event->getOrphanedEntitiesAmount();

    if ($orphaned_entities_amount !== 0) {
      $form['delete_entities']['orphaned_entites'] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#description' => $this->t('There are @count entities published from this client: @client. You have to delete/reoriginate those entities before proceeding with the unregistration. @blank
          If you want to delete those entities and unregister the client, use the following drush command on the given client "drush ach-disconnect --delete=all".',
          [
            '@count' => $orphaned_entities_amount,
            '@client' => $this->event->getClientName(),
            '@blank' => new FormattableMarkup('<br>', []),
          ]
        ),
        '#title' => $this->t('Un-register Acquia Content Hub'),
      ];
    }

    if ($this->event->getOrphanedFilters()) {
      $form['delete_filters'] = [
        '#type' => 'details',
        '#title' => $this->t('After un-registration the following filters will be deleted:'),
        '#open' => TRUE,
      ];

      $form['delete_filters']['orphaned_filters'] = [
        '#type' => 'table',
        '#title' => $this->t('Orphaned filters'),
        '#header' => ['Filter name', 'Filter UUID'],
        '#rows' => $this->formatOrphanedFiltersTable($this->event->getOrphanedFilters()),
      ];

      if ($this->checkDiscoveryRoute()) {
        $form['actions']['redirect'] = [
          '#type' => 'link',
          '#title' => $this->t('Go to Discovery Interface'),
          '#url' => Url::fromRoute('acquia_contenthub_curation.discovery'),
          '#attributes' => [
            'class' => [
              'button',
            ],
          ],
        ];
      }
    }

    if (empty($this->event->getOrphanedFilters()) && !$orphaned_entities_amount) {
      $form['safe_message'] = [
        '#markup' => $this->t('Everything is in order, safe to proceed.'),
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unregister'),
      '#button_type' => 'primary',
      '#attributes' => [
        'disabled' => (bool) $orphaned_entities_amount,
      ],
    ];

    $form['settings'] = [
      '#type' => 'submit',
      '#value' => $this->t('Content Hub Settings'),
      '#button_type' => 'primary',
      '#name' => 'settings',
    ];

    $form['subscription'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#button_type' => 'primary',
      '#name' => 'subscription',
    ];

    return $form;
  }

  /**
   * Dispatches AcquiaContentHubUnregisterEvent.
   *
   * @param string $webhook_uuid
   *   Webhook uuid.
   */
  public function dispatchEvent(string $webhook_uuid) {
    $this->event = new AcquiaContentHubUnregisterEvent($webhook_uuid);
    $this->eventDispatcher->dispatch(AcquiaContentHubEvents::ACH_UNREGISTER, $this->event);
  }

  /**
   * Get webhook UUID.
   *
   * @return string
   *   Webhook uuid.
   *
   * @throws \Exception
   */
  public function getWebhookUuid(): string {
    $logger = $this->logger('acquia_contenthub');
    $settings = $this->client->getSettings();
    $remote_settings = $this->client->getRemoteSettings();

    $webhook_uuid = $settings->getWebhook('uuid');
    if (!$webhook_uuid) {
      $logger->info('Webhook was not registered.');
      return '';
    }

    $remote_webhook = $this->getSelectedWebhookByUuid($remote_settings['webhooks'], $webhook_uuid);
    if (empty($remote_webhook)) {
      $logger->info(sprintf('Local configurations is out of sync, %s (%s) was not registered to Content Hub, but remained in configuration.', $settings->getWebhook(), $webhook_uuid));
      return '';
    }

    // Standard case. The webhook is registered and is associated with the
    // client and stored in local configuration as well.
    if ($remote_webhook['client_name'] === $settings->getName()) {
      return $remote_webhook['uuid'];
    }

    // The webhook is found in local configuration, but the client_name
    // didn't match with the registered, remote information of the webhook.
    foreach ($remote_settings['clients'] as $remote_client) {
      if ($remote_client['name'] === $remote_webhook['client_name']) {
        $logger->info('The webhook is registered to other client. The configuration was outdated');
        return '';
      }
    }

    // If it doesn't belong to any of the clients it needs to be deleted.
    $logger->info(sprintf('The webhook %s was orphaned (was registered to a non-existent client).', $remote_webhook['uuid']));
    return $remote_webhook['uuid'];
  }

  /**
   * Returns the desired webhook from the array by uuid.
   *
   * @param array $webhooks
   *   The list of webhooks returned from /settings endpoint.
   * @param string $webhook_uuid
   *   The selected webhook uuid.
   *
   * @return array
   *   The selected webhook array or an empty array if it wasn't found.
   */
  protected function getSelectedWebhookByUuid(array $webhooks, string $webhook_uuid): array {
    foreach ($webhooks as $webhook) {
      if ($webhook['uuid'] === $webhook_uuid) {
        return $webhook;
      }
    }
    return [];
  }

  /**
   * Obtains the Content Hub Admin Settings Configuration.
   *
   * @return \Drupal\Core\Config\Config
   *   The Editable Content Hub Admin Settings Configuration.
   */
  protected function getContentHubConfig(): Config {
    return $this->configFactory()->getEditable('acquia_contenthub.admin_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] === 'subscription') {
      $form_state->setRedirect('acquia_contenthub.subscription_settings');
      return;
    }

    if ($form_state->getTriggeringElement()['#name'] === 'settings') {
      $form_state->setRedirect('acquia_contenthub.admin_settings');
      return;
    }

    if (!$this->client) {
      $this->messenger()->addError("Couldn't instantiate client. Please check connection settings.");
      $form_state->setRedirect('acquia_contenthub.admin_settings');
      return;
    }

    if ($form_state->getTriggeringElement()['#name'] === 'delete_client_without_webhook') {
      $this->unregisterClientNoWebhook();
      $form_state->setRedirect('acquia_contenthub.admin_settings');
      return;
    }

    try {
      $this->chConnectionManager->unregister($this->event);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error during unregistration: @error_message', ['@error_message' => $e->getMessage()]));
      return;
    }

    $form_state->setRedirect('acquia_contenthub.admin_settings');
  }

  /**
   * Unregister local client and configurations when no webhook registered.
   */
  protected function unregisterClientNoWebhook(): void {
    $client_settings = $this->client->getSettings();
    $resp = $this->client->deleteClient($client_settings->getUuid());
    if ($resp && $resp->getStatusCode() === 202) {
      $this->logger('acquia_contenthub')->info(
        sprintf('Client %s has been removed, no webhook was registered.', $client_settings->getName())
      );
    }
    $this->getContentHubConfig()->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contenthub_delete_client_confirmation';
  }

}
