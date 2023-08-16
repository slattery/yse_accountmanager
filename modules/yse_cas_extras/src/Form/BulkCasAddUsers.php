<?php

namespace Drupal\yse_cas_extras\Form;

use Drupal\cas\CasPropertyBag;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Exception\CasLoginException;
use Drupal\cas\Service\CasHelper;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\user\RoleInterface;

/**
 * Class BulkAddCasUsers.
 *
 * A form for bulk registering CAS users.
 */
class BulkCasAddUsers extends FormBase {


  /**
   * The CAS Helper.
   *
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bulk_add_cas_users_yse_cas_extras';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}


/**
 * {@inheritdoc}
 */
public static function parseLines(array &$form, FormStateInterface $form_state) {
    $roles = array_filter($form_state->getValue('roles'));
    unset($roles[RoleInterface::AUTHENTICATED_ID]);
    $roles = array_keys($roles);

    $cas_usernames = trim($form_state->getValue('cas_usernames'));
    $cas_usernames = preg_split('/[\n\r|\r|\n]+/', $cas_usernames);

    $email_hostname = trim($form_state->getValue('email_hostname'));

    $operations = [];
    // $cas_usernames are netids
    foreach ($cas_usernames as $cas_username) {
      $cas_username = trim($cas_username);
      if (!empty($cas_username)) {
        $operations[] = [
          '\Drupal\yse_cas_extras\Form\BulkCasAddUsers::userAdd',
          [$cas_username, $roles, $email_hostname],
        ];
      }
    }

    $batch = [
      'title' => t('Creating YSE CAS users...'),
      'operations' => $operations,
      'finished' => '\Drupal\cas\Form\BulkAddCasUsers::userAddFinished',
      'progress_message' => t('Processed @current out of @total.'),
    ];

    batch_set($batch);
  }

  /**
   * Perform a single CAS user creation batch operation.
   *
   * Callback for batch_set().
   *
   * @param string $cas_username
   *   The CAS username, which will also become the Drupal username.
   * @param array $roles
   *   An array of roles to assign to the user.
   * @param string $email_hostname
   *   The hostname to combine with the username to create the email address.
   * @param array $context
   *   The batch context array, passed by reference.
   */
  public static function userAdd($cas_username, array $roles, $email_hostname, array &$context) {
    $cas_user_manager = \Drupal::service('cas.user_manager');
    //$cas_bag_handler  = \Drupal::service('yse_cas_extras.baggagehandler');
    $event_dispatcher = \Drupal::service('event_dispatcher');

    // skip an line if drupal user found.
    $existing_uid = $cas_user_manager->getUidForCasUsername($cas_username);
    if ($existing_uid && $context) {
      $context['results']['messages']['already_exists'][] = $cas_username;
      return;
    }

    // Create event with baggage and ship any roles from the form
    // We model our dispatch on the autoregister portion of the cas.user_manager service login
    // We build the email ourselves without a ticket to parse.
    $cas_init = $cas_username . '@' . $email_hostname;
    $cas_atts = ['roles' => $roles, 'mail' => $cas_init ];

   // $cas_property_bag = $cas_bag_handler->openNewCasPropertyBag($cas_username, $cas_atts);
    $cas_property_bag = new CasPropertyBag($cas_username);
    $cas_property_bag->setAttributes($cas_atts);
    $cas_pre_register_event = new CasPreRegisterEvent($cas_property_bag);
    // we need to preserve manual role choices and have a fallback email
    $cas_pre_register_event->setPropertyValues($cas_atts);

    $event_dispatcher->dispatch($cas_pre_register_event, CasHelper::EVENT_PRE_REGISTER);
    // the dispatched event should trigger the subscriber to populate the CAS attributes  
    // it is assumed that the event object can be passed on and used below with those attributes.

    try {
      /** @var \Drupal\user\UserInterface $user */
     //uncomment to find out what we have
     //dpr($cas_pre_register_event->getCasPropertyBag(), $return = FALSE, $name = 'bulkaddatts');
     //dpr($cas_pre_register_event->getPropertyValues(), $return = FALSE, $name = 'bulkaddprops');

      $user = $cas_user_manager->register($cas_property_bag->getOriginalUsername(), $cas_pre_register_event->getDrupalUsername(), $cas_pre_register_event->getPropertyValues());

      // ExternalAuthRegisterEvent: after successful user entity creation,
      // a subscriber for ExternalAuthRegisterEvent will take care of user.data
      if ($context){
        $context['results']['messages']['created'][] = $user->toLink()->toString();
      }
    }
    catch (CasLoginException $e) {
      // catching CasLoginException because cas already passes on the ExternalAuthRegisterException
      // with message from CasLoginException::USERNAME_ALREADY_EXISTS) but we test above so not sure why.

      \Drupal::logger('cas')->error('ExternalAuthRegisterException when registering user with name %name: %e', [
        '%name' => $cas_username,
        '%e' => $e->getMessage(),
      ]);

      if ($context){
        $context['results']['messages']['errors'][] = $cas_username;
      }
      return;
    }
  }

   /**
   * Complete CAS user creation batch process.
   *
   * Callback for batch_set().
   *
   * Consolidates message output.
   */
  public static function userAddFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      if (!empty($results['messages']['errors'])) {
        $messenger->addError(t(
          'An error was encountered creating accounts for the following users (check logs for more details): %usernames',
          ['%usernames' => implode(', ', $results['messages']['errors'])]
        ));
      }
      if (!empty($results['messages']['already_exists'])) {
        $messenger->addError(t(
          'The following accounts were not registered because existing accounts are already using the usernames: %usernames',
          ['%usernames' => implode(', ', $results['messages']['already_exists'])]
        ));
      }
      if (!empty($results['messages']['created'])) {
        $userLinks = Markup::create(implode(', ', $results['messages']['created']));
        $messenger->addStatus(t(
          'Successfully created accounts for the following usernames: %usernames',
          ['%usernames' => $userLinks]
        ));
      }
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addError(t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]));
    }
  }
}

