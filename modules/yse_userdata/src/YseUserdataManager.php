<?php

namespace Drupal\yse_userdata;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserDataInterface;
use Drupal\yse_userdata\Plugin\YseUserdataPluginManager;
use Psr\Log\LoggerInterface;

/**
 * A service class to provide file userdata.
 */
class YseUserdataManager implements YseUserdataManagerInterface {

  use StringTranslationTrait;

  /**
   * The YseUserdata plugin manager.
   *
   * @var \Drupal\yse_userdata\Plugin\YseUserdataPluginManager
   */
  protected $pluginManager;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The yse_userdata logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The array of YseUserdata objects currently in use.
   *
   * @var \Drupal\yse_userdata\YseUserdataInterface[]
   */
  protected $lookupkeys = [];

  /**
   * Holds are lookups enabled setting.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $lookupsEnabled;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Constructs a YseUserdataManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\yse_userdata\Plugin\YseUserdataPluginManager $plugin_manager
   *   The YseUserdata plugin manager.
   * @param \Psr\Log\LoggerInterface $logger_interface
   *   The yse_userdata logger.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_service
   *   The cache service.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StreamWrapperManagerInterface $stream_wrapper_manager, YseUserdataPluginManager $plugin_manager, LoggerInterface $logger_interface, CacheBackendInterface $cache_service, UserDataInterface $user_data) {
    $this->pluginManager = $plugin_manager;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->configFactory = $config_factory;
    $this->logger = $logger_interface;
    $this->cache = $cache_service;
    $this->userData = $user_data;
    $this->lookupsEnabled = $this->configFactory->get('yse_userdata.settings')->get('enable_directory_lookups');
  }

  /**
   * Returns an hash for the lookupkey, not used currently.
   *
   * @param string $lookupkey
   *   The lookupkey to a file.
   *
   * @return string
   *   An hash string.
   */
  protected function calculateHash($lookupkey) {
    // Sanitize lookupkey removing duplicate slashes, if any.
    // @see http://stackoverflow.com/questions/12494515/remove-unnecessary-slashes-from-path
    $lookupkey = preg_replace('/([^:])(\/{2,})/', '$1/', $lookupkey);
    // If lookupkey is invalid and no local file path exists, return NULL.
    if (!$this->streamWrapperManager->isValidlookupkey($lookupkey) && !\Drupal::service('file_system')->realpath($lookupkey)) {
      return NULL;
    }
    // Return a hash of the lookupkey.
    return hash('sha256', $lookupkey);
  }

  /**
   * {@inheritdoc}
   */
  public function has($lookupkey) {
    return $this->lookupkey() ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupkey($lookupkey) {
    if (!isset($this->lookupkeys[$lookupkey])) {
      //we don't load this with use or svc whats up????
      if (empty($lookupkey)) {
        return; //need exception here
      }
      elseif (is_numeric($lookupkey) && strlen($lookupkey) == 8) {
        $netid = NULL; //we have a UPI
      }
      else {
        $netid = $lookupkey;
      }
      $this->lookupkeys[$lookupkey] = new YseUserdata($this->pluginManager, $this->logger, $lookupkey, $netid, $this->userData);
    }
    return $this->lookupkeys[$lookupkey];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCachedUserdata($lookupkey) {
    foreach (array_keys($this->pluginManager->getDefinitions()) as $plugin_id) {
      $this->cache->delete("hash:{$plugin_id}:{$lookupkey}");
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function release($lookupkey) {
    if (isset($this->lookupkeys[$lookupkey])) {
      unset($this->lookupkeys[$lookupkey]);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    return count($this->lookupkeys);
  }

}

