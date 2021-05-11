<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\CdfAttributesEvent;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds a CDF attribute that contains the Entity URL.
 */
class EntityUrlCdfAttribute implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::POPULATE_CDF_ATTRIBUTES][] =
      ['onPopulateAttributes', 100];
    return $events;
  }

  /**
   * Method called on the POPULATE_CDF_ATTRIBUTES event.
   *
   * Adds a CDF attribute that contains the Entity URL.
   *
   * @param \Drupal\acquia_contenthub\Event\CdfAttributesEvent $event
   *   The CdfAttributesEvent object.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function onPopulateAttributes(CdfAttributesEvent $event) {
    $entity = $event->getEntity();
    if (!($entity instanceof ContentEntityInterface) || !$entity->hasLinkTemplate('canonical')) {
      return;
    }

    try {
      $base_path = Url::fromUserInput("/", ['absolute' => TRUE])->toString();
      $entity_url = $base_path . $entity->toUrl('canonical')->getInternalPath();
    }
    catch (\UnexpectedValueException $ex) {
      // We could not obtain the internal path for this entity.
      \Drupal::logger('acquia_contenthub')->error(sprintf("We could not obtain the Internal URL for Entity (%s:%s). The URL attribute was not added to the CDF. Error code: %s, Error message: \"%s\"",
        $entity->getEntityTypeId(),
        $entity->id(),
        $ex->getCode(),
        $ex->getMessage()
      ));
      return;
    }
    $cdf = $event->getCdf();
    $cdf->addAttribute('url', CDFAttribute::TYPE_STRING, $entity_url);
  }

}
