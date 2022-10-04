<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CreateCdfObject;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\Event\CreateCdfEntityEvent;
use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The Content entity CDF creator.
 *
 * @see \Drupal\acquia_contenthub\Event\CreateCdfEntityEvent
 */
class ContentEntityCreateCdfHandler implements EventSubscriberInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The client factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * ContentEntity constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $factory
   *   The client factory.
   */
  public function __construct(
    EventDispatcherInterface $dispatcher,
    ClientFactory $factory
  ) {
    $this->dispatcher = $dispatcher;
    $this->clientFactory = $factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::CREATE_CDF_OBJECT][] = ['onCreateCdf', 100];
    return $events;
  }

  /**
   * Creates a new CDF representation of Content Entities.
   *
   * @param \Drupal\acquia_contenthub\Event\CreateCdfEntityEvent $event
   *   Event.
   *
   * @throws \Exception
   */
  public function onCreateCdf(CreateCdfEntityEvent $event) {
    $entity = $event->getEntity();
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $settings = $this->clientFactory->getSettings();
    $cdf = $this->prepareCdf($entity, $settings->getUuid(), $event->getDependencies());

    $fields = $this->serializeEligibleFields($entity, $cdf);
    $metadata = $cdf->getMetadata();
    $metadata['data'] = $this->encodeMetadataContent($fields);
    $cdf->setMetadata($metadata);

    $cdf = $this->dispatchSerializeAdditionalMetadataEvent($entity, $cdf);
    $event->addCdf($cdf);
  }

  /**
   * Creates new CDF object and extends it with metadata.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity in question.
   * @param string $uuid
   *   The client / origin uuid.
   * @param array $dependencies
   *   Dependencies of the entity.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject
   *   The parameterized entity CDF.
   */
  protected function prepareCdf(ContentEntityInterface $entity, string $uuid, array $dependencies = []): CDFObject {
    $created = $this->getCreatedTime($entity);
    $modified = $this->getModifiedTime($entity);

    $cdf = new CDFObject('drupal8_content_entity', $entity->uuid(), $created, $modified, $uuid);
    $metadata = [
      'default_language' => $entity->language()->getId(),
    ];
    if ($dependencies) {
      $metadata['dependencies'] = $dependencies;
    }
    $cdf->setMetadata($metadata);
    return $cdf;
  }

  /**
   * Returns the created time of the entity if it implements ::getCreatedTime.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity in question.
   *
   * @return string
   *   The formatted date.
   */
  protected function getCreatedTime(ContentEntityInterface $entity): string {
    if (!method_exists($entity, 'getCreatedTime')) {
      return date('c');
    }

    $created = $entity->getCreatedTime();
    if (!is_int($created)) {
      return date('c');
    }

    return date('c', $created);
  }

  /**
   * Returns the modified time of the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity in question.
   *
   * @return string
   *   The formatted date.
   */
  protected function getModifiedTime(ContentEntityInterface $entity): string {
    return !$entity instanceof EntityChangedInterface
      ? date('c')
      : date('c', $entity->getChangedTime());
  }

  /**
   * Serializes non-excluded fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity in question.
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The cdf of the content entity.
   *
   * @return array
   *   The serialized fields of the entity.
   */
  protected function serializeEligibleFields(ContentEntityInterface $entity, CDFObject $cdf): array {
    $fields = [];
    foreach ($entity as $field_name => $field) {
      $exclude_field_event = new ExcludeEntityFieldEvent($entity, $field_name, $field);
      $this->dispatcher->dispatch($exclude_field_event, AcquiaContentHubEvents::EXCLUDE_CONTENT_ENTITY_FIELD);
      if ($exclude_field_event->isExcluded()) {
        continue;
      }

      $field_event = new SerializeCdfEntityFieldEvent($entity, $field_name, $field, $cdf);
      $this->dispatcher->dispatch($field_event, AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD);

      $fields[$field_name] = $field_event->getFieldData();
    }
    return $fields;
  }

  /**
   * Encodes provided data in base64 format.
   *
   * @param array $data
   *   The data to encode.
   *
   * @return string
   *   Encoded data.
   */
  public function encodeMetadataContent(array $data): string {
    return base64_encode(json_encode($data));
  }

  /**
   * Dispatches an event for final alteration.
   *
   * Enables extension of the CDF.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity passed to the event.
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The CDF to alter.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject
   *   The altered CDF.
   */
  protected function dispatchSerializeAdditionalMetadataEvent(ContentEntityInterface $entity, CDFObject $cdf): CDFObject {
    $event = new SerializeAdditionalMetadataEvent($entity, $cdf);
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::SERIALIZE_ADDITIONAL_METADATA);
    return $event->getCdf();
  }

}
