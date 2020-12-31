<?php

namespace Drupal\acquia_contenthub_publisher\Form\Webhook;

use Acquia\ContentHubClient\Webhook;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_publisher\Form\SubscriptionManagerFormTrait;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WebhookEnableForm.
 *
 * Defines the form for re-enabling a webhook.
 */
class WebhookEnableForm extends ConfirmFormBase {

  use SubscriptionManagerFormTrait;

  /**
   * The Acquia ContentHub Client object.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient
   */
  protected $client;

  /**
   * The UUID of an item (a webhook or a client) to delete.
   *
   * @var string
   */
  protected $uuid;

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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uuid = NULL) {
    $this->uuid = $uuid;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $webhooks = $this->client->getWebHooks();
    $uuid = $this->uuid;
    /** @var \Acquia\ContentHubClient\Webhook $webhook */
    $webhook = current(array_filter($webhooks, function (Webhook $webhook) use ($uuid) {
      return $webhook->getUuid() === $uuid;
    }));
    if ($webhook) {
      // Re-enable the webhook by performing a POST request.
      $response = $this->client->addWebhook($webhook->getUrl());
      if (empty($response) || empty($response['success'])) {
        $this->messenger()->addError(
          $this->t('Unable to re-enable webhook %uuid (%url). Error code: %code. Error message: %message',
            [
              '%uuid' => $this->uuid,
              '%url' => $webhook->getUrl(),
              '%code' => $response['error']['code'] ?? $this->t('n/a'),
              '%message' => $response['error']['message'] ?? $this->t('n/a'),
            ]));
        return;
      }

      $form_state->setRedirect('acquia_contenthub.subscription_settings');
      $this->messenger()->addStatus(
        $this->t('Webhook %uuid (%url) has been re-enabled.',
          ['%uuid' => $this->uuid, '%url' => $webhook->getUrl()]));
      return;
    }

    $this->messenger()->addError(
      $this->t('Failed to re-enable webhook %uuid. The webhook is not found.',
        ['%uuid' => $this->uuid]));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub_webhook_enable_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('acquia_contenthub.subscription_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to re-enable webhook %uuid?', ['%uuid' => $this->uuid]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

}
