<?php

namespace Drupal\acquia_contenthub\EventSubscriber\CdfAttributes;

use Acquia\ContentHubClient\CDFAttribute;
use Drupal\acquia_contenthub\AcquiaContentHubEvents;
use Drupal\acquia_contenthub\Event\BuildClientCdfEvent;
use Drupal\acquia_contenthub\SslCertificateFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds Valid Ssl certificate attribute to client CDF.
 */
class ValidSslAttribute implements EventSubscriberInterface {

  /**
   * Drupal Site url.
   *
   * @var string
   */
  protected $siteUrl;

  /**
   * Site scheme: http or https.
   *
   * @var string
   */
  protected $siteScheme;

  /**
   * SSL certificate factory.
   *
   * @var \Drupal\acquia_contenthub\SslCertificateFactory
   */
  protected $sslCertFactory;

  /**
   * ValidSslAttribute constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\acquia_contenthub\SslCertificateFactory $ssl_cert_factory
   *   SSL certificate factory.
   */
  public function __construct(RequestStack $request_stack, SslCertificateFactory $ssl_cert_factory) {
    $request = $request_stack->getCurrentRequest();
    $this->siteUrl = $request ? $request->getSchemeAndHttpHost() : '';
    $this->siteScheme = $request ? $request->getScheme() : 'http';
    $this->sslCertFactory = $ssl_cert_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaContentHubEvents::BUILD_CLIENT_CDF][] = ['onBuildClientCdf'];
    return $events;
  }

  /**
   * Method called on the BUILD_CLIENT_CDF event.
   *
   * Adds Valid SSL attribute to the cdf.
   *
   * @param \Drupal\acquia_contenthub\Event\BuildClientCdfEvent $event
   *   The event being dispatched.
   *
   * @throws \Exception
   */
  public function onBuildClientCdf(BuildClientCdfEvent $event): void {
    $cdf = $event->getCdf();
    $cdf->addAttribute('valid_ssl', CDFAttribute::TYPE_BOOLEAN, $this->getValidStatusForSite());
  }

  /**
   * Helper method to get the valid status.
   *
   * @return bool
   *   Valid SSL status.
   */
  protected function getValidStatusForSite(): bool {
    if (empty($this->siteScheme)
      || empty($this->siteUrl)
      || $this->siteScheme === 'http') {
      return FALSE;
    }

    try {
      $cert = $this->sslCertFactory->getCertByHostname($this->siteUrl);
    }
    catch (\Exception $e) {
      $cert = NULL;
    }
    return $cert !== NULL && $cert->isValid();
  }

}
