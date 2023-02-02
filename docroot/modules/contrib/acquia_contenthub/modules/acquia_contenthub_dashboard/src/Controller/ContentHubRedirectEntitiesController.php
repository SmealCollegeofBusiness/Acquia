<?php

namespace Drupal\acquia_contenthub_dashboard\Controller;

use Drupal\acquia_contenthub\Client\ClientFactory;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ContentHubRedirectEntitiesController.
 *
 * Controller to redirect entities via entity type and uuid.
 *
 * @package Drupal\acquia_contenthub\Controller
 */
class ContentHubRedirectEntitiesController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Content Hub Client Factory.
   *
   * @var \Drupal\acquia_contenthub\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * ContentHubRedirectEntitiesController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\acquia_contenthub\Client\ClientFactory $client_factory
   *   The client factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ClientFactory $client_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->clientFactory = $client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $container->get('entity_type.manager');

    /** @var \Drupal\acquia_contenthub\Client\ClientFactory $client_factory */
    $client_factory = $container->get('acquia_contenthub.client.factory');

    return new static(
      $entity_type_manager,
      $client_factory,
    );
  }

  /**
   * Redirect to entity edit form via entity type and uuid.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $uuid
   *   The uuid of the entity.
   *
   * @returns \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The JsonResponse object.
   *
   * @throws \Exception
   */
  public function redirectToEntityEditForm(string $entity_type, string $uuid): Response {
    if (!Uuid::isValid($uuid)) {
      return $this->prepareResponse("Provided UUID '{$uuid}' is not a valid UUID.", 400);
    }

    try {
      $storage = $this->entityTypeManager->getStorage($entity_type);
    }
    catch (PluginNotFoundException | \Exception $ex) {
      return $this->prepareResponse("The '{$entity_type}' entity type does not exist.", 404);
    }

    $entity = $storage->loadByProperties([
      'uuid' => $uuid,
    ]);
    if (empty($entity)) {
      return $this->prepareResponse("Provided UUID '{$uuid}' for entity type '{$entity_type}' does not exist.", 404);
    }

    $entity = reset($entity);
    if ($entity->getEntityType()->hasLinkTemplate('edit-form') && $entity->toUrl('edit-form')->isRouted()) {
      $url = $entity->toUrl('edit-form')->toString();
      return new RedirectResponse($url);
    }

    return $this->prepareResponse('Edit link template does not exist for this entity.', 404);
  }

  /**
   * Prepare json output whenever there's some error.
   *
   * @param string $msg
   *   Output message.
   * @param int $status
   *   Status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The json response.
   */
  protected function prepareResponse(string $msg, int $status): JsonResponse {
    return new JsonResponse([
      'success' => FALSE,
      'error' => [
        'message' => $msg,
        'code' => $status,
      ],
    ], $status);
  }

}
