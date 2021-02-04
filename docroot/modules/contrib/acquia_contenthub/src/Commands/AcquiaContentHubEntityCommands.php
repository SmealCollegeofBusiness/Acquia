<?php

namespace Drupal\acquia_contenthub\Commands;

use Acquia\ContentHubClient\CDF\CDFObjectInterface;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\EntityCDFSerializer;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\Uuid;
use Drupal\depcalc\DependencyCalculator;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for interacting with Acquia Content Hub entities.
 *
 * @package Drupal\acquia_contenthub\Commands
 */
class AcquiaContentHubEntityCommands extends DrushCommands {

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * The dependency calculator.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * The CDF Serializer.
   *
   * @var \Drupal\acquia_contenthub\EntityCDFSerializer
   */
  protected $serializer;

  /**
   * AcquiaContentHubEntityCommands constructor.
   *
   * @param \Drupal\depcalc\DependencyCalculator $calculator
   *   The dependency calculator.
   * @param \Drupal\acquia_contenthub\EntityCDFSerializer $serializer
   *   The dependency calculator.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   */
  public function __construct(DependencyCalculator $calculator, EntityCDFSerializer $serializer, ClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
    $this->calculator = $calculator;
    $this->serializer = $serializer;
  }

  /**
   * Retrieves an Entity from a local source or contenthub.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $uuid
   *   Entity identifier or entity's UUID.
   * @param string $entity_type
   *   The entity type in case of local retrieval.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option decode
   *   Decodes the metadata 'data' element to make it easier to understand the
   *   content of each CDF entity stored in Content Hub.
   *
   * @command acquia:contenthub-entity
   * @aliases ach-ent
   *
   * @throws \Exception
   */
  public function contenthubEntity($op, $uuid, $entity_type = NULL, array $options = ['decode' => NULL]) {
    $client = $this->clientFactory->getClient();

    if (empty($uuid)) {
      throw new \Exception("Please supply the uuid of the entity you want to retrieve.");
    }

    switch ($op) {
      case 'local':
        if (empty($entity_type)) {
          throw new \Exception(dt("Entity_type is required for local entities"));
        }
        $repository = \Drupal::service('entity.repository');
        $entity = $repository->loadEntityByUuid($entity_type, $uuid);

        $wrapper = new DependentEntityWrapper($entity);
        $stack = new DependencyStack();
        $this->calculator->calculateDependencies($wrapper, $stack);
        $entities = NestedArray::mergeDeep(
          [$wrapper->getEntity()->uuid() => $wrapper],
          $stack->getDependenciesByUuid(array_keys($wrapper->getDependencies()))
        );
        $objects = $this->serializer->serializeEntities(...array_values($entities));
        $data = [];
        foreach ($objects as $object) {
          $data[$object->getUuid()] = $object->toArray();
          // Decode the base64 'data' element in 'metadata'.
          if ($options['decode']) {
            $this->decodeEntityArrayMetadata($data[$object->getUuid()]);
          }
        }
        $json = json_encode(
          $data,
          JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $this->output()->writeln($json);
        break;

      case 'remote':
        $entity = $client->getEntity($uuid);
        $entity_array = $entity->toArray();
        // Decode the base64 'data' element in 'metadata'.
        if ($options['decode']) {
          $this->decodeEntityArrayMetadata($entity_array);
        }
        elseif (!$entity instanceof CDFObjectInterface) {
          return;
        }
        $json = json_encode($entity_array, JSON_PRETTY_PRINT |
          JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->output()->writeln($json);
        break;

      default:
        // Invalid operation.
        throw new \Exception(dt('The op "@op" is invalid', ['@op' => $op]));
    }
  }

  /**
   * Prints the CDF from a local source (drupal site)
   *
   * @param string $entity_type
   *   The entity type to load.
   * @param string $entity_id
   *   The entity identifier or entity's UUID.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option decode
   *   Decodes the metadata 'data' element to make it easier to understand the
   *   content of each CDF entity stored in Content Hub.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @command acquia:contenthub-local
   * @aliases ach-lo,acquia-contenthub-local
   *
   * @deprecated  //@codingStandardsIgnoreLine
   */
  public function contenthubLocal($entity_type, $entity_id, array $options = ['decode' => NULL]) {
    $entity_type_manager = \Drupal::entityTypeManager();

    /** @var \Drupal\Core\Entity\EntityRepository $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');

    if (empty($entity_type) || empty($entity_id)) {
      throw new \Exception(dt("Missing required parameters: entity_type and entity_id (or entity's uuid)"));
    }
    elseif (!$entity_type_manager->getDefinition($entity_type)) {
      throw new \Exception(dt("Entity type @entity_type does not exist", [
        '@entity_type' => $entity_type,
      ]));
    }
    else {
      if (Uuid::isValid($entity_id)) {
        $entity = $entity_repository->loadEntityByUuid($entity_type, $entity_id);
      }
      else {
        $entity = $entity_type_manager->getStorage($entity_type)->load($entity_id);
      }
    }
    if (!$entity) {
      throw new \Exception(dt("Entity having entity_type = @entity_type and entity_id = @entity_id does not exist.", [
        '@entity_type' => $entity_type,
        '@entity_id' => $entity_id,
      ]));
    }
    // If nothing else, return our object structure.
    $this->contenthubEntity('local', $entity->uuid(), $entity_type, $options);
  }

  /**
   * Prints the CDF from a remote source (Content Hub)
   *
   * @param string $uuid
   *   The entity's UUID.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option decode
   *   Decodes the metadata 'data' element to make it easier to understand the
   *   content of each CDF entity stored in Content Hub.
   *
   * @command acquia:contenthub-remote
   * @aliases ach-re,acquia-contenthub-remote
   *
   * @throws \Exception
   */
  public function contenthubRemote($uuid, array $options = ['decode' => NULL]) {
    if (FALSE === Uuid::isValid($uuid)) {
      throw new \Exception(dt("Argument provided is not a UUID."));
    }
    $this->contenthubEntity('remote', $uuid, NULL, $options);
  }

  /**
   * Deletes a single entity from the Content Hub.
   *
   * @param string $uuid
   *   The entity's UUID.
   *
   * @command acquia:contenthub-delete
   * @aliases ach-del,acquia-contenthub-delete
   *
   * @throws \Exception
   */
  public function contenthubDelete($uuid) {
    if (!$this->io()->confirm(dt('Are you sure you want to delete the entity with uuid = @uuid from the Content Hub? There is no way back from this action!', [
      '@uuid' => $uuid,
    ]))) {
      return;
    }

    /** @var \Drupal\acquia_contenthub\ContentHubCommonActions $common */
    $common = \Drupal::service('acquia_contenthub_common_actions');
    if ($common->deleteRemoteEntity($uuid)) {
      $this->output()->writeln(dt('Entity with UUID = @uuid has been successfully deleted from the Content Hub Service.', [
        '@uuid' => $uuid,
      ]));
      return;
    }
    $this->output()->writeln(dt('WARNING: Entity with UUID = @uuid cannot be deleted from the Content Hub Service.', [
      '@uuid' => $uuid,
    ]));
  }

  /**
   * Decodes the base64 'data' element inside a CDF entity 'metadata'.
   *
   * @param array $cdf_entity
   *   The CDF entity array before it is written to the output.
   */
  protected function decodeEntityArrayMetadata(array &$cdf_entity) {
    $types = [
      'drupal8_content_entity',
      'drupal8_config_entity',
      'rendered_entity',
    ];
    if (in_array($cdf_entity['type'], $types)) {
      $cdf_entity['metadata']['data'] = base64_decode($cdf_entity['metadata']['data']);
    }
  }

}
