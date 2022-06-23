<?php

namespace Drupal\acquia_contenthub;

use Acquia\ContentHubClient\CDF\CDFObjectInterface;
use Acquia\ContentHubClient\CDFDocument;
use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\ContentHubPublishEntitiesEvent;
use Drupal\acquia_contenthub\Event\DeleteRemoteEntityEvent;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
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
  public function __construct(
    EventDispatcherInterface $dispatcher,
    EntityCdfSerializer $serializer,
    DependencyCalculator $calculator,
    ClientFactory $factory,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory
  ) {
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
   * @param \Drupal\Core\Entity\EntityInterface ...$entities
   *   The entities for which to generate a CDFDocument.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   The CDFDocument object.
   *
   * @throws \Exception
   */
  public function getLocalCdfDocument(EntityInterface ...$entities) {
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
      $this->dispatcher->dispatch($event, AcquiaContentHubEvents::PUBLISH_ENTITIES);
      $entities = $event->getDependencies();
    }

    return $this->serializer->serializeEntities(...array_values($entities));
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
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::DELETE_REMOTE_ENTITY);
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
    $webhook_uuid = $settings->getWebhook('uuid') ?? '';
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
