<?php

namespace Drupal\acquia_contenthub_publisher\Form\Webhook;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\AcquiaContentHubUnregisterHelperTrait;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Drupal\acquia_contenthub_publisher\Form\SubscriptionManagerFormTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Confirmation form for webhook deletion.
 *
 * @package Drupal\acquia_contenthub_publisher\Form\Webhook
 */
class WebhookDeleteConfirmForm extends FormBase {

  use SubscriptionManagerFormTrait;
  use AcquiaContentHubUnregisterHelperTrait;

  /**
   * The Acquia ContentHub Client object.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * The Acquia ContentHub Unregister event.
   *
   * @var \Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent
   */
  protected $event;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The UUID of a webhook delete.
   *
   * @var string
   */
  protected $uuid;

  /**
   * WebhookDeleteConfirmForm constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   ACH client factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Symfony event dispatcher.
   */
  public function __construct(ClientFactory $client_factory, EventDispatcherInterface $dispatcher) {
    $this->client = $client_factory->getClient();
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_contenthub.client.factory'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uuid = NULL) {
    $this->uuid = $uuid;

    $this->event = new AcquiaContentHubUnregisterEvent($this->uuid, '', TRUE);
    $this->dispatcher->dispatch(AcquiaContentHubEvents::ACH_UNREGISTER, $this->event);

    $form['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#button_type' => 'primary',
      '#name' => 'cancel',
      '#weight' => 101,
    ];

    $orphaned_filters = $this->event->getOrphanedFilters();
    if (!empty($orphaned_filters)) {
      $form['filters'] = [
        '#type' => 'details',
        '#title' => $this->t('With webhook deletion the following filters will be deleted as well:'),
        '#open' => TRUE,
        '#weight' => -1,
      ];

      $form['filters']['orphaned_filters'] = [
        '#type' => 'table',
        '#title' => $this->t('Filters'),
        '#header' => ['Filter name', 'Filter UUID'],
        '#rows' => $this->formatOrphanedFiltersTable($orphaned_filters),
      ];

      $form['actions']['delete_webhook_and_filters'] = [
        '#type' => 'submit',
        '#submit' => [
          [$this, 'deleteFilters'],
          [$this, 'deleteWebhook'],
        ],
        '#value' => $this->t('Delete webhook and filters'),
        '#button_type' => 'primary',
        '#weight' => 100,
        '#limit_validation_errors' => [],
      ];

      if ($this->checkDiscoveryRoute()) {
        $form['actions']['redirect'] = [
          '#type' => 'link',
          '#title' => $this->t('Go to Discovery Interface'),
          '#url' => Url::fromRoute('acquia_contenthub_curation.discovery'),
          '#weight' => 99,
          '#attributes' => [
            'class' => [
              'button',
            ],
          ],
        ];
      }

      return $form;
    }

    $this->messenger()->addStatus('Everything is in order, safe to proceed!');

    $form['actions']['delete_webhook'] = [
      '#type' => 'submit',
      '#submit' => [
        [$this, 'deleteFilters'],
        [$this, 'deleteWebhook'],
      ],
      '#value' => $this->t('Delete webhook'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] === 'cancel') {
      $form_state->setRedirect('acquia_contenthub.subscription_settings');
    }
  }

  /**
   * Delete orphaned and default filters from event.
   */
  public function deleteFilters(): void {
    foreach ($this->event->getOrphanedFilters() as $filter_id) {
      $response = $this->client->deleteFilter($filter_id);
      if (!$this->isResponseSuccessful($response, $this->t('delete'), $this->t('filter'), $filter_id, $this->messenger())) {
        return;
      }
    }

    $default_filter_response = $this->client->deleteFilter($this->event->getDefaultFilter());
    $this->isResponseSuccessful($default_filter_response, $this->t('delete'), $this->t('default filter'), $this->event->getDefaultFilter(), $this->messenger());
  }

  /**
   * Delete webhook.
   *
   * @param array $form
   *   Form object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface object.
   */
  public function deleteWebhook(array &$form, FormStateInterface $form_state): void {
    $response = $this->client->deleteWebhook($this->uuid);
    if (!$this->isResponseSuccessful($response, $this->t('delete'), $this->t('webhook'), $this->uuid, $this->messenger())) {
      return;
    }

    $this->messenger()->addStatus(
      $this->t('Webhook %uuid has been deleted successfully.',
        ['%uuid' => $this->uuid]));

    $form_state->setRedirect('acquia_contenthub.subscription_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub_webhook_delete_confirm_form';
  }

}
