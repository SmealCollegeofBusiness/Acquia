<?php

namespace Drupal\Tests\acquia_contenthub_dashboard\Kernel;

use Drupal\acquia_contenthub_dashboard\Access\ContentHubDashboardAccess;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests Acquia ContentHub Dashboard Access check.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub_dashboard\Kernel
 *
 * @covers \Drupal\acquia_contenthub_dashboard\Access\ContentHubDashboardAccess::access
 */
class ContentHubDashboardAccessTest extends KernelTestBase {

  use UserCreationTrait;
  use AcquiaContentHubAdminSettingsTrait;

  /**
   * Authorized user.
   *
   * @var \Drupal\User\UserInterface
   */
  protected $authorizedUser;

  /**
   * Admin user.
   *
   * @var \Drupal\User\UserInterface
   */
  protected $adminUser;

  /**
   * Authorized user.
   *
   * @var \Drupal\User\UserInterface
   */
  protected $unauthorizedUser;

  /**
   * Client Factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'depcalc',
    'system',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'acquia_contenthub_dashboard',
    'acquia_contenthub_server_test',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installSchema('acquia_contenthub_publisher', 'acquia_contenthub_publisher_export_tracking');
    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');

    $this->createAcquiaContentHubAdminSettings();
    $this->clientFactory = $this->container->get('acquia_contenthub.client.factory');

    $this->authorizedUser = $this->createUser(['administer ach dashboard'], 'regular_user');
    $this->adminUser = $this->createUser([], 'admin_user', TRUE);
    $this->unauthorizedUser = $this->createUser([], 'regular_user2');
  }

  /**
   * Tests Acquia ContentHub Dashboard Access Check.
   */
  public function testContentHubDashboardAccessCheck() {
    $access_check_object = new ContentHubDashboardAccess($this->prophesize(LoggerChannelFactoryInterface::class)->reveal(), $this->clientFactory);
    $authorized_access_result = $access_check_object->access($this->authorizedUser);
    $this->assertInstanceOf(AccessResultAllowed::class, $authorized_access_result);

    $unauthorized_access_result = $access_check_object->access($this->unauthorizedUser);
    $this->assertInstanceOf(AccessResultForbidden::class, $unauthorized_access_result);

    $admin_access_result = $access_check_object->access(($this->adminUser));
    $this->assertInstanceOf(AccessResultAllowed::class, $admin_access_result);
  }

}
