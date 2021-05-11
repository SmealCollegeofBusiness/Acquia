<?php

namespace Drupal\acquia_contenthub\EventSubscriber\Cdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CreateCdfEntityEvent;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Manipulates file content entity CDF representation to better support files.
 */
class FileEntityHandler implements EventSubscriberInterface {

  /**
   * The file scheme handler manager.
   *
   * @var \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface
   */
  protected $manager;

  /**
   * The acquia_contenthub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $channel;

  /**
   * FileEntityHandler constructor.
   *
   * @param \Drupal\acquia_contenthub\Plugin\FileSchemeHandler\FileSchemeHandlerManagerInterface $manager
   *   File scheme handler manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(FileSchemeHandlerManagerInterface $manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->manager = $manager;
    $this->channel = $logger_factory->get('acquia_contenthub');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::CREATE_CDF_OBJECT][] = ['onCreateCdf', 90];
    $events[AcquiaContentHubEvents::PARSE_CDF][] = ['onParseCdf', 110];
    return $events;
  }

  /**
   * Add attributes to file entity CDF representations.
   *
   * @param \Drupal\acquia_contenthub\Event\CreateCdfEntityEvent $event
   *   The create CDF entity event.
   */
  public function onCreateCdf(CreateCdfEntityEvent $event) {
    if ($event->getEntity()->getEntityTypeId() == 'file') {
      /** @var \Drupal\file\FileInterface $entity */
      $entity = $event->getEntity();
      $handler = $this->manager->getHandlerForFile($entity);
      $handler->addAttributes($event->getCdf($entity->uuid()), $entity);
    }
  }

  /**
   * Parse CDF attributes to import files as necessary.
   *
   * @param \Drupal\acquia_contenthub\Event\ParseCdfEntityEvent $event
   *   The Parse CDF Entity Event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function onParseCdf(ParseCdfEntityEvent $event) {
    $cdf = $event->getCdf();
    $entity_type = $cdf->getAttribute('entity_type');

    if ($entity_type && $entity_type->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED] !== 'file') {
      return;
    }

    if ($cdf->getAttribute('file_scheme')) {
      $scheme = $cdf->getAttribute('file_scheme')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
      $handler = $this->manager->createInstance($scheme);
      $handler->getFile($cdf);
    }
    else {
      $label_attribute = $cdf->getAttribute('label');
      $label = $label_attribute ? $label_attribute->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED] : '';

      $this->channel->warning(sprintf('File %s does not have a scheme and therefore was not copied. This likely 
      means that its scheme is remote, unsupported or that it should already exist within a module, theme or library in 
      the subscriber.', $label));
    }
  }

}
