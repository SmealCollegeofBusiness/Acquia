<?php

namespace Drupal\acquia_contenthub;

use Spatie\SslCertificate\SslCertificate;

/**
 * Audit SSL certificates.
 *
 * Validate the SSL certificates and prevent the calls to foreign sources.
 */
class SslCertificateFactory {

  /**
   * Gets SSL cert of a site by hostname.
   *
   * @param string $hostname
   *   Hostname.
   *
   * @return \Spatie\SslCertificate\SslCertificate|null
   *   SSL cert object of given host.
   */
  public function getCertByHostname(string $hostname): ?SslCertificate {
    return SslCertificate::createForHostName($hostname);
  }

}
