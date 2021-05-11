<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent;
use Drupal\Core\Field\FieldItemList;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

abstract class AcquiaContentHubSerializerTestBase extends KernelTestBase {

  /**
   * Entity Bundle name.
   */
  protected const BUNDLE = 'article';

  /**
   * Entity type name.
   */
  protected const ENTITY_TYPE = 'node';

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configFactory;

  /**
   * Node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $entity;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Config object.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'field',
    'filter',
    'depcalc',
    'acquia_contenthub',
    'system',
    'user',
    'node',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setup();

    $this->installEntitySchema('user');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');

    $this->configFactory = $this->container->get('config.factory');
    $this->dispatcher = $this->container->get('event_dispatcher');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->createAcquiaContentHubAdminSettings();
    $this->clientFactory = $this->container->get('acquia_contenthub.client.factory');
  }

  /**
   * Get Acquia Content Hub settings.
   *
   * @return mixed
   *   Acquia Content Hub admin settings.
   */
  public function createAcquiaContentHubAdminSettings() {
    $admin_settings = $this->configFactory
      ->getEditable('acquia_contenthub.admin_settings');

    return $admin_settings
      ->set('client_name', 'test-client')
      ->set('origin', '00000000-0000-0001-0000-123456789123')
      ->set('api_key', 'HqkhciruZhJxg6b844wc')
      ->set('secret_key', 'u8Pk4dTaeBWpRxA9pBvPJfru8BFSenKZi79CBKkk')
      ->set('hostname', 'https://dev-use1.content-hub-dev.acquia.com')
      ->set('shared_secret', '12312321312321')
      ->save();
  }

  /**
   * Create content type.
   *
   * @param string $field_name
   *   Field name.
   * @param string $field_type
   *   Field type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createContentType(string $field_name = '', string $field_type = '') {
    // Create a content type.
    NodeType::create([
      'type' => self::BUNDLE,
      'name' => self::BUNDLE,
    ])->save();

    if ($field_name && $field_type) {
      // Add a field to the content type.
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => self::ENTITY_TYPE,
        'type' => $field_type,
        'cardinality' => 1,
      ])->save();

      FieldConfig::create([
        'entity_type' => self::ENTITY_TYPE,
        'field_name' => $field_name,
        'bundle' => self::BUNDLE,
        'label' => $this->randomMachineName(),
      ])->save();
    }
  }

  /**
   * Create node entity.
   *
   * @param array $values
   *   Additional fields array.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node entity.
   */
  public function createNode(array $values = []): NodeInterface {
    $data = [
      'title' => $this->randomMachineName(),
      'type' => self::BUNDLE,
      'created' => \Drupal::time()->getRequestTime(),
      'changed' => \Drupal::time()->getRequestTime(),
      'uid' => 1,
    ];
    $data = array_merge($data, $values);

    // Create node.
    $entity = Node::create($data);
    $entity->save();

    return $entity;
  }

  /**
   * Get the CDF being created.
   *
   * @param string $field_name
   *   Field name.
   * @param \Drupal\Core\Field\FieldItemList $field
   *   Field items list.
   *
   * @return \Drupal\acquia_contenthub\Event\SerializeCdfEntityFieldEvent
   *   The CDF object.
   */
  public function dispatchSerializeEvent(string $field_name, FieldItemList $field): SerializeCdfEntityFieldEvent {
    $settings = $this->clientFactory->getClient()->getSettings();

    $cdf = new CDFObject('drupal8_content_entity', $this->entity->uuid(), date('c'), date('c'), $settings->getUuid());
    $event = new SerializeCdfEntityFieldEvent($this->entity, $field_name, $field, $cdf);
    $this->dispatcher->dispatch(AcquiaContentHubEvents::SERIALIZE_CONTENT_ENTITY_FIELD, $event);

    // Check propagationStopped property is changed.
    $this->assertTrue($event->isPropagationStopped());

    return $event;
  }

  /**
   * {@inheritDoc}
   */
  public function tearDown(): void {
    // Delete the previously created node.
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::BUNDLE,
    ]);
    foreach ($nodes as $node) {
      $node->delete();
    }

    parent::tearDown();
  }

}
