<?php

namespace Drupal\yse_userdata\Plugin\YseUserdata;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\yse_userdata\YseUserdataException;
use Drupal\yse_userdata\YseUserdataInterface;
use Drupal\yse_userdata\Plugin\YseUserdataPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract implementation of a base File Userdata plugin.
 */
abstract class YseUserdataPluginBase extends PluginBase implements YseUserdataPluginInterface {

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The NetID of the file.
   *
   * @var string
   */
  protected $lookupkey;

  /**
   * The Userdata of the file.
   *
   * @var mixed
   */
  protected $userdata = NULL;

  /**
   * The Userdata loading status.
   *
   * @var int
   */
  protected $isUserdataLoaded = YseUserdataInterface::NOT_LOADED;

  /**
   * Track if file Userdata on cache needs update.
   *
   * @var bool
   */
  protected $hasUserdataChangedFromCacheVersion = FALSE;

  /**
   * Constructs a YseUserdataPluginBase plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_service
   *   The cache service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, CacheBackendInterface $cache_service, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cache = $cache_service;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.yse_userdata'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultConfiguration() {
    return [
      'cache' => [
        'override' => FALSE,
        'settings' => [
          'enabled' => TRUE,
          'expiration' => 900,
          'disallowed_paths' => [],
        ],
      ],
    ];
  }

  /**
   * Gets the configuration object for this plugin.
   *
   * @param bool $editable
   *   If TRUE returns the editable configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig|\Drupal\Core\Config\Config
   *   The ImmutableConfig of the Config object for this plugin.
   */
  protected function getConfigObject($editable = FALSE) {
    $plugin_definition = $this->getPluginDefinition();
    $config_name = $plugin_definition['provider'] . '.yse_userdata_plugin.' . $plugin_definition['id'];
    return $editable ? $this->configFactory->getEditable($config_name) : $this->configFactory->get($config_name);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override main caching settings'),
      '#default_value' => $this->configuration['cache']['override'],
    ];
    $form['cache_details'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => FALSE,
      '#title' => $this->t('Userdata caching'),
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getPluginId() . '[override]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['cache_details']['settings'] = [
      '#type' => 'yse_userdata_caching',
      '#default_value' => $this->configuration['cache']['settings'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // @codingStandardsIgnoreStart
    $this->configuration['cache']['override'] = (bool) $form_state->getValue([$this->getPluginId(), 'override']);
    $this->configuration['cache']['settings'] = $form_state->getValue([$this->getPluginId(), 'cache_details', 'settings']);
    // @codingStandardsIgnoreEnd

    $config = $this->getConfigObject(TRUE);
    $config->set('configuration', $this->configuration);
    if ($config->getOriginal('configuration') != $config->get('configuration')) {
      $config->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setLookupkey($lookupkey) {
    if (!$lookupkey) {
      throw new YseUserdataException('Missing $lookupkey argument', $this->getPluginId(), __FUNCTION__);
    }
    if (!is_numeric($lookupkey)){
      $lookupkey = strtolower($lookupkey);
    }

    $this->lookupkey = $lookupkey;
    return $this;
  }

    /**
   * {@inheritdoc}
   */
  public function setNetid($netid) {
    if (!$netid) {
      throw new YseUserdataException('Missing netid argument', $this->getPluginId(), __FUNCTION__);
    }
    if (is_numeric($netid)){
      throw new YseUserdataException('NetID malformed', $this->getPluginId(), __FUNCTION__);
    }
    if (!ctype_alnum($netid)){
      throw new YseUserdataException('NetID is malformed', $this->getPluginId(), __FUNCTION__);
    }

    $this->netid = strtolower($netid);
    return $this;
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
  public function isUserdataLoaded() {
    return $this->isUserdataLoaded;
  }

  /**
   * {@inheritdoc}
   */
  public function loadUserdata($userdata) {
    $this->userdata = $userdata;
    $this->hasUserdataChangedFromCacheVersion = TRUE;
    $this->deleteCachedUserdata();
    if ($this->userdata === NULL) {
      $this->isUserdataLoaded = YseUserdataInterface::NOT_LOADED;
    }
    else {
      $this->isUserdataLoaded = YseUserdataInterface::LOADED_BY_CODE;
      $this->saveUserdataToCache();
    }
    return (bool) $this->userdata;
  }


    /**
   * {@inheritdoc}
   */
  public function loadUserdataFromQuery() {
    
    if (($this->userdata = $this->doFetchUserdataFromQuery()) === NULL) {
      $this->isUserdataLoaded = YseUserdataInterface::NOT_LOADED;
    }
    else {
      $this->isUserdataLoaded = YseUserdataInterface::LOADED_FROM_QUERY;
      $this->saveUserdataToCache();
    }
    return (bool) $this->userdata;
  }

  /**
   * Gets file userdata from the query in the specific plugin.
   *
   * @return mixed
   *   The userdata retrieved from the file.
   *
   * @throws \Drupal\yse_userdata\YseUserdataException
   *   In case there were significant errors reading from file.
   */
  abstract protected function doFetchUserdataFromQuery();


  /**
   * {@inheritdoc}
   */
  public function loadUserdataFromCache() {
    $plugin_id = $this->getPluginId();
    $this->hasUserdataChangedFromCacheVersion = FALSE;
    if ($this->isRecordYseUserdataCacheable() !== FALSE && ($cache = $this->cache->get("hash:{$plugin_id}:{$this->netid}"))) {
      $this->userdata = $cache->data;
      $this->isUserdataLoaded = YseUserdataInterface::LOADED_FROM_CACHE;
    }
    else {
      $this->userdata = NULL;
      $this->isUserdataLoaded = YseUserdataInterface::NOT_LOADED;
    }
    return (bool) $this->userdata;
  }

  /**
   * Checks if file Userdata should be cached.
   *
   * @return array|bool
   *   The caching settings array retrieved from configuration if file Userdata
   *   is cacheable, FALSE otherwise.
   */
  protected function isRecordYseUserdataCacheable() {
    // Check plugin settings first, if they override general settings.
    if ($this->configuration['cache']['override']) {
      $settings = $this->configuration['cache']['settings'];
      if (!$settings['enabled']) {
        return FALSE;
      }
    }

    // Use general settings if they are not overridden by plugin.
    if (!isset($settings)) {
      $settings = $this->configFactory->get('yse_userdata.settings')->get('userdata_cache');
      if (!$settings['enabled']) {
        return FALSE;
      }
    }

    if (empty($this->netid) && empty($this->getNetid())){
      // I would put error here but this gets called to see if a cache lookup is possible, and so
      // this means every request.
      return FALSE;
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchUserdata($key = NULL) {
    // fetchUserdata sources data from another source than user.data, the returned data can be cached 
    // and stored in later calls for use in get/set operations on cache or user.data store
    if (!$this->getLookupkey()) {
      throw new YseUserdataException("No Lookup key specified", $this->getPluginId(), __FUNCTION__);
    }
    if ($this->userdata === NULL) {
      // Userdata has not been loaded yet. Try loading it from cache first.
      $this->loadUserdataFromCache();
    }
    if ($this->userdata === NULL && $this->isUserdataLoaded !== YseUserdataInterface::LOADED_FROM_QUERY) {
      // Userdata has not been loaded yet. Try loading it from file if Lookupkey is
      // defined and a read attempt was not made yet.
      $this->loadUserdataFromQuery();
    }
    return $this->dofetchUserdata($key);
  }

  /**
   * Gets a Userdata element.
   *
   * @param mixed|null $key
   *   A key to determine the Userdata element to be returned. If NULL, the
   *   entire Userdata will be returned.
   *
   * @return mixed|null
   *   The value of the element specified by $key. If $key is NULL, the entire
   *   Userdata. If no Userdata is available, return NULL.
   */
  abstract protected function dofetchUserdata($key = NULL);


  /**
   * {@inheritdoc}
   */
  public function saveUserdataToCache(array $tags = []) {
    if ($this->userdata === NULL) {
      return FALSE;
    }

    // object needs to have netid to get past this, refactor for better prior checks
    // and/or type definitions for userdata.
    if (empty($this->netid) && empty($this->getNetid())){  
      $this->logger->error('@plugin_id userdata must have netid to save to cache.', [
        '@plugin_id' => $this->getPluginId() ?? ''
      ]);
      return FALSE;
    }

    if (($cache_settings = $this->isRecordYseUserdataCacheable()) === FALSE) {
      return FALSE;
    }
    if ($this->isUserdataLoaded !== YseUserdataInterface::LOADED_FROM_CACHE || ($this->isUserdataLoaded === YseUserdataInterface::LOADED_FROM_CACHE && $this->hasUserdataChangedFromCacheVersion)) {
      $tags = ["{$this->getPluginId()}:{$this->netid}"];
      $expire = time() + $cache_settings['expiration'];
      $this->cache->set("hash:{$this->getPluginId()}:{$this->netid}", $this->fetchUserdataToCache(), $expire, $tags);
      $this->hasUserdataChangedFromCacheVersion = FALSE;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * returns Userdata to save to cache.
   *
   * @return mixed
   *   The Userdata to be cached.
   */
  protected function fetchUserdataToCache() {
    // reserve the right to override this per plugin?
    // possibly too late to manipulate for netid property setting.
    return $this->userdata;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCachedUserdata() {
    if ($this->isRecordYseUserdataCacheable() === FALSE) {
      return FALSE;
    }
    $plugin_id = $this->getPluginId();
    $this->cache->delete("hash:{$plugin_id}:{$this->lookupkey}");
    $this->hasUserdataChangedFromCacheVersion = FALSE;
    return TRUE;
  }

}
