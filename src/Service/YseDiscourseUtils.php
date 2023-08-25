<?php

namespace Drupal\yse_accountmanager\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;
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
  

  /**
   * Sync a user account with the YSE Discourse server for SSO
   *
   * @param array $parameters
   *   A reference of parameters passed via Discourse logic
   * @param \Drupal\user\UserInterface|mixed $account
   *   Either numeric drupal user ID or UserInterface object
   *
   * @return string|null
   *   A simple confirmation of the single_sign_on_record external_name or NULL
   */
  public function sync_sso( &$parameters, $account ){
    //am I losing the ref here???           
    $this->setup_parameters( $parameters, $account );
    $is_setup = true;
    //dpr($parameters, $return = FALSE, $name = 'postparamsetup'); 

    $method = 'POST';
    $call   = '/admin/users/sync_sso';
    $proof  = 'single_sign_on_record';
    $data  = [];
    //build ssostring to encode with params
    $ssostr  = 'external_id=' . $parameters['external_id'];
    $ssostr .= '&name=' . $parameters['name'];  
    $ssostr .= '&email=' . $parameters['email']; 
    $ssostr .= '&username=' . $parameters['username'];
    $ssostr .= '&require_activation=false';

    $ssostr .= $parameters['add_groups']    ? '&add_groups='          . $parameters['add_groups']    : null;
    $ssostr .= $parameters['remove_groups'] ? '&remove_groups='       . $parameters['remove_groups'] : null;

    //these are not coerced from strings in discourse so they already get encoded with ampersands, etc.
    //$ssostr .= $parameters['customstr']     ?  $parameters['customstr']   : null;
    //$ssostr .= $parameters['mutedstr']      ?  $parameters['mutedstr']    : null;

    $result = $this->make_request($method, $call, $proof, $data, $ssostr);

    if ($result && $result[$proof]){
      $this->logger->info('Verified discourse account for %account.', ['%account' => $parameters['name'] ]);
      //return $parameters['name'];
      $second = $this->config_user( $parameters, $account, $is_setup );
      if (!empty($second)){
        return $parameters['username'];
      }
      else {
        $this->logger->error('Config failed for %failedaccount.', ['%failedaccount' => $parameters['name'] ]);
        return NULL;
      }
    }
    else {
      $this->logger->error('Null result for %failedaccount.', ['%failedaccount' => $parameters['name'] ]);
      return NULL;
    }
      
  }


  /**
   * Sync a user account with the YSE Discourse server for SSO
   *
   * @param array $parameters
   *   A reference of parameters passed via Discourse logic
   * @param \Drupal\user\UserInterface|Drupal\user\Entity\User|mixed $account
   *   Either numeric drupal user ID or UserInterface object
   * @param bool|null $is_setup
   *   Have the parameters been through setup_parameters already?
   *
   * @return string|null
   *   A simple confirmation of the single_sign_on_record external_name or NULL
   */
  public function config_user( &$parameters, $account, $is_setup = NULL ){

    if (empty($is_setup)){
      $this->setup_parameters( $parameters, $account );
    }
      //note - this will only work after sync_sso, the username is canned and changed from the past.
      $method = 'PUT';
      $call   = '/users/' . $parameters['username']; //can't find this on API docs, but /u/ fails.
      $proof  = 'user';
      
      $data  = [];
      
      $data['primary_group_name'] = $parameters['primary'];
      $data['muted_category_ids'] = $parameters['muted'];
      
      if ($parameters['primary'] == 'fes_students'){
        $data['custom_fields'] = ['fixed_digest_emails' => true];
      }

      $result = $this->make_request($method, $call, $proof, $data);

      if ($result && $result[$proof]){
        return $parameters['username'];
     } 
      else {
        return NULL;
      }

  }

public function anonymize_user( &$parameters, $account, $is_setup = NULL ){
  if (empty($is_setup)){
    $this->setup_parameters( $parameters, $account );
  }

  list($duid, $dnom) = $this->get_discourse_user( $parameters );
  if (!empty($duid) and is_numeric($duid)){

    $method = 'PUT';
    $call   = '/admin/users/' . $duid . '/anonymize.json';
    $proof  = 'username';

    $result = $this->make_request($method, $call, $proof); // might need [] for form

    if ($result && $result[$proof]){
      return $result[$proof];
    } 
    else {
      $this->logger->error('Null anon for %failedaccount.', ['%failedaccount' => $parameters['name'] ]);
      return NULL;
    }
  } else {
    $this->logger->error('No uid to anon for %failedaccount.', ['%failedaccount' => $parameters['name'] ]);
    return NULL;
  }



  https://{defaultHost}/admin/users/{id}/anonymize.json
}


public function delete_user( &$parameters, $account ){

  $this->setup_parameters( $parameters, $account );

  list($duid, $dnom) = $this->get_discourse_user( $parameters );

  if (!empty($duid) and is_numeric($duid)){
    $is_setup = true;
    $method = 'DELETE';
    $call   = '/admin/users/' . $duid . '.json';
    $proof  = 'deleted';
    $data   = [
      "delete_posts"  => false,
      "block_email"   => true,
      "block_urls"    => true,
      "block_ip"      => false
    ];

    $result = $this->make_request($method, $call, $proof, $data); // might need [] for form

    if ($result && $result[$proof]){
      return 'DELETED';
    } 
    else {
      // need to check in faux pas for errors that demand a second call. like 403 Forbidden {"deleted":false... 
      // when users have posts, we don't nuke them right away, but we make it so no emails get to them and no SSO.   
      $second = $this->anonymize_user( $parameters, $account, $is_setup );
      if (!empty($second)){
        return $second; //should be anon name or old username
      }
      else {
        $this->logger->error('Anon failed for %failedaccount.', ['%failedaccount' => $parameters['name'] ]);
        return NULL;
      }
    }
  } else {
    $this->logger->error('No uid to delete for %failedaccount.', ['%failedaccount' => $parameters['name'] ]);
    return NULL;
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
   * @param \Drupal\user\UserInterface|Drupal\user\Entity\User|mixed $account
   *    An account id or object
   *
   * @see \Drupal\discourse_sso\Controller\DiscourseSsoController::validate
   */
  function setup_parameters(array &$parameters, $account) {
    // I could test for User and load only when needed.
    // instanceof UserInterface
    // Do I care is this is UserSession vs User?
    
    if ($account instanceof \Drupal\user\UserInterface){
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
      $parameters['primary'] = "fes_students";
      array_push($groups_in,   "fes_students");
    }
    if ($is_staff){
      $parameters['primary'] = "fes_staff";
      array_push($groups_in,   "fes_staff");
      array_push($groups_out,  "fes_students");
    }
    if ($is_faculty){
      $parameters['primary'] = "fes_faculty";
      array_push($groups_in,   "fes_faculty");
      array_push($groups_out,  "fes_staff");
    }
    if ($is_daily || $is_student){
      array_push($groups_in,  "daily_digest");
    }

    if(!empty($groups_in))  { $parameters['add_groups']     = implode(',', $groups_in); }
    if(!empty($groups_out)) { $parameters['remove_groups']  = implode(',', $groups_out);}

    //keeping nonce untouched

    $muted  = [ 
      'fes_students' => [16,15,3,4,1,13,12],
      'fes_staff'    => [16,17,3,4,1,13,12],
      'fes_faculty'  => [16,17,3,4,1,13,12],
    ];

    //Encode complex stuff for later like: "dog[name]=John&dog[age]=12&user_ids[0]=1&user_ids[1]=3"

    if (!empty($muted[$parameters['primary']])){
        $encodedstr = '';
        foreach($muted[$parameters['primary']] as $idx => $mid){
          $encodedstr .= "&muted_category_ids[{$idx}]={$mid}";
        }
        $parameters['muted']    = $muted[$parameters['primary']];
        $parameters['mutedstr'] = $encodedstr;
    }

    if ($parameters['primary'] == 'fes_students'){
      $parameters['custom']    = ['fixed_digest_emails' => true];
      $parameters['customstr'] = '&custom_fields[fixed_digest_emails]=true';
    }

 }


  public function get_discourse_user( $in ){

    $method = 'GET';
    $call   = '/u/by-external/' . $in['external_id'] . '.json';
    $proof  = 'user';

    $result = $this->make_request($method, $call, $proof); // might need [] for form

    if ($result && $result[$proof]){
    return [$result[$proof]['id'], $result[$proof]['username']];
    } 
    else {
      return NULL;
    }
  }

  /**
   * Execute a web request against YSE Discourse server
   *
   * @param string $method
   *  GET, PUT, DELETE, ETC.
   * @param string $call
   *  full URL with args
   * @param string $proof
   *  key to look for in results array from discourse
   * @param array $data
   *  keyval array when args are not shipped in the URL.
   * 
   * @return string|null
   *   A simple confirmation of the proof param or NULL
   */
  private function make_request($method, $call, $proof, $data = NULL, $ssostr = NULL){

    $path = \Drupal::service('file_system')->realpath('private://secrets.json');
    $shhh = json_decode(file_get_contents($path), TRUE);

    if( isset( $shhh['discourse_secret'], $shhh['discourse_host'], $shhh['discourse_username'], $shhh['discourse_key'] ) ) {
      $dest                 = 'https://' . $shhh['discourse_host'] . $call;

      if (!empty($ssostr)){
        $data['Api-Key']      = $shhh['discourse_key'];
        $data['Api-Username'] = $shhh['discourse_username'];
        $data['sso'] = base64_encode($ssostr);
        $data['sig'] = hash_hmac('sha256', $data['sso'], $shhh['discourse_secret']); // hex
        $send = 'form_params';
      } else {
        //sub in json for form_params based on args
        $send = 'json';
      }

      $trip_config = [
        'verify'   => false, //this is very shameful but the traefik proxy and globalsign mid certs are not happy.
        'headers'  => ['accept' => 'application/json', 'Api-Username' => $shhh['discourse_username'], 'Api-Key' => $shhh['discourse_key']],
      ];

      if(!empty($data)){
        $trip_config[$send] = $data;
      }

      try {
        $response = $this->guzzle->request($method, $dest, $trip_config);
        $results = Json::decode($response->getBody(), TRUE);
        //$logme = (string) $response->getBody();
        //$this->logger->notice("Results:  %results", ['%results' => $logme]);

        if ($results && $results[$proof]){
          return $results;
        } else {
          return NULL;
        }
      }
      // TODO: get more precise with 'errors' array and 'error_type' string in body
      catch (ClientException $e) {
        $this->faux_pas($e);
        return NULL;
      } 
      catch (ServerException $e) {
        $this->faux_pas($e);
        return NULL;
      } 
      catch (RequestException $e) {
        $this->faux_pas($e);
        return NULL;
      } 
      catch (\Exception $e) {
        $this->faux_pas($e);
        return NULL;
      }
    }
    else {
      return NULL;
    }
  }


  private function faux_pas($e){
    $catcher = get_class($e);
    $errtype =  'Unknown';
    $fauxpas = 'There was an error in discourse';
    if ($e->hasResponse()) {
      $errtype = $e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase();
      $fauxpas = (string) $e->getResponse()->getBody();
    }
    //dpr($e, $return = FALSE, $name = 'faux_pas'); 
    $this->logger->notice("%c %typ %err", ['%c' => $catcher, '%typ' => $errtype, '%err' => $fauxpas]);
  }
}