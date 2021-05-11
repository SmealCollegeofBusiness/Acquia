<?php

namespace Drupal\acquia_contenthub_publisher\Form\Webhook;

use Acquia\ContentHubClient\Webhook;
use Drupal\acquia_contenthub_publisher\Form\ClientFormBase;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class WebhookEditForm.
 *
 * Defines the form for editing a webhook.
 */
class WebhookEditForm extends ClientFormBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uuid = NULL) {
    $this->uuid = $uuid;

    $webhooks = $this->client->getWebHooks();
    /** @var \Acquia\ContentHubClient\Webhook $webhook */
    $webhook = current(array_filter($webhooks, function (Webhook $webhook) use ($uuid) {
      return $webhook->getUuid() === $uuid;
    }));
    if (!$webhook) {
      $this->messenger()->addError(
        $this->t("Can't edit webhook %uuid. The webhook is not found.",
          ['%uuid' => $uuid]));
      return $this->redirect('acquia_contenthub.subscription_settings');
    }

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webhook URL'),
      '#description' => $this->t('Example: @url',
        [
          '@url' => Url::fromRoute('acquia_contenthub.webhook', [], ['absolute' => TRUE])->toString(),
        ]),
      '#required' => TRUE,
      '#default_value' => $webhook->getUrl(),
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
    $options['url'] = trim($form_state->getValue('url'));

    /** @var \Psr\Http\Message\ResponseInterface $response */
    $response = $this->client->updateWebhook($this->uuid, $options);
    if (!$this->isResponseSuccessful($response, $this->t('update'), $this->t('webhook'), $this->uuid, $this->messenger())) {
      return;
    }

    $this->messenger()->addStatus(
      $this->t('Webhook %uuid has been updated.',
        ['%uuid' => $this->uuid]));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub_edit_webhook_form';
  }

}
