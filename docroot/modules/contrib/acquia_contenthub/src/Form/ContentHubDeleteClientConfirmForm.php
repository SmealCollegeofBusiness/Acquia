<?php

namespace Drupal\acquia_contenthub\Form;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\AcquiaContentHubUnregisterHelperTrait;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubConnectionManager;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Drupal\Component\Render\FormattableMarkup;
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
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

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
    $this->clientFactory = $client_factory;
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
      $this->messenger()->addError($this->t('Cannot find webhook uuid.'));
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
    $client = $this->clientFactory->getClient();
    $settings = $client->getSettings();
    $remote_settings = $client->getRemoteSettings();

    foreach ($remote_settings['webhooks'] as $webhook) {
      if ($webhook['client_name'] === $settings->getName()) {
        return $webhook['uuid'];
      }
    }

    return '';
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

    $client = $this->clientFactory->getClient();
    if (!$client) {
      $this->messenger()->addError("Couldn't instantiate client. Please check connection settings.");
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return "contenthub_delete_client_confirmation";
  }

}
