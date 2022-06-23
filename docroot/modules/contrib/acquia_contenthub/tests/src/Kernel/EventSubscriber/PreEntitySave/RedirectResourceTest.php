<?php

namespace Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\PreEntitySave;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\PreEntitySaveEvent;
use Drupal\depcalc\DependencyStack;
use Drupal\KernelTests\KernelTestBase;
use Drupal\redirect\Entity\Redirect;

/**
 * Test that redirects are handled correctly in PreEntitySave event.
 *
 * @group acquia_contenthub
 * @coversDefaultClass \Drupal\acquia_contenthub\EventSubscriber\PreEntitySave\RedirectSource
 *
 * @requires module depcalc
 * @requires module redirect
 *
 * @package Drupal\Tests\acquia_contenthub\Kernel\EventSubscriber\PreEntitySave
 */
class RedirectResourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'acquia_contenthub',
    'depcalc',
    'user',
    'redirect',
    'link',
    'path',
    'path_alias',
    'field',
    'system',
  ];

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $dispatcher;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setup(): void {
    parent::setUp();
    $this->installEntitySchema('field_config');
    $this->installEntitySchema('redirect');
    $this->installConfig(['system']);

    $this->dispatcher = $this->container->get('event_dispatcher');
  }

  /**
   * Tests RedirectSource event subscriber.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf
   *   Mock cdf data.
   * @param array $expected_source
   *   Expected value for assert.
   *
   * @dataProvider dataProviderRedirectResource
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRedirectResource(CDFObject $cdf, array $expected_source) {
    $redirect = Redirect::create();
    $redirect->setSource('test');
    $redirect->setLanguage('en');
    $redirect->save();

    $event = new PreEntitySaveEvent($redirect, new DependencyStack(), $cdf);
    $this->dispatcher->dispatch($event, AcquiaContentHubEvents::PRE_ENTITY_SAVE);

    /** @var \Drupal\redirect\Entity\Redirect $entity */
    $entity = $event->getEntity();

    $source = $entity->getSource();
    $this->assertEquals($expected_source, $source);

  }

  /**
   * Provides test data for testRedirectResource.
   *
   * @return array
   *   Test input and expected result.
   */
  public function dataProviderRedirectResource() {
    return [
      [
        new CDFObject(
          'drupal8_content_entity',
          'uuid',
          'date',
          'date',
          'uuid',
          [
            'data' => 'eyJ1dWlkIjp7InZhbHVlIjp7ImVuIjp7InZhbHVlIjoiODE1ZDhkZmEtZDJjMi00ZmE4LTk2M2MtZWI3ZmM5YWJhMThkIn19fSwiaGFzaCI6eyJ2YWx1ZSI6eyJlbiI6IjhxX0xfY2NScmRtdUJJcWdYNTJhemNsczg1bGFIVUc3NEJLOXY4QWNVaFEifX0sInR5cGUiOnsidmFsdWUiOnsiZW4iOiJyZWRpcmVjdCJ9fSwidWlkIjp7InZhbHVlIjp7ImVuIjpbIjJiZTM1MzMyLWYyMmYtNDJiNC1hYmMwLWZkNGU2YzY2YTdjZiJdfX0sInJlZGlyZWN0X3NvdXJjZSI6eyJ2YWx1ZSI6eyJlbiI6eyJwYXRoIjoibGluayIsInF1ZXJ5Ijp7InRvcGljSWQiOiIxIn19fX0sInJlZGlyZWN0X3JlZGlyZWN0Ijp7InZhbHVlIjp7ImVuIjpbeyJ1cmkiOiIyZjM5Njk4Ni1lNDQ4LTQ0YmItYTQ2Yi00MWJmYmQ2ZjAzNjYiLCJ0aXRsZSI6IiIsIm9wdGlvbnMiOltdLCJ1cmlfdHlwZSI6ImludGVybmFsIiwiaW50ZXJuYWxfdHlwZSI6ImludGVybmFsX2VudGl0eSJ9XX19LCJzdGF0dXNfY29kZSI6eyJ2YWx1ZSI6eyJlbiI6IjMwMSJ9fSwiY3JlYXRlZCI6eyJ2YWx1ZSI6eyJlbiI6eyJ2YWx1ZSI6IjE2MjA5MDk0MDkifX19fQ==',
          ]
        ),
        [
          'path' => 'test',
          'query' => [
            'topicId' => '1',
          ],
        ],
      ],
      [
        new CDFObject(
          'drupal8_content_entity',
          'uuid',
          'date',
          'date',
          'uuid',
          [
            'data' => 'eyJ1dWlkIjp7InZhbHVlIjp7ImVuIjp7InZhbHVlIjoiYjMwMDRmZWMtYjAwMC00YWY2LThmODctNWJhNzRjM2JlNjQ1In19fSwiaGFzaCI6eyJ2YWx1ZSI6eyJlbiI6IlFjekc1NjBqX2hEa2xFeHNJOUpHX0E4cmsxdU9yT3pCbGloUHkxMmEzVzAifX0sInR5cGUiOnsidmFsdWUiOnsiZW4iOiJyZWRpcmVjdCJ9fSwidWlkIjp7InZhbHVlIjp7ImVuIjpbIjJiZTM1MzMyLWYyMmYtNDJiNC1hYmMwLWZkNGU2YzY2YTdjZiJdfX0sInJlZGlyZWN0X3NvdXJjZSI6eyJ2YWx1ZSI6eyJlbiI6eyJwYXRoIjoibGluayIsInF1ZXJ5Ijp7InRvcGljSWQiOiIyIn19fX0sInJlZGlyZWN0X3JlZGlyZWN0Ijp7InZhbHVlIjp7ImVuIjpbeyJ1cmkiOiIyZjM5Njk4Ni1lNDQ4LTQ0YmItYTQ2Yi00MWJmYmQ2ZjAzNjYiLCJ0aXRsZSI6IiIsIm9wdGlvbnMiOltdLCJ1cmlfdHlwZSI6ImludGVybmFsIiwiaW50ZXJuYWxfdHlwZSI6ImludGVybmFsX2VudGl0eSJ9XX19LCJzdGF0dXNfY29kZSI6eyJ2YWx1ZSI6eyJlbiI6IjMwMSJ9fSwiY3JlYXRlZCI6eyJ2YWx1ZSI6eyJlbiI6eyJ2YWx1ZSI6IjE2MjA5MDk1NTEifX19fQ==',
          ]
        ),
        [
          'path' => 'test',
          'query' => [
            'topicId' => '2',
          ],
        ],
      ],
    ];
  }

}
