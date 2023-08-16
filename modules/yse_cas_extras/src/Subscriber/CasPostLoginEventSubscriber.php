<?php

namespace Drupal\yse_cas_extras\Subscriber;

use Drupal\cas\CasPropertyBag;
use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\cas\Service\CasUserManager;
use Drupal\cas_attributes\Form\CasAttributesSettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\yse_cas_extras\Service\CasBaggagehandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// PostSub is here to perform user.data operations when check is EVERY.
// BUT UPI won't change, title might.
/**
 * Provides a CasAttributesSubscriber.
 */
class CasPostLoginEventSubscriber implements EventSubscriberInterface {

  /**
   * local mgr.
   *
   * @var string
   */
  protected $casusermanager;

  /**
   * local bagger.
   *
   * @var string
   */
  protected $baggagehandler;


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
    $this->casattsettings = $config_factory->get('cas_attributes.settings');
  }

  /**
   *  Set priorities to populate bag before normal CAS Attribute process.
   *  From drupal docs: Event subscribers with higher priority numbers get executed first
   */
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[CasHelper::EVENT_POST_LOGIN][] = ['onPostLogin', 21];
    return $events;
  }

  /**
   * Subscribe to the CasPreLoginEvent.
   *
   * @param \Drupal\cas\Event\CasPostLoginEvent $event
   *   The CasPostLoginEvent containing account and property information. CHECK OVERWRITE!
   *   you get UserInterface $account, CasPropertyBag $cas_property_bag (hopefully stuffed from lookup)
   */
  public function onPostLogin(CasPostLoginEvent $event) {
    $yseusr = $event->getAccount();
    $ysecas = $this->casusermanager->getCasUsernameForAccount($yseusr->id());
    
    
    if ($this->casattsettings->get('field.sync_frequency') == CasAttributesSettings::SYNC_FREQUENCY_EVERY_LOGIN) {

      $ysemgr = \Drupal::service('yse_userdata.manager');
      $yseobj = $ysemgr->lookupkey($ysecas);
      $ysedat = $yseobj->fetchUserdata('yalesites_dirproxy');  //hopefully this is still cached but every login is harsh

      foreach( ['title','upi'] as $k ){
        if ($ysedat[$k]) { 
          $yseobj->setUserdata($yseusr->id(), $k, $ysedat[$k]);
          // kind of like 
          // \Drupal::service('user.data')->set('yse_userdata', $yseusr->id(), 'upi', $ysedat['upi'] );
        }
      }
    }
  }
}
