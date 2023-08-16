<?php
// HUGE debt to https://git.drupalcode.org/project/file_mdm/-/tree/8.x-2.x

namespace Drupal\yse_userdata;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\yse_userdata\Plugin\YseUserdataPluginManager;
use Psr\Log\LoggerInterface;

/**
 * A YSE userdata object.
 */
class YseUserdata implements YseUserdataInterface {
  /**
   * The YseUserdata plugin manager.
   *
   * @var \Drupal\yse_userdata\Plugin\YseUserdataPluginManager
   */
  protected $pluginManager;

  /**
   * The yse_userdata logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The lookup string, should represent the upi or netid of person.
   *
   * @var string
   */
  protected $lookupkey = '';

  /**
   * The netid of person.
   *
   * @var string
   */
  protected $netid = '';

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The array of YseUserdata plugins.
   *
   * @var \Drupal\yse_userdata\Plugin\YseUserdataPluginInterface[]
   */
  protected $plugins = [];

   /**
   * Constructs a YseUserdata object.
   *
   * @param \Drupal\yse_userdata\Plugin\YseUserdataPluginManager $plugin_manager
   *   The file userdata plugin manager.
   * @param \Psr\Log\LoggerInterface $logger_interface
   *   The logger service.
   * @param string $lookup_key
   *   The netid or UPI used to find a record.
   * @param string $net_id
   *   The NetID of the record. Needed if we are accessing cache or user.data store.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function __construct(YseUserdataPluginManager $plugin_manager, LoggerInterface $logger_interface, $lookup_key, $net_id, UserDataInterface $user_data) {
    $this->pluginManager = $plugin_manager;
    $this->logger = $logger_interface;
    $this->lookupkey = $lookup_key;
    $this->netid = $net_id;
    $this->userData = $user_data;
  }
  /**
   * {@inheritdoc}
   */
  public function getLookupkey() {
     // if someone just sets netid and nothing else, assume that is the lookupkey.
     if ( empty($this->lookupkey) && !empty($this->netid) ){
        $this->setLookupkey($this->netid);
      }
      return $this->lookupkey;
  }
  
  /**
   * {@inheritdoc}
   */
  public function getNetid() {
    return $this->netid;
  }

  /**
   * {@inheritdoc}
   */
  public function getYseUserdataPlugin($plugin_id) {
    // userdata id will be the id value in the userdata id field of the plugin itself, how it is named in the system
    if (!isset($this->plugins[$plugin_id])) {
      try {
        $this->plugins[$plugin_id] = $this->pluginManager->createInstance($plugin_id);
        $this->plugins[$plugin_id]->setNetid($this->netid);
        $this->plugins[$plugin_id]->setLookupkey($this->lookupkey);
      }
      catch (PluginNotFoundException $e) {
        return NULL;
      }
    }
    return $this->plugins[$plugin_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedKeys($plugin_id, $options = NULL) {
    // check to see if the keys we need to do the job are present
    try {
      if ($plugin = $this->getYseUserdataPlugin($plugin_id)) {
        $keys = $plugin->getExpectedKeys($options);
      }
      else {
        $keys = NULL;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting expected keys for @userdata userdata for @lookupkey. Message: @message', [
        '@userdata' => $plugin_id ?? '',
        '@lookupkey' => $this->lookupkey ?? '',
        '@message' => $e->getMessage() ?? '',
      ]);
      $keys = NULL;
    }
    return $keys;
  }

 /**
   * {@inheritdoc}
   */
  public function fetchUserdata($plugin_id, $key = NULL) {
    try {
      if ($plugin = $this->getYseUserdataPlugin($plugin_id)) {
        $userdata = $plugin->fetchUserdata($key);
      }
      else {
        $userdata = NULL;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting @plugin_id@key userdata for @lookupkey. Message: @message', [
        '@plugin_id' => $plugin_id ?? '',
        '@key' => $key ? ' ('. var_export($key, TRUE) . ')' : '',
        '@lookupkey' => $this->lookupkey ?? '',
        '@message' => $e->getMessage() ?? '',
      ]);
      $userdata = NULL;
    }
    return $userdata;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserdata($u, $key, $value) {
    // getUserdata assumed lookup is complete and you are writing to cache and/or user object for an Expected Key
    $uid = $this->forageForUid($u);
    if ($uid){
        $this->userData->set('yse_userdata', $uid, $key, $value);
        $stored = $this->getUserdata($uid, $key);
        return $stored;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserdata($u, $key = NULL) {
    // getUserdata assumed lookup is complete and you are looking in cache or user object for Expected Keys
    $uid = $this->forageForUid($u);
    if ($uid){
      $gotten = $this->userData->get('yse_userdata', $uid, $key);
      if (!empty($gotten)) {
        return $gotten;
      } else {
        return NULL;
      }
    }
    return NULL;
  }


  /**
   * {@inheritdoc}
   */
  public function isUserdataLoaded($plugin_id) {
    if ($plugin = $this->getYseUserdataPlugin($plugin_id)) {
      return $plugin->isUserdataLoaded();
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function loadUserdata($plugin_id, $userdata) {
    if ($plugin = $this->getYseUserdataPlugin($plugin_id)) {
      return $plugin->loadUserdata($userdata);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function loadUserdataFromCache($plugin_id) {
    if ($plugin = $this->getYseUserdataPlugin($plugin_id)) {
      return $plugin->loadUserdataFromCache();
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function saveUserdataToCache($plugin_id, array $tags = []) {
    if ($plugin = $this->getYseUserdataPlugin($plugin_id)) {
      return $plugin->saveUserdataToCache($tags);
    }
    return FALSE;
  }


  protected function forageForUid($u){
    $uid = NULL;

    if (empty($u)){ 
        $uid = NULL;
    } elseif ($u instanceof UserInterface){
        $uid = $u->id();
    } elseif (is_numeric($u) && \Drupal\user\Entity\User::load($u)){
        $uid = $u;
    } elseif (is_numeric($u) && strlen($u) == 8){
        $uid = NULL;
        $this->logger->error('YSE Userdata: UPI submitted. Acceptable values are netID, drupal userid, or User object.');
    } elseif (is_string($u) && \Drupal::hasService('cas.user_manager') ){
        // assume it is a lookupkey
        $cas_usermgr = \Drupal::service('cas.user_manager');
        $cas_useruid = $cas_usermgr->getUidForCasUsername($u);
        $uid = $cas_useruid ?: NULL;
    }
    return $uid;
  }

}