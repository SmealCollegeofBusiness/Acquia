<?php

namespace Drupal\Tests\acquia_contenthub_dashboard\Unit;

use Acquia\ContentHubClient\ContentHubClient;
use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\acquia_contenthub_dashboard\Libs\ContentHubAllowedOrigins;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests ContentHubAllowOrigins.
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_dashboard\Libs\ContentHubAllowedOrigins
 *
 * @group acquia_contenthub_dashboard
 *
 * @package Drupal\Tests\acquia_contenthub_dashboard\Unit
 */
class ContentHubAllowedOriginsTest extends UnitTestCase {

  /**
   * Content Hub Client Factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->clientFactory = $this->prophesize(ClientFactory::class);
  }

  /**
   * Tests with Content Hub Client.
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testGetAllowedOriginsWithClient(...$args): void {

    $client = $this->prophesize(ContentHubClient::class);
    $client
      ->queryEntities(Argument::type('array'))
      ->willReturn(['data' => [$args[0]]]);

    $this->clientFactory
      ->getClient()
      ->willReturn($client->reveal());

    $allowed_origins = new ContentHubAllowedOrigins($this->clientFactory->reveal());
    $output = $allowed_origins->getAllowedOrigins();
    $this->assertIsArray($output);
    if ($args[1]) {
      $this->assertEquals('https://www.example.com', $output[0]);
    }
    else {
      $this->assertEmpty($output);
    }
  }

  /**
   * Tests without Content Hub Client.
   *
   * @throws \Exception
   */
  public function testGetAllowedOriginsWithoutClient(): void {
    $allowed_origins = new ContentHubAllowedOrigins($this->clientFactory->reveal());
    $output = $allowed_origins->getAllowedOrigins();
    $this->assertEmpty($output);
  }

  /**
   * Data provider for testGetAllowedOriginsWithClient.
   */
  public function dataProvider(): array {
    return [
      [
        [
          'attributes' => [],
        ],
        FALSE,
      ],
      [
        [
          'attributes' => [
            'publisher' => [],
          ],
        ],
        FALSE,
      ],
      [
        [
          'attributes' => [
            'publisher' => [
              'und' => FALSE,
            ],
          ],
        ],
        FALSE,
      ],
      [
        [
          'attributes' => [
            'publisher' => [
              'und' => TRUE,
            ],
          ],
          'metadata' => [],
        ],
        FALSE,
      ],
      [
        [
          'attributes' => [
            'publisher' => [
              'und' => TRUE,
            ],
          ],
          'metadata' => [
            'settings' => [],
          ],
        ],
        FALSE,
      ],
      [
        [
          'attributes' => [
            'publisher' => [
              'und' => TRUE,
            ],
          ],
          'metadata' => [
            'settings' => [
              'webhook' => [],
            ],
          ],
        ],
        FALSE,
      ],
      [
        [
          'attributes' => [
            'publisher' => [
              'und' => TRUE,
            ],
          ],
          'metadata' => [
            'settings' => [
              'webhook' => [
                'settings_url' => '',
              ],
            ],
          ],
        ],
        FALSE,
      ],
      [
        [
          'attributes' => [
            'publisher' => [
              'und' => TRUE,
            ],
          ],
          'metadata' => [
            'settings' => [
              'webhook' => [
                'settings_url' => 'https://www.example.com',
              ],
            ],
          ],
        ],
        TRUE,
      ],
    ];
  }

}
