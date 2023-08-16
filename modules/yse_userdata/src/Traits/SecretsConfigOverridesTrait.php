<?php

namespace Drupal\yse_userdata\Traits;

trait SecretsConfigOverridesTrait {
 /**
   * Test for non-secret values
   *
   * If a real value is stored for a config setting, and it is not HIDDEN, then we might need it.
   * TBD use injection and define ys_secrets.manager and configfactory up top
   *
   * @param string $settingset
   *   The settings collection we want the configfactory to look at.
   *
   * @param string $key
   *   The key we check or set in this settings collection.
   *
   * @return string|null
   *   The value retrieved from settings store or secrets store.
   */

  public function substantiate($settingset, $key){
    $preconfigured = $settingset ? \Drupal::configFactory()->getEditable($settingset)->get($key) : NULL;
    //if (\Drupal::hasService('ys_secrets.manager')) {
    //  $secrets = \Drupal::service('ys_secrets.manager');
    //} else {
      $path = \Drupal::service('file_system')->realpath('private://secrets.json');
      $secretsobj = json_decode(file_get_contents($path), TRUE);
    //}
    return self::debunk($preconfigured) ?? $secretsobj[$key];
  }

  /**
   * debunk - crossword answer to "expose as false"
   * 
   * When using the 'HIDDEN' config value to be a placeholder for a secret config value designed 
   * for override in settings.php or ConfigFactoryOverrideInterface, if we find no secret we need
   * to treat 'HIDDEN' as false or null when we want to use a local alternative value
   *
   * TBD move into secrets service
   *
   * @param string $str
   *   The value we check for falsey and 'HIDDEN'.
   *
   * @return string|bool
   *   The value retrieved from settings store or a false
   */

  public function debunk($str){
    return (empty($str) || $str === 'HIDDEN') ? false : $str;
  }
}