<?php

namespace Drupal\yse_cas_extras\Subscriber;

use Drupal\cas\CasPropertyBag;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\cas_attributes\Form\CasAttributesSettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\yse_cas_extras\Service\CasBaggagehandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a CasAttributesSubscriber.
 */
class CasPreRegisterEventSubscriber implements EventSubscriberInterface {


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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory to get module settings.
   */
  public function __construct(CasBaggagehandler $baggage_handler, ConfigFactoryInterface $config_factory) {
    $this->baggagehandler = $baggage_handler;
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
    $events[CasHelper::EVENT_PRE_REGISTER][] = ['onPreRegister', 10];
    return $events;
  }

  /**
   * Subscribe to the CasPreRegisterEvent.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The CasPreAuthEvent containing property information.
   */
  public function onPreRegister(CasPreRegisterEvent $event) {    
    if ($this->casattsettings->get('field.sync_frequency') !== CasAttributesSettings::SYNC_FREQUENCY_NEVER) {
      // Perform lookup and set event properties and cas property bag attributes.
      // next Subscriber should deal with fields and roles using the CAS Attributes mapping.
      $cas_username   = $event->getCasPropertyBag()->getOriginalUsername();
      //uid here should be fed to externalauth.authmap, it is not the drupal uid.
      $event->getCasPropertyBag()->setAttribute('uid', $cas_username);
      $basket = $this->baggagehandler->pickAndPack($cas_username);
      $merged = array_merge($event->getCasPropertyBag()->getAttributes(), $basket);
      $event->getCasPropertyBag()->setAttributes($merged);

     //uncomment to find out what we have
     //dpr($event->getCasPropertyBag()->getAttributes(), $return = FALSE, $name = 'prereg');


      // hopefull here the event is in tact with a full bag.
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
