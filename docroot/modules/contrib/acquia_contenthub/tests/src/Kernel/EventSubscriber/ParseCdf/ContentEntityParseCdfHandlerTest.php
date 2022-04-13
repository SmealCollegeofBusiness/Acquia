<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ParseCdf;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent;
use Drupal\acquia_contenthub\EventSubscriber\ParseCdf\ContentEntityParseCdfHandler;
use Drupal\depcalc\DependencyStack;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AssetHandlerTrait;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\StringFormatterTrait;

/**
 * Tests content entity parse cdf handler.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\ParseCdf\ContentEntityParseCdfHandler
 *
 * @requires module depcalc
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\ParseCdf
 */
class ContentEntityParseCdfHandlerTest extends KernelTestBase {

  use AssetHandlerTrait;
  use StringFormatterTrait;

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
    'language',
    'content_translation',
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
   * @var \Drupal\acquia_contenthub\EventSubscriber\ParseCdf\ContentEntityParseCdfHandler
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
    $this->installEntitySchema('configurable_language');
    $this->installConfig([
      'field',
      'filter',
      'node',
      'language',
    ]);
    $this->dispatcher = $this->container->get('event_dispatcher');
    $this->entityHandler = new ContentEntityParseCdfHandler(
      $this->container->get('event_dispatcher')
    );
    $this->clientFactory = $this->container->get('acquia_contenthub.client.factory');
    ConfigurableLanguage::createFromLangcode('hu')->save();
  }

  /**
   * Tests the OnParseCdf() method.
   */
  public function testOnParseCdf(): void {
    $data = $this->getCdfArray('node_article_onparsecdf.json');
    $cdf = CDFObject::fromArray($data);
    $event = $this->triggerOnParseCdfEvent($cdf);

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getEntity();
    // Entity has just been created, not saved into database yet.
    $this->assertTrue($entity->isNew());

    $this->assertEquals($entity->uuid(), $cdf->getUuid());
    $this->assertEquals($entity->label(), $cdf->getAttribute('label')->getValue()['und']);
    $fields = $this->entityHandler->decodeMetadataContent($cdf->getMetadata()['data']);
    unset($fields['content_translation_source'], $fields['content_translation_outdated']);
    $translations = ['en', 'hu'];
    foreach ($translations as $langcode) {
      $translation = $entity->getTranslation($langcode);
      foreach ($fields as $field_name => $value) {
        $entity_value = $translation->get($field_name)->first()->getValue();
        $entity_value = $entity_value['value'] ?? $entity_value;
        $entity_value = $entity_value['target_id'] ?? $entity_value;
        $value = $this->getFieldValue($value, $langcode);
        $this->assertTrue($entity_value == $value, $this->stringPrintFormat(
          'Field: %s. Actual: %s - Expected: %s',
          $field_name, $entity_value, $value,
        ));
      }
    }
  }

  /**
   * Tests UnserializeAdditionalMetadataEvent.
   */
  public function testOnParseCdfUnserializeAdditionalMetadataEvent(): void {
    $data = $this->getCdfArray('node_article_onparsecdf.json');
    $cdf = CDFObject::fromArray($data);

    // Add additional metadata.
    $this->dispatcher->addListener(
      AcquiaContentHubEvents::UNSERIALIZE_ADDITIONAL_METADATA,
      [$this, 'unserializeAdditionalMetadata']
    );
    $event = $this->triggerOnParseCdfEvent($cdf);
    $entity = $event->getEntity();
    $this->assertTrue($entity->label() === 'Change label to this one');
  }

  /**
   * Tests immutable event.
   */
  public function testOnParseCdfImmutableEvent(): void {
    $data = $this->getCdfArray('node_article_onparsecdf.json');
    $cdf = CDFObject::fromArray($data);

    $event = new ParseCdfEntityEvent($cdf, new DependencyStack());
    $event->setMutable(FALSE);
    $this->entityHandler->onParseCdf($event);
    $this->assertFalse($event->hasEntity(), 'Entity has not been created, the event is not mutable.');
  }

  /**
   * Tests onParseCdf for exceptions.
   */
  public function testOnParseCdfException(): void {
    $data = $this->getCdfArray('node_article_onparsecdf.json', [
      'metadata' => [
        'default_language' => [],
      ],
    ]);

    $cdf = CDFObject::fromArray($data);
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage(sprintf("No language available for entity with UUID %s.", $cdf->getUuid()));

    $event = $this->triggerOnParseCdfEvent($cdf);
    $this->entityHandler->onParseCdf($event);
  }

  /**
   * Unserialize additional metadata on the fly event subscriber.
   *
   * @param \Drupal\acquia_contenthub\Event\UnserializeAdditionalMetadataEvent $event
   *   The event object.
   */
  public function unserializeAdditionalMetadata(UnserializeAdditionalMetadataEvent $event): void {
    $entity = $event->getEntity();
    $entity->set('title', 'Change label to this one');
    $event->setEntity($entity);
  }

  /**
   * Returns field value.
   *
   * @param array $value
   *   The array to fetch the value from.
   * @param string $langcode
   *   The langcode to use to get field value.
   *
   * @return mixed
   *   The fetched value.
   */
  protected function getFieldValue(array $value, string $langcode = 'en') {
    if ($value) {
      $value = $value['value'][$langcode]['value'] ?? $value['value'][$langcode];
      return is_array($value) ? current($value) : $value;
    }
    return '';
  }

  /**
   * Triggers PARSE_CDF event.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   The CDF to pass to the event.
   *
   * @return \Drupal\acquia_contenthub\Event\ParseCdfEntityEvent
   *   The dispatched event object.
   *
   * @throws \Exception
   */
  protected function triggerOnParseCdfEvent(CDFObject $cdf): ParseCdfEntityEvent {
    $event = new ParseCdfEntityEvent($cdf, new DependencyStack());
    $this->entityHandler->onParseCdf($event);
    return $event;
  }

}
