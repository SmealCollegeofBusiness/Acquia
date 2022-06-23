<?php

namespace Drupal\acquia_contenthub\EventSubscriber\HandleWebhook;

use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\acquia_contenthub\Event\HandleWebhookEvent;
use Drupal\acquia_contenthub\Libs\Traits\HandleResponseTrait;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles webhooks with a payload requesting site report.
 *
 * @package Drupal\acquia_contenthub\EventSubscriber\HandleWebhook
 */
class Report implements EventSubscriberInterface {

  use HandleResponseTrait;

  const PAYLOAD_REPORT = 'report';

  /**
   * The common contenthub actions object.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  protected $common;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * Report constructor.
   *
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common
   *   Common Actions.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   List of modules.
   */
  public function __construct(ContentHubCommonActions $common, ModuleExtensionList $module_list) {
    $this->common = $common;
    $this->moduleList = $module_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::HANDLE_WEBHOOK][] =
      ['onHandleWebhook', 1000];
    return $events;
  }

  /**
   * Handles webhook events.
   *
   * @param \Drupal\acquia_contenthub\Event\HandleWebhookEvent $event
   *   The HandleWebhookEvent object.
   *
   * @throws \Exception
   */
  public function onHandleWebhook(HandleWebhookEvent $event): void {
    $payload = $event->getPayload();

    if ('successful' !== $payload['status'] || self::PAYLOAD_REPORT !== $payload['crud']) {
      return;
    }

    $response = $this->getResponse($event, json_encode($this->getReport()));
    $event->setResponse($response);
    $event->stopPropagation();
  }

  /**
   * Get report of site modules and db update status.
   *
   * @return array
   *   Array of modules and db update status.
   */
  protected function getReport(): array {
    return [
      'modules' => $this->moduleList->getAllInstalledInfo(),
      'updatedb-status' => $this->common->getUpdateDbStatus(),
    ];
  }

}
