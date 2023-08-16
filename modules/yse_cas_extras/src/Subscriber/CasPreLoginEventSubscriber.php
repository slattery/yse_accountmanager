<?php

namespace Drupal\yse_cas_extras\Subscriber;

use Drupal\cas\CasPropertyBag;
use Drupal\cas\Event\CasPreLoginEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\cas\Service\CasUserManager;
use Drupal\cas_attributes\Form\CasAttributesSettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\yse_cas_extras\Service\CasBaggagehandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Provides a CasAttributesSubscriber.
 */
class CasPreLoginEventSubscriber implements EventSubscriberInterface {

  /**
   * local bagger.
   *
   * @var \Drupal\yse_cas_extras\Service\CasBaggagehandler
   *   YSE Cas Property Bag attribute attendant.
   */
  protected $baggagehandler;

  /**
   * local mgr.
   *
   * @var \Drupal\cas\Service\CasUserManager
   *   YSE Cas Property Bag attribute attendant.
   */
  protected $casusermanager;

  /**
   * Settings object for CAS attributes.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $casattsettings;

  /**
   * Constructor.
   *
   * @param \Drupal\yse_cas_extras\Service\CasBaggagehandler $baggage_handler
   *   YSE Cas Property Bag attribute attendant.
   * @param \Drupal\cas\Service\CasUserManager $cas_user_manager
   *   YSE Cas Property Bag attribute attendant.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory to get module settings.
   */


  public function __construct(CasBaggagehandler $baggage_handler, CasUserManager $cas_user_manager, ConfigFactoryInterface $config_factory) {
    $this->baggagehandler = $baggage_handler;
    $this->casusermanager = $cas_user_manager;
    $this->casattsettings = $config_factory->get('cas_attributes.settings');;
  }

  /**
   *  Set priorities to populate bag before normal CAS Attribute process.
   *  From drupal docs: Event subscribers with higher priority numbers get executed first
   */
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[CasHelper::EVENT_PRE_LOGIN][] = ['onPreLogin', 21];
    return $events;
  }
  /**
   * Subscribe to the CasPreLoginEvent.
   *
   * @param \Drupal\cas\Event\CasPreLoginEvent $event
   *   The CasPreAuthEvent containing property information.
   */
  public function onPreLogin(CasPreLoginEvent $event) {

    if ($this->casattsettings->get('field.sync_frequency') == CasAttributesSettings::SYNC_FREQUENCY_EVERY_LOGIN) {

      $yseusr = $event->getAccount();
      $ysecas = $this->casusermanager->getCasUsernameForAccount($yseusr->id());
      // also helps with getUidForCasUsername

      // Perform lookup and set event properties and cas property bag attributes.
      // next Subscriber should deal with fields and roles using the CAS Attributes mapping.
      //uid here should be fed to externalauth.authmap, it is not the drupal uid.
      $event->getCasPropertyBag()->setAttribute('uid', $ysecas);
      $basket = $this->baggagehandler->pickAndPack($ysecas);
      $merged = array_merge($event->getCasPropertyBag()->getAttributes(), $basket);
      $event->getCasPropertyBag()->setAttributes($merged);

      // shop and fill should set the name attribute to first, last.  the cas event holds the original username (netid)
      // and drupal username (also set to netid by cas) in module props not in the bag attributes.
      // So we set this at the event level after we have retrieved them in the bag.  The externalauth register() function 
      // takes both username variants, so we inject them into the cas event object here as a subscriber to the pre-register
      // event.  If we do not do this, the CAS code just sets netid to both cas and drupal.

      // name is a calculated field in the yse_userdata service, but could be overridden here.

      $cas_nicename = $event->getCasPropertyBag()->getAttribute('name');
      if (!empty($cas_nicename)){
        $event->setDrupalUsername($cas_nicename);
      }
    }
  }
}