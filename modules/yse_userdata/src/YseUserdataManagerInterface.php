<?php

namespace Drupal\yse_userdata;

/**
 * Provides an interface for userdata manager objects.
 */
interface YseUserdataManagerInterface {

  /**
   * Determines if the lookupkey is currently in use by the manager.
   *
   * @param string $lookupkey
   *   The lookupkey to a file.
   *
   * @return bool
   *   TRUE if the lookupkey is in use, FALSE otherwise.
   */
  public function has($lookupkey);

  /**
   * Returns a YseUserdata object for the lookupkey, creating it if necessary.
   *
   * @param string $lookupkey
   *   The lookupkey to a file.
   *
   * @return \Drupal\yse_userdata\YseUserdataInterface|null
   *   The YseUserdata object for the specified lookupkey.
   */
  public function lookupkey($lookupkey);

  /**
   * Deletes the all the cached userdata for the lookupkey.
   *
   * @param string $lookupkey
   *   The lookupkey to a file.
   *
   * @return bool
   *   TRUE if the cached userdata was removed, FALSE in case of error.
   */
  public function deleteCachedUserdata($lookupkey);

  /**
   * Releases the YseUserdata object for the lookupkey.
   *
   * @param string $lookupkey
   *   The lookupkey to a file.
   *
   * @return bool
   *   TRUE if the YseUserdata for the lookupkey was removed from the manager,
   *   FALSE otherwise.
   */
  public function release($lookupkey);

  /**
   * Returns the count of YseUserdata objects currently in use.
   *
   * @return int
   *   The number of YseUserdata objects currently in use.
   */
  public function count();

}
