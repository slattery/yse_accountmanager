<?php

namespace Drupal\yse_userdata\Plugin\YseUserdata;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\yse_userdata\YseUserdataException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * YseUserdata plugin to fetch data from the Yalesites L7 Service.
 *
 * @YseUserdata(
 *   id = "yalesites_dirproxy",
 *   title = @Translation("Yalesites Directory Proxy Lookup"),
 *   help = @Translation("YSE Userdata plugin to fetch data from the Yalesites L7 Service."),
 * )
 */
class YalesitesDirectoryProxy extends YseUserdataPluginBase {

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Settings array for Directory attributes.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $queryConfig;

  /**
   * The arr.
   *
   * @var array
   */
  protected $userdata;

  /**
   * The lookup string, should represent the upi or netid of person.
   *
   * @var string
   */
  protected $lookupkey = '';

  /**
   * The netid of person.
   *
   * @var string
   */
  protected $netid = '';

  /**
   * Constructs the YalesitesDirectoryProxy plugin.
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
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzzle http client.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, CacheBackendInterface $cache_service, ConfigFactoryInterface $config_factory, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cache_service, $config_factory);
    // querymap below is banking on the config overrides service doing its job before this get
    $this->queryConfig = $config_factory->get('yse_userdata.yse_userdata_plugin.yalesites_directory_proxy');
    $this->httpClient = $http_client;
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
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getExpectedKeys($options = NULL) {
    return ['upi', 'title'];
  }

  /**
   * {@inheritdoc}
   */
  protected function doFetchUserdataFromQuery() {
    if (!$this->lookupkey) {
      throw new YseUserdataException("No lookupkey specified", $this->getPluginId(), __FUNCTION__);
    }

    $userdata = [];
    $ysdirrec = $this->retrieveDirectoryRecord();

    if ($ysdirrec && is_array($ysdirrec)) {
      $userdata = $this->gatherAttributes($ysdirrec);
    }
    elseif ($ysdirrec && is_string($ysdirrec)) {
      //I should pass an Ecxeption here to allow try/catch at the caller.
    }
    else {
      \Drupal::logger('yse_userdata')->notice('Directory returned a malformed result for user %lookupkey', ['%lookupkey' => $this->lookupkey]);
    }



    if (empty($this->netid) && empty($this->getNetid())) {
      if (isset($userdata['netid'])) {
        $this->setNetid($userdata['netid']);
      }
    }

    return $userdata;
  }

  /**
   * Validates a file Userdata key.
   *
   * @return bool
   *   TRUE if the key is valid.
   *
   * @throws \Drupal\yse_userdata\YseUserdataException
   *   In case the key is invalid.
   */
  protected function validateKey($key, $method) {
    if (!is_int($key) && !is_string($key)) {
      throw new YseUserdataException("Invalid Userdata key specified", $this->getPluginId(), $method);
    }
    if (!in_array($key, $this->getExpectedKeys(), TRUE)) {
      throw new YseUserdataException("Invalid Userdata key '{$key}' specified", $this->getPluginId(), $method);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function dofetchUserdata($key = NULL) {
    // awkward to say fetch here but get and set are for the user object only.
    if ($key === NULL) {
      return $this->userdata;
    }
    else {
      $this->validateKey($key, __FUNCTION__);
      return isset($this->userdata[$key]) ? $this->userdata[$key] : NULL;
    }
  }

  /**
   * Returns the record if found
   * SOURCE
   */
  protected function retrieveDirectoryRecord() {

    $path = \Drupal::service('file_system')->realpath('private://secrets.json');
    $secretsobj = json_decode(file_get_contents($path), TRUE);
    //dpr($secretsobj, $return = FALSE, $name = 'shhhh');

    $host = $secretsobj['ys_directory_service_host']; //$this->queryConfig->get('ys_directory_service_host', FALSE);
    $path = $secretsobj['ys_directory_service_path']; //$this->queryConfig->get('ys_directory_service_path', FALSE);
    $name = $secretsobj['ys_directory_service_name']; //$this->queryConfig->get('ys_directory_service_name', FALSE);
    $pass = $secretsobj['ys_directory_service_pass']; //$this->queryConfig->get('ys_directory_service_pass', FALSE);

    // if the lookupkey comes to us as exactly 8 numeric chars we can change the 'lookupkey' parameter key to 'upi'
    // but that would only be useful for migrations, not CAS regulated registrations

    if (!empty($name) && !empty($pass)) {

      $lookupkey = $this->lookupkey;
      $lookuparg = (is_numeric($lookupkey) && strlen($lookupkey) == 8) ? 'upi' : 'netid';

      try {
        $gateway = "https://{$host}/{$path}";
        $response = $this->httpClient->get($gateway, [
          'auth' => [$name, $pass],
          'query' => ['outputformat' => 'json', $lookuparg => $this->lookupkey],
          'headers' => ['Accept' => 'application/json'],
        ]);
        $results = Json::decode($response->getBody(), TRUE);

        if (is_array($results)) {
          if ($results['root']) {
            $errmsg = $results['root']['message'];
            //Exception here for try/catch in caller
            \Drupal::logger('yse_userdata')->notice('Lookup did not return a recordfor %lookupkey: %errmsg', ['%lookupkey' => $this->lookupkey, '%errmsg' => $errmsg]);
          }
          elseif ($results['ServiceResponse']) {
            if ($results['ServiceResponse']['Record'] && is_array($results['ServiceResponse']['Record'])) {
              $record = $results['ServiceResponse']['Record'];
              return $record;
            }
            else {
              \Drupal::logger('yse_userdata')->notice('ServiceResponse did not return a record for %lookupkey', ['%lookupkey' => $this->lookupkey]);
            }
          }
          else {
            \Drupal::logger('yse_userdata')->notice('Server did not contain a record for %lookupkey', ['%lookupkey' => $this->lookupkey]);
            //Exception here for try/catch in caller
            return FALSE;
          }
        }
        else {
          $type = gettype($results);
          \Drupal::logger('yse_userdata')->notice('json_decode failed with data of type %type for user %lookupkey', ['%type' => $type, '%lookupkey' => $this->lookupkey]);
          //Exception here for try/catch in caller
          return FALSE;
        }

      }
      catch (ServerException $e) {
        $errmsg = "Gateway did not like this request.";
        if ($e->hasResponse()) {
          $format = $e->getResponse()->getHeader('Content-Type');
          if (strstr($format, 'xml')) {
            $xmlbod = (string) $e->getResponse()->getBody();
            $errxml = simplexml_load_string($xmlbod, NULL, TRUE);
            $errmsg = $errxml->entry->children('l7', TRUE)->policyresult->attributes()->status;
          }
          if (strstr($format, 'json')) {
            $errjsn = Json::decode($e->getResponse->getBody(), TRUE);
            $errmsg = $errjsn['Error']['Message'];
          }
        }
        \Drupal::logger('yse_userdata')->notice("Error: %err", ['%err', $errmsg]);
      }
      catch (RequestException $e) {
        //network badness
        \Drupal::logger('yse_userdata')->notice("A network error occurred.");
      }
      catch (\Exception $e) {
        // fallback, in case of other exception
        \Drupal::logger('yse_userdata')->notice("An error occurred.");
      }
    }
  }


  /**
   * gatherAttributes
   * Reform a directory record and make it a flat userdata array
   *
   * @param array $rec
   *   The attribute value to compare against.
   *
   */
  protected function gatherAttributes(array $rec) {
    // should have a validate thing here.

    if (is_array($rec)) {

      $user_data = [];
      $side_band = [];

      if (isset($rec['FirstName'])) {
        $user_data['firstname'] = $rec['FirstName'];
      }
      if (isset($rec['LastName'])) {
        $user_data['lastname'] = $rec['LastName'];
      }
      if (isset($rec['FirstName']) && isset($rec['LastName'])) {
        $user_data['name'] = $rec['FirstName'] . ' ' . $rec['LastName'];
      }
      if (isset($rec['EmailAddress'])) {
        $user_data['email'] = $rec['EmailAddress'];
      }
      if (isset($rec['WorkPhone'])) {
        $user_data['phone'] = $rec['WorkPhone'];
      }
      if (isset($rec['DirectoryTitle'])) {
        $user_data['title'] = $rec['DirectoryTitle'];
        $side_band['title'] = strtolower($rec['DirectoryTitle']);
      }
      if (isset($rec['DepartmentName'])) {
        $user_data['department'] = $rec['DepartmentName'];
      }
      elseif (isset($rec['PrimaryDepartmentName'])) {
        $user_data['department'] = $rec['PrimaryDepartmentName'];
      }
      if (isset($rec['PlanningUnitName'])) {
        $user_data['division'] = $rec['PlanningUnitName'];
      }
      elseif (isset($rec['PrimaryDivisionName'])) {
        $user_data['division'] = $rec['PrimaryDivisionName'];
      }
      if (isset($rec['PrimaryAffiliation'])) {
        $user_data['primary_affiliation'] = $rec['PrimaryAffiliation'];
      }
      if (isset($rec['NetId'])) {
        $user_data['netid'] = $rec['NetId'];
      }
      if (isset($rec['Upi'])) {
        $user_data['upi'] = $rec['Upi'];
      }

      // sideband sidebar - post* are considered staff but ydir sets faculty
      if ($side_band['title'] && (preg_match("/postdoc/i", $side_band['title']) || preg_match("/post-doc/i", $side_band['title']) || preg_match("/post doc/i", $side_band['title']))) {
        $side_band['hasrole'] = 'staff';
      }
      elseif ($side_band['title'] && (preg_match("/postgrad/i", $side_band['title']) || preg_match("/post-grad/i", $side_band['title']) || preg_match("/post grad/i", $side_band['title']))) {
        $side_band['hasrole'] = 'staff';
      }


      // set up these atts for Role Mapping
      if ($rec['hasEmployeeRole'] == 'Y') {
        $user_data['hasEmployeeRole'] = 'Y';
      }
      if ($rec['hasStudentRole'] == 'Y') {
        $user_data['hasStudentRole'] = 'Y';
      }
      if ($rec['hasFacultyRole'] == 'Y') {
        if ($side_band['hasrole'] && $side_band['hasrole'] == 'staff') {
          $user_data['hasFacultyRole'] = 'N';
          $user_data['hasStaffRole'] = 'Y';
        }
        else {
          $user_data['hasFacultyRole'] = 'Y';
        }
      }
      if ($rec['hasStaffRole'] == 'Y') {
        $user_data['hasStaffRole'] = 'Y';
      }
      if ($rec['hasAlumnusRole'] == 'Y') {
        $user_data['hasAlumnusRole'] = 'Y';
      }
      if ($rec['hasMemberRole'] == 'Y') {
        $user_data['hasMemberRole'] = 'Y';
      }
      if ($rec['hasAffiliateRole'] == 'Y') {
        $user_data['hasAffiliateRole'] = 'Y';
      }

      return $user_data;
    }
    else {
      \Drupal::logger('yse_userdata')->notice('Web service response is missing "Record" element for user %lookupkey', ['%lookupkey' => $this->lookupkey]);
    }
  }
}
