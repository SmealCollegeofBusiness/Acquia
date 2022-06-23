<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Client;

use Drupal\acquia_contenthub\Client\ProjectVersionClient;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests ProjectVersionClient.
 *
 * @group acquia_contenthub
 *
 * @coversDefaultClass \Drupal\acquia_contenthub\Client\ProjectVersionClient
 *
 * @package Drupal\Tests\acquia_contenthub\Unit\Client
 */
class ProjectVersionClientTest extends UnitTestCase {

  /**
   * Logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerChannel;

  /**
   * {@inheritDoc}
   */
  protected function setup(): void {
    parent::setUp();
    $this->loggerChannel = $this->prophesize(LoggerChannelInterface::class)->reveal();
  }

  /**
   * Provides test data to test XML parser.
   *
   * @dataProvider drupalXmlProvider()
   */
  public function testDrupalReleases(string $xml, string $drupal_version, ?string $also_available, ?string $latest_version): void {
    $client = $this->prophesize(Client::class);
    $response = $this->prophesize(ResponseInterface::class);
    $response
      ->getBody()
      ->shouldBeCalled()
      ->willReturn($xml);
    $client
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn($response->reveal());

    $pv_client = new ProjectVersionClient($client->reveal(), $this->loggerChannel);
    $versions = $pv_client->getDrupalReleases($drupal_version);
    if ($also_available) {
      $this->assertEquals($also_available, $versions['also_available']);
    }
    if ($latest_version) {
      $this->assertEquals($latest_version, $versions['latest']);
    }
  }

  /**
   * Tests getContentHubReleases().
   */
  public function testContentHubReleases(): void {
    $client = $this->prophesize(Client::class);
    $response = $this->prophesize(ResponseInterface::class);
    $response
      ->getBody()
      ->shouldBeCalled()
      ->willReturn($this->getAchXml());
    $client
      ->get(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn($response->reveal());

    $pv_client = new ProjectVersionClient($client->reveal(), $this->loggerChannel);
    $versions = $pv_client->getContentHubReleases();
    $this->assertEquals('8.x-2.25', $versions['latest']);
  }

  /**
   * Returns expected xml for ACH project from D.O.
   *
   * @return string
   *   Test xmls for acquia_contenthub project.
   */
  protected function getAchXml(): string {
    return '<?xml version="1.0" encoding="utf-8"?>
            <project xmlns:dc="http://purl.org/dc/elements/1.1/">
                <title>Acquia Content Hub</title>
                <short_name>acquia_contenthub</short_name>
                <releases>
                    <release>
                        <name>acquia_contenthub 8.x-2.25</name>
                        <version>8.x-2.25</version>
                        <security covered="1">Covered by Drupal\'s security advisory policy</security>
                    </release>
                </releases>
            </project>';
  }

  /**
   * Returns expected xml for Drupal project from D.O.
   *
   * @return iterable
   *   Test xmls for drupal project.
   */
  public function drupalXmlProvider(): iterable {
    $data_set_1 = '<?xml version="1.0" encoding="utf-8"?>
            <project xmlns:dc="http://purl.org/dc/elements/1.1/">
                <title>Drupal core</title>
                <short_name>drupal</short_name>
                <releases>
                    <release>
                      <name>drupal 9.2.1</name>
                      <version>9.2.1</version>
                      <security covered="1">Covered by Drupal\'s security advisory policy</security>
                    </release>
                    <release>
                      <name>drupal 8.9.16</name>
                      <version>8.9.16</version>
                      <security covered="1">Covered by Drupal\'s security advisory policy</security>
                    </release>
                </releases>
            </project>';

    $data_set_2 = '<?xml version="1.0" encoding="utf-8"?>
            <project xmlns:dc="http://purl.org/dc/elements/1.1/">
                <title>Drupal core</title>
                <short_name>drupal</short_name>
                <releases>
                    <release>
                      <name>drupal %1$s</name>
                      <version>%1$s</version>
                      <security %2$s>Covered by Drupal\'s security advisory policy</security>
                    </release>
                </releases>
            </project>';

    return [
      [
        $data_set_1, '8', '9.2.1', '8.9.16',
      ],
      [
        $data_set_1, '9', NULL, '9.2.1',
      ],
      [
        // Not covered by the security team, therefore invalid.
        sprintf($data_set_2, '8.9.16', ''), '8', NULL, NULL,
      ],
      [
        sprintf($data_set_2, '8.9.16', ''), '9', NULL, NULL,
      ],
      [
        sprintf($data_set_2, '9.2.1', ''), '9', NULL, NULL,
      ],
      [
        sprintf($data_set_2, '9.2.1', 'covered="1"'), '9', NULL, '9.2.1',
      ],
      [
        sprintf($data_set_2, '9.2.1', ''), '8', '', '',
      ],
    ];
  }

}
