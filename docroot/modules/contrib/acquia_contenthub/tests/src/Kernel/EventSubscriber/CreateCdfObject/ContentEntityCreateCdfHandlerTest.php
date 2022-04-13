<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CreateCdfObject;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CreateCdfEntityEvent;
use Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent;
use Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent;
use Drupal\acquia_contenthub\EventSubscriber\CreateCdfObject\ContentEntityCreateCdfHandler;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AssetHandlerTrait;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\CdfDocumentTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests Content entity create cdf handler.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\CreateCdfObject\ContentEntityCreateCdfHandler
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CreateCdfObject
 */
class ContentEntityCreateCdfHandlerTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use CdfDocumentTestTrait;
  use AssetHandlerTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'depcalc',
    'user',
    'field',
    'filter',
    'node',
    'text',
    'system',
    'path',
  ];

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * ContentEntityHandler object.
   *
   * @var \Drupal\acquia_contenthub\EventSubscriber\CreateCdfObject\ContentEntityCreateCdfHandler
   */
  protected $entityHandler;

  /**
   * Config object.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp() : void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig([
      'field',
      'filter',
      'node',
    ]);
    $this->dispatcher = $this->container->get('event_dispatcher');
    $this->clientFactory = $this->container->get('acquia_contenthub.client.factory');
    $this->entityHandler = new ContentEntityCreateCdfHandler(
      $this->dispatcher,
      $this->clientFactory
    );

  }

  /**
   * Test the OnCreateCdf() method.
   */
  public function testOnCreateCdf(): void {
    $config_entity = $this->prophesize(ConfigEntityInterface::class);
    $event = $this->triggerOnCdfEvent($config_entity->reveal());
    $this->assertSame(count($event->getCdfList()), 0, 'CDF list is empty, only content entities are eligible.');

    $content_type = $this->createContentTypeWithFields('article');
    $entity = $this->createNode(['title' => 'Test title']);
    $deps = $this->generateRandomDependencies(15);
    $event = $this->triggerOnCdfEvent($entity, $deps);
    $cdf = $event->getCdf($entity->uuid());
    $metadata = $cdf->getMetadata();

    $fields_expectation = $this->getCdfArray('node_article_oncdfcreate.json', [
      'uuid' => [
        'value' => [
          'en' => [
            'value' => $entity->uuid(),
          ],
        ],
      ],
      'type' => [
        'value' => [
          'en' => $content_type->uuid(),
        ],
      ],
      'title' => [
        'value' => [
          'en' => 'Test title',
        ],
      ],
      'revision_timestamp' => [
        'value' => [
          'en' => [
            'value' => $entity->getRevisionCreationTime(),
          ],
        ],
      ],
      'created' => [
        'value' => [
          'en' => [
            'value' => $entity->getCreatedTime(),
          ],
        ],
      ],
      'changed' => [
        'value' => [
          'en' => [
            'value' => $entity->getChangedTime(),
          ],
        ],
      ],
      'field_text' => [
        'value' => [
          'en' => [
            [
              'value' => 'Custom test field',
            ],
          ],
        ],
      ],
    ]);

    $this->assertTrue($metadata['dependencies'] === $deps, "Metadata contains the entity's dependencies");
    $this->assertTrue($metadata['default_language'] === 'en', 'Metadata contains the default language');
    $this->assertEquals($cdf->getUuid(), $entity->uuid());

    $field_data = base64_decode($metadata['data']);
    $json = json_encode($fields_expectation);
    $this->assertTrue($field_data === $json,
      "Actual: $field_data - Expected: $json");

    // Remove a field from the cdf.
    $this->dispatcher->addListener(
      AcquiaContentHubEvents::EXCLUDE_CONTENT_ENTITY_FIELD,
      [$this, 'excludeField'],
    );
    $event = $this->triggerOnCdfEvent($entity);
    $cdf = $event->getCdf($entity->uuid());
    $metadata = $cdf->getMetadata();
    unset($fields_expectation['field_text']);

    $field_data = base64_decode($metadata['data']);
    $json = json_encode($fields_expectation);
    $this->assertTrue($field_data === $json,
      "Actual: $field_data - Expected: $json");

    // Add additional metadata.
    $this->dispatcher->addListener(
      AcquiaContentHubEvents::SERIALIZE_ADDITIONAL_METADATA,
      [$this, 'addAdditionalMetadata'],
    );
    $event = $this->triggerOnCdfEvent($entity);
    $cdf = $event->getCdf($entity->uuid());
    $metadata = $cdf->getMetadata();
    $this->assertTrue($metadata['extra_metadata'] === 'some_value', 'Additional metadata added through event subscriber.');
  }

  /**
   * Exclude field on the fly event subscriber.
   *
   * @param \Drupal\acquia_contenthub\Event\ExcludeEntityFieldEvent $event
   *   Exclude entity field event object.
   */
  public function excludeField(ExcludeEntityFieldEvent $event): void {
    if ($event->getFieldName() !== 'field_text') {
      return;
    }
    $event->exclude();
  }

  /**
   * Serialize additional metadata on the fly event subscriber.
   *
   * @param \Drupal\acquia_contenthub\Event\SerializeAdditionalMetadataEvent $event
   *   SerializeAdditionalMetadataEvent event object.
   */
  public function addAdditionalMetadata(SerializeAdditionalMetadataEvent $event): void {
    $cdf = $event->getCdf();
    $metadata = $cdf->getMetadata();
    $metadata['extra_metadata'] = 'some_value';
    $cdf->setMetadata($metadata);
    $event->setCdf($cdf);
  }

  /**
   * Triggers onCreateCdf action.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to test with.
   * @param array $deps
   *   Array of dependencies.
   *
   * @return \Drupal\acquia_contenthub\Event\CreateCdfEntityEvent
   *   Returns modified event object.
   *
   * @throws \Exception
   */
  protected function triggerOnCdfEvent(EntityInterface $entity, array $deps = []): CreateCdfEntityEvent {
    $event = new CreateCdfEntityEvent($entity, $deps);
    $this->entityHandler->onCreateCdf($event);
    return $event;
  }

  /**
   * Creates a new content type.
   *
   * @param string $type
   *   The name of the content type.
   *
   * @return \Drupal\node\Entity\NodeType
   *   The newly created content type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createContentTypeWithFields(string $type): NodeType {
    $content_type = $this->createContentType([
      'type' => $type,
    ]);

    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'type' => 'text',
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_text',
      'bundle' => $type,
      'label' => $this->randomMachineName(),
    ])->save();

    return $content_type;
  }

  /**
   * Creates a node.
   *
   * @param array $values
   *   The values to use for overrides or extension.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createNode(array $values = []): NodeInterface {
    $data = [
      'title' => $this->randomMachineName(),
      'type' => 'article',
      'created' => \Drupal::time()->getRequestTime(),
      'changed' => \Drupal::time()->getRequestTime(),
      'uid' => 1,
      'default_language' => 'en',
      'field_text'  => 'Custom test field',
    ];
    $data = array_merge($data, $values);

    // Create node.
    $entity = Node::create($data);
    $entity->save();

    return $entity;
  }

}
