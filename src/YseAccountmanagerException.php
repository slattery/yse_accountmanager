<?php

namespace Drupal\yse_accountmanager;

/**
 * Exception thrown by file_mdm and plugins on failures.
 */
class YseAccountmanagerException extends \Exception {

  /**
   * Constructs a YseAccountmanagerException object.
   */
  public function __construct($message, $plugin_id = NULL, $method = NULL, \Exception $previous = NULL) {
    $msg = $message;
    $msg .= $plugin_id ? " (plugin: {$plugin_id})" : "";
    $msg .= $method ? " (method: {$method})" : "";
    parent::__construct($msg, 0, $previous);
  }

}
