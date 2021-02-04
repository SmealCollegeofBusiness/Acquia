<?php

namespace Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CdfAttributesEvent;
use Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\HashCdfAttribute;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests hashing of cdf attributes.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\CdfAttributes
 *
 * @covers \Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\HashCdfAttribute
 */
class HashCdfAttributeTest extends UnitTestCase {

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->dispatcher = new EventDispatcher();
    $this->dispatcher->addSubscriber(new HashCdfAttribute());
  }

  /**
   * Tests 'hash' attribute population.
   *
   * @param string $type
   *   Type.
   * @param string $uuid
   *   Uuid.
   * @param string $created
   *   Created date.
   * @param string $modified
   *   Modified date.
   * @param string $origin
   *   Origin.
   * @param array $metadata
   *   Metadata.
   *
   * @dataProvider onPopulateAttributesProvider
   */
  public function testOnPopulateAttributes($type, $uuid, $created, $modified, $origin, array $metadata) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getMockBuilder(ContentEntityInterface::class)
      ->disableOriginalConstructor()
      ->setMethods([])
      ->getMockForAbstractClass();

    /** @var \Acquia\ContentHubClient\CDF\CDFObject $cdf */
    $cdf = new CDFObject($type, $uuid, $created, $modified, $origin, $metadata);

    $event = new CdfAttributesEvent($cdf, $entity);
    $this->dispatcher->dispatch(AcquiaContentHubEvents::POPULATE_CDF_ATTRIBUTES, $event);

    $hash_attribute = $event->getCdf()->getAttribute('hash');

    $this->assertEquals(CDFAttribute::TYPE_STRING, $hash_attribute->getType());

    $expected = [
      CDFObject::LANGUAGE_UNDETERMINED => sha1($cdf->getMetadata()['data']),
    ];
    $this->assertArrayEquals($expected, $hash_attribute->getValue());
  }

  /**
   * Data provider for testOnPopulateAttributes.
   *
   * @return array
   *   Data sets.
   */
  public function onPopulateAttributesProvider() {
    return [
      [
        'drupal8_content_entity',
        '3f0b403c-4093-4caa-ba78-37df21125f09',
        date('c'),
        date('c'),
        '3f0b403c-4093-4caa-ba78-37df21125f09',
        [
          'data' => 'ZW46CiAgdXVpZDogZjc1NzM4OTAtNTRlMi00MmZhLTk2YzQtYTA4ZmZjZTZjOGExCiAgbGFuZ2NvZGU6IGVuCiAgc3RhdHVzOiB0cnVlCiAgZGVwZW5kZW5jaWVzOiB7ICB9CiAgbmFtZTogJ1Rlc3QgQ29udGVudCBUeXBlICMyJwogIHR5cGU6IHRlc3RfY29udGVudF90eXBlMgogIGRlc2NyaXB0aW9uOiBudWxsCiAgaGVscDogbnVsbAogIG5ld19yZXZpc2lvbjogdHJ1ZQogIHByZXZpZXdfbW9kZTogMQogIGRpc3BsYXlfc3VibWl0dGVkOiB0cnVlCg==',
        ],
      ],
      [
        'drupal8_content_entity',
        '3f0b403c-4093-4caa-ba78-37df21125f09',
        date('c'),
        date('c'),
        '3f0b403c-4093-4caa-ba78-37df21125f09',
        [
          'data' => 'eyJ1dWlkIjp7InZhbHVlIjp7ImVuIjp7InZhbHVlIjoiZWQ0ZWQ0NzgtMGI5YS00NmU4LWI0OGMtNmVkM2U2ODEyYjdjIn19fSwidHlwZSI6eyJ2YWx1ZSI6eyJlbiI6IjgyMDhmMjA3LTc1YzUtNDU4Yy04M2Q3LWM0ZWM2ZDE0NjMxYSJ9fSwicmV2aXNpb25fdGltZXN0YW1wIjp7InZhbHVlIjp7ImVuIjp7InZhbHVlIjoiMTU3MTMwOTM1MiJ9fX0sInJldmlzaW9uX3VpZCI6eyJ2YWx1ZSI6eyJlbiI6WyI2NGYwZGQ0My1hY2ZjLTQzNTAtYjc1Yy1kOTNlZTkzZWVhYTgiXX19LCJyZXZpc2lvbl9sb2ciOltdLCJzdGF0dXMiOnsidmFsdWUiOnsiZW4iOiIxIn19LCJ1aWQiOnsidmFsdWUiOnsiZW4iOlsiNjRmMGRkNDMtYWNmYy00MzUwLWI3NWMtZDkzZWU5M2VlYWE4Il19fSwidGl0bGUiOnsidmFsdWUiOnsiZW4iOiJcIm8kQnA2ey4sUWVnTnxGPiYuOitjPVV9S0x0ckYuIn19LCJjcmVhdGVkIjp7InZhbHVlIjp7ImVuIjp7InZhbHVlIjoiMTU3MTMwOTM1MiJ9fX0sImNoYW5nZWQiOnsidmFsdWUiOnsiZW4iOnsidmFsdWUiOiIxNTcxMzA5MzUyIn19fSwicHJvbW90ZSI6eyJ2YWx1ZSI6eyJlbiI6IjEifX0sInN0aWNreSI6eyJ2YWx1ZSI6eyJlbiI6IjAifX0sImRlZmF1bHRfbGFuZ2NvZGUiOnsidmFsdWUiOnsiZW4iOiIxIn19LCJyZXZpc2lvbl9kZWZhdWx0Ijp7InZhbHVlIjp7ImVuIjoiMSJ9fSwicmV2aXNpb25fdHJhbnNsYXRpb25fYWZmZWN0ZWQiOnsidmFsdWUiOnsiZW4iOiIxIn19fQ==',
        ],
      ],
    ];
  }

}
