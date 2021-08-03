<?php

namespace Drupal\acquia_contenthub_publisher\Form;

use Drupal\acquia_contenthub_publisher\EventSubscriber\EnqueueEligibility\EntityTypeOrBundleExclude;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a form to exclude entity types or bundles.
 */
class ExcludeSettingsForm extends ConfigFormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_contenthub_publisher_exclude_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['acquia_contenthub_publisher.exclude_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    parent::__construct($config);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = $this->getTypeAndBundleOptions();
    $exclude_settings = $this->config('acquia_contenthub_publisher.exclude_settings');

    $form = parent::buildForm($form, $form_state);

    $form['exclude_entity_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity types'),
      '#options' => $options['types'],
      '#multiple' => TRUE,
      '#size' => 5,
      '#default_value' => $exclude_settings->get('exclude_entity_types'),
    ];
    $form['exclude_bundles'] = [
      '#type' => 'select',
      '#title' => $this->t('Bundles'),
      '#options' => $options['bundles'],
      '#multiple' => TRUE,
      '#size' => 5,
      '#default_value' => $exclude_settings->get('exclude_bundles'),
    ];
    $form['message'] = [
      '#type' => '#item',
      '#markup' => $this->t('This will only limit the entities that trigger syndication,
     but not the dependencies as all dependencies will be exported regardless of any 
     limitation set by this feature.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('acquia_contenthub_publisher.exclude_settings')
      ->set('exclude_entity_types', $form_state->getValue('exclude_entity_types', []))
      ->set('exclude_bundles', $form_state->getValue('exclude_bundles', []))
      ->save();
    $this->messenger()->addMessage($this->t('The settings have been saved.'));
  }

  /**
   * Returns entity type and bundles options array.
   *
   * @return array
   *   Entity types and bundles options array.
   */
  protected function getTypeAndBundleOptions(): array {
    $entity_type_options = [];
    $bundle_options = [];

    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $id => $type) {
      $entity_type_label = $type->getLabel()->render();
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($id);
      $entity_type_options[$id] = $entity_type_label;

      // If bundle and entity type id are the same then entity does not have
      // bundles.
      if (count($bundles) === 1 && array_key_exists($id, $bundles)) {
        continue;
      }

      $bundle_options[$entity_type_label] = [];
      foreach ($bundles as $bundle_id => $bundle_label) {
        $bundle_options[$entity_type_label][EntityTypeOrBundleExclude::formatTypeBundle($id, $bundle_id)] =
          $bundle_label['label'];
      }
    }

    return [
      'types' => $entity_type_options,
      'bundles' => $bundle_options,
    ];
  }

}
