<?php

namespace Drupal\Tests\acquia_contenthub_translations\Unit;

use Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistrar;
use Drupal\Core\Config\Config;
use Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests UndesiredLanguageRegistrar.
 *
 * @group acquia_contenthub_translations
 *
 * @package Drupal\Tests\acquia_contenthub_translations\Unit
 *
 * @coversDefaultClass \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistrar
 */
class UndesiredLanguageRegistrarTest extends UnitTestCase {

  /**
   * Undesired language registrar.
   *
   * @var \Drupal\acquia_contenthub_translations\UndesiredLanguageRegistry\UndesiredLanguageRegistryInterface
   */
  protected $registrar;

  /**
   * Content Hub translations config.
   *
   * @var \Drupal\Core\Config\Config|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $config;

  /**
   * Undesired language list mock.
   *
   * @var array
   */
  public static $undesiredList = [];

  /**
   * Logger mock.
   *
   * @var \Drupal\Tests\acquia_contenthub\Unit\Helpers\LoggerMock
   */
  protected $logger;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config = $this->prophesize(Config::class);
    self::$undesiredList = [];
    $this->mockConfig();
    $this->config->set('undesired_languages', Argument::type('array'))->will(function ($arguments) {
      $langcodes = $arguments[1];
      UndesiredLanguageRegistrarTest::$undesiredList = $langcodes;
      return $this;
    });
    $this->logger = new LoggerMock();
    $this->config->save()->willReturn();
    $this->registrar = new UndesiredLanguageRegistrar($this->config->reveal(), $this->logger);
  }

  /**
   * Tests languages are getting marked as undesired.
   *
   * @covers ::markLanguagesUndesired
   */
  public function testMarkLanguagesUndesired(): void {
    $languages = ['en', 'es'];
    $existing_languages = $this->registrar->getUndesiredLanguages();
    $this->assertEmpty($existing_languages);
    $this->registrar->markLanguagesUndesired(...$languages);
    $this->mockConfig();
    $updated_languages = $this->registrar->getUndesiredLanguages();
    $this->assertEqualsCanonicalizing($languages, $updated_languages);
    // Add one more language.
    $this->registrar->markLanguagesUndesired('fr');
    $this->mockConfig();
    $updated_languages = $this->registrar->getUndesiredLanguages();
    $this->assertEqualsCanonicalizing(array_merge($languages, ['fr']), $updated_languages);
  }

  /**
   * @covers ::getUndesiredLanguages
   */
  public function testGetUndesiredLanguages(): void {
    $this->assertEmpty($this->registrar->getUndesiredLanguages());
    // Add fr to undesired list.
    $this->registrar->markLanguagesUndesired('fr');
    $this->mockConfig();
    $this->assertEqualsCanonicalizing(['fr'], $this->registrar->getUndesiredLanguages());
  }

  /**
   * @covers ::isLanguageUndesired
   */
  public function testIsLanguageUndesired(): void {
    $this->assertFalse($this->registrar->isLanguageUndesired('fr'));
    // Add fr to undesired list.
    $this->registrar->markLanguagesUndesired('fr');
    $this->mockConfig();
    $this->assertTrue($this->registrar->isLanguageUndesired('fr'));
    $this->assertFalse($this->registrar->isLanguageUndesired('en'));
  }

  /**
   * @covers ::removeLanguageFromUndesired
   */
  public function testRemoveLanguageFromUndesired(): void {
    $languages = ['fr', 'es', 'br', 'pt', 'zh'];
    $this->registrar->markLanguagesUndesired(...$languages);
    $this->mockConfig();
    $this->assertEqualsCanonicalizing($languages, $this->registrar->getUndesiredLanguages());
    $this->registrar->removeLanguageFromUndesired('es', 'pt');
    $log_message = 'Language(s) (%s) have been removed from undesired languages.';
    $this->assertContains(
      sprintf(
        $log_message,
        implode(', ', ['es', 'pt'])
      ),
      $this->logger->getInfoMessages()
    );
    $this->mockConfig();
    $this->assertEqualsCanonicalizing(['fr', 'br', 'zh'], $this->registrar->getUndesiredLanguages());
    $this->registrar->removeLanguageFromUndesired('br');
    $this->assertContains(
      sprintf(
        $log_message,
        implode(', ', ['br'])
      ),
      $this->logger->getInfoMessages()
    );
    $this->mockConfig();
    $this->assertEqualsCanonicalizing(['fr', 'zh'], $this->registrar->getUndesiredLanguages());
    // Hi is not undesired language so will not appear up in log message.
    $this->registrar->removeLanguageFromUndesired('fr', 'hi');
    $this->assertContains(
      sprintf(
        $log_message,
        implode(', ', ['fr'])
      ),
      $this->logger->getInfoMessages()
    );
    $this->mockConfig();
    $this->assertEqualsCanonicalizing(['zh'], $this->registrar->getUndesiredLanguages());
  }

  /**
   * Mocks config get method every time list is updated.
   */
  protected function mockConfig(): void {
    $this->config->get('undesired_languages')->willReturn(self::$undesiredList);
  }

}
