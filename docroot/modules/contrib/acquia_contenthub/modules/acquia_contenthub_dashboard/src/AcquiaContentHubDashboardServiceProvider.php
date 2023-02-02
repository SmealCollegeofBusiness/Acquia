<?php

namespace Drupal\acquia_contenthub_dashboard;

use Drupal\acquia_contenthub_dashboard\Libs\ContentHubCors;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Overrides the CORS params.
 */
class AcquiaContentHubDashboardServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritDoc}
   */
  public function alter(ContainerBuilder $container) {
    if (!$container->hasDefinition('acquia_contenthub_subscriber.tracker')) {
      return;
    }
    /** @var array $cors */
    $cors = $container->getParameter('cors.config');
    if ($this->isWildcard($cors)) {
      return;
    }

    $cors['enabled'] = TRUE;
    $container->setParameter('cors.config', $cors);

    $cors_def = $container->getDefinition('http_middleware.cors');
    $cors_def->setClass(ContentHubCors::class);
  }

  /**
   * Check for wildcard in CORS.
   *
   * @param mixed $cors
   *   CORS settings.
   *
   * @return bool
   *   TRUE if wildcard exists FALSE otherwise.
   */
  protected function isWildcard($cors): bool {
    return $cors['enabled'] === TRUE
      && in_array('*', $cors['allowedHeaders'])
      && in_array('*', $cors['allowedMethods'])
      && in_array('*', $cors['allowedOrigins']);
  }

}
