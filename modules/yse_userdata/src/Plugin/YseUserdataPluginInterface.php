<?php

namespace Drupal\yse_userdata\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Provides an interface defining a YseUserdata plugin.
 */
interface YseUserdataPluginInterface extends ContainerFactoryPluginInterface, PluginInspectionInterface, PluginFormInterface {

  /**
   * Gets default configuration for this plugin.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  public static function defaultConfiguration();

  /**
   * Sets the Lookup key of the file UPI or netid
   *
   * @param string $lookupkey
   *   A NetID.
   *
   * @return $this
   *
   * @throws \Drupal\yse_userdata\YseUserdataException
   *   If no key is specified.
   */
  public function setLookupkey($lookupkey);

   /**
   * Sets the NetID of the file.
   *
   * @param string $netid
   *   A NetID.
   *
   * @return $this
   *
   * @throws \Drupal\yse_userdata\YseUserdataException
   *   If no NetID is specified.
   */
  public function setNetid($netid);

 /**
   * Gets the lookupkey for a query to an external resource.
   *
   * @return string|null
   *   The lookupkey.
   */
  public function getLookupkey();

 /**
   * Gets the netid for a user.
   *
   * @return string|null
   *   The lookupkey.
   */
  public function getNetid();

  /**
   * Returns a list of Userdata keys supported by the plugin.
   *
   * @param mixed $options
   *   (optional) Allows specifying additional options to control the list of
   *   Userdata keys returned.
   *
   * @return array
   *   A simple array of Userdata keys supported.
   */
  public function getExpectedKeys($options = NULL);

  /**
   * Checks if file Userdata has been already loaded.
   *
   * @return bool
   *   TRUE if Userdata is loaded, FALSE otherwise.
   */
  public function isUserdataLoaded();

  /**
   * Loads file Userdata from an in-memory object/array.
   *
   * @param mixed $userdata
   *   The file Userdata associated to the NetID.
   *
   * @return bool
   *   TRUE if Userdata was loaded successfully, FALSE otherwise.
   */
  public function loadUserdata($userdata);

  /**
   * Loads file Userdata from a cache entry.
   *
   * @return bool
   *   TRUE if Userdata was loaded successfully, FALSE otherwise.
   *
   * @throws \Drupal\yse_userdata\YseUserdataException
   *   In case of significant errors.
   */
  public function loadUserdataFromCache();

    /**
   * Loads file userdata from query set up by plugin using lookupkey as key.
   *
   * @return bool
   *   TRUE if userdata was loaded successfully, FALSE otherwise.
   *
   * @throws \Drupal\yse_userdata\YseUserdataException
   *   In case there were significant errors reading from query.
   */
  public function loadUserdataFromQuery();


  /**
   * Gets a Userdata element.
   *
   * @param mixed|null $key
   *   A key to determine the Userdata element to be returned. If NULL, the
   *   entire Userdata will be returned.
   *
   * @return mixed
   *   The value of the element specified by $key. If $key is NULL, the entire
   *   Userdata.
   */
  public function fetchUserdata($key = NULL);

  /**
   * Caches Userdata for file at URI.
   *
   * Uses the 'yse_userdata' cache bin.
   *
   * @param array $tags
   *   (optional) An array of cache tags to save to cache.
   *
   * @return bool
   *   TRUE if Userdata was saved successfully, FALSE otherwise.
   */
  public function saveUserdataToCache(array $tags = []);

  /**
   * Removes cached Userdata for file at URI.
   *
   * Uses the 'yse_userdata' cache bin.
   *
   * @return bool
   *   TRUE if Userdata was removed, FALSE otherwise.
   */
  public function deleteCachedUserdata();

}
