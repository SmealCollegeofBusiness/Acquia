<?php

namespace Drupal\acquia_contenthub_preview\EventSubscriber\KernelResponse;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Removes the x-frame-options header from previews.
 *
 * @package Drupal\acquia_contenthub_preview\EventSubscriber\KernelResponse
 */
class KillXFrameOptions implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Adds the event in the list of KernelEvents::RESPONSE with priority -10.
    $events[KernelEvents::RESPONSE][] = ['onKernelResponse', -10];
    return $events;
  }

  /**
   * Filter X-Frame-Options on Kernel Response.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The Filter Response Event.
   */
  public function onKernelResponse(FilterResponseEvent $event) {
    $response = $event->getResponse();
    $response->headers->remove('X-Frame-Options');
  }

}
