<?php

namespace Drupal\yse_cas_extras\Subscriber;

use Drupal\cas_attributes\Form\CasAttributesSettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\externalauth\Event\ExternalAuthEvents;
use Drupal\externalauth\Event\ExternalAuthRegisterEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a ExternalAuthRegisterEventSubscriber.
 */
class ExternalAuthRegisterEventSubscriber implements EventSubscriberInterface {

  use \Drupal\yse_userdata\Traits\YseProfileRepackagingTrait;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Settings object for CAS attributes.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $casattsettings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory to get module settings.
   * @param \Psr\Log\LoggerInterface $logger_instance
   *   The logger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger_instance) {
    $this->casattsettings = $config_factory->get('cas_attributes.settings');
    $this->logger = $logger_instance;
  }

  /**
   *  Set priorities to populate bag before normal CAS Attribute process.
   *  From drupal docs: Event subscribers with higher priority numbers get executed first
   */
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ExternalAuthEvents::REGISTER][] = ['onRegister', 1];
    return $events;
  }

  /**
   * Subscribe to the ExternalAuthRegisterEvent.
   *
   * @param \Drupal\externalauth\Event\ExternalAuthRegisterEvent $event
   *   The ExternalAuthRegisterEvent containing property information.
   *   gives you UserInterface $account, $provider, $authname, $data = NULL
   */
  public function onRegister(ExternalAuthRegisterEvent $event) {

    

    $ysecas = $event->getAuthname();
    $yseusr = $event->getAccount();
    $ysepro = $event->getProvider();

    if ($ysepro == 'cas'){
      if ($this->casattsettings->get('field.sync_frequency') !== CasAttributesSettings::SYNC_FREQUENCY_NEVER) {

        $ysemgr = \Drupal::service('yse_userdata.manager');
        $yseobj = $ysemgr->lookupkey($ysecas);
        $ysedat = $yseobj->fetchUserdata('yalesites_dirproxy');  //hopefully this is still cached!
        
        foreach( ['title','upi'] as $k ){
          if ($ysedat[$k]) { 
            $yseobj->setUserdata($yseusr->id(), $k, $ysedat[$k]);
            // kind of like 
            // \Drupal::service('user.data')->set('yse_userdata', $yseusr->id(), 'upi', $ysedat['upi'] );
          }
        }

        // Create profile stub
        $notuniq = \Drupal::entityQuery('node')
          ->accessCheck(FALSE)
          ->condition('type', 'yse_detail_profile')
          ->condition('field_yse_netid', $ysecas);
        $collisions = $notuniq->count()->execute();
        // add some updateOK flag in the future
        if ($collisions == 0){
          $target_migration     = 'yse_accountmgr_migrateusers';
          $cache = \Drupal::cache()->get("hash:{$target_migration}:{$ysecas}");
          $migrateprops         = $cache->data; // not sure this is plugnplay yet
          //dpr($cache->data, $return = FALSE, $name = 'mogrationcheck');
          //\Drupal\yse_userdata\Traits\YseProfileRepackagingTrait
          $packed               = !empty($migrateprops) ? array_merge($migrateprops, $ysedat) : $ysedat;
          $profileprops         = $this->profileprep($packed);
          //could run a separate cache check for yse_migration_csv_users key....
          //or can I look into migration data directly?
          $profileprops['type'] = 'yse_detail_profile';
          $profileprops['uid']  = $yseusr->id();
          $profile_storage      = \Drupal::entityTypeManager()->getStorage('node');
          $profile              = $profile_storage->create($profileprops);
          $profile->save();
        }
      }
    }
  }
}