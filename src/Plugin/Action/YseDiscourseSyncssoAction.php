<?php

//declare(strict_types=1);

namespace Drupal\yse_accountmanager\Plugin\Action;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Sync a user account with the YSE Discourse server for SSO.
 *
 * @Action(
 *  id = "yse_discourse_syncsso_action",
 *  label = @Translation("YSE Users: SSO Sync with YSE Discourse"),
 *  type = "user",
 *  confirm = TRUE
 * )
 */
final class YseDiscourseSyncssoAction extends ViewsBulkOperationsActionBase {

  public function execute($account = NULL) {
    //if the user has no cas account then there is limited utility

    
    $netid =  \Drupal::service('externalauth.authmap')->get(intval($account->id()), 'cas');

    if (empty($netid)){
        $this->messenger()->addWarning('Cannot find authmap entry for ' . $account->getDisplayName() . '. Skipping.');
        return NULL;
    }

    // if the UPI is there, try the sync.
    $discourse_extid    = \Drupal::service('user.data')->get('yse_userdata', $account->id(), 'upi');
    if (!empty($discourse_extid) && is_numeric($discourse_extid)){
      $parameters = [];
      //sending id because object is not a full user object.
      $discourse_confirmation = \Drupal::service('yse_accountmanager.discourseutils')->sync_sso($parameters, $account->id());
      //dpr($discourse_confirmation, $return = FALSE, $name = 'endsso'); 
      //dpr($account->getDisplayName(), $return = FALSE, $name = 'endsso'); 
      if (!empty($discourse_confirmation)){
        return $this->messenger()->addMessage('Discourse synced for ' . $account->getDisplayName() . ' as ' . $discourse_confirmation . '. Success.');
      }
    } else {
      return  $this->messenger()->addWarning('Failed to sync with Discourse for ' . $account->getDisplayName() . '. Skipping.');
    }

  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $admin = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\user\UserInterface $object */
    return $object->access('edit', $admin, $return_as_object);
  }

}
