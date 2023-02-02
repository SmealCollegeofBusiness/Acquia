<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDF\ClientCDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\ValidSslAttribute;
use Drupal\acquia_contenthub\SslCertificateFactory;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests that Valid SSL version attribute is added to client CDF.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\CdfAttributes\ValidSslAttribute
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\CdfAttributes
 */
class ValidSslAttributeTest extends UnitTestCase {

  /**
   * Tests ValidSslAttribute event subscriber.
   *
   * Test covers that the attribute gets added or not.
   *
   * @param string $host
   *   Request's hostname.
   * @param string $scheme
   *   Request's scheme.
   * @param bool $expected
   *   SSL expected value.
   * @param bool $is_null_cert
   *   Checks certificate is null.
   * @param bool $exception
   *   Checks exception.
   *
   * @covers ::onBuildClientCdf
   *
   * @dataProvider dataProvider
   *
   * @throws \Exception
   */
  public function testValidSslAttribute(string $host, string $scheme, bool $expected, bool $is_null_cert, bool $exception): void {
    $request = $this->prophesize(Request::class);
    $request
      ->getSchemeAndHttpHost()
      ->willReturn($host);

    $request
      ->getScheme()
      ->willReturn($scheme);

    $request_stack = $this->prophesize(RequestStack::class);
    $request_stack
      ->getCurrentRequest()
      ->willReturn($request->reveal());

    $cdf = ClientCDFObject::create('uuid', ['settings' => ['name' => 'test']]);
    $event = new BuildClientCdfEvent($cdf);

    $ssl_cert = $this->prophesize(SslCertificate::class);
    $ssl_cert
      ->isValid(Argument::any())
      ->willReturn($expected);

    $ssl_cert_factory = $this->prophesize(SslCertificateFactory::class);
    if ($exception) {
      $ssl_cert_factory
        ->getCertByHostname(Argument::any())
        ->willThrow(new \Exception('Ssl cert exception.'));
    }
    else {
      $ssl_cert_factory
        ->getCertByHostname(Argument::any())
        ->willReturn($is_null_cert ? NULL : $ssl_cert->reveal());
    }

    $valid_ssl = new ValidSslAttribute($request_stack->reveal(), $ssl_cert_factory->reveal());
    $valid_ssl->onBuildClientCdf($event);

    $valid_ssl_attribute = $event->getCdf()->getAttribute('valid_ssl');
    $actual_value = $valid_ssl_attribute->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];

    $this->assertNotNull($valid_ssl_attribute);
    $this->assertEquals($actual_value, $expected);
    $this->assertEquals(CDFAttribute::TYPE_BOOLEAN, $valid_ssl_attribute->getType());
  }

  /**
   * Data provider for testValidSslAttribute().
   *
   * @return array
   *   Mock data.
   */
  public function dataProvider(): array {
    return [
      [
        'https://www.example.com',
        'https',
        TRUE,
        FALSE,
        FALSE,
      ],
      [
        'http://www.example.com',
        'http',
        FALSE,
        FALSE,
        FALSE,
      ],
      [
        '',
        'http',
        FALSE,
        FALSE,
        FALSE,
      ],
      [
        'https://www.example.com',
        '',
        FALSE,
        FALSE,
        FALSE,
      ],
      [
        '',
        '',
        FALSE,
        FALSE,
        FALSE,
      ],
      [
        'https://www.example.com',
        'https',
        FALSE,
        TRUE,
        FALSE,
      ],
      [
        'https://www.example.com',
        'https',
        FALSE,
        TRUE,
        TRUE,
      ],
    ];
  }

}
