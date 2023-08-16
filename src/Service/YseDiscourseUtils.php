<?php

namespace Drupal\yse_accountmanager\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

class YseDiscourseUtils {

 /**
   * Constructor.
   * 
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory to get module settings.
   * 
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * 
   * @param \GuzzleHttp\Client $http_client
   *    Guzzle HTTP client
   */

  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger_interface, Client $http_client) {
    $this->ssosettings  = $config_factory->get('discourse_sso.settings');
    $this->logger       = $logger_interface;
    $this->guzzle       = $http_client;
  }
  
  public function sync_sso( &$parameters, $account ){
    //am I losing the ref here???
    $this->setup_parameters( $parameters, $account );

    //build ssostring to encode with params
    $ssostr  = 'external_id=' . $parameters['external_id'];
    $ssostr .= '&name=' + $parameters['name'];  
    $ssostr .= '&email=' + $parameters['email']; 
    $ssostr .= '&username=' + $parameters['username'];
    $ssostr .= '&require_activation=false';
    $ssostr .= $parameters['add_groups']    ? '&add_groups='    . $parameters['add_groups']    : null;
    $ssostr .= $parameters['remove_groups'] ? '&remove_groups=' . $parameters['remove_groups'] : null;
    // encode and hash with secret
    $sso     = base64_encode($ssostr);
    $sig     = hash_hmac('sha256', $sso, $this->secret); // hex
    // ship as POST to discourse sso API call.
  
    $path = \Drupal::service('file_system')->realpath('private://secrets.json');
    $secretsobj = json_decode(file_get_contents($path), TRUE);

    $discourse_add_url  = 'https://'        . $secretsobj['discourse_host'] . '/admin/users/sync_sso';
    $discourse_add_url .= '?api_username='  . $secretsobj['discourse_username'];
    $discourse_add_url .= '&api_key='       . $secretsobj['discourse_key'];

    try {
      $response = $this-guzzle->request('POST', $discourse_add_url, [
        'headers'     => ['accept' => 'application/json', 'Api-Username' => $secretsobj['discourse_username'], 'Api-Key' => $secretsobj['discourse_key']],
        'form_params' => [
            'Api-Key'       =>  $secretsobj['discourse_key'],
            'Api-Username'  =>  $secretsobj['discourse_username'],
            'sso'           =>  $sso,
            'sig'           =>  $sig,
          ]
      ]);
    }
    catch (ClientException $e) {
      $this-logger->notice(Psr7\Message::toString($e->getResponse()));
    }
  }

  /**
   * HODE UP - account is not passed.
   * username on discourse has been slugified before
   * 
   * Alter the parameters for the Discourse SSO.
   *
   * @param array $parameters
   *   The parameters to be altered its a REF.
   * 
   * @param string $accountid
   *    An account id
   *
   * @see \Drupal\discourse_sso\Controller\DiscourseSsoController::validate
   */
  function setup_parameters(array &$parameters, $account) {
    // I could test for User and load only when needed.
    // instanceof UserInterface
    // Do I care is this is UserSession vs User?
    
    if ($account instanceof UserInterface){
      $user = $account;
    } else if (is_numeric($account)){
      $user = \Drupal\user\Entity\User::load($account);
    } else {
      $this->logger->error('No user found for account @failedaccount.', ['@failedaccount' => is_numeric($account) ? $account : '' ]);
      return;
    }

    $discourse_name     = $user->getDisplayName();
    $discourse_email    = $user->getEmail();
    $discourse_username = explode('@', $discourse_email)[0];
    $discourse_extid    = \Drupal::service('user.data')->get('yse_userdata', $user->id(), 'upi');
    
    if (empty($discourse_extid) || !is_numeric($discourse_extid)){
      //nothing we can meaningfully do here with UPI on discourse
      $this->logger->error('No external id found for account @failedaccount.', ['@failedaccount' => $user->id() ]);
      return;
    }

    $parameters['username']     = $discourse_username;
    $parameters['external_id']  = $discourse_extid;
    $parameters['name']         = $discourse_name;
    $parameters['email']        = $discourse_email;

    unset($parameters['avatar_force_update']);
    unset($parameters['avatar_url']);

    $user_roles = $user->getRoles();
    $is_student = in_array('student', $user_roles) ? true : false;
    $is_staff   = in_array('staff', $user_roles) ? true : false;
    $is_faculty = in_array('faculty', $user_roles) ? true : false;
    $is_daily   = in_array('daily_digest', $user_roles) ? true : false;

    $groups_in = [];
    $groups_out = [];


    if ($is_student){
      array_push($groups_in,  "fes_student");
    }
    if ($is_staff){
      array_push($groups_in,  "fes_staff");
      array_push($groups_out, "fes_student");
    }
    if ($is_faculty){
      array_push($groups_in,  "fes_faculty");
      array_push($groups_out, "fes_staff");
    }
    if ($is_daily){
      array_push($groups_in,  "daily_digest");
    }

    if(!empty($groups_in))  { $parameters['add_groups']     = implode(',', $groups_in); }
    if(!empty($groups_out)) { $parameters['remove_groups']  = implode(',', $groups_out);}

    //keeping nonce untouched
  }

}