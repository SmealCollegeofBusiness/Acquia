<?php

namespace Drupal\Tests\acquia_contenthub\Functional;

use Drupal\Tests\acquia_contenthub\Kernel\Traits\AcquiaContentHubAdminSettingsTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for testing Queue Forms.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub\Functional
 */
abstract class ContentHubQueueFormTestBase extends BrowserTestBase {

  use AcquiaContentHubAdminSettingsTrait;

  /**
   * Anonymous user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $unauthorizedUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_test',
    'acquia_contenthub_server_test',
    'node',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createAcquiaContentHubAdminSettings();
    $authorizedUser = $this->drupalCreateUser([
      'administer acquia content hub',
    ]);
    $this->unauthorizedUser = $this->drupalCreateUser();
    $this->drupalLogin($authorizedUser);

    $this->drupalCreateContentType(['type' => 'test_type']);
  }

  /**
   * Tests whether data purge properly after clicking on the purge button.
   *
   * @param string $form_path
   *   The path to the queue form.
   * @param string $table_name
   *   The tracking table name.
   * @param string $button_label
   *   The button label to assert.
   * @param string $page_title
   *   The page title to assert.
   *
   * @dataProvider queueFormDataProvider
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testQueueFormPurgeData(string $form_path, string $table_name, string $button_label, string $page_title): void {
    $this->drupalCreateNode([
      'title' => 'test title 1',
      'type' => 'test_type',
    ]);

    $tracked_entity = $this->getTrackingTableColByDynamicField(
      $table_name,
      'queued'
    );
    $this->assertNotNull($tracked_entity, 'Queue is not empty.');

    $session = $this->assertSession();
    $this->drupalGet($form_path);
    $session->buttonExists($button_label);
    $session->buttonExists('Purge');
    $this->submitForm([], 'Purge');

    $this->assertSession()->pageTextContains($page_title);
    $tracked = $this->getTrackingTableColByDynamicField(
      $table_name,
      'queued'
    );
    $this->assertNull($tracked, 'The queue is empty.');
  }

  /**
   * Check form access for different users.
   *
   * @param string $form_path
   *   The form path.
   * @param string $page_title
   *   Text contain on page.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function checkFormAccessForUsers(string $form_path, string $page_title): void {
    $session = $this->assertSession();

    $this->drupalGet($form_path);
    $session->pageTextContains($page_title);
    $session->statusCodeEquals(200);

    $this->drupalLogout();
    $this->drupalLogin($this->unauthorizedUser);

    $this->drupalGet($form_path);
    $session->pageTextContains('Access denied');
    $session->statusCodeEquals(403);
  }

  /**
   * Fetch tracking table column for a given field value.
   *
   * @param string $table_name
   *   The table name.
   * @param string $col_value
   *   Column value.
   * @param string $col_name
   *   Column name.
   *
   * @return string|null
   *   The tracking table respective data.
   */
  public function getTrackingTableColByDynamicField(string $table_name, string $col_value, string $col_name = 'status'): ?string {
    $query = \Drupal::database()->select($table_name, 't');
    $query->fields('t', [$col_name]);
    $query->condition($col_name, $col_value);
    $result = $query->execute()->fetchField();
    return $result ? $result : NULL;
  }

  /**
   * Returns the necessary array structure to test purge queue mechanism.
   *
   * @return array
   *   Data returned to testQueueFormPurgeData test.
   */
  abstract public function queueFormDataProvider(): array;

}
