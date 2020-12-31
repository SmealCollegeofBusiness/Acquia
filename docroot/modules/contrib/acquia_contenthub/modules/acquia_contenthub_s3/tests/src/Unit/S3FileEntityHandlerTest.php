<?php

namespace Drupal\Tests\acquia_contenthub_s3\Unit;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\acquia_contenthub_s3\EventSubscriber\Cdf\S3FileEntityHandler;
use Drupal\acquia_contenthub_s3\S3FileMapper;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\MethodProphecy;

/**
 * Class S3FileEntityHandlerTest.
 *
 * @group acquia_contenthub_s3
 * @coversDefaultClass \Drupal\acquia_contenthub_s3\EventSubscriber\Cdf\S3FileEntityHandler
 *
 * @package Drupal\Tests\acquia_contenthub_s3\Unit
 */
class S3FileEntityHandlerTest extends UnitTestCase {

  /**
   * The s3 file mapper mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $s3FileMapper;

  /**
   * @covers ::onParseCdf
   */
  public function testOnParseCdfWithValidData() {
    $file = $this->prophesize(FileInterface::class);
    $event = $this->prophesize(ParseCdfEntityEvent::class);
    $event->getEntity()
      ->willReturn($file->reveal())
      ->shouldBeCalledOnce();

    $cdf = $this->prophesize(CDFObject::class);
    $cdf->getAttribute(Argument::type('string'))
      ->willReturn('s3://example.png');
    $event->getCdf()
      ->willReturn($cdf->reveal())
      ->shouldBeCalledOnce();

    $this->assertMapS3FileMethodThat([$this, 'shouldBeCalledOnce'], $event->reveal());
  }

  /**
   * @covers ::onParseCdf
   */
  public function testOnParseCdfWithInvalidEntity() {
    $node = $this->prophesize(NodeInterface::class);
    $event = $this->prophesize(ParseCdfEntityEvent::class);
    $event->getEntity()
      ->willReturn($node->reveal())
      ->shouldBeCalledOnce();
    $event->getCdf()->shouldNotBeCalled();

    $this->assertMapS3FileMethodThat([$this, 'shouldNotBeCalled'], $event->reveal());
  }

  /**
   * @covers ::onParseCdf
   */
  public function testOnParseCdfWithEmptyFileUriAttribute() {
    $file = $this->prophesize(FileInterface::class);
    $event = $this->prophesize(ParseCdfEntityEvent::class);
    $event->getEntity()
      ->willReturn($file->reveal())
      ->shouldBeCalledOnce();

    $cdf = $this->prophesize(CDFObject::class);
    $cdf->getAttribute(Argument::type('string'))
      ->willReturn(NULL);
    $event->getCdf()
      ->willReturn($cdf->reveal())
      ->shouldBeCalledOnce();

    $this->assertMapS3FileMethodThat([$this, 'shouldNotBeCalled'], $event->reveal());
  }

  /**
   * Returns an S3FileEntityHandler object.
   *
   * @param callable $callback
   *   Extend the file mapper prophecy method.
   * @param \Drupal\acquia_contenthub\Event\ParseCdfEntityEvent $event
   *   The applicable event.
   *
   * @throws \Exception
   */
  protected function assertMapS3FileMethodThat(callable $callback, ParseCdfEntityEvent $event): void {
    $file_mapper = $this->prophesize(S3FileMapper::class);
    $method = $file_mapper->mapS3File(
      Argument::type(CDFObject::class),
      Argument::type(FileInterface::class)
    );
    $callback($method);

    (new S3FileEntityHandler($file_mapper->reveal()))->onParseCdf($event);
  }

  /**
   * Callback for shouldBeCalledOnce.
   *
   * @param \Prophecy\Prophecy\MethodProphecy $method
   *   The method to extend.
   */
  protected function shouldBeCalledOnce(MethodProphecy $method): void {
    $method->shouldBeCalledOnce();
  }

  /**
   * Callback for shouldNotBeCalled.
   *
   * @param \Prophecy\Prophecy\MethodProphecy $method
   *   The method to extend.
   */
  protected function shouldNotBeCalled(MethodProphecy $method): void {
    $method->shouldNotBeCalled();
  }

}
