<?php

namespace Drupal\acquia_contenthub_publisher\Form\Webhook;

use Drupal\acquia_contenthub_publisher\Form\ClientFormBase;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class WebhookAddForm.
 *
 * Defines the form to add a webhook.
 */
class WebhookAddForm extends ClientFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webhook URL'),
      '#description' => $this->t('Example: @url',
        [
          '@url' => Url::fromRoute('acquia_contenthub.webhook', [], ['absolute' => TRUE])->toString(),
        ]),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Add Webhook'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $url = trim($form_state->getValue('url'));
    if (!UrlHelper::isValid($url, TRUE)) {
      $form_state->setErrorByName('url', $this->t('URL is not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('acquia_contenthub.subscription_settings');
    $url = trim($form_state->getValue('url'));
    $response = $this->client->addWebhook($url);
    if (empty($response) || empty($response['success'])) {
      $this->messenger()->addError(
        $this->t('Unable to add webhook %url. Error %code: %message',
          [
            '%url' => $url,
            '%code' => $response['error']['code'] ?? $this->t('n/a'),
            '%message' => $response['error']['message'] ?? $this->t('n/a'),
          ]));
      return;
    }

    $this->messenger()->addStatus(
      $this->t('Webhook %url has been added.',
        ['%url' => $url]));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub_add_webhook_form';
  }

}
