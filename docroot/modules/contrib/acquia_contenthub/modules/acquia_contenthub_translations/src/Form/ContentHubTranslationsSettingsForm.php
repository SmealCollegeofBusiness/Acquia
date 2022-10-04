<?php

namespace Drupal\acquia_contenthub_translations\Form;

use Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Acquia ContentHub Translations settings.
 *
 * @codeCoverageIgnore
 */
class ContentHubTranslationsSettingsForm extends ConfigFormBase {

  /**
   * Undesired language registrar.
   *
   * @var \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface
   */
  protected $registrar;

  /**
   * ContentHubTranslationsSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Configuration Factory.
   * @param \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface $registrar
   *   Undesired language registrar.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UndesiredLanguageRegistryInterface $registrar) {
    parent::__construct($config_factory);
    $this->registrar = $registrar;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('acquia_contenthub_translations.undesired_language_registrar')
    );
  }

  /**
   * Config settings name.
   */
  public const CONFIG = 'acquia_contenthub_translations.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub_translations_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::CONFIG];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['contenthub_translations'] = [
      '#type' => 'details',
      '#title' => $this->t('Translation syndication'),
      '#open' => TRUE,
    ];
    $form['contenthub_translations']['selective_language_import'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable selective language import'),
      '#default_value' => $this->config(self::CONFIG)->get('selective_language_import'),
    ];
    $form['contenthub_translations']['override_translation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow overriding locally modified translation'),
      '#default_value' => $this->config(self::CONFIG)->get('override_translation') ?? FALSE,
    ];

    $undesired_languages = $this->registrar->getUndesiredLanguages();
    if (!empty($undesired_languages)) {
      $form['contenthub_translations']['undesired_languages'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('List of undesired languages'),
        '#description' => $this->t('Languages that are imported only because of hard dependency, but such translations will be excluded from syndication where possible.'),
        '#options' => $undesired_languages,
        '#default_value' => array_keys($undesired_languages),
        '#disabled' => TRUE,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(self::CONFIG)
      ->set('selective_language_import', $form_state->getValue('selective_language_import'))
      ->set('override_translation', $form_state->getValue('override_translation'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
