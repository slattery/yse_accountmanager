<?php

namespace Drupal\yse_userdata\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;


/**
 * QueryConfigOverrides service.  The ConfigFactoryOverrideInterface allows for secrets and other 
 * config overrides to be respected when you cannot or do not want to edit settings.php, such as an
 * optional module.  Often settings need to be overridden when using placeholders for secret values.
 * Also useful when a module only has installed config settings and no form to edit them.
 */

class YalesitesDirectoryProxyConfigOverrides implements ConfigFactoryOverrideInterface {

  use \Drupal\yse_userdata\Traits\SecretsConfigOverridesTrait;

  const CONFIG_PATH = 'yse_userdata.yse_userdata_plugin.yalesites_directory_proxy';

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    if (in_array(self::CONFIG_PATH, $names)) {      
      $overrides[self::CONFIG_PATH] = [
        'ys_directory_service_host' => $this->substantiate(self::CONFIG_PATH,'ys_directory_service_host'),
        'ys_directory_service_path' => $this->substantiate(self::CONFIG_PATH,'ys_directory_service_path'),
        'ys_directory_service_name' => $this->substantiate(self::CONFIG_PATH,'ys_directory_service_name') ?? $this->substantiate(NULL,'l7_user'),
        'ys_directory_service_pass' => $this->substantiate(self::CONFIG_PATH,'ys_directory_service_pass') ?? $this->substantiate(NULL,'l7_pass'),
        ];
    }
    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'YalesitesDirectoryProxyConfigOverrides';
  }
  
  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }
}