<?php

namespace Drupal\acquia_contenthub\Plugin\FileSchemeHandler;

use Acquia\ContentHubClient\CDF\CDFObject;
use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\ContentHubCommonActions;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * File scheme handler for private files.
 *
 * @FileSchemeHandler(
 *   id = "private",
 *   label = @Translation("Private file handler")
 * )
 */
class PrivateFileSchemeHandler extends PluginBase implements FileSchemeHandlerInterface, ContainerFactoryPluginInterface {

  /**
   * The file system object.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Common actions for Content Hub.
   *
   * @var \Drupal\acquia_contenthub\ContentHubCommonActions
   */
  private $contentHubCommonActions;

  /**
   * PrivateFileSchemeHandler constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The definition.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\acquia_contenthub\ContentHubCommonActions $common_actions
   *   Content Hub Common Actions.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, FileSystemInterface $file_system, ContentHubCommonActions $common_actions) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileSystem = $file_system;
    $this->contentHubCommonActions = $common_actions;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('acquia_contenthub_common_actions')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function addAttributes(CDFObject $object, FileInterface $file) {
    $uri = $file->getFileUri();
    $webhook_url = Url::fromUri('internal:/acquia-contenthub/webhook', ['absolute' => TRUE])->toString();
    $object->addAttribute('file_scheme', CDFAttribute::TYPE_STRING, 'private');
    $object->addAttribute('file_location', CDFAttribute::TYPE_STRING, $webhook_url);
    $object->addAttribute('file_uri', CDFAttribute::TYPE_STRING, $uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getFile(CDFObject $object) {
    if ($object->getAttribute('file_location') && $object->getAttribute('file_uri') && $object->getUuid()) {
      $url = $object->getAttribute('file_location')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
      $uri = $object->getAttribute('file_uri')->getValue()[LanguageInterface::LANGCODE_NOT_SPECIFIED];
      $uuid = $object->getUuid();
      $dirname = $this->fileSystem->dirname($uri);
      if ($this->fileSystem->prepareDirectory($dirname, FileSystemInterface::CREATE_DIRECTORY)) {
        try {
          $contents = $this->contentHubCommonActions->requestRemoteEntity($url, $uri, $uuid, 'private');
        }
        catch (\Exception $exception) {
          \Drupal::messenger()->addError(t('Unable to request file.'));
        }
        return $this->saveData($contents, $uri, FileSystemInterface::EXISTS_REPLACE);
      }
    }
    return FALSE;
  }

  /**
   * Save the data to file system.
   *
   * @param string $data
   *   Data to save.
   * @param string $destination
   *   Destination of data.
   * @param int $replace
   *   Replacement option.
   *
   * @return bool|string
   *   String saved or bool if failed.
   */
  private function saveData(string $data, string $destination, int $replace) {
    try {
      return $this->fileSystem->saveData($data, $destination, $replace);
    }
    catch (FileWriteException $e) {
      \Drupal::messenger()->addError(t('The file could not be created.'));
      return FALSE;
    }
    catch (FileException $e) {
      return FALSE;
    }
  }

}
