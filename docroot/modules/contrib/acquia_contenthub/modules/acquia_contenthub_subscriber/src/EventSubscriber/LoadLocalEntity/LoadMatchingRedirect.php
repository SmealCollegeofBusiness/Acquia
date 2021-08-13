<?php

namespace Drupal\acquia_contenthub_subscriber\EventSubscriber\LoadLocalEntity;

use Acquia\ContentHubClient\CDF\CDFObject;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\LoadLocalEntityEvent;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class LoadMatchingRedirect.
 *
 * Loads a local redirect entity by its information.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\LoadLocalEntity
 */
class LoadMatchingRedirect implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::LOAD_LOCAL_ENTITY][] =
      ['onLoadLocalEntity', 7];
    return $events;
  }

  /**
   * Loads local matching redirects to avoid duplicate redirect errors.
   *
   * @param \Drupal\acquia_contenthub\Event\LoadLocalEntityEvent $event
   *   The local entity loading event.
   *
   * @throws \Exception
   */
  public function onLoadLocalEntity(LoadLocalEntityEvent $event) {

    $cdf = $event->getCdf();
    if (!$this->isSupported($cdf)) {
      return;
    }

    $data = json_decode(base64_decode($cdf->getMetadata()['data']), TRUE);
    $redirect_source = $data['redirect_source'];
    $langcode = $cdf->getMetadata()['default_language'];

    $redirect = $this->getExistingRedirect($redirect_source, $langcode);
    if ($redirect) {
      $event->setEntity($redirect);
      $event->stopPropagation();
    }
  }

  /**
   * Checks should object be processed or not.
   *
   * @param \Acquia\ContentHubClient\CDF\CDFObject $cdf_object
   *   CDF Object.
   *
   * @return bool
   *   TRUE if CDF object is taxonomy term.
   */
  protected function isSupported(CDFObject $cdf_object): bool {
    $type = $cdf_object->getAttribute('entity_type');

    return $type->getValue()[CDFObject::LANGUAGE_UNDETERMINED] === 'redirect';
  }

  /**
   * Looks for an existing redirect based on data from the CDF.
   *
   * @param array $redirect_source
   *   The redirect source data from the CDF.
   * @param string $langcode
   *   The langcode.
   *
   * @return \Drupal\redirect\Entity\Redirect|null
   *   The existing Redirect (if any).
   */
  protected function getExistingRedirect(array $redirect_source, string $langcode): ?Redirect {
    $query = $redirect_source['value'][$langcode]['query'] ?? [];
    $path = $redirect_source['value'][$langcode]['path'];

    /** @var \Drupal\Redirect\RedirectRepository $redirect_repository */
    $redirect_repository = \Drupal::service('redirect.repository');
    $existing_redirect = $redirect_repository->findMatchingRedirect($path, $query, $langcode);

    return $existing_redirect;
  }

}
