<?php

//declare(strict_types=1);

namespace Drupal\yse_userdata\Plugin\Action;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;


/**
 * Populates fields in userdata store.
 *
 * @Action(
 *  id = "yse_userdata_refresh_action",
 *  label = @Translation("Populates fields in userdata store"),
 *  type = "user",
 *  confirm = TRUE
 * )
 */
final class YseUserdataRefreshAction extends ViewsBulkOperationsActionBase {
    
    //going to try without executemultiple or finished etc just to see what the defaults do
    public function execute($account = NULL) {
        //if the user has no cas account then there is limited utility

        
        $netid =  \Drupal::service('externalauth.authmap')->get($account->id(), 'cas');

        if (empty($netid)){
            $this->messenger()->addWarning('Cannot find authmap entry for ' . $account->getDisplayName() . '. Skipping.');
            return NULL;
        }

        //look in profile first for data
        //add directory lookup later, might be a rare edge case

        //get nid for matching profile should only be ONE
        //make constraint TODO
        $getprofiles = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('type', 'yse_detail_profile')
        ->condition('field_yse_netid', $netid)
        ->exists('field_yse_upi');

        $profile_nids = $getprofiles->execute();

        // check for nopes
        if (empty($profile_nids)){
            $this->messenger()->addWarning('Cannot find profile for ' . $account->getDisplayName() . '. Skipping.');
            return NULL;
        }
        if (count($profile_nids) > 1){
            $this->messenger()->addWarning('Found multiple profiles with netid '. $netid . ' for ' . $account->getDisplayName() . '. Skipping.');
            return NULL;
        }


        $profile = Node::load(reset($profile_nids));
        
        if ($profile->get('status')->getString() != '1'){
            $this->messenger()->addWarning('Found unpublished profile for ' . $account->getDisplayName() . '. Skipping.');
            return NULL;
        }

        $set_netid = $profile->get('field_yse_netid')->getString();
        $set_upi   = $profile->get('field_yse_upi')->getString();
        //might inject service along with account
        if ( !empty($set_netid) && !empty($set_upi) ){
            \Drupal::service('user.data')->set('yse_userdata', $account->id(), 'upi', $set_upi);
            \Drupal::service('user.data')->set('yse_userdata', $account->id(), 'netid', $set_netid);
            return $this->messenger()->addMessage('Userdata store populated for ' . $account->getDisplayName() . ' ' . $set_upi . '. Success.');
        }
    }



  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\user\UserInterface $object */
    return $object->access('edit', $account, $return_as_object);
  }


}
