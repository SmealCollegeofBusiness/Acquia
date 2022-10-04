<?php

namespace Drupal\acquia_contenthub_translations;

use Drupal\acquia_contenthub_translations\EntityHandler\NonTranslatableEntityHandlerCompilerPass;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Service provider for acquia_contenthub_translations module.
 */
class AcquiaContenthubTranslationsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->addCompilerPass(new NonTranslatableEntityHandlerCompilerPass());
  }

}
