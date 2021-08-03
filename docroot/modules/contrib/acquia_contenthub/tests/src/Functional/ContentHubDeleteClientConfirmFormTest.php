<?php

namespace Drupal\Tests\acquia_contenthub\Functional;

use Drupal\acquia_contenthub_test\MockDataProvider;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Content Hub Delete Client confirmation form.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\Form\ContentHubDeleteClientConfirmForm
 *
 * @group acquia_contenthub
 */
class ContentHubDeleteClientConfirmFormTest extends BrowserTestBase {

  /**
   * User that has administer permission.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $authorizedUser;

  /**
   * Anonymous user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $unauthorizedUser;

  /**
   * Path to Content Hub delete client confirmation form.
   */
  const CH_DELETE_CLIENT_CONFIRM_FORM_PATH = '/admin/config/services/acquia-contenthub/delete-client-confirm';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_test',
    'acquia_contenthub_server_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->authorizedUser = $this->drupalCreateUser([
      'administer acquia content hub',
    ]);

    $this->unauthorizedUser = $this->drupalCreateUser();
    $this->drupalLogin($this->authorizedUser);

    $settings = [
      'hostname' => MockDataProvider::VALID_HOSTNAME,
      'api_key' => MockDataProvider::VALID_API_KEY,
      'secret_key' => MockDataProvider::VALID_SECRET,
      'client_name' => MockDataProvider::VALID_CLIENT_NAME,
      'webhook' => 'http://invalid-url.com',
    ];

    // Successful attempt to register client, but webhook url is unreachable.
    $this->drupalPostForm('/admin/config/services/acquia-contenthub', $settings, 'Register Site');
  }

  /**
   * Tests permissions of different users.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testContentHubDeleteClientConfirmFormPagePermissions() {
    $session = $this->assertSession();

    $this->drupalGet(self::CH_DELETE_CLIENT_CONFIRM_FORM_PATH);
    $session->pageTextContains('Acquia Content Hub Delete Client Confirmation');
    $session->statusCodeEquals(200);

    $this->drupalLogout();
    $this->drupalLogin($this->unauthorizedUser);

    $this->drupalGet(self::CH_DELETE_CLIENT_CONFIRM_FORM_PATH);
    $session->pageTextContains('Access denied');
    $session->statusCodeEquals(403);
  }

  /**
   * Tests whether form rendered properly.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testContentHubDeleteClientConfirmFormRenderedProperly() {
    $session = $this->assertSession();

    $this->drupalGet(self::CH_DELETE_CLIENT_CONFIRM_FORM_PATH);
    $session->pageTextContains("Everything is in order, safe to proceed");
    $session->buttonExists('Unregister');
    $session->buttonExists('Cancel');
  }

}
