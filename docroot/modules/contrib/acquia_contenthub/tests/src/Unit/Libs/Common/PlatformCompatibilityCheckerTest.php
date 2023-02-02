<?php

namespace Drupal\Tests\acquia_contenthub\Unit\Libs;

use Acquia\ContentHubClient\ContentHubClient;
use Acquia\ContentHubClient\Settings;
use Drupal\acquia_contenthub\Exception\PlatformIncompatibilityException;
use Drupal\acquia_contenthub\Libs\Common\PlatformCompatibilityChecker;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\acquia_contenthub\Libs\Common\PlatformCompatibilityChecker
 *
 * @group acquia_contenthub
 */
class PlatformCompatibilityCheckerTest extends UnitTestCase {

  /**
   * SUT object.
   *
   * @var \Drupal\acquia_contenthub\Libs\Common\PlatformCompatibilityChecker
   */
  protected $checker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->checker = new PlatformCompatibilityChecker(
      $this->prophesize(MessengerInterface::class)->reveal(),
      $this->prophesize(LoggerChannelInterface::class)->reveal(),
    );
  }

  /**
   * @covers ::intercept
   */
  public function testInterceptWhenAccountIsFeatured(): void {
    $client = $this->prophesize(ContentHubClient::class);
    $client->isFeatured()->willReturn(TRUE);

    $expected = $this->checker->intercept($client->reveal());
    $this->assertSame($expected, $client->reveal());
  }

  /**
   * @covers ::intercept
   */
  public function testInterceptWhenAccountIsNotFeatured(): void {
    $client = $this->prophesize(ContentHubClient::class);
    $client->isFeatured()->willReturn(FALSE);

    $expected = $this->checker->intercept($client->reveal());
    $this->assertSame($expected, NULL);
  }

  /**
   * @covers ::interceptAndDelete
   */
  public function testInterceptAndDeleteWhenAccountIsNotFeatured(): void {
    $settings = $this->prophesize(Settings::class);
    $settings->getUuid()->willReturn('someuuid');

    $client = $this->prophesize(ContentHubClient::class);
    $client->isFeatured()->willReturn(FALSE);

    $client->getSettings()->willReturn($settings->reveal());
    $client->deleteClient(Argument::exact('someuuid'))->willReturn();

    $this->expectException(PlatformIncompatibilityException::class);
    $this->checker->interceptAndDelete($client->reveal());
  }

  /**
   * @covers ::interceptAndDelete
   */
  public function testInterceptAndDeleteWhenAccountIsFeatured(): void {
    $client = $this->prophesize(ContentHubClient::class);
    $client->isFeatured()->willReturn(TRUE);

    $expected = $this->checker->interceptAndDelete($client->reveal());
    $this->assertSame($expected, $client->reveal());
  }

}
