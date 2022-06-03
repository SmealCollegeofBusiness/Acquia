<?php

namespace Drupal\acquia_contenthub_subscriber;

use Acquia\ContentHubClient\CDFDocument;
use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\EntityCdfSerializer;
use Drupal\acquia_contenthub\Libs\Traits\CommonActionsTrait;
use Drupal\acquia_contenthub_subscriber\Exception\ContentHubImportException;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Responsible for import related operations.
 */
class CdfImporter {

  use CommonActionsTrait;

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
   * The ContentHub client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $factory;

  /**
   * The acquia_contenthub_subscriber logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $channel;

  /**
   * The subscriber tracker.
   *
   * @var \Drupal\acquia_contenthub_subscriber\SubscriberTracker
   */
  protected $tracker;

  /**
   * CdfImporter constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\acquia_contenthub\EntityCdfSerializer $serializer
   *   The entity cdf serializer.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   The ContentHub client factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $channel
   *   The acquia_contenthub_subscriber channel.
   * @param \Drupal\acquia_contenthub_subscriber\SubscriberTracker $tracker
   *   Subscriber tracker.
   */
  public function __construct(
    EventDispatcherInterface $dispatcher,
    EntityCdfSerializer $serializer,
    ClientFactory $factory,
    LoggerChannelInterface $channel,
    SubscriberTracker $tracker
  ) {
    $this->dispatcher = $dispatcher;
    $this->serializer = $serializer;
    $this->factory = $factory;
    $this->channel = $channel;
    $this->tracker = $tracker;
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
  public function importEntities(string ...$uuids) {
    $stack = new DependencyStack();
    $document = $this->getCdfDocument($stack, ...$uuids);
    return $this->importEntityCdfDocument($document, $stack);
  }

  /**
   * Imports a list of entities from a CDFDocument object.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $document
   *   The CDF document representing the entities to import.
   * @param \Drupal\depcalc\DependencyStack|null $stack
   *   Dependency stack.
   *
   * @return \Drupal\depcalc\DependencyStack
   *   The DependencyStack object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function importEntityCdfDocument(CDFDocument $document, ?DependencyStack $stack = NULL): DependencyStack {
    if (is_null($stack)) {
      $stack = new DependencyStack();
    }
    $this->serializer->unserializeEntities($document, $stack);
    return $stack;
  }

  /**
   * Retrieves entities and dependencies by uuid and returns a CDFDocument.
   *
   * @param \Drupal\depcalc\DependencyStack $stack
   *   Dependency stack.
   * @param string ...$uuids
   *   The list of uuids to retrieve.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   The CDFDocument object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\acquia_contenthub_subscriber\Exception\ContentHubImportException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getCdfDocument(DependencyStack $stack, string ...$uuids): CDFDocument {
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
    $stack = $this->updateStackFromSubscriberTracker($document, $stack);
    $missing_dependencies = $this->getMissingDependencies($document, $stack);
    $client = $this->getClient();
    while ($missing_dependencies) {
      $document->mergeDocuments($client->getEntities($missing_dependencies));
      $uuid_list += $missing_dependencies;
      $this->validateDocument($document, $uuid_list);
      $missing_dependencies = $this->getMissingDependencies($document, $stack);
    }
    return $document;
  }

  /**
   * Checks all the dependencies from all cdf objects in the document.
   *
   * Looks up whether a dependency already exists in the import tracking table
   * with same hash. If it does then there is no need to fetch it again just
   * adds it to dependency stack. This relies on accurate CDF dependency data.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $document
   *   Initial cdf document for uuids in the import queue.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   Dependency stack.
   *
   * @return \Drupal\depcalc\DependencyStack
   *   Updated stack with existing entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function updateStackFromSubscriberTracker(CDFDocument $document, DependencyStack $stack): DependencyStack {
    foreach ($document->getEntities() as $cdf) {
      foreach ($cdf->getDependencies() as $uuid => $hash) {
        $stack = $this->addEntityToDependencyStack($uuid, $hash, $stack);
      }
      // Check to add the main queue entities to the stack if already imported
      // with same hash.
      $hash = $cdf->getAttribute('hash')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED] ?? '';
      $stack = $this->addEntityToDependencyStack($cdf->getUuid(), $hash, $stack);
    }
    return $stack;
  }

  /**
   * Adds entity to dependency stack if already imported with same hash.
   *
   * @param string $uuid
   *   Entity uuid from cdf.
   * @param string $hash
   *   Entity hash from cdf.
   * @param \Drupal\depcalc\DependencyStack $stack
   *   Dependency stack.
   *
   * @return \Drupal\depcalc\DependencyStack
   *   Updated dependency stack.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function addEntityToDependencyStack(string $uuid, string $hash, DependencyStack $stack): DependencyStack {
    $entity = $this->tracker->getEntityByRemoteIdAndHash($uuid, $hash);
    if (!$entity) {
      return $stack;
    }
    $wrapper = new DependentEntityWrapper($entity);
    $wrapper->setRemoteUuid($uuid);
    $stack->addDependency($wrapper);
    $this->tracker->setStatusByUuid($uuid, SubscriberTracker::IMPORTED);
    return $stack;
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
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function validateDocument(CDFDocument $document, array $uuids): void {
    $cdf_objects = $document->getEntities();
    $uuids_count = count($uuids);
    $document_count = count($cdf_objects);
    $message = '';
    if ($uuids_count <= $document_count) {
      return;
    }
    $diff_uuids = array_diff($uuids, array_keys($cdf_objects));
    // Entities are sorted by origin.
    $marked_for_republish = [];
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
        $marked_for_republish[$origin][] = [
          'uuid' => $uuid,
          'type' => $type,
          'dependencies' => array_keys($dependencies),
        ];
        $message .= sprintf("The entity (%s, %s) could not be imported because the following dependencies are missing from Content Hub: %s.", $type, $uuid, implode(', ', $vanished_uuids)) . PHP_EOL;
      }
    }
    if (!empty($marked_for_republish)) {
      $this->requestToRepublishEntities($marked_for_republish);
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
   * @param \Drupal\depcalc\DependencyStack $stack
   *   Dependency stack.
   *
   * @return array
   *   The array of missing uuids.
   */
  protected function getMissingDependencies(CDFDocument $document, DependencyStack $stack): array {
    $missing_dependencies = [];
    foreach ($document->getEntities() as $cdf) {
      // @todo add the hash to the CDF so that we can check it here to see if we need to update.
      foreach ($cdf->getDependencies() as $dependency => $hash) {
        // If this dependency is available in stack
        // it means it was already imported with same hash
        // so no need to fetch it again.
        if ($stack->hasDependency($dependency)) {
          continue;
        }
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
   * Request to republish an entity via Webhook.
   *
   * @param array $entities_by_origin
   *   An array of dependency UUIDs.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function requestToRepublishEntities(array $entities_by_origin): void {
    $client = $this->getClient();
    foreach ($entities_by_origin as $origin => $entities) {
      $webhook_url = $this->getWebhookUrlFromClientOrigin($origin);
      if ($webhook_url === '') {
        $message = sprintf('Could not find Webhook URL for origin = "%s". Request to re-export entities could not be made.',
          $origin
        );
        $this->channel->error($message);
        continue;
      }
      $settings = $client->getSettings();

      $payload = [
        'status' => 'successful',
        'crud' => 'republish',
        'initiator' => $settings->getUuid(),
        'entities' => $entities,
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
  }

  /**
   * Obtains the webhook from the registered webhooks, given origin.
   *
   * @param string $origin
   *   The origin of the site.
   *
   * @return string
   *   The webhook URL if it can be obtained otherwise empty string.
   *
   * @throws \Exception
   */
  public function getWebhookUrlFromClientOrigin(string $origin): string {
    $ch_client = $this->getClient();
    $ch_client->cacheRemoteSettings(TRUE);
    $publisher_client = $ch_client->getClientByUuid($origin);
    if (!isset($publisher_client['uuid'], $publisher_client['name'])) {
      $this->channel->error(sprintf('The Publisher site "%s" is not registered properly to Content Hub.', $origin));
      return '';
    }

    $webhooks = $ch_client->getWebHooks();
    foreach ($webhooks as $webhook) {
      if ($webhook->getClientName() === $publisher_client['name']) {
        return $webhook->getUrl();
      }
    }
    return '';
  }

  /**
   * Gets the client or throws a common exception when it's unavailable.
   *
   * @return \Acquia\ContentHubClient\ContentHubClient
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
