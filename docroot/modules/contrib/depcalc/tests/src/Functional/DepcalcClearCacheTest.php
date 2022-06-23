<?php

namespace Drupal\Tests\depcalc_ui\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\depcalc\Kernel\DependencyHelperTrait;

/**
 * Tests that the depcalc clear cache works.
 *
 * @group depcalc
 */
class DepcalcClearCacheTest extends BrowserTestBase {

  use DependencyHelperTrait;

  /**
   * User that has administer permission.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $authorizedUser;

  /**
   * User that is $unauthorizedUser.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $unauthorizedUser;

  /**
   * The Depcalc Cache backend.
   *
   * @var \Drupal\depcalc\Cache\DepcalcCacheBackend
   */
  protected $depcalcCache;

  /**
   * The database connection
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity uuid.
   *
   * @var string
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'depcalc',
    'depcalc_ui',
    'node',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->calculator = $this->container->get('entity.dependency.calculator');
    $this->depcalcCache = $this->container->get('cache.depcalc');
    $this->database = $this->container->get('database');

    // Create test node article
    $this->uuid = '2d666602-74c0-4d83-a6ef-d181fd562291';
    $entity_values = [
      'type' => 'article',
      'title' => 'A test article',
      'uuid' => $this->uuid,
    ];
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity = $entity_type_manager->getStorage('node')->create($entity_values);
    $entity->save();

    $this->getEntityDependencies($entity);

    // User to clear caches.
    $this->authorizedUser = $this->drupalCreateUser([], NULL, TRUE);
    $this->unauthorizedUser = $this->drupalCreateUser();
    $this->drupalLogin($this->authorizedUser);
  }

  /**
   * Tests the depcalc clear cache button.
   */
  public function testClearCacheForm(): void {
    $session = $this->assertSession();
    $this->drupalGet('admin/config/development/performance');
    $session->statusCodeEquals(200);

    $cache = $this->depcalcCache->get($this->uuid);
    $this->assertNotEmpty($cache);

    $session->buttonExists('Clear all caches');
    $this->submitForm([], 'Clear all caches');
    $cache = $this->depcalcCache->get($this->uuid);
    $this->assertNotEmpty($cache);

    $session->buttonExists('Clear depcalc cache');
    $this->submitForm([], 'Clear depcalc cache');
    $session->pageTextContains('Cleared all depcalc cache.');
    $cache = $this->database->select('cache_depcalc', 'c')->fields('c')->execute()->fetchAll();
    $this->assertEmpty($cache);

    $this->drupalLogout();
    $this->drupalLogin($this->unauthorizedUser);

    $this->drupalGet('admin/config/development/performance');
    $session->pageTextContains('Access denied');
    $session->statusCodeEquals(403);
  }

}
