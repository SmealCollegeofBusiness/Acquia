<?php

namespace Drupal\acquia_contenthub_s3\EventSubscriber\Cdf;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\ParseCdfEntityEvent;
use Drupal\acquia_contenthub_s3\S3FileMapper;
use Drupal\file\FileInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Manipulates file content entity CDF representation to better support files.
 */
class S3FileEntityHandler implements EventSubscriberInterface {

  /**
   * The s3 file mapper.
   *
   * @var \Drupal\acquia_contenthub_s3\S3FileMap
   */
  protected $s3FileMapper;

  /**
   * FileEntityHandler constructor.
   *
   * @param \Drupal\acquia_contenthub_s3\S3FileMapper $s3_file_mapper
   *   The s3 file mapper.
   */
  public function __construct(S3FileMapper $s3_file_mapper) {
    $this->s3FileMapper = $s3_file_mapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // At this point the entity has been already crated, hence the weight.
    $events[AcquiaContentHubEvents::PARSE_CDF][] = ['onParseCdf', 90];
    return $events;
  }

  /**
   * Parse CDF attributes to record s3 related files.
   *
   * @param \Drupal\acquia_contenthub\Event\ParseCdfEntityEvent $event
   *   The Parse CDF Entity Event.
   *
   * @throws \Exception
   */
  public function onParseCdf(ParseCdfEntityEvent $event) {
    /** @var \Drupal\file\FileInterface $entity */
    $entity = $event->getEntity();
    if (!$entity instanceof FileInterface) {
      return;
    }

    $cdf = $event->getCdf();
    $file_uri = $cdf->getAttribute('file_uri');
    if (!$file_uri) {
      return;
    }

    $this->s3FileMapper->mapS3File($cdf, $entity);
  }

}
