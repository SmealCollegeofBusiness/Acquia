<?php

namespace Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CdfAttributesEvent;
use Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\UserDataCdfAttribute;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests the user data cdf attribute.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\CdfAttributes
 *
 * @covers \Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\UserDataCdfAttribute
 */
class UserDataCdfAttributeTest extends UnitTestCase {

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * CDF Object.
   *
   * @var \Acquia\ContentHubClient\CDF\CDFObject
   */
  private $cdf;

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setUp();

    $this->cdf = $this->getMockBuilder(CDFObject::class)
      ->disableOriginalConstructor()
      ->addMethods([])
      ->getMock();

    $this->dispatcher = new EventDispatcher();
    $this->dispatcher->addSubscriber(new UserDataCdfAttribute());
  }

  /**
   * Tests username attribute population.
   *
   * @param array $data
   *   Data.
   *
   * @dataProvider populateUserNameAttributeProvider
   */
  public function testPopulateUserNameAttribute(array $data) {
    /** @var \Drupal\user\UserInterface $entity */
    $entity = $this->getMockBuilder(UserInterface::class)
      ->disableOriginalConstructor()
      ->addMethods([])
      ->getMockForAbstractClass();

    $entity->method('label')->willReturn($data);
    $entity->method('getEntityTypeId')->willReturn('user');
    $entity->method('uuid')->willReturn('3f0b403c-4093-4caa-ba78-37df21125f09');

    $wrapper = new DependentEntityWrapper($entity);

    $event = new CdfAttributesEvent($this->cdf, $entity, $wrapper);
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::POPULATE_CDF_ATTRIBUTES);

    $attribute = $event->getCdf()->getAttribute('username');
    $this->assertEquals(CDFAttribute::TYPE_STRING, $attribute->getType());

    $this->assertEquals($attribute->getValue(), [
      CDFObject::LANGUAGE_UNDETERMINED => $data,
    ]);
  }

  /**
   * Tests is_anonymous attribute population.
   *
   * @param array $data
   *   Data.
   *
   * @dataProvider populateUserNameAttributeProvider
   */
  public function testIsAnonymousAttributePopulation(array $data) {
    /** @var \Drupal\user\UserInterface $entity */
    $entity = $this->getMockBuilder(UserInterface::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMockForAbstractClass();
    $entity->method('label')->willReturn($data);
    $entity->method('getEntityTypeId')->willReturn('user');
    $entity->method('isAnonymous')->willReturn(TRUE);
    $entity->method('uuid')->willReturn('3f0b403c-4093-4caa-ba78-37df21125f09');

    $wrapper = new DependentEntityWrapper($entity);

    $event = new CdfAttributesEvent($this->cdf, $entity, $wrapper);
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::POPULATE_CDF_ATTRIBUTES);

    $attribute = $event->getCdf()->getAttribute('is_anonymous');
    $this->assertEquals(CDFAttribute::TYPE_BOOLEAN, $attribute->getType());

    $this->assertEquals($attribute->getValue(), [
      CDFObject::LANGUAGE_UNDETERMINED => TRUE,
    ]);

    $this->assertNull($event->getCdf()->getAttribute('mail'));
  }

  /**
   * Tests mail attribute population.
   *
   * @param string $user_name
   *   User name.
   * @param string $email
   *   User email.
   *
   * @dataProvider populateMailAttributeProvider
   */
  public function testMailAttributePopulation($user_name, $email) {
    /** @var \Drupal\user\UserInterface $entity */
    $entity = $this->getMockBuilder(UserInterface::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMockForAbstractClass();
    $entity->method('label')->willReturn($user_name);
    $entity->method('getEntityTypeId')->willReturn('user');
    $entity->method('isAnonymous')->willReturn(FALSE);
    $entity->method('getEmail')->willReturn($email);
    $entity->method('uuid')->willReturn('3f0b403c-4093-4caa-ba78-37df21125f09');

    $wrapper = new DependentEntityWrapper($entity);

    $event = new CdfAttributesEvent($this->cdf, $entity, $wrapper);
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::POPULATE_CDF_ATTRIBUTES);

    $attribute = $event->getCdf()->getAttribute('mail');
    $this->assertEquals(CDFAttribute::TYPE_STRING, $attribute->getType());

    $this->assertNull($event->getCdf()->getAttribute('is_anonymous'));

    $this->assertEquals($attribute->getValue(), [
      CDFObject::LANGUAGE_UNDETERMINED => $email,
    ]);
  }

  /**
   * Returns test user names.
   *
   * @return array
   *   Data sets.
   */
  public function populateUserNameAttributeProvider() {
    return [
      [['user name']],
      [['']],
    ];
  }

  /**
   * Returns test user names and emails.
   *
   * @return array
   *   Data sets.
   */
  public function populateMailAttributeProvider() {
    return [
      ['user name', 'example@example.com'],
      ['', 'example@example.com'],
    ];
  }

}
