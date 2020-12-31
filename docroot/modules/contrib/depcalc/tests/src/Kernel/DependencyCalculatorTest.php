<?php

namespace Drupal\Tests\depcalc\Kernel;

use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\depcalc\DependencyStack;
use Drupal\depcalc\DependentEntityWrapper;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

/**
 * Class DependencyCalculatorTest.
 *
 * @requires module path_alias
 * @group depcalc
 */
class DependencyCalculatorTest extends KernelTestBase {

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
    'image',
    'system',
    'field',
    'text',
    'file',
  ];

  /**
   * Calculates all the dependencies of a given entity.
   *
   * @var \Drupal\depcalc\DependencyCalculator
   */
  protected $calculator;

  /**
   * The DependentEntityWrapper object.
   *
   * @var \Drupal\depcalc\DependentEntityWrapper
   */
  protected $dependentEntityWrapper;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp() {
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

    $this->calculator = Drupal::service('entity.dependency.calculator');

    // Create test user.
    /** @var \Drupal\Core\Entity\EntityRepository $entity_repository */
    $entity_repository = Drupal::service('entity.repository');
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
   * Tests the calculation of entity dependencies.
   *
   * @param string $entity_type
   *   The entity type.
   * @param array $entities
   *   Entities to create.
   * @param array $expected_entities
   *   The list of expected entities.
   * @param array $expected_modules
   *   The list of expected modules.
   *
   * @throws \Exception
   *
   * @dataProvider entityDependenciesDataProvider
   */
  public function testEntityDependencies(string $entity_type, array $entities, array $expected_entities, array $expected_modules) {
    foreach ($entities as $entity_values) {
      /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
      $entity_type_manager = Drupal::service('entity_type.manager');
      $entity = $entity_type_manager->getStorage($entity_type)->create($entity_values);
      $entity->save();
    }

    // Calculate dependencies for the last entity from the $entities list.
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $actual_entities = $this->getEntityDependencies($entity);
    $this->assertEqual($actual_entities, $expected_entities);
    $actual_modules = $this->getModuleDependencies($entity);
    $this->assertEqual($actual_modules, $expected_modules);
  }

  /**
   * Returns the list of entity dependencies.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The list of UUIDs of dependencies (entities).
   *
   * @throws \Exception
   */
  protected function getEntityDependencies(EntityInterface $entity) {
    $wrapper = $this->getDependentEntityWrapper($entity);

    return array_keys($wrapper->getDependencies());
  }

  /**
   * Returns the list of module dependencies.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return array
   *   The list of UUIDs of entity dependencies.
   *
   * @throws \Exception
   */
  protected function getModuleDependencies(EntityInterface $entity) {
    $wrapper = $this->getDependentEntityWrapper($entity);

    return $wrapper->getModuleDependencies();
  }

  /**
   * Calculate entity dependencies and return the DependentEntityWrapper object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Drupal\depcalc\DependentEntityWrapper
   *   The DependentEntityWrapper object.
   *
   * @throws \Exception
   */
  protected function getDependentEntityWrapper(EntityInterface $entity): DependentEntityWrapper {
    if (isset($this->dependentEntityWrapper)) {
      return $this->dependentEntityWrapper;
    }

    $this->dependentEntityWrapper = new DependentEntityWrapper($entity);
    $stack = new DependencyStack();
    $this->calculator->calculateDependencies($this->dependentEntityWrapper, $stack);

    return $this->dependentEntityWrapper;
  }

  /**
   * Data provider for testEntityDependencies.
   *
   * @return array
   *   Test data sets consisting of entity values and a list of dependencies
   *   respectively.
   */
  public function entityDependenciesDataProvider() {
    return [
      [
        'user',
        [
          [
            'uuid' => '3e346612-34c0-4d83-a6ef-e181fd562281',
            'name' => 'Test user',
          ],
        ],
        [
          '6e452034-9a51-42c4-8c51-eda1be63d048',
          '87932f74-b9c8-496a-829a-e3bf1d7a3610',
          'cd47420e-c98b-467c-b1f7-8154ad56043b',
          '6bb68fe4-cfb0-42ad-a66d-fad0e03fc195',
          'd1c3d486-f14e-4c14-9463-ae5b8675bedb',
          '112f57c0-8edf-47f5-aa63-ba709c417db0',
          '2074a437-8497-4b0e-9cf4-f49e6adf859b',
        ],
        [
          'file',
          'image',
          'user',
        ],
      ],
      [
        'taxonomy_term',
        [
          [
            'name' => 'Test taxonomy term',
            'vid' => 'tags',
          ],
        ],
        ['4bc246fa-fb6e-4e27-922b-d77d89fb8fa5'],
        [],
      ],
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
          '6e452034-9a51-42c4-8c51-eda1be63d048',
          '87932f74-b9c8-496a-829a-e3bf1d7a3610',
          'cd47420e-c98b-467c-b1f7-8154ad56043b',
          '6bb68fe4-cfb0-42ad-a66d-fad0e03fc195',
          'd1c3d486-f14e-4c14-9463-ae5b8675bedb',
          '112f57c0-8edf-47f5-aa63-ba709c417db0',
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
        [
          'file',
          'image',
          'user',
          'comment',
          'text',
          'node',
          'taxonomy',
          'path',
        ],
      ],
      [
        'node',
        [
          [
            'uuid' => '6fbfe3b5-1423-464f-8b97-e172e2294a8f',
            'type' => 'article',
            'title' => 'A test article',
            'field_body' => 'body content',
            'field_tags' => [1],
            'field_image' => 1,
            'uid' => 1,
          ],
          [
            'uuid' => '855a3124-2484-4d2d-9c4a-b83b198ac4f1',
            'type' => 'page',
            'title' => 'A test page with a referenced article',
            'field_body' => 'body content',
            'field_article' => 1,
            'uid' => 1,
          ],
        ],
        [
          '2d666602-74c0-4d83-a6ef-d181fd562291',
          '27202596-169e-4835-b9d4-c51ded9a03b8',
          '6e452034-9a51-42c4-8c51-eda1be63d048',
          '87932f74-b9c8-496a-829a-e3bf1d7a3610',
          'cd47420e-c98b-467c-b1f7-8154ad56043b',
          '6bb68fe4-cfb0-42ad-a66d-fad0e03fc195',
          'd1c3d486-f14e-4c14-9463-ae5b8675bedb',
          '112f57c0-8edf-47f5-aa63-ba709c417db0',
          '2074a437-8497-4b0e-9cf4-f49e6adf859b',
          '6fbfe3b5-1423-464f-8b97-e172e2294a8f',
          'ab09f838-e8f3-4d3e-957c-685c6c82d01f',
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
          '81576c52-c601-41d8-9ee3-4497b2a7921b',
          '922a6725-dffe-43e6-b899-ab868086e828',
          '714e9491-9d31-42d7-8713-a81c6c4911eb',
          '47bdf976-6166-4841-948a-3b1982d95b93',
          '3e07f2cc-44d1-4965-8fd6-b472d6622a6a',
          'a6f9cb4a-4f12-46e8-a4e6-d27214f7f7d3',
        ],
        [
          'file',
          'image',
          'user',
          'comment',
          'text',
          'node',
          'taxonomy',
          'path',
        ],
      ],
    ];
  }

}
