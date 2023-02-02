<?php

namespace Drupal\acquia_contenthub_dashboard\Libs;

use Asm89\Stack\Cors;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Overrides the 'http_middleware.cors' definition.
 *
 * @package Drupal\acquia_contenthub_dashboard\Libs
 */
class ContentHubCors extends Cors {

  public const HEADERS_TO_ADD = [
    'Authorization',
    'X-Acquia-Plexus-Client-Id',
    'X-Authorization-Content-SHA256',
    'X-Authorization-Timestamp',
  ];
  public const METHODS_TO_ADD = ['GET', 'OPTIONS', 'POST', 'PUT'];

  /**
   * ContentHubCors constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $app
   *   Http kernel interface.
   * @param array $options
   *   CORS options.
   */
  public function __construct(HttpKernelInterface $app, array $options = []) {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = \Drupal::service('config.factory');
    $config = $config_factory->get('acquia_contenthub_dashboard.settings');
    if ($config->get('auto_publisher_discovery')) {
      $options['allowedHeaders'] = $this->mergeCors($options['allowedHeaders'], ContentHubCors::HEADERS_TO_ADD);
      $options['allowedMethods'] = $this->mergeCors($options['allowedMethods'], ContentHubCors::METHODS_TO_ADD);

      $allowed_origins = $config->get('allowed_origins') ?? [];
      $options['allowedOrigins'] = $this->mergeCors($options['allowedOrigins'], $allowed_origins);
    }

    parent::__construct($app, $options);
  }

  /**
   * Add additional CORS params.
   *
   * @param array $original_cors
   *   Existing CORS params.
   * @param array $additional_cors
   *   Additional CORS params.
   *
   * @return array
   *   CORS parameters.
   */
  protected function mergeCors(array $original_cors, array $additional_cors): array {
    $key = array_search('*', $original_cors);
    if ($key !== FALSE) {
      if (count($original_cors) === 1) {
        return $original_cors;
      }
      unset($original_cors[$key]);
    }

    return array_unique(array_merge($original_cors, $additional_cors));
  }

}
