<?php

namespace Drupal\Tests\depcalc\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Test depcalc clear cache mechanism.
 *
 * @group depcalc
 *
 * @package Drupal\Tests\depcalc\Kernel
 */
class ClearCacheTest extends KernelTestBase {

  use DependencyHelperTrait;

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
   * {@inheritdoc}
   */
  protected static $modules = [
    'depcalc_test',
    'depcalc',
    'node',
    'user',
    'taxonomy',
    'comment',
    'block_content',
    'path',
    'path_alias',
    'image',
    'system',
    'field',
    'text',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    // Change container to database cache backends.
    $container
      ->register('cache_factory', 'Drupal\Core\Cache\CacheFactory')
      ->addArgument(new Reference('settings'))
      ->addMethodCall('setContainer', [new Reference('service_container')]);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('file', ['file_usage']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installConfig('depcalc_test');

    $this->calculator = $this->container->get('entity.dependency.calculator');
    $this->depcalcCache = $this->container->get('cache.depcalc');
    $this->database = $this->container->get('database');

    // Create test user.
    /** @var \Drupal\Core\Entity\EntityRepository $entity_repository */
    $entity_repository = $this->container->get('entity.repository');
    $admin_role = $entity_repository->loadEntityByUuid(
      'user_role',
      '27202596-169e-4835-b9d4-c51ded9a03b8');
    $test_user = User::create([
      'name' => 'Admin',
      'roles' => [$admin_role->id()],
      'uuid' => '2d666602-74c0-4d83-a6ef-d181fd562291',
    ]);
    $test_user->save();

    // Create test taxonomy term.
    $test_taxonomy_term = Term::create([
      'name' => 'test-tag',
      'vid' => 'tags',
      'uuid' => 'e0fa273d-a5e4-4d22-81be-ab344fb8acd8',
    ]);
    $test_taxonomy_term->save();

    // Create test image file.
    $test_image_file = File::create([
      'uri' => 'public://test.jpg',
      'uuid' => '4dcb20e3-b3cd-4b09-b157-fb3609b3fc93',
    ]);
    $test_image_file->save();

  }

  /**
   * Tests the depcalc clear cache mechanism.
   *
   * @param string $entity_type
   *   The entity type.
   * @param array $entities
   *   Entities to create.
   * @param array $expected_entities
   *   Entities expected.
   *
   * @throws \Exception
   *
   * @dataProvider entityDataProvider
   */
  public function testClearCache(string $entity_type, array $entities, array $expected_entities): void {
    foreach ($entities as $entity_values) {
      /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
      $entity_type_manager = $this->container->get('entity_type.manager');
      $entity = $entity_type_manager->getStorage($entity_type)->create($entity_values);
      $entity->save();
    }
    // Calculate dependencies for the last entity from the $entities list.
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $actual_entities = $this->getEntityDependencies($entity);
    $this->assertSame($expected_entities, $actual_entities);

    $original_expected = $original_expected_copy = $expected_entities;
    $cache = $this->depcalcCache->getMultiple($expected_entities);
    $this->assertNotEmpty($cache);

    drupal_flush_all_caches();
    $cache = $this->depcalcCache->getMultiple($original_expected);
    $this->assertNotEmpty($cache, '');

    $this->depcalcCache->deleteAllPermanent();
    $cache = $this->database->select('cache_depcalc', 'c')->fields('c')->execute()->fetchAll();
    $this->assertEmpty($cache);
  }

  /**
   * Data provider for testClearCache.
   *
   * @return array
   *   Test data sets consisting of entity values and a list of dependencies
   *   respectively.
   */
  public function entityDataProvider(): array {
    return [
      [
        'node',
        [
          [
            'type' => 'article',
            'title' => 'A test article',
            'field_body' => 'body content',
            'field_tags' => [1],
            'field_image' => 1,
            'uid' => 1,
          ],
        ],
        [
          'ab09f838-e8f3-4d3e-957c-685c6c82d01f',
          '2d666602-74c0-4d83-a6ef-d181fd562291',
          '27202596-169e-4835-b9d4-c51ded9a03b8',
          'd1c3d486-f14e-4c14-9463-ae5b8675bedb',
          '112f57c0-8edf-47f5-aa63-ba709c417db0',
          '87932f74-b9c8-496a-829a-e3bf1d7a3610',
          'cd47420e-c98b-467c-b1f7-8154ad56043b',
          '6bb68fe4-cfb0-42ad-a66d-fad0e03fc195',
          '6e452034-9a51-42c4-8c51-eda1be63d048',
          '2074a437-8497-4b0e-9cf4-f49e6adf859b',
          '4dcb20e3-b3cd-4b09-b157-fb3609b3fc93',
          'e0fa273d-a5e4-4d22-81be-ab344fb8acd8',
          '4bc246fa-fb6e-4e27-922b-d77d89fb8fa5',
          '01684b4a-9019-4d00-b6f4-84e9ee50b9e6',
          'bc0e1d2e-cf32-4f00-84f8-8517ffc4c3a4',
          '86fe9e43-0cc5-4be1-babc-0519d00ae066',
          'ce58eb43-8200-4a7b-9af0-4ed95e1a671a',
          '0523dc92-0970-4ac6-952a-9bf56a7ee7d2',
          '8d659cb4-bcc8-4abd-a5a7-e784bcb85d45',
          '35d4f1ff-1340-4718-8855-7bfd5d138dc1',
          '1cde0bc6-5976-4cb7-b446-1d43a5bd5153',
          'd6b8a332-fae1-4d09-a932-fbbb855389bb',
          '32a5cb90-48d4-456d-a538-2331d848347f',
          '7f542913-3e24-4bbd-aa99-4c88da4f7add',
          '6a1746e0-4b44-45af-bc6a-a3d6941689d7',
          'd73d88cd-8885-4d82-9383-4759243cde50',
          '19cbb474-95e2-4135-963e-fc1b24125675',
          '06f1e299-0d0c-46e2-96f2-71d0311dafe8',
          'a636f196-4692-4cec-90bf-5b843af0232e',
          '73a9d56a-8272-4503-bb40-3734ea323f39',
          'dfff239b-1437-442c-b2e6-9fc2ddb07fe9',
          'cbb1c6b6-002c-4f00-aa2d-910c79033a6e',
          '958a4894-c5af-4867-a2ce-4909e0c60bcf',
        ],
      ],
    ];
  }

}
