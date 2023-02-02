<?php

namespace Drupal\Tests\acquia_contenthub_dashboard\Kernel\EventSubscriber\HandleWebhook;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Acquia\Hmac\Key;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub_dashboard\EventSubscriber\HandleWebhook\UpdateAllowedOrigins;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests updated allowed origins.
 *
 * @group acquia_contenthub_dashboard
 *
 * @requires module depcalc
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_dashboard\EventSubscriber\HandleWebhook\UpdateAllowedOrigins
 *
 * @package Drupal\Tests\acquia_contenthub_dashboard\Kernel\EventSubscriber\HandleWebhook
 */
class UpdateAllowedOriginsTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'acquia_contenthub',
    'acquia_contenthub_dashboard',
    'depcalc',
  ];

  /**
   * @covers ::onHandleWebhook
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testAllowedOrigins(string $client_uuid, array $payload, bool $is_passed) {
    $key = new Key('id', 'secret');
    $request = Request::createFromGlobals();

    $settings = $this->prophesize(Settings::class);
    $settings
      ->getUuid()
      ->willReturn($client_uuid);
    $client = $this->prophesize(ContentHubClient::class);

    $client
      ->getSettings()
      ->willReturn($settings->reveal());

    $client_cdf = $this->prophesize(ClientCDFObject::class);
    $cdf_attributes = new CDFAttribute('publisher', 'boolean', TRUE);

    $client_cdf
      ->getAttribute('publisher')
      ->willReturn($cdf_attributes);

    $client_cdf
      ->getMetaData()
      ->willReturn([
        'settings' => [
          'webhook' => [
            'settings_url' => 'https://www.example.com',
          ],
        ],
      ]);

    $client
      ->getEntity(Argument::any())
      ->willReturn($client_cdf->reveal());

    $event = new HandleWebhookEvent($request, $payload, $key, $client->reveal());

    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('acquia_contenthub_dashboard.settings');
    $config->set('auto_publisher_discovery', TRUE);
    $config->save();

    $update_allowed_origins = new UpdateAllowedOrigins($config_factory);
    $update_allowed_origins->onHandleWebhook($event);

    $allowed_origins = $config_factory->get('acquia_contenthub_dashboard.settings')->get('allowed_origins');
    if ($is_passed) {
      $this->assertIsArray($allowed_origins);
      $this->assertEquals('https://www.example.com', $allowed_origins[0]);
    }
    else {
      $this->assertNull($allowed_origins);
    }
  }

  /**
   * Data provider for testAllowedOrigins.
   */
  public function dataProvider(): array {
    return [
      [
        '3eb058c5-0de8-4165-58ab-7d6bd02d2bd8',
        [
          'initiator' => '3eb058c5-0de8-4165-58ab-7d6bd02d2bd8',
          'assets' => [
            [
              'type' => 'client',
              'uuid' => '3eb058c5-0de8-4165-58ab-7d6bd02d2bd8',
            ],
          ],
        ],
        FALSE,
      ],
      [
        '00000000-0000-0001-0000-123456789123',
        [
          'crud' => 'update',
          'status' => 'successful',
          'initiator' => '3eb058c5-0de8-4165-58ab-7d6bd02d2bd8',
          'assets' => [
            [
              'type' => 'client',
              'uuid' => '3eb058c5-0de8-4165-58ab-7d6bd02d2bd8',
            ],
          ],
        ],
        TRUE,
      ],
      [
        '00000000-0000-0001-0000-123456789123',
        [
          'crud' => 'update',
          'status' => 'successful',
          'initiator' => '3eb058c5-0de8-4165-58ab-7d6bd02d2bd8',
          'assets' => [
            [
              'type' => 'test_entity',
              'uuid' => '3eb058c5-0de8-4165-58ab-7d6bd02d2bd8',
            ],
          ],
        ],
        FALSE,
      ],
      [
        '',
        [],
        FALSE,
      ],
    ];
  }

}
