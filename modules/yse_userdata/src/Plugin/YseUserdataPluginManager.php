<?php

namespace Drupal\yse_userdata\Plugin;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for YseUserdata plugins.
 */
class YseUserdataPluginManager extends DefaultPluginManager {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    parent::__construct('Plugin/YseUserdata', $namespaces, $module_handler, 'Drupal\yse_userdata\Plugin\YseUserdataPluginInterface', 'Drupal\yse_userdata\Plugin\Annotation\YseUserdata');
    $this->alterInfo('yse_userdata_plugin_info');
    $this->setCacheBackend($cache_backend, 'yse_userdata_plugins');
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $default_config = call_user_func($plugin_definition['class'] . '::defaultConfiguration');
    $configuration = $this->configFactory->get($plugin_definition['provider'] . '.yse_userdata_plugin.' . $plugin_id)->get('configuration') ?: [];
    return parent::createInstance($plugin_id, NestedArray::mergeDeep($default_config, $configuration));
  }

}
