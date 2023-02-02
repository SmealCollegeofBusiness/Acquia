<?php

namespace Drupal\Tests\acquia_contenthub_dashboard\Kernel;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_dashboard\Controller\ContentHubRedirectEntitiesController;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Test ContentHubRedirectEntitiesController class.
 *
 * @group acquia_contenthub
 *
 * @package Drupal\Tests\acquia_contenthub_dashboard\Kernel
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_dashboard\Controller\ContentHubRedirectEntitiesController
 */
class ContentHubRedirectEntitiesControllerTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'acquia_contenthub',
    'depcalc',
    'field',
    'file',
    'filter',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('node');
    $this->installConfig('file');
    $this->installConfig('field');
    $this->installConfig('filter');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    // Create a test node.
    $this->createNode([
      'type' => 'article',
      'uuid' => '8a3d072e-0993-4212-990e-0f3239d452d4',
    ]);

    // Create test image file.
    File::create([
      'uri' => 'public://test.jpg',
      'uuid' => '4dcb20e3-b3cd-4b09-b157-fb3609b3fc93',
    ])->save();
  }

  /**
   * Test method to redirect entity via type and uuid.
   *
   * @param string $type
   *   Entity type.
   * @param string $uuid
   *   Entity UUID.
   * @param string $expected
   *   Expected outcome.
   * @param int $status_code
   *   Status code.
   *
   * @covers ::redirectToEntityEditForm
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testEditEntityRedirect(string $type, string $uuid, string $expected, int $status_code) {
    $client_factory = $this->prophesize(ClientFactory::class);

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $redirect_controller = new ContentHubRedirectEntitiesController($entity_type_manager, $client_factory->reveal());
    $actual = $redirect_controller->redirectToEntityEditForm($type, $uuid);
    if ($status_code === 302) {
      $this->assertInstanceOf(RedirectResponse::class, $actual);
    }
    else {
      $this->assertSame($expected, $actual->getContent());
      $this->assertSame($status_code, $actual->getStatusCode());
    }
  }

  /**
   * Data provider for testEditEntityRedirect method.
   *
   * @return array[]
   *   Data to test.
   */
  public function dataProvider(): array {
    return [
      [
        'test_node_type',
        '4dcb20e3-b3cd-4b09-b157-fb3609b3fc93',
        '{"success":false,"error":{"message":"The \u0027test_node_type\u0027 entity type does not exist.","code":404}}',
        404,
      ],
      [
        'node',
        '4dcb20e3-b3cd-4b09-b157',
        '{"success":false,"error":{"message":"Provided UUID \u00274dcb20e3-b3cd-4b09-b157\u0027 is not a valid UUID.","code":400}}',
        400,
      ],
      [
        'node',
        '4dcb20e3-b3cd-4b09-b157-fb3609b3fc93',
        '{"success":false,"error":{"message":"Provided UUID \u00274dcb20e3-b3cd-4b09-b157-fb3609b3fc93\u0027 for entity type \u0027node\u0027 does not exist.","code":404}}',
        404,
      ],
      [
        'node',
        '8a3d072e-0993-4212-990e-0f3239d452d4',
        '',
        302,
      ],
      [
        'file',
        '4dcb20e3-b3cd-4b09-b157-fb3609b3fc93',
        '{"success":false,"error":{"message":"Edit link template does not exist for this entity.","code":404}}',
        404,
      ],
    ];
  }

}
