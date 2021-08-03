<?php

namespace Drupal\acquia_contenthub\Client;

use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Project version client to get latest releases for a project from D.O.
 *
 * @package Drupal\acquia_contenthub\Client
 */
class ProjectVersionClient {

  /**
   * The HTTP client to fetch the project release data.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * URL to check for updates.
   *
   * @var string
   */
  protected $fetchUrl = 'https://updates.drupal.org/release-history';

  /**
   * The acquia_contenthub logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $achLoggerChannel;

  /**
   * ProjectVersionClient constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   A Guzzle client object.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   The logger factory.
   */
  public function __construct(ClientInterface $http_client, LoggerChannelInterface $logger_channel) {
    $this->httpClient = $http_client;
    $this->achLoggerChannel = $logger_channel;
  }

  /**
   * Returns latest versions of the acquia_contentub module from D.O.
   *
   * @return string[]
   *   Array of recommended versions.
   */
  public function getContentHubReleases(): array {
    return $this->getReleaseData('acquia_contenthub');
  }

  /**
   * Returns latest versions of drupal core from D.O.
   *
   * @param string $drupal_version
   *   Drupal major version installed on system.
   *
   * @return string[]
   *   Array of recommended versions.
   */
  public function getDrupalReleases(string $drupal_version): array {
    return $this->getReleaseData('drupal', $drupal_version);
  }

  /**
   * Helper method to get latest release versions for a given d.o project.
   *
   * @param string $project_name
   *   Name of the D.O module/project to fetch release versions.
   * @param string $drupal_version
   *   Drupal major version installed.
   *
   * @return string[]
   *   Recommended versions for a given project.
   */
  public function getReleaseData(string $project_name, string $drupal_version = ''): array {
    $versions = [];
    $url = $this->fetchUrl . '/' . $project_name . '/current';
    try {
      $data = (string) $this->httpClient
        ->get($url, ['headers' => ['Accept' => 'text/xml']])
        ->getBody();
      $versions = $this->parseXml($data, $drupal_version);
    }
    catch (RequestException $exception) {
      $this->achLoggerChannel->error($exception->getMessage());
    }
    return $versions;
  }

  /**
   * Parses XML from D.O and get latest versions of security covered releases.
   *
   * @param string $xml_data
   *   XML Data.
   * @param string $drupal_version
   *   Drupal major version installed.
   *
   * @return string[]
   *   Array of versions of security covered releases
   *   for a given project.
   */
  protected function parseXml(string $xml_data, string $drupal_version = ''): array {
    $versions = [];
    try {
      $xml = new \SimpleXMLElement($xml_data);
    }
    catch (\Exception $e) {
      return [];
    }

    if (!isset($xml->short_name) || !isset($xml->releases)) {
      return [];
    }

    // Break out the loop after the first valid security release.
    foreach ($xml->releases->children() as $release) {
      $version = (string) $release->version;
      $major_version = $version[0];
      $security_covered = $this->isSecurityCoveredRelease($release);
      if (!$security_covered) {
        continue;
      }

      if ($this->isLatestVersion($drupal_version, $major_version)) {
        $versions['latest'] = $version;
        return $versions;
      }

      if (
        !isset($versions['also_available']) &&
        $drupal_version === '8' &&
        $major_version === '9'
      ) {
        $versions['also_available'] = $version;
      }
    }

    return $versions;
  }

  /**
   * Checks whether the module's version is the latest.
   *
   * @param string $drupal_version
   *   The drupal version.
   * @param string $major_version
   *   The module's major version.
   *
   * @return bool
   *   Returns TRUE if both versions are matching.
   */
  protected function isLatestVersion(string $drupal_version, string $major_version): bool {
    return empty($drupal_version) ||
      (
        $drupal_version === $major_version &&
        in_array($drupal_version, ['8', '9'], TRUE)
      );
  }

  /**
   * Checks whether a given release is security covered on D.O.
   *
   * @param \SimpleXMLElement $release
   *   XML element.
   *
   * @return bool
   *   True if security covered release otherwise False.
   */
  protected function isSecurityCoveredRelease(\SimpleXMLElement $release): bool {
    $security_release = (string) $release->security['covered'];
    return $security_release && $security_release === "1";
  }

}
