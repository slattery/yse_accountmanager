<?php

namespace Drupal\yse_userdata\Plugin\Annotation;


use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Plugin annotation object for YseUserdata plugins.
 *
 * @Annotation
 */
class YseUserdata extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The title of the plugin.
   *
   * The string should be wrapped in a @Translation().
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * An informative description of the plugin.
   *
   * The string should be wrapped in a @Translation().
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $help;

}

