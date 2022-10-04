<?php

namespace Drupal\Tests\acquia_contenthub\Unit\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CdfAttributesEvent;
use Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\UserDataCdfAttribute;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
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
   * Entity uuid.
   */
  protected const ENTITY_UUID = '3f0b403c-4093-4caa-ba78-37df21125f09';

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
   * User mock entity.
   *
   * @var \Drupal\user\UserInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setUp();

    $this->cdf = new CDFObject('drupal8_content_entity', self::ENTITY_UUID, date('c'), date('c'), self::ENTITY_UUID, []);
    $this->entity = $this->prophesize(UserInterface::class);

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
  public function testPopulateUserNameAttribute(array $data): void {

    $this->entity->label()->willReturn($data);
    $this->entity->isAnonymous()->willReturn(TRUE);
    $this->mockUserEntity();

    $wrapper = new DependentEntityWrapper($this->entity->reveal());

    $event = new CdfAttributesEvent($this->cdf, $this->entity->reveal(), $wrapper);
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
  public function testIsAnonymousAttributePopulation(array $data): void {
    $this->entity->label()->willReturn($data);
    $this->entity->isAnonymous()->willReturn(TRUE);

    $this->mockUserEntity();

    $wrapper = new DependentEntityWrapper($this->entity->reveal());

    $event = new CdfAttributesEvent($this->cdf, $this->entity->reveal(), $wrapper);
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
  public function testMailAttributePopulation(string $user_name, string $email): void {
    $this->entity->label()->willReturn($user_name);
    $this->entity->isAnonymous()->willReturn(FALSE);
    $this->entity->getEmail()->willReturn($email);
    $this->mockUserEntity();

    $wrapper = new DependentEntityWrapper($this->entity->reveal());

    $event = new CdfAttributesEvent($this->cdf, $this->entity->reveal(), $wrapper);
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
  public function populateUserNameAttributeProvider(): array {
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
  public function populateMailAttributeProvider(): array {
    return [
      ['user name', 'example@example.com'],
      ['', 'example@example.com'],
    ];
  }

  /**
   * Mocks field definition for the entity.
   */
  protected function mockUserEntity(): void {
    $lang = 'en';
    $this->entity->getTranslationLanguages()->willReturn([$lang => $lang]);
    $this->entity->getTranslation($lang)->willReturn($this->entity->reveal());
    $this->entity->getEntityTypeId()->willReturn('user');
    $this->entity->uuid()->willReturn(self::ENTITY_UUID);
    $this->entity->id()->willReturn(1);
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_name = 'body';
    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn('');
    $this->entity->getFieldDefinitions()->willReturn([$field_name => $field_definition->reveal()]);
    $this->entity->get($field_name)->willReturn($field);
  }

}
