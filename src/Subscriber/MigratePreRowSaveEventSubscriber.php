<?php
// OMG these almost need to be plugins so we can have field mapping from csv to entity etc.

namespace Drupal\yse_accountmanager\Subscriber;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\migrate\Event\EventBase;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * Class EntityRedirectMigrateSubscriber.
 *
 * @package Drupal\nber_migration\EventSubscriber
 */

class MigratePreRowSaveEventSubscriber implements EventSubscriberInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $acctmgrsettings;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;
  
  /**
   * The yse_userdata logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * EntityRedirectMigrateSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_service
   *   The cache service.
   * @param \Psr\Log\LoggerInterface $logger_interface
   *  The yse_userdata logger.
   */

  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache_service, LoggerInterface $logger_interface) {
    $this->acctmgrsettings = $config_factory->get('yse_accountmanager.settings');
    $this->cache = $cache_service;
    $this->logger = $logger_interface;
  }


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    $events = [];
    $events[MigrateEvents::PRE_ROW_SAVE] = ['onPreRowSave'];
    return $events;
  }
  /**
   * Helper method to check if the current migration has redirects in its source.
   *
   * @param \Drupal\migrate\Event\EventBase $event
   *   The migrate event.
   *
   * @return bool
   *   True if the migration is configured with has_redirects.
   */
  protected function cacheMigrationRows(EventBase $event) : bool {
    $migration = $event->getMigration();
    $source_configuration = $migration->getSourceConfiguration();
    return !empty($source_configuration['cache_rows']) && $source_configuration['cache_rows'] == TRUE;
  }

  /**
   * Maps the existing redirects to the new node id.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   The migrate post row save event.
   */
  public function onPreRowSave(MigratePreRowSaveEvent $event) : void {
    if ($this->cacheMigrationRows($event)) {
      $src  = $event->getSource();
      $row  = $event->getRow();
      $rec  = $row->getSource();

      if ($src->getPluginId() != 'csv'){  // for now...
        return;
      }

      $mid  = $event->getMigration()->id();      //yse_user_import
      $key  = reset(array_keys($src->getIds())); //username
      $rid  = $rec[$key];
      $cfg  = $this->acctmgrsettings->get('accountmanager_cache');
      $tag  = ["{$mid}:{$rid}"];    //900 seconds
      $exp  = time() + $cfg['expiration'];
      $this-logger->notice('attempting cache for mid: @mid and rid: @rid', ['@mid' => $mid, '@rid' => $rid]);
      $this->cache->set("hash:{$mid}:{$rid}", $rec, $exp, $tag);

    }
  }
}