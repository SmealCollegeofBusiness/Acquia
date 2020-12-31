<?php

namespace Drupal\acquia_contenthub\Form;

use Acquia\ContentHubClient\Webhook;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubConnectionManager;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the form to configure the Content Hub connection settings.
 */
class ContentHubSettingsForm extends ConfigFormBase {

  /**
   * The Content Hub endpoint for receiving webhooks.
   *
   * @var \Drupal\Core\GeneratedUrl|string
   */
  protected $achPath;

  /**
   * The client factory.
   *
   * @var |Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Contenthub Settings.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $settings;

  /**
   * Contenthub Client.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient|bool
   */
  protected $client;

  /**
   * The Content Hub connection manager.
   *
   * @var \Drupal\acquia_contenthub\ContentHubConnectionManager
   */
  protected $chConnectionManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub_admin_settings';
  }

  /**
   * Returns bootstrapped client.
   *
   * Modules might want to alter the settings form. In some cases
   * a bootstrapped client would come in handy before the end of
   * submission. Get it from the form object.
   *
   * @return \Acquia\ContentHubClient\ContentHubClient|bool
   *   The content hub client.
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['acquia_contenthub.admin_settings'];
  }

  /**
   * ContentHubSettingsForm constructor.
   *
   * @param \Drupal\acquia_contenthub\ContentHubConnectionManager $ch_connection_manager
   *   The Content Hub connection manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Configuration Factory.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The Client Factory.
   */
  public function __construct(ContentHubConnectionManager $ch_connection_manager, ConfigFactoryInterface $config_factory, ClientFactory $client_factory) {
    parent::__construct($config_factory);
    $this->chConnectionManager = $ch_connection_manager;
    $this->clientFactory = $client_factory;
    $this->achPath = Url::fromRoute('acquia_contenthub.webhook')->toString();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_contenthub.connection_manager'),
      $container->get('config.factory'),
      $container->get('acquia_contenthub.client.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $provider = $this->clientFactory->getProvider();
    $this->settings = $this->clientFactory->getSettings();
    $this->client = $this->clientFactory->getClient();
    $readonly = $provider !== 'core_config';
    $connected = !empty($this->client);
    $client_name = '';

    $is_suppressed = $this
      ->config('acquia_contenthub.admin_settings')
      ->get('is_suppressed');

    $form['actions']['env_var'] = [
      '#type' => 'details',
      '#title' => $this->t('Register with environment variable'),
      '#description' => $this->t('Credentials are set in environment variables.'),
      '#open' => TRUE,
      '#access' => $provider === 'environment_variable',
    ];

    $form['actions']['env_var']['remove_suppression'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register site'),
      '#button_type' => 'primary',
      '#weight' => 100,
      '#limit_validation_errors' => [],
      '#access' => $is_suppressed,
      '#submit' => [[$this, 'removeWebhookSuppression']],
    ];

    $form['actions']['env_var']['suppress_webhook'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unregister site'),
      '#button_type' => 'primary',
      '#weight' => 100,
      '#limit_validation_errors' => [],
      '#access' => !$is_suppressed,
      '#submit' => [[$this, 'suppressWebhook']],
    ];

    if ($connected) {
      $client_name = $this->ensureClientNameIsSynced();
    }

    if ($readonly && $connected && $provider !== 'environment_variable') {
      $this->messenger()->addMessage($this->t('Settings are currently read-only, provided by %provider. Values shown for informational purposes only.', ['%provider' => $provider]));
    }

    if ($readonly && !$connected) {
      $this->messenger()->addMessage($this->t('Warning, you are not connected to Content Hub, but your settings provider (%provider) does not allow settings changes. Values shown for informational purposes only.', ['%provider' => $provider]), 'warning');
      $connected = TRUE;
    }

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Connection Settings'),
      '#collapsible' => TRUE,
      '#description' => $this->t('Settings for connection to Acquia Content Hub'),
      '#disabled' => $connected,
      '#open' => !$connected,
      '#access' => $provider !== 'environment_variable',
    ];

    $form['settings']['hostname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Acquia Content Hub Hostname'),
      '#description' => $this->t('The hostname of the Acquia Content Hub API without trailing slash at end of URL, e.g. http://localhost:5000'),
      '#default_value' => $this->settings->getUrl(),
      '#required' => TRUE,
    ];

    $form['settings']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->settings->getApiKey(),
      '#required' => TRUE,
    ];

    $form['settings']['secret_key'] = [
      '#type' => 'password',
      '#title' => $this->t('Secret Key'),
      '#default_value' => $this->settings->getSecretKey(),
      '#required' => TRUE,
    ];

    $form['settings']['client_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Name'),
      '#default_value' => $client_name,
      '#required' => TRUE,
      '#description' => $this->t('A unique client name by which Acquia Content Hub will identify this site. The name of this client site cannot be changed once set.'),
    ];

    $webhook_is_disabled = FALSE;
    if ($this->client) {
      $webhooks = $this->client->getWebHooks();
      $webhook_uuid = $this->settings->getWebhook('uuid');

      /** @var \Acquia\ContentHubClient\Webhook $webhook */
      $webhook = current(array_filter($webhooks, function (Webhook $webhook) use ($webhook_uuid) {
        return $webhook->getUuid() === $webhook_uuid;
      }));
      $webhook_is_disabled = $webhook ? !$webhook->isEnabled() : FALSE;
    }
    $webhook_description = $this->t('This should be the domain (and drupal subpath if applicable). Do not include trailing slash.');
    if ($webhook_is_disabled) {
      $webhook_description .= ' <b>' . $this->t('To re-enable the webhook please click "Update Public URL" button.') . '</b>';
    }
    $form['settings']['webhook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publicly Accessible URL'),
      '#required' => TRUE,
      '#default_value' => $this->settings->getWebhook() ?: $GLOBALS['base_url'],
      '#description' => $webhook_description,
      '#disabled' => $webhook_is_disabled,
    ];

    $form['settings']['origin'] = [
      '#type' => 'item',
      '#title' => $this->t("Site's Origin UUID"),
      '#markup' => $this->settings->getUuid(),
    ];

    if ($readonly) {
      return $form;
    }

    $form = parent::buildForm($form, $form_state);

    if (!$connected) {
      $form['actions']['submit']['#value'] = $this->t('Register Site');
      $form['actions']['submit']['#name'] = 'register_site';
      return $form;
    }

    $this->messenger()->addMessage($this->t('Site successfully connected to Content Hub. To change connection settings, unregister the site first.'));

    $form['webhook_details'] = [
      '#type' => 'fieldset',
      '#title' => 'Webhook',
      '#collapsible' => FALSE,
      '#open' => TRUE,
    ];

    $form['webhook_details']['webhook'] = $form['settings']['webhook'];
    unset($form['settings']['webhook']);

    $form['actions']['updatewebhook'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Public URL'),
      '#button_type' => 'primary',
      '#weight' => 100,
      '#submit' => [[$this, 'updateWebhook']],
      '#validate' => [[$this, 'validateWebhook']],
      '#name' => 'update_webhook',
    ];

    $form['actions']['unregister'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unregister Site'),
      '#button_type' => 'secondary',
      '#weight' => 999,
      '#limit_validation_errors' => [],
      '#submit' => [[$this, 'unregister']],
      // No validation at all is required in the equivocate case, so
      // we include this here to make it skip the form-level validator.
      '#validate' => [],
      '#name' => 'unregister_site',
    ];

    unset($form['actions']['submit']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $action_name = $form_state->getTriggeringElement()['#name'];
    if (!in_array($action_name,
      ['register_site', 'update_webhook', 'unregister_site'],
      TRUE)) {
      return;
    }

    if (!UrlHelper::isValid($form_state->getValue('hostname'), TRUE)) {
      $form_state->setErrorByName('hostname', $this->t('This is not a valid URL. Please insert another one.'));
    }

    $webhook = $this->getFormattedWebhookUrl($form_state);
    if (!UrlHelper::isValid($webhook, TRUE)) {
      $form_state->setErrorByName('webhook', $this->t('Please type a publicly accessible url.'));
    }

    // Important. This should never validate if it is an UUID. Lift 3 does not
    // use UUIDs for the api_key but they are valid for Content Hub.
    $fields = [
      'api_key' => $this->t('Please insert an API Key.'),
      'secret_key' => $this->t('Please insert a Secret Key.'),
      'client_name' => $this->t('Please insert a Client Name.'),
    ];
    foreach ($fields as $field => $error_message) {
      if (!$form_state->hasValue($field)) {
        $form_state->setErrorByName($field, $error_message);
      }
    }

    if (!empty($form_state->getErrors())) {
      return;
    }

    if ($action_name === 'register_site') {
      $this->register($form_state);
    }
  }

  /**
   * Validates webhook update.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Exception
   */
  public function validateWebhook(array &$form, FormStateInterface $form_state): void {
    if (!empty($form_state->getErrors())) {
      return;
    }

    $webhook_url = $this->getFormattedWebhookUrl($form_state);
    if (!UrlHelper::isValid($webhook_url, TRUE)) {
      $form_state->setErrorByName('webhook', $this->t('Please type a publicly accessible url.'));
    }

    if ($this->chConnectionManager->webhookIsRegistered($webhook_url)) {
      $form_state->setErrorByName('webhook', $this->t('This webhook is already being used. (%name) Please insert another one, or unregister the existing one first.', [
        '%name' => $webhook_url,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // We assume here all inserted values have passed validation.
    $this->settings = $this->client->getSettings();
    $hostname = rtrim($this->settings->getUrl(), '/');
    $remote = $this->client->getRemoteSettings();

    $config = $this->config('acquia_contenthub.admin_settings')
      ->set('hostname', $hostname)
      ->set('api_key', $this->settings->getApiKey())
      ->set('secret_key', $this->settings->getSecretKey())
      ->set('origin', $this->settings->getUuid())
      ->set('client_name', $this->settings->getName());
    if (isset($remote['shared_secret'])) {
      $config->set('shared_secret', $remote['shared_secret']);
    }
    // Call Save now, so when the webhook call from Plexus occurs, we can
    // respond with a valid signature.
    $config->save();

    // If a webhook was sent during registration, lets get that added as
    // well, return the webhook UUID if successful.
    $webhook = $form_state->getValue('webhook');
    $response = $this->chConnectionManager->registerWebhook($webhook);
    if (empty($response)) {
      $this->messenger()->addWarning($this->t('Registration to Content Hub was successful, but Content Hub could not reach your site to verify connectivity. Please update your publicly accessible URL and try again.'));
      return;
    }
  }

  /**
   * Updates webhook.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Exception
   */
  public function updateWebhook(array &$form, FormStateInterface $form_state): void {
    $webhook_url = $this->getFormattedWebhookUrl($form_state);
    $response = [];
    try {
      $response = $this->chConnectionManager
        ->checkClient()
        ->updateWebhook($webhook_url);
    }
    catch (\Exception $e) {
      $this->logger('acquia_contenthub')->warning($e);
    }

    if (!$response) {
      $this->messenger()
        ->addError($this->t("Something went wrong during webhook (%webhook) update. Check logs for more information.", [
          '%webhook' => $webhook_url,
        ]));
      return;
    }
    $this->messenger()->addMessage($this->t('Successfully Updated Public URL.'));
  }

  /**
   * Unregistration submit form handler.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function unregister(array &$form, FormStateInterface $form_state) {
    $client_name = $this->config('acquia_contenthub.admin_settings')
      ->get('client_name');
    try {
      $this->chConnectionManager->unregister();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error during unregistration: @error_message', ['@error_message' => $e->getMessage()]));
      return;
    }

    $this->messenger()->addMessage($this->t('Successfully disconnected site %site from Content Hub.', ['%site' => $client_name]));
  }

  /**
   * Returns formatted webhook.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The formatted webhook url.
   */
  protected function getFormattedWebhookUrl(FormStateInterface $form_state): string {
    $webhook = rtrim($form_state->getValue('webhook'), '/');

    if (strpos($webhook, $this->achPath) === FALSE) {
      $webhook .= $this->achPath;
    }

    $form_state->setValue('webhook', $webhook);

    return $webhook;
  }

  /**
   * Registers client.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function register(FormStateInterface $form_state): void {
    try {
      $values = $form_state->getValues();
      if ($client = $this->clientFactory->registerClient(
        $values['client_name'],
        $values['hostname'],
        $values['api_key'],
        $values['secret_key']
      )) {
        // If the client passes back, then we're good.
        $this->client = $client;
        $this->chConnectionManager->setClient($client);
      }
    }
    catch (RequestException $ch_error) {
      // Were we able to connect to the service?
      if ($ch_error->getResponse()) {
        $service_error = json_decode($ch_error->getResponse()->getBody(), TRUE);
        $message = $service_error['error']['message'];
      }
      // We failed during the response, grab the parent request exception.
      else {
        $service_error['error']['code'] = 1;
        $message = $ch_error->getMessage();
      }

      if (isset($service_error['error']['code'])) {
        switch ($service_error['error']['code']) {
          case 4006:
            $form_state->setErrorByName('client_name', $message);
            break;

          case 401:
            $form_state->setErrorByName('secret_key', $this->t('Access Denied, Reason: %message', ['%message' => $message]));
            break;

          case 1:
            $form_state->setErrorByName('hostname', $message);

          default:
            $form_state->setErrorByName('settings',
              $this->t('There is a problem connecting to Acquia Content Hub. Please ensure that your hostname and credentials are correct.'));
        }
      }
    }
    catch (\Exception $ch_error) {
      $form_state->setErrorByName('settings', $this->t('Unexpected error occurred: @message', ['@message' => $ch_error->getMessage()]));
    }
  }

  /**
   * Ensures client is in sync with the registered one on Content Hub.
   *
   * @return string
   *   The ensured and synced client name.
   *
   * @throws \Exception
   */
  protected function ensureClientNameIsSynced(): string {
    $client_name = $this->settings->getName();
    $uuid = $this->settings->getUuid();
    foreach ($this->client->getClients() as $client) {
      // If its not in-sync secretly change the values and resave the config.
      if ($client['uuid'] === $uuid && $client['name'] !== $client_name) {
        $client_name = $client['name'];
        if ($this->clientFactory->getProvider() !== 'core_config') {
          $this->messenger()->addMessage($this->t('Warning: client name is out of sync it should be %name. Please manually update your settings file.', ['%name' => $client_name]));
          break;
        }
        $this->config('acquia_contenthub.admin_settings')
          ->set('client_name', $client_name)
          ->save();
      }
    }

    return $client_name;
  }

  /**
   * Remove webhook suppression.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Exception
   */
  public function removeWebhookSuppression(array &$form, FormStateInterface $form_state): void {
    $webhook_uuid = $this->settings->getWebhook('uuid');
    $success = $this->chConnectionManager->removeWebhookSuppression($webhook_uuid);

    if (!$success) {
      $this
        ->messenger()
        ->addError($this->t('Cannot register with env vars, please check your log messages.'));
    }

    $this
      ->config('acquia_contenthub.admin_settings')
      ->set('is_suppressed', FALSE)
      ->save();

    $this
      ->messenger()
      ->addMessage($this->t('Registering ACH was successful.'));
  }

  /**
   * Suppress webhook.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Exception
   */
  public function suppressWebhook(array &$form, FormStateInterface $form_state): void {
    $webhook_uuid = $this->settings->getWebhook('uuid');
    $success = $this->chConnectionManager->suppressWebhook($webhook_uuid);

    if (!$success) {
      $this
        ->messenger()
        ->addError($this->t('Cannot register with env vars, please check your log messages.'));
    }

    $this
      ->config('acquia_contenthub.admin_settings')
      ->set('is_suppressed', TRUE)
      ->save();

    $this
      ->messenger()
      ->addMessage($this->t('Unregistering ACH was successful.'));
  }

}
