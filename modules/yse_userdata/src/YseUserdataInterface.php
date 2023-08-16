<?php

namespace Drupal\yse_userdata;

/**
 * Provides an interface for file userdata objects.
 */
interface YseUserdataInterface {

  /**
   * userdata not loaded.
   */
  const NOT_LOADED = 0;

  /**
   * userdata loaded by code.
   */
  const LOADED_BY_CODE = 1;

  /**
   * userdata loaded from cache.
   */
  const LOADED_FROM_CACHE = 2;

  /**
   * userdata loaded from user.data store.
   */
  const LOADED_FROM_QUERY = 3;

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
   * Gets a YseUserdata plugin instance.
   *
   * @param string $plugin_id
   *   The id of the plugin whose instance is to be returned. If it is does
   *   not exist, an instance is created.
   *
   * @return \Drupal\yse_userdata\Plugin\YseUserdataPluginInterface|null
   *   The YseUserdata plugin instance. NULL if no plugin is found.
   */
  public function getYseUserdataPlugin($plugin_id);

  /**
   * Returns a list of supported userdata keys.
   *
   * @param string $plugin_id
   *   The id of the YseUserdata plugin.
   * @param mixed $options
   *   (optional) Allows specifying additional options to control the list of
   *   userdata keys returned.
   *
   * @return array
   *   A simple array of userdata keys supported.
   */
  public function getExpectedKeys($plugin_id, $options = NULL);

  /**
   * Gets a userdata element via source plugins.
   *
   * @param string $plugin_id
   *   The id of the YseUserdata plugin.
   * @param mixed|null $key
   *   A key to determine the userdata element to be returned. If NULL, the
   *   entire userdata will be returned.
   *
   * @return mixed
   *   The value of the element specified by $key. If $key is NULL, the entire
   *   userdata.
   */
  public function fetchUserdata($plugin_id, $key = NULL);

    /**
   * Reads a userdata element in user.data store.
   *
   * @param mixed|string $u
   *   The id of the userdata object or lookup ids or UserInterface user object
   * @param mixed|null $key
   *   A key to determine the userdata element to be returned. If NULL, the
   *   entire userdata will be returned.
   *
   * @return mixed
   *   The value of the element specified by $key. If $key is NULL, the entire
   *   userdata.
   */
  public function getUserdata($u, $key = NULL);

  /**
   * Sets a userdata element in user.data store.
   *
   * @param mixed|string $u
   *   The id of the userdata object or lookup ids or UserInterface user object
   * @param mixed $key
   *   A key to determine the userdata element to be changed.
   * @param mixed $value
   *   The value to change the userdata element to.
   *
   * @return bool
   *   TRUE if userdata was changed successfully, FALSE otherwise.
   */
  public function setUserdata($u, $key, $value);

  /**
   * Checks if file userdata has been already loaded.
   *
   * @param string $plugin_id
   *   The id of the YseUserdata plugin.
   *
   * @return bool
   *   TRUE if userdata is loaded, FALSE otherwise.
   */
  public function isUserdataLoaded($plugin_id);

  /**
   * Loads file userdata.
   *
   * @param string $plugin_id
   *   The id of the YseUserdata plugin.
   * @param mixed $userdata
   *   The file userdata associated to the file at lookupkey.
   *
   * @return bool
   *   TRUE if userdata was loaded successfully, FALSE otherwise.
   */
  public function loadUserdata($plugin_id, $userdata);

  /**
   * Loads file userdata from a cache entry.
   *
   * @param string $plugin_id
   *   The id of the YseUserdata plugin.
   *
   * @return bool
   *   TRUE if userdata was loaded successfully, FALSE otherwise.
   */
  public function loadUserdataFromCache($plugin_id);

  /**
   * Caches userdata for file at lookupkey.
   *
   * Uses the 'yse_userdata' cache bin.
   *
   * @param string $plugin_id
   *   The id of the YseUserdata plugin.
   * @param array $tags
   *   (optional) An array of cache tags to save to cache.
   *
   * @return bool
   *   TRUE if userdata was saved successfully, FALSE otherwise.
   */
  public function saveUserdataToCache($plugin_id, array $tags = []);

}

