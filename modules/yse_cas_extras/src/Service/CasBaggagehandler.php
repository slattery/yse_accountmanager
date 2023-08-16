<?php

namespace Drupal\yse_cas_extras\Service;

use Drupal\cas\CasPropertyBag;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas_attributes\Form\CasAttributesSettings;
use Drupal\Component\Serialization\Json;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a CasAttributesSubscriber.
 */
class CasBaggagehandler {

  /**
   * Used to dispatch CAS login events.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The CAS Helper.
   *
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;


  /**
   * Constructor.
   * 
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The configuration factory to get module settings.
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, CasHelper $cas_helper) {
    $this->eventDispatcher = $event_dispatcher;
    $this->casHelper = $cas_helper;
  }



 
  /**
   * Create new CasPropertyBag with CAS netid, 
   * fill with attributes if given.  Might add attribute type tests
   * 
   * @param string $casusr
   *   Netid.
   * 
   * @param array|null $attributes
   *   ex:  ['roles' => $roles, 'mail' => $cas_init_address ]
   * 
   * @return \Drupal\cas\CasPropertyBag $casbag
   *   Returning a bag with cas handle and optional attributes.
   */
  public function openNewCasPropertyBag($casusr, $attributes = NULL){
    $casbag = new CasPropertyBag($casusr);
    $casbag->setAttributes($attributes);
    return $casbag;
  }

  /**
   * Fill the CasPropertyBag with more attributes, taken from lookup.
   * 
   */
  public function pickAndPack($casusr) {
    // Need to call a class that handles its own plugins here or something.
    // next Subscriber will deal with fields and roles
    $ysemgr = \Drupal::service('yse_userdata.manager');
    $yseobj = $ysemgr->lookupkey($casusr);
    $ysedat = $yseobj->fetchUserdata('yalesites_dirproxy');
    return $ysedat;
    // now you have the atts, you need to map using event and bag and dispatch
  }
}
