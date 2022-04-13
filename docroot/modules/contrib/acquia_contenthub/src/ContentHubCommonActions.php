<?php

namespace Drupal\acquia_contenthub;

use Acquia\ContentHubClient\CDF\CDFObjectInterface;
use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Acquia\ContentHubClient\CDFDocument;
use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\ContentHubPublishEntitiesEvent;
use Drupal\acquia_contenthub\Event\DeleteRemoteEntityEvent;
use Drupal\acquia_contenthub_subscriber\Exception\ContentHubImportException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\depcalc\DependencyCalculator;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Common actions across the entirety of Content Hub.
 *
 * @package Drupal\acquia_contenthub
 */
class ContentHubCommonActions {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The entity cdf serializer.
   *
   * @var \Drupal\acquia_contenthub\EntityCdfSerializer
   */
  protected $serializer;

  /**
   * The dependency calculator.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * The ContentHub client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $factory;

  /**
   * The acquia_contenthub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $channel;

  /**
   * Acquia ContentHub Admin Settings Config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * ContentHubCommonActions constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\acquia_contenthub\EntityCdfSerializer $serializer
   *   The entity cdf serializer.
   * @param \Drupal\depcalc\DependencyCalculator $calculator
   *   The dependency calculator.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   The ContentHub client factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config Factory.
   */
  public function __construct(EventDispatcherInterface $dispatcher, EntityCdfSerializer $serializer, DependencyCalculator $calculator, ClientFactory $factory, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory) {
    $this->dispatcher = $dispatcher;
    $this->serializer = $serializer;
    $this->calculator = $calculator;
    $this->factory = $factory;
    $this->channel = $logger_factory->get('acquia_contenthub');
    $this->config = $config_factory->getEditable('acquia_contenthub.admin_settings');
  }

  /**
   * Get a single merged CDF Document of entities and their dependencies.
   *
   * This is useful for getting a single merged CDFDocument of various entities
   * and all their dependencies. Normally the process of getting a CDFDocument
   * runs through a process that reduces the number of CDFObjects returned
   * based upon data that's been previously syndicated. This function skips
   * that process in order to give a full representation of the entities
   * requested and their dependencies.
   *
   * @param \Drupal\Core\Entity\EntityInterface ...$entities @codingStandardsIgnoreLine
   *   The entities for which to generate a CDFDocument.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   The CDFDocument object.
   *
   * @throws \Exception
   */
  public function getLocalCdfDocument(EntityInterface ...$entities) { //@codingStandardsIgnoreLine
    $document = new CDFDocument();
    $wrappers = [];
    foreach ($entities as $entity) {
      $entityDocument = new CDFDocument(...array_values($this->getEntityCdf($entity, $wrappers, FALSE)));
      $document->mergeDocuments($entityDocument);
    }
    return $document;
  }

  /**
   * Gets the CDF objects representation of an entity and its dependencies.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity from which to calculate dependencies and generate CDFObjects.
   * @param array $entities
   *   (optional) The array of collected DependentEntityWrappers.
   * @param bool $return_minimal
   *   Whether to dispatch the PUBLISH_ENTITIES event subscribers.
   * @param bool $calculate_dependencies
   *   Whether to calculate dependencies on the entity.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject[]
   *   An array of CDFObjects.
   *
   * @throws \Exception
   */
  public function getEntityCdf(EntityInterface $entity, array &$entities = [], bool $return_minimal = TRUE, bool $calculate_dependencies = TRUE) {
    $wrapper = new DependentEntityWrapper($entity);
    $stack = new DependencyStack();
    if ($calculate_dependencies) {
      $this->calculator->calculateDependencies($wrapper, $stack);
    }
    /** @var \Drupal\depcalc\DependentEntityWrapper[] $entities */
    $entities = NestedArray::mergeDeep([$wrapper->getUuid() => $wrapper], $stack->getDependenciesByUuid(array_keys($wrapper->getDependencies())));
    if ($return_minimal) {
      // Modify/Remove objects before publishing to ContentHub service.
      $event = new ContentHubPublishEntitiesEvent($entity->uuid(), ...array_values($entities));
      $this->dispatcher->dispatch(AcquiaContentHubEvents::PUBLISH_ENTITIES, $event);
      $entities = $event->getDependencies();
    }

    return $this->serializer->serializeEntities(...array_values($entities));
  }

  /**
   * Import a group of entities by their uuids from the ContentHub Service.
   *
   * The uuids passed are just the list of entities you absolutely want,
   * ContentHub will calculate all the missing entities and ensure they are
   * installed on your site.
   *
   * @param string ...$uuids @codingStandardsIgnoreLine
   *   The list of uuids to import.
   *
   * @return \Drupal\depcalc\DependencyStack
   *   The DependencyStack object.
   *
   * @throws \Exception
   */
  public function importEntities(string ...$uuids) { //@codingStandardsIgnoreLine
    $document = $this->getCdfDocument(...$uuids);
    return $this->importEntityCdfDocument($document);
  }

  /**
   * Imports a list of entities from a CDFDocument object.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $document
   *   The CDF document representing the entities to import.
   *
   * @return \Drupal\depcalc\DependencyStack
   *   The DependencyStack object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function importEntityCdfDocument(CDFDocument $document) {
    $stack = new DependencyStack();
    $this->serializer->unserializeEntities($document, $stack);
    return $stack;
  }

  /**
   * Retrieves entities and dependencies by uuid and returns a CDFDocument.
   *
   * @param string ...$uuids @codingStandardsIgnoreLine
   *   The list of uuids to retrieve.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   The CDFDocument object.
   *
   * @throws \Drupal\acquia_contenthub_subscriber\Exception\ContentHubImportException
   */
  public function getCdfDocument(string ...$uuids) { //@codingStandardsIgnoreLine
    $uuid_list = [];
    foreach ($uuids as $uuid) {
      if (!Uuid::isValid($uuid)) {
        $exception = new ContentHubImportException(sprintf("Invalid uuid %s.", $uuid), 101);
        $exception->setUuids([$uuid]);
        throw $exception;
      }
      $uuid_list[$uuid] = $uuid;
    }
    $document = $this->getClient()->getEntities($uuid_list);
    $this->validateDocument($document, $uuid_list);
    $missing_dependencies = $this->getMissingDependencies($document);
    $client = $this->getClient();
    while ($missing_dependencies) {
      $document->mergeDocuments($client->getEntities($missing_dependencies));
      $uuid_list += $missing_dependencies;
      $this->validateDocument($document, $uuid_list);
      $missing_dependencies = $this->getMissingDependencies($document);
    }
    return $document;
  }

  /**
   * Validate the expected number of retrieved entities.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $document
   *   The CDFDocument object.
   * @param array $uuids
   *   The list of expected uuids.
   *
   * @throws \Drupal\acquia_contenthub_subscriber\Exception\ContentHubImportException
   */
  protected function validateDocument(CDFDocument $document, array $uuids) {
    $cdf_objects = $document->getEntities();
    $uuids_count = count($uuids);
    $document_count = count($cdf_objects);
    $message = '';
    if ($uuids_count <= $document_count) {
      return;
    }
    $diff_uuids = array_diff($uuids, array_keys($cdf_objects));
    foreach ($uuids as $uuid) {
      $cdf_object = $document->getCdfEntity($uuid);
      if (!$cdf_object) {
        $message .= sprintf("The entity with UUID = \"%s\" could not be imported because it is missing from Content Hub.", $uuid) . PHP_EOL;
        continue;
      }
      $dependencies = $cdf_object->getDependencies();
      $vanished_uuids = array_intersect($diff_uuids, array_keys($dependencies));
      if (!empty($vanished_uuids)) {
        $type = $cdf_objects[$uuid]->getAttribute('entity_type')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
        $origin = $cdf_objects[$uuid]->getOrigin();
        // Using array_keys() to only pass the dependency UUIDs, not the hashes.
        $this->requestToRepublishEntity($origin, $type, $uuid, array_keys($dependencies));
        $message .= sprintf("The entity (%s, %s) could not be imported because the following dependencies are missing from Content Hub: %s.", $type, $uuid, implode(', ', $vanished_uuids)) . PHP_EOL;
      }
    }
    $exception = new ContentHubImportException($message, 100);
    $exception->setUuids($diff_uuids);
    throw $exception;
  }

  /**
   * Gets missing dependencies from CDFObjects within a CDFDocument.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $document
   *   The document from which to identify missing dependencies.
   *
   * @return array
   *   The array of missing uuids.
   */
  protected function getMissingDependencies(CDFDocument $document) {
    $missing_dependencies = [];
    foreach ($document->getEntities() as $cdf) {
      // @todo add the hash to the CDF so that we can check it here to see if we need to update.
      foreach ($cdf->getDependencies() as $dependency => $hash) {
        // If the document doesn't have a version of this dependency, it might
        // be missing.
        if (!$document->hasEntity($dependency)) {
          $missing_dependencies[$dependency] = $dependency;
        }
      }
    }
    return $missing_dependencies;
  }

  /**
   * Get the remote entity CDFObject if available.
   *
   * @param string $uuid
   *   The uuid of the remote entity to retrieve.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObjectInterface|null
   *   CDFObject if found, null on caught exception.
   */
  public function getRemoteEntity(string $uuid) {
    try {
      $client = $this->getClient();
      $entity = $client->getEntity($uuid);
      if (!$entity instanceof CDFObjectInterface) {
        if (isset($entity['error']['message'])) {
          throw new \Exception($entity['error']['message']);
        }

        throw new \Exception('Unexpected error.');
      }
      return $entity;
    }
    catch (\Exception $e) {
      $this->channel
        ->error('Error during remote entity retrieval: @error_message', ['@error_message' => $e->getMessage()]);
    }
    return NULL;
  }

  /**
   * Delete a remote entity if we own it.
   *
   * @param string $uuid
   *   The uuid of the remote entity to delete.
   *
   * @return bool|void
   *   Boolean for success or failure, void if nonexistent or not ours.
   *
   * @throws \Exception
   */
  public function deleteRemoteEntity(string $uuid) {
    if (!Uuid::isValid($uuid)) {
      throw new \Exception(sprintf("Invalid uuid %s.", $uuid));
    }
    $event = new DeleteRemoteEntityEvent($uuid);
    $this->dispatcher->dispatch(AcquiaContentHubEvents::DELETE_REMOTE_ENTITY, $event);
    $remote_entity = $this->getRemoteEntity($uuid);
    if (!$remote_entity) {
      return; //@codingStandardsIgnoreLine
    }
    $client = $this->getClient();
    $settings = $client->getSettings();
    if ($settings->getUuid() !== $remote_entity->getOrigin()) {
      return; //@codingStandardsIgnoreLine
    }
    $response = $client->deleteEntity($uuid);
    if ($response->getStatusCode() !== 202) {
      return FALSE;
    }
    $this->channel
      ->info(sprintf("Deleted entity with UUID = \"%s\" from Content Hub.", $uuid));

    // Clean up the interest list.
    $webhook_uuid = $settings->getWebhook('uuid');
    $send_update = $this->config->get('send_contenthub_updates') ?? TRUE;
    if ($send_update && Uuid::isValid($webhook_uuid)) {
      $client->deleteInterest($uuid, $webhook_uuid);
      $this->channel
        ->info(sprintf("Deleted entity with UUID = \"%s\" from webhook's interest list.", $uuid));
    }
    return $response->getStatusCode() === 202;
  }

  /**
   * Generates the CDF of an entity and all its dependencies keyed by UUIDs.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to generate the CDF from.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject[]|array
   *   An array of CDF Objects.
   *
   * @throws \Exception
   */
  public function getEntityCdfFullKeyedByUuids(EntityInterface $entity): array {
    $entities = [];
    $data = [];
    $objects = $this->getEntityCdf($entity, $entities, FALSE);
    foreach ($objects as $object) {
      $data[$object->getUuid()] = $object;
    }
    return $data;
  }

  /**
   * Request to republish an entity via Webhook.
   *
   * @param string $origin
   *   Entity Origin.
   * @param string $type
   *   Entity Type.
   * @param string $uuid
   *   Entity UUID to republish.
   * @param array $dependencies
   *   An array of dependency UUIDs.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function requestToRepublishEntity(string $origin, string $type, string $uuid, array $dependencies): void {
    $client = $this->getClient();
    $webhook_url = $this->getWebhookUrlFromClientOrigin($origin);
    if (!$webhook_url) {
      $message = sprintf('Could not find Webhook URL for origin = "%s". Request to re-export entity "%s/%s" could not be made.',
        $origin,
        $type,
        $uuid
      );
      $this->channel->error($message);
      return;
    }
    $settings = $client->getSettings();
    $cdf = [
      'uuid' => $uuid,
      'type' => $type,
      'dependencies' => $dependencies,
    ];

    $payload = [
      'status' => 'successful',
      'uuid' => $uuid,
      'crud' => 'republish',
      'initiator' => $settings->getUuid(),
      'cdf' => $cdf,
    ];
    try {
      $response = $client->request('post', $webhook_url, [
        'body' => json_encode($payload),
      ]);
    }
    catch (\Exception $e) {
      $this->channel->error('An error occurred while connecting to Publisher. Webhook Url: @webhook_url, Error: @error',
        [
          '@webhook_url' => $webhook_url,
          '@error' => $e->getMessage(),
        ]
      );
      return;
    }

    $message = $response->getBody()->getContents();
    $code = $response->getStatusCode();
    if ($code == 200) {
      $this->channel->info('@message', ['@message' => $message]);
    }
    else {
      $this->channel->error(sprintf('Request to re-export entity failed. Response code = %s, Response message = "%s".', $code, $message));
    }
  }

  /**
   * Obtain the webhook from the Client CDF, given origin.
   *
   * @param string $origin
   *   The origin of the site.
   *
   * @return false|mixed
   *   The webhook URL if it can be obtained, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function getWebhookUrlFromClientOrigin(string $origin) {
    // Obtaining the webhook from the remote origin.
    $cdf = $this->getClient()->getEntity($origin);
    if ($cdf instanceof ClientCDFObject) {
      $webhook = $cdf->getWebhook();
      if (isset($webhook['url'])) {
        return $webhook['url'];
      }
    }
    return FALSE;
  }

  /**
   * Request a Remote Entity via Webhook.
   *
   * @param string $webhook_url
   *   Webhook url.
   * @param string $uri
   *   File URI requested.
   * @param string $uuid
   *   UUID of file entity.
   * @param string $scheme
   *   File scheme.
   *
   * @return string
   *   File as a stream.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function requestRemoteEntity(string $webhook_url, string $uri, string $uuid, string $scheme) {
    $url = $webhook_url;
    $settings = $this->factory->getClient()->getSettings();
    $remote_settings = new Settings(
      "getFile",
      $settings->getUuid(),
      $settings->getApiKey(),
      $settings->getSecretKey(),
      $url,
      $settings->getSharedSecret(),
      [
        "uuid" => $settings->getWebhook('uuid'),
        "url" => $settings->getWebhook('url'),
        "settings_url" => $settings->getWebhook(),
      ]
    );

    $client = $this->factory->getClient($remote_settings);
    $cdf = [
      'uri' => $uri,
      'uuid' => $uuid,
      'scheme' => $scheme,
    ];

    $payload = [
      'status' => 'successful',
      'uuid' => $uuid,
      'crud' => 'getFile',
      'initiator' => $remote_settings->getUuid(),
      'cdf' => $cdf,
    ];
    $response = $client->request('post', $url, [
      'body' => json_encode($payload),
    ]);

    return (string) $response->getBody();
  }

  /**
   * Update db status.
   *
   * @return array
   *   Returns a list of all the pending database updates..
   */
  public function getUpdateDbStatus(): array {
    require_once DRUPAL_ROOT . "/core/includes/install.inc";
    require_once DRUPAL_ROOT . "/core/includes/update.inc";

    drupal_load_updates();

    return \update_get_update_list();
  }

  /**
   * Gets the client or throws a common exception when it's unavailable.
   *
   * @return \Acquia\ContentHubClient\ContentHubClient|bool
   *   The ContentHubClient object or FALSE.
   *
   * @throws \Exception
   */
  protected function getClient() {
    $client = $this->factory->getClient();
    if (!($client instanceof ContentHubClient)) {
      $message = "Client is not properly configured. Please check your ContentHub registration credentials.";
      $this->channel->error($message);
      throw new \Exception($message);
    }
    return $client;
  }

}
