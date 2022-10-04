<?php

namespace Drupal\acquia_contenthub_translations\EntityHandler;

use Drupal\acquia_contenthub_translations\Exceptions\NonTranslatableEntityHandlerException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers non-translatable entity handlers.
 *
 * Example:
 * @code
 * acquia_contenthub_translations.nt_entity_handler.removable:
 *   class: Drupal\acquia_contenthub_translations\EntityHandler\Removable
 *   tags:
 *      - { name: nt_entity_handler, id: removable }
 * @endcode
 */
class NonTranslatableEntityHandlerCompilerPass implements CompilerPassInterface {

  public const SERVICE_TAG = 'nt_entity_handler';

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container) {
    $context = $container->findDefinition('acquia_contenthub_translations.nt_entity_handler.context');
    $tagged_services = $container->findTaggedServiceIds(static::SERVICE_TAG);
    foreach ($tagged_services as $service => $attr) {
      $id = $attr[0]['id'] ?? NULL;
      if (is_null($id)) {
        throw new NonTranslatableEntityHandlerException(
          'Non-translatable entity handler must have an id!'
        );
      }
      $context->addMethodCall('addHandler', [new Reference($service), $id]);
    }
  }

}
