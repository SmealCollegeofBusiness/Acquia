<?php

namespace Drupal\acquia_contenthub_publisher\Form\Client;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\AcquiaContentHubUnregisterEvent;
use Drupal\acquia_contenthub\Form\ContentHubDeleteClientConfirmForm;
use Drupal\acquia_contenthub_publisher\Form\SubscriptionManagerFormTrait;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ClientDeleteConfirmForm.
 *
 * Defines the confirmation form to delete a client.
 */
class ClientDeleteConfirmForm extends ContentHubDeleteClientConfirmForm {

  use SubscriptionManagerFormTrait;

  /**
   * The UUID of an item (a webhook or a client) to delete.
   *
   * @var string
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uuid = NULL): array {
    $this->uuid = $uuid;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getWebhookUuid(): string {

    $remote_settings = $this->client->getRemoteSettings();
    $client_name = '';

    foreach ($remote_settings['clients'] as $client) {
      if ($client['uuid'] === $this->uuid) {
        $client_name = $client['name'];
        break;
      }
    }

    foreach ($remote_settings['webhooks'] as $webhook) {
      if ($webhook['client_name'] === $client_name) {
        return $webhook['uuid'];
      }
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function dispatchEvent(string $webhook_uuid) {
    $this->event = new AcquiaContentHubUnregisterEvent($webhook_uuid, $this->uuid);
    $this->eventDispatcher->dispatch($this->event, AcquiaContentHubEvents::ACH_UNREGISTER);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub_client_delete_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function unregisterClientNoWebhook(): void {
    $client_to_delete = $this->client->getClientByUuid($this->uuid);
    $resp = $this->client->deleteClient($this->uuid);
    if ($this->isResponseSuccessful($resp, 'delete', 'client', $this->uuid, $this->messenger())) {
      $this->logger('acquia_contenthub')
        ->info(sprintf('Client %s has been removed, no webhook was registered.', $client_to_delete['name']));
      $this->messenger()->addMessage($this->t('Client @name has been removed, no webhook was registered.', ['@name' => $client_to_delete['name']]));
    }
  }

}
