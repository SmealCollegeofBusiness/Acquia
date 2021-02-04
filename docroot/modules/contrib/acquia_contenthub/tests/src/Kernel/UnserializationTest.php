<?php

namespace Drupal\Tests\acquia_contenthub\Kernel;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFDocument;
use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker\ContentHubImportQueueWorker;
use Drupal\acquia_contenthub_subscriber\SubscriberTracker;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\acquia_contenthub\Kernel\Stubs\DrupalVersion;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * Tests that entities are properly unserialized.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel
 */
class UnserializationTest extends EntityKernelTestBase {

  use DrupalVersion;

  /**
   * Sample View Mode UUID.
   */
  const CLIENT_UUID_1 = 'fefd7eda-4244-4fe4-b9b5-b15b89c61aa8';

  /**
   * Sample Taxonomy Term UUID.
   */
  const CLIENT_UUID_2 = 'de9606dc-56fa-4b09-bcb1-988533edc814';

  /**
   * Sample Vocabulary UUID.
   */
  const CLIENT_UUID_3 = '22dc8835-7b14-4b08-b25d-eae99e1d4d91';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'file',
    'node',
    'field',
    'taxonomy',
    'depcalc',
    'acquia_contenthub',
    'acquia_contenthub_subscriber',
  ];

  /**
   * Queue worker instance.
   *
   * @var \Drupal\acquia_contenthub_subscriber\Plugin\QueueWorker\ContentHubImportQueueWorker
   */
  protected $contentHubImportQueueWorker;

  /**
   * Client instance.
   *
   * @var \Acquia\ContentHubClient\ContentHubClient|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $contentHubClient;

  /**
   * Client settings.
   *
   * @var \Acquia\ContentHubClient\Settings
   */
  protected $settings;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('acquia_contenthub_subscriber', ['acquia_contenthub_subscriber_import_tracking']);

    $this->contentHubClient = $this->prophesize(ContentHubClient::class);
    $this->settings = $this->prophesize(Settings::class);
    $this->settings->getWebhook('uuid')->willReturn('foo');
    $this->settings->getName()->willReturn('foo');
    $this->settings->getUuid()->willReturn(self::CLIENT_UUID_1);

    $client_factory_mock = $this->prophesize(ClientFactory::class);
    $client_factory_mock->getClient()->willReturn($this->contentHubClient);
    $client_factory_mock->getSettings()->willReturn($this->settings->reveal());
    $this->container->set('acquia_contenthub.client.factory', $client_factory_mock->reveal());
    $subscriber_tracker_mock = $this->prophesize(SubscriberTracker::class);
    $this->container->set('acquia_contenthub_subscriber.tracker', $subscriber_tracker_mock->reveal());
    $logger_channel_mock = $this
      ->prophesize(LoggerChannelInterface::class);
    $this->container->set('acquia_contenthub.logger_channel', $logger_channel_mock->reveal());
    $common = $this->getMockBuilder(ContentHubCommonActions::class)
      ->setConstructorArgs([
        $this->container->get('event_dispatcher'),
        $this->container->get('entity.cdf.serializer'),
        $this->container->get('entity.dependency.calculator'),
        $this->container->get('acquia_contenthub.client.factory'),
      ])
      ->setMethods(['getUpdateDbStatus'])
      ->getMock();
    $this->container->set('acquia_contenthub_common_actions', $common);

    $this->contentHubImportQueueWorker = $this->getMockBuilder(ContentHubImportQueueWorker::class)
      ->setConstructorArgs([
        $this->container->get('event_dispatcher'),
        $this->container->get('acquia_contenthub_common_actions'),
        $this->container->get('acquia_contenthub.client.factory'),
        $this->container->get('acquia_contenthub_subscriber.tracker'),
        $this->container->get('acquia_contenthub.logger_channel'),
        [],
        NULL,
        NULL,
      ])
      ->setMethods(NULL)
      ->getMock();
  }

  /**
   * Tests configuration entity unserialization.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \ReflectionException
   */
  public function testConfigEntityUnserialization() {
    $cdf_document = $this->createCDFDocumentFromFixture('view_modes.json');

    $this->contentHubClient->getEntities([self::CLIENT_UUID_1 => self::CLIENT_UUID_1])->willReturn($cdf_document);
    $this->contentHubClient->getInterestsByWebhook(Argument::type('string'))->willReturn([self::CLIENT_UUID_1]);

    $this->initializeContentHubClientExpectation($cdf_document);
    $this->contentHubClient->addEntitiesToInterestList("foo", [self::CLIENT_UUID_1])->willReturn(new Response());

    $item = new \stdClass();
    $item->uuids = implode(', ', [self::CLIENT_UUID_1]);
    $this->contentHubImportQueueWorker->processItem($item);
    $view_mode = EntityViewMode::load('node.teaser');

    $this->assertNotEmpty($view_mode->id());
  }

  /**
   * Tests content entity unserialization.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \ReflectionException
   *
   * @see _acquia_contenthub_publisher_enqueue_entity()
   */
  public function testTaxonomyTermUnserialization() {
    $cdf_document = $this->createCDFDocumentFromFixture('taxonomy.json');

    $this->contentHubClient->getEntities([self::CLIENT_UUID_2 => self::CLIENT_UUID_2])->willReturn($cdf_document);
    $this->contentHubClient->getInterestsByWebhook(Argument::type('string'))->willReturn([self::CLIENT_UUID_2]);

    $this->initializeContentHubClientExpectation($cdf_document);
    $this->contentHubClient->addEntitiesToInterestList("foo", [self::CLIENT_UUID_3, self::CLIENT_UUID_2])->willReturn(new Response());

    $item = new \stdClass();
    $item->uuids = implode(', ', [self::CLIENT_UUID_2]);
    $this->contentHubImportQueueWorker->processItem($item);

    // Checks that vocabulary has been imported.
    $vocabulary = Vocabulary::load('tags');
    $this->assertNotEmpty($vocabulary->id());
    $this->assertEquals('Tags', $vocabulary->label());

    // Checks that taxonomy has been imported.
    /** @var \Drupal\taxonomy\Entity\Term[] $taxonomy_terms */
    $taxonomy_terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => 'tag1']);
    $this->assertNotEmpty($taxonomy_terms);

    $taxonomy_term = current($taxonomy_terms);
    $this->assertNotEmpty($taxonomy_term->id());
  }

  /**
   * Creates CDF document from fixture.
   *
   * @param string $fixture_filename
   *   Fixture file name.
   *
   * @return \Acquia\ContentHubClient\CDFDocument
   *   CDF document.
   *
   * @throws \ReflectionException
   */
  protected function createCdfDocumentFromFixture($fixture_filename): CDFDocument {
    $version_directory = $this->getDrupalVersion();
    $path_to_fixture = sprintf("%s/tests/fixtures/import/$version_directory/%s",
      drupal_get_path('module', 'acquia_contenthub'),
      $fixture_filename
    );
    $json = file_get_contents($path_to_fixture);
    $data = Json::decode($json);
    $document_parts = [];
    foreach ($data['entities'] as $entity) {
      $document_parts[] = $this->populateCdfObject($entity);
    }

    $cdf_document = new CDFDocument(...$document_parts);

    return $cdf_document;
  }

  /**
   * Populates CDF object from array.
   *
   * @param array $entity
   *   Entity.
   *
   * @return \Acquia\ContentHubClient\CDF\CDFObject
   *   Populated CDF object.
   *
   * @throws \Exception
   * @throws \ReflectionException
   *
   * @see \Acquia\ContentHubClient\ContentHubClient::getEntities()
   */
  protected function populateCdfObject(array $entity): CDFObject {
    $object = new CDFObject($entity['type'], $entity['uuid'], $entity['created'], $entity['modified'], $entity['origin'], $entity['metadata']);

    foreach ($entity['attributes'] as $attribute_name => $values) {
      // Refactor ClientHub.php: get rid of duplicated code blocks.
      if (!$attribute = $object->getAttribute($attribute_name)) {
        $class = !empty($object->getMetadata()['attributes'][$attribute_name]) ? $object->getMetadata()['attributes'][$attribute_name]['class'] : FALSE;
        if ($class && class_exists($class)) {
          $object->addAttribute($attribute_name, $values['type'], NULL, 'und', $class);
        }
        else {
          $object->addAttribute($attribute_name, $values['type'], NULL);
        }
        $attribute = $object->getAttribute($attribute_name);
      }

      $value_property = (new \ReflectionClass($attribute))->getProperty('value');
      $value_property->setAccessible(TRUE);
      $value_property->setValue($attribute, $values['value']);
    }

    return $object;
  }

  /**
   * Client expectation.
   *
   * @param \Acquia\ContentHubClient\CDFDocument $cdf_document
   *   CDF Document for client.
   *
   * @throws \Exception
   */
  private function initializeContentHubClientExpectation(CDFDocument $cdf_document): void {
    $this->contentHubClient->getSettings()->willReturn($this->settings);
    $cdf_entity = current($cdf_document->getEntities());
    $this->contentHubClient->getEntity(self::CLIENT_UUID_1)->willReturn($cdf_entity);
    $this->contentHubClient->putEntities($cdf_entity)->willReturn(NULL);
  }

}
