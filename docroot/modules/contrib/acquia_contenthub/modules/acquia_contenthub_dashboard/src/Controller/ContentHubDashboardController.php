<?php

namespace Drupal\acquia_contenthub_dashboard\Controller;

use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Render\Markup;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ContentHubDashboardController to show Acquia ContentHub Dashboard.
 */
class ContentHubDashboardController extends ControllerBase {

  /**
   * The Content Hub Client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Csrf Token Generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfTokenGenerator;

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The Module Handler Service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueService;

  /**
   * The Content Hub Config URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $configUrl;

  /**
   * ContentHubDashboardController constructor.
   *
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The contenthub client factory.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token_generator
   *   The Token generator service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State Interface Service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The Module Handler Service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_service
   *   The queue service.
   * @param \Drupal\Core\Url $content_hub_config_url
   *   The Content Hub Config Url.
   */
  public function __construct(
    ClientFactory $client_factory,
    CsrfTokenGenerator $csrf_token_generator,
    StateInterface $state,
    ModuleHandlerInterface $module_handler,
    QueueFactory $queue_service,
    Url $content_hub_config_url) {
    $this->clientFactory = $client_factory;
    $this->csrfTokenGenerator = $csrf_token_generator;
    $this->state = $state;
    $this->moduleHandler = $module_handler;
    $this->queueService = $queue_service;
    $this->configUrl = $content_hub_config_url;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $client_factory */
    $client_factory = $container->get('acquia_contenthub.client.factory');
    /** @var \Drupal\Core\Access\CsrfTokenGenerator $csrf_token_generator */
    $csrf_token_generator = $container->get('csrf_token');
    /** @var \Drupal\Core\State\StateInterface $state */
    $state = $container->get('state');
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = $container->get('module_handler');
    /** @var \Drupal\Core\Queue\QueueFactory $queue_service */
    $queue_service = $container->get('queue');
    /** @var \Drupal\Core\Url $content_hub_config_url */
    $content_hub_config_url = Url::fromRoute('acquia_contenthub.admin_settings');

    return new static(
      $client_factory,
      $csrf_token_generator,
      $state,
      $module_handler,
      $queue_service,
      $content_hub_config_url
    );
  }

  /**
   * Loads the Acquia ContentHub Dashboard.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request.
   *
   * @return array
   *   Renderable array.
   */
  public function loadContentHubDashboard(Request $request): array {
    $content = [];
    $content['#attached']['library'][] = 'acquia_contenthub_dashboard/acquia_contenthub_dashboard';

    // A dummy query-string is added to filenames, to gain control over
    // browser-caching. The string changes on every update or full cache
    // flush, forcing browsers to load a new copy of the files, as the
    // URL changed.
    $query_string = $this->state->get('system.css_js_query_string') ?: '0';
    $angular_endpoint = $request->getSchemeAndHttpHost() . '/admin/acquia-contenthub/contenthub-dashboard/dashboard/index' . '?' . $query_string;

    $content['#attached']['drupalSettings']['acquia_contenthub_dashboard'] =
      $this->getDrupalSettings($request, $angular_endpoint) +
      $this->getApiSettings($this->clientFactory->getSettings()) +
      $this->getAcquiaContentHubModuleData();

    $content['#markup'] = Markup::create('<iframe id="acquia-contenthub-dashboard" src=' . $angular_endpoint . ' width="100%" style="border:0; height: 100vh;"></iframe>');
    return $content;
  }

  /**
   * Modifies the base path from index.html in the Angular app.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTML Response.
   */
  public function indexPage(Request $request): Response {
    $module_path = $this->moduleHandler->getModule('acquia_contenthub_dashboard')->getPath();
    $base_path = $request->getBasePath();
    $angular_endpoint = DRUPAL_ROOT . $base_path . '/' . $module_path . '/dashboard/index.html';
    if (!file_exists($angular_endpoint)) {
      return new Response('The Acquia ContentHub Dashboard app could not be found.');
    }
    $file = file_get_contents($angular_endpoint);
    $path = $base_path . '/' . $module_path . '/dashboard/';
    $file = str_replace('base href="/dc-ch/"', "base href=\"{$path}\"", $file);
    $additional_js = "<script>
      if (this.window.parent.drupalSettings == null) {
        window.location.replace('/admin/acquia-contenthub/contenthub-dashboard');
      }
    </script>";
    $file = str_replace('</head>', $additional_js . '</head>', $file);
    return new Response($file);
  }

  /**
   * Gets initial Drupal settings needed for the Acquia ContentHub Dashboard.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $angular_endpoint
   *   Angular endpoint.
   *
   * @return array
   *   Initial Drupal settings needed for the Acquia ContentHub Dashboard.
   */
  protected function getDrupalSettings(Request $request, string $angular_endpoint): array {
    return [
      'base_url' => $request->getSchemeAndHttpHost(),
      'angular_app' => $angular_endpoint,
      'token' => $this->csrfTokenGenerator->get('rest'),
      'cookie' => session_name() . '=' . current($request->cookies->all()),
      'timezone' => date('P'),
      'content_hub_config_url' => $this->configUrl->toString(),
    ];
  }

  /**
   * Returns an array with the necessary API settings.
   *
   * @param \Acquia\ContentHubClient\Settings $settings
   *   The settings object.
   *
   * @return array
   *   An array with the necessary API settings.
   */
  protected function getApiSettings(Settings $settings): array {
    return [
      'host' => $settings->getUrl(),
      'public_key' => $settings->getApiKey(),
      'secret_key' => $settings->getSecretKey(),
      'client' => $settings->getUuid(),
      'ch_version' => '2',
    ];
  }

  /**
   * Returns an array with CH module data.
   *
   * @return array
   *   Array with CH module data.
   */
  protected function getAcquiaContentHubModuleData(): array {
    return [
      'publisher' => $this->moduleHandler->moduleExists('acquia_contenthub_publisher'),
      'subscriber' => $this->moduleHandler->moduleExists('acquia_contenthub_subscriber'),
      'export_queue_count' => $this->queueService->get('acquia_contenthub_publish_export')->numberOfItems(),
      'import_queue_count' => $this->queueService->get('acquia_contenthub_subscriber_import')->numberOfItems(),
    ];
  }

}
