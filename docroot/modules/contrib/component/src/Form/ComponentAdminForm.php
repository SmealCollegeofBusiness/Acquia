<?php

namespace Drupal\component\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the admin form for managing component plugins.
 */
class ComponentAdminForm extends ConfigFormBase {

  /**
   * Drupal\component\ComponentDiscoveryInterface definition.
   *
   * @var Drupal\component\ComponentDiscoveryInterface
   */
  protected $componentDiscovery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->componentDiscovery = $container->get('component.discovery');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'component.admin',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'component_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get a list of components.
    $components = $this->componentDiscovery->getComponents();
    // Generate the form or render array for each type.
    $form['plugin'] = $this->pluginForm($components);
    $form['library'] = $this->componentTable($components['library'], 'Libraries');
    $form['block'] = $this->componentTable($components['block'], 'Blocks');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $plugins = $form_state->getValues()['plugins'];
    foreach ($plugins as $parent => $value) {
      $this->config('component.admin')
        ->set($parent, $value['select'])
        ->save();
    }

  }

  /**
   * Generate the form for the plugins.
   */
  protected function pluginForm($components) {
    $component_plugins = $components['plugin'];
    $form['plugin_title'] = ['#markup' => '<h3>' . $this->t('Plugins') . '</h3>'];
    // Check if there are any plugins.
    if (count($component_plugins)) {
      // Build the options for the plugins.
      $plugins = [];
      foreach ($component_plugins as $name => $data) {
        $parent = $data['parent'];
        $plugins[$parent][$name] = $data['name'];
      }
      // Build a table for the plugins.
      $form['plugins'] = $this->pluginTable($plugins);
      // Add the save button.
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save configuration'),
        '#button_type' => 'primary',
      ];

      // By default, render the form using system-config-form.html.twig.
      $form['#theme'] = 'system_config_form';
    }
    else {
      // Return a message if not.
      $form['component_plugins'] = [
        '#markup' => '<p>' . $this->t('There are no plugin type components found in the system.') . '</p>',
      ];
    }
    return $form;
  }

  /**
   * Build the plugins table.
   */
  protected function pluginTable(array $plugins) {
    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Plugin to use'),
      ],
    ];
    // Build each row.
    foreach ($plugins as $name => $options) {
      $default = $this->config('component.admin')->get($name);
      $table[$name]['name'] = [
        '#markup' => '<strong>' . $name . '</strong>',
      ];
      $table[$name]['select'] = [
        '#type' => 'select',
        '#title' => $this->t('Select plugin'),
        '#options' => $options,
        '#default_value' => $default,
      ];
    }
    return $table;
  }

  /**
   * Generate the form for the plugins.
   */
  protected function componentTable(array $components, string $title) {
    ksort($components);
    $output = ['#markup' => '<h3>' . $this->t($title) . '</h3>'];
    // Create a table to list components.
    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Machine name'),
        $this->t('Description'),
        $this->t('Dependencies'),
      ],
    ];
    // Build each row.
    foreach ($components as $name => $component) {
      $table[$name]['name'] = [
        '#markup' => '<strong>' . $component['name'] . '</strong>',
      ];
      $table[$name]['machine_name'] = [
        '#markup' => '<em>component/' . $name . '</em>',
      ];
      $table[$name]['description'] = [
        '#markup' => $component['description'],
      ];
      $table[$name]['dependencies'] = [
        '#markup' => implode("</br>", $component['dependencies']),
      ];
    }
    $output['table'] = $table;
    return $output;
  }

}
