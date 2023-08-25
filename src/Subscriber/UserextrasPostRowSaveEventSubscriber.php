<?php

namespace Drupal\yse_accountmanager\Subscriber;

use Drupal\externalauth\AuthmapInterface;
use Drupal\migrate\Event\EventBase;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\user\UserInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * Class UserextrasPostRowSaveEventSubscriber. *
 */

class UserextrasPostRowSaveEventSubscriber implements EventSubscriberInterface {

  use \Drupal\yse_userdata\Traits\YseProfileRepackagingTrait;

  /**
   * The authmap service.
   *
   * @var \Drupal\externalauth\AuthmapInterface
   */
  protected $authmap;
  
  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userdata;

  /**
   * The yse_userdata logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * UserextrasPostRowSaveEventSubscriber constructor.
   *
   * @param \Drupal\externalauth\AuthmapInterface $auth_map
   *   The Authmap handling class.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Psr\Log\LoggerInterface $logger_interface
   *  The yse_userdata logger.
   */

  public function __construct(AuthmapInterface $auth_map, UserDataInterface $user_data, LoggerInterface $logger_interface) {
    $this->authmap = $auth_map;
    $this->userdata = $user_data;
    $this->logger = $logger_interface;
  }


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    $events = [];
    $events[MigrateEvents::POST_ROW_SAVE] = ['onPostRowSave'];
    return $events;
  }
  /**
   * Helper method to check if the current migration has redirects in its source.
   *
   * @param \Drupal\migrate\Event\EventBase $event
   *   The migrate event.
   *
   * @return bool
   *   True if the migration is configured with has_redirects.
   */
  protected function purposeCheck(EventBase $event, array $criteria) : array {
    $migration            = $event->getMigration();
    $source_configuration = $migration->getSourceConfiguration();
    $allowed = [];
    foreach( $criteria as $c ){
      if (!empty($source_configuration[$c]) && $source_configuration[$c] == TRUE) { 
        $allowed[$c] = TRUE;
      }
    }
    return $allowed;
  }

  /**
   * Maps the existing redirects to the new node id.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The migrate post row save event.
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) : void {

    $is_allowed = self::purposeCheck($event,['add_cas','add_profiles']);
    if (empty($is_allowed)){
      // nothing to do here
      return;
    }

    //$mid  = $event->getMigration()->id();      //yse_user_import_students etc
    $row  = $event->getRow();
    $csv  = $row->getSource();
    $out  = $row->getDestination();

    //UserInterface includes AccountInterface methods
    /** @var \Drupal\user\UserInterface $account */
    $account = user_load_by_mail($csv['email']);

    if ($account && $account instanceof UserInterface){
      $provider = 'cas';
      $authname = $csv['netid'];
      $upi = $csv['upi'];
      $uid = $account->id();
      $this->userdata->set('yse_userdata', $uid, 'netid', $authname);
      $this->userdata->set('yse_userdata', $uid, 'upi', $upi);

      if ($is_allowed['add_cas']) {
        $this->authmap->save($account, $provider, $authname);
      }

      if ($is_allowed['add_profiles']) {
        // Create profile stub
        $notuniq = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('type', 'yse_detail_profile')
        ->condition('field_yse_netid', $authname);
        $collisions = $notuniq->count()->execute();
        // add some updateOK flag in the future
        if ($collisions == 0){
          $ysedat = array_merge($csv, $out);
          if(empty($ysedat['name'])){
            $ysedat['name'] =  $ysedat['firstname'] . ' ' . $ysedat['lastname'];
          }
          $ysedat['primary_affiliation'] = strtoupper($out['roles'][0]);
          //\Drupal\yse_userdata\Traits\YseProfileRepackagingTrait
          $profileprops         = $this->profileprep($ysedat);
          //could run a separate cache check for yse_migration_csv_users key....
          //or can I look into migration data directly?
          $profileprops['type'] = 'yse_detail_profile';
          $profileprops['uid']  = $uid;
          $profile_storage      = \Drupal::entityTypeManager()->getStorage('node');
          $profile              = $profile_storage->create($profileprops);
          $profile->save();
        }
      }

      if($is_allowed['add_discourse']){
        if (\Drupal::hasService('yse_accountmanager.discourseutils')) {
          $parameters = [];
          $discourser = \Drupal::service('yse_accountmanager.discourseutils')->sync_sso($parameters, $uid);
        }
      }
    }
  }
}