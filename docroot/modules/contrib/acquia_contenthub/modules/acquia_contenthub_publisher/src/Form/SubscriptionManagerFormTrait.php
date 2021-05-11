<?php

namespace Drupal\acquia_contenthub_publisher\Form;

use Drupal\Core\Messenger\MessengerInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;

/**
 * Trait for consistency in subscription manager form.
 */
trait SubscriptionManagerFormTrait {

  /**
   * Returns the success status of the response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response object.
   * @param string $operation_label
   *   The label of an operation.
   * @param string $item_label
   *   The label of an item/entity.
   * @param string $uuid
   *   The uuid of the entity.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   *
   * @return bool
   *   Whether the response is successful or is not.
   */
  protected function isResponseSuccessful(ResponseInterface $response, $operation_label, $item_label, $uuid, MessengerInterface $messenger) {
    if ((new HttpFoundationFactory())->createResponse($response)->isSuccessful()) {
      return TRUE;
    }

    $messenger->addError(
      $this->t('Unable to %operation %item %uuid. Status code: %status_code. Message: %message',
        [
          '%operation' => $operation_label,
          '%item' => $item_label,
          '%uuid' => $uuid,
          '%status_code' => $response->getStatusCode(),
          '%message' => $response->getReasonPhrase(),
        ]));
    return FALSE;
  }

}
