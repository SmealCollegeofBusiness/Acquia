<?php

namespace Drupal\acquia_contenthub_publisher\Form\Client;

use Drupal\acquia_contenthub_publisher\Form\ClientFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ClientEditForm.
 *
 * Defines the form for editing a client.
 */
class ClientEditForm extends ClientFormBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uuid = NULL) {
    $this->uuid = $uuid;

    $clients = $this->client->getClients();
    $key = array_search($uuid, array_column($clients, 'uuid'));
    if (FALSE === $key) {
      $this->messenger()->addError(
        $this->t("Can't edit client %uuid. The client is not found.",
          ['%uuid' => $uuid]));
      return $this->redirect('acquia_contenthub.subscription_settings');
    }
    $edit_client = $clients[$key];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client name'),
      '#required' => TRUE,
      '#default_value' => $edit_client['name'],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('acquia_contenthub.subscription_settings');
    $name = trim($form_state->getValue('name'));

    /** @var \Psr\Http\Message\ResponseInterface $response */
    $response = $this->client->updateClient($this->uuid, $name);
    if (!$this->isResponseSuccessful($response, $this->t('update'), $this->t('client'), $this->uuid, $this->messenger())) {
      return;
    }

    $this->messenger()->addStatus(
      $this->t('Client %uuid has been updated.',
        ['%uuid' => $this->uuid]));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub_edit_client_form';
  }

}
