<?php

use Give\Log\ValueObjects\LogType;
use Give\Subscriptions\Models\Subscription;
use Give\Subscriptions\ValueObjects\SubscriptionPeriod;
use Give\Subscriptions\ValueObjects\SubscriptionMode;
use Give\Subscriptions\ValueObjects\SubscriptionStatus;
use Give\Framework\Support\ValueObjects\Money;

use Give\Donations\Models\Donation;
use Give\Donations\ValueObjects\DonationType;
use Give\Donations\ValueObjects\DonationStatus;

class Chip_Givewp_Recurring_Listener
{

  private static $_instance;

  const CALLBACK_KEY = 'chip-for-givewp-recurring-callback';
  const CALLBACK_PASSPHRASE = 'chip-for-givewp-recurring-webhook';

  const REDIRECT_KEY = 'chip-for-givewp-recurring-redirect';
  const REDIRECT_PASSPHRASE = 'chip-for-givewp-recurring-redirect';


  // const CALLBACK_KEY = 'chip-for-givewp-callback';
  // const CALLBACK_PASSPHRASE = 'chip-for-givewp-webhook';

  // const REDIRECT_KEY = 'chip-for-givewp-redirect';
  // const REDIRECT_PASSPHRASE = 'chip-for-givewp-redirect';


  public static function get_instance()
  {
    if (self::$_instance == null) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct()
  {
    add_action('init', array($this, 'handle_callback'));
    add_action('init', array($this, 'handle_redirect'));
  }

  public function get_callback_url(array $params)
  {

    $passphrase = get_option(self::CALLBACK_PASSPHRASE, false);
    if (!$passphrase) {
      $passphrase = md5(site_url() . time());
      update_option(self::CALLBACK_PASSPHRASE, $passphrase);
    }

    $params[self::CALLBACK_KEY] = $passphrase;

    return add_query_arg($params, site_url('/'));
  }

  public function get_redirect_url($params)
  {
    $params[self::REDIRECT_KEY] = self::REDIRECT_PASSPHRASE;
    return add_query_arg($params, site_url('/'));
  }

  public function handle_redirect()
  {



    if (!isset($_GET[self::REDIRECT_KEY])) {
      return;
    }

    if ($_GET[self::REDIRECT_KEY] != self::REDIRECT_PASSPHRASE) {
      return;
    }

    Chip_Givewp_Helper::log(null, LogType::INFO, __('Recurring Redirect received', 'chip-for-givewp'));

    $this->handle_processing();
  }

  public function handle_callback()
  {
    if (!isset($_GET[self::CALLBACK_KEY])) {
      return;
    }

    $passphrase = get_option(self::CALLBACK_PASSPHRASE, false);
    if (!$passphrase) {
      return;
    }

    if ($_GET[self::CALLBACK_KEY] != $passphrase) {
      Chip_Givewp_Helper::log(null, LogType::NOTICE, __('Recurring callback failed due to invalid passphrase: %1$s', 'chip-for-givewp'));
      return;
    }

    Chip_Givewp_Helper::log(null, LogType::INFO, __('Recurring callback received', 'chip-for-givewp'));

    $this->handle_processing();
  }

  // private function handle_processing() {
  //   if ( !isset( $_GET['donation_id'] ) ) {
  //     Chip_Givewp_Helper::log( null, LogType::ERROR, __( 'Recurring: Processing halted due to empty donation id', 'chip-for-givewp' ) );
  //     status_header(403);
  //     exit;
  //   }

  //   $donation_id = absint( $_GET['donation_id'] );

  //   $payment_gateway = give_get_payment_gateway( $donation_id );

  //   if ( $payment_gateway != 'chip' ) {
  //     Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Recurring: Processing halted as payment gateway is not chip', 'chip-for-givewp' ) );
  //     exit;
  //   }

  //   $payment_id = Give()->session->get( 'chip_id' );
  //   $session_donation_id = Give()->session->get( 'donation_id' );

  //   if ( $payment_id ) {
  //     Give()->session->set( 'chip_id', false );
  //   }

  //   if ( $session_donation_id ) {
  //     Give()->session->set( 'donation_id', false );
  //   }

  //   if ( !empty($session_donation_id) && $donation_id != $session_donation_id) {
  //     Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __( 'Recurring: Session donation not match with donation id!', 'chip-for-givewp' ) );
  //     give_die( __('Recurring: Session donation not match with donation id!', 'chip-for-givewp') );
  //   }

  //   $form_id = give_get_payment_form_id( $donation_id );
  //   $customization = give_get_meta( $form_id, '_give_customize_chip_donations', true );

  //   $prefix = '';
  //   if ( give_is_setting_enabled( $customization ) ) {
  //     $prefix = '_give_';
  //   }

  //   $secret_key = give_is_test_mode() ? Chip_Givewp_Helper::get_fields($form_id, 'chip-test-secret-key', $prefix) : Chip_Givewp_Helper::get_fields($form_id, 'chip-secret-key', $prefix);

  //   $chip = Chip_Givewp_API::get_instance($secret_key, '');

  //   error_log("GET Data: ");
  //   error_log(print_r($_GET, true));

  //   $payment_id = sanitize_key( $_GET['id'] );

  //   $payment = $chip->get_payment( $payment_id );

  //   if ( give_get_payment_total( $donation_id ) != round($payment['purchase']['total'] / 100, give_get_price_decimals( $donation_id )) ) {
  //     Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __('Recurring: Payment total does not match!', 'chip-for-givewp'), $payment );
  //     give_die( __('Payment total does not match!', 'chip-for-givewp'));
  //   }

  //   if ( $payment['status'] != 'paid' ) {
  //     Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __('Recurring: Status updated to failed', 'chip-for-givewp'), $payment );

  //     give_update_payment_status( $donation_id, 'failed' );
  //     wp_safe_redirect( give_get_failed_transaction_uri('?payment-id=' . $donation_id) );
  //     exit;
  //   }

  //   $GLOBALS['wpdb']->get_results(
  //     "SELECT GET_LOCK('gwp_chip_payment_$donation_id', 15);"
  //   );

  //   if ( !give_is_payment_complete( $donation_id ) ) {
  //     if ($payment['status'] == 'paid') {

  //       Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __('Recurring: Status updated to publish', 'chip-for-givewp'), $payment );

  //       $give_payment = new Give_Payment( $donation_id );

  //       if ( $give_payment && $give_payment->ID > 0 ) {

  //         $give_payment->status         = 'publish';
  //         $give_payment->transaction_id = $payment['id'];
  //         $give_payment->save();

  //       }

  //       $subscription = new Give_Subscription( $payment['billing_template_id'], true );

  //       $args = array(
  //         'amount'         => $payment['payment']['amount'],
  //         'transaction_id' => $payment['id'],
  //         'post_date'      => date_i18n( 'Y-m-d H:i:s', $payment['payment']['paid_on'] ),
  //       );

  //       $subscription->add_payment( $args );
  //       $subscription->renew();
  //     }
  //   }

  //   $GLOBALS['wpdb']->get_results(
  //     "SELECT RELEASE_LOCK('gwp_chip_payment_$donation_id');"
  //   );

  //   $return = add_query_arg(
  //     array(
  //       'payment-confirmation' => 'chip',
  //       'payment-id' => $donation_id,
  //     ), give_get_success_page_uri()
  //   );

  //   Chip_Givewp_Helper::log( $donation_id, LogType::INFO, __('Recurring: Processing completed', 'chip-for-givewp'), $payment );

  //   wp_safe_redirect( $return );
  //   exit;
  // }

  private function handle_processing()
  {
    if (!isset($_GET['donation_id'])) {
      Chip_Givewp_Helper::log(null, LogType::ERROR, __('Processing halted due to empty donation id', 'chip-for-givewp'));
      status_header(403);
      exit;
    }

    $donation_id = absint($_GET['donation_id']);

    $payment_gateway = give_get_payment_gateway($donation_id);

    // donor id should be in integer
    $donorID = give_get_payment_donor_id($donation_id);
    // change to integer
    $donorID = intval($donorID);




    if ($payment_gateway != 'chip') {
      Chip_Givewp_Helper::log($donation_id, LogType::ERROR, __('Processing halted as payment gateway is not chip', 'chip-for-givewp'));
      exit;
    }

    $payment_id = give_get_meta($donation_id, '_chip_purchase_id', true, false, 'donation');

    if (isset($_SERVER['HTTP_X_SIGNATURE'])) {
      $form_id = give_get_payment_form_id($donation_id);
      $customization = give_get_meta($form_id, '_give_customize_chip_donations', true);

      $prefix = '';
      if (give_is_setting_enabled($customization)) {
        $prefix = '_give_';
      }

      $secret_key = give_is_test_mode() ? Chip_Givewp_Helper::get_fields($form_id, 'chip-test-secret-key', $prefix) : Chip_Givewp_Helper::get_fields($form_id, 'chip-secret-key', $prefix);
      $ten_secret_key = substr($secret_key, 0, 10);

      if (empty($public_key = Chip_Givewp_Helper::get_fields($form_id, 'chip-public-key' . $ten_secret_key, $prefix))) {
        $chip = Chip_Givewp_API::get_instance($secret_key, '');
        $public_key = str_replace('\n', "\n", $chip->get_public_key());

        Chip_Givewp_Helper::log($donation_id, LogType::INFO, __('Public key successfully fetched', 'chip-for-givewp'));

        Chip_Givewp_Helper::update_fields($form_id, 'chip-secret-key' . $ten_secret_key, $public_key, $prefix);
      }

      $content = file_get_contents('php://input');

      if (openssl_verify($content,  base64_decode($_SERVER['HTTP_X_SIGNATURE']), $public_key, 'sha256WithRSAEncryption') != 1) {
        $message = __('Success callback failed to be processed due to failure in verification.', 'chip-for-givewp');

        Chip_Givewp_Helper::log($donation_id, LogType::ERROR, $message);

        give_die($message, __('Failed verification', 'chip-for-givewp'), 403);
      }

      $payment    = json_decode($content, true);
      $payment_id = array_key_exists('id', $payment) ? sanitize_key($payment['id']) : '';

      Chip_Givewp_Helper::log($donation_id, LogType::INFO, __('Callback message successfully validated', 'chip-for-givewp'), $payment);
    } elseif ($payment_id) {

      $form_id = give_get_payment_form_id($donation_id);
      $customization = give_get_meta($form_id, '_give_customize_chip_donations', true);

      $prefix = '';
      if (give_is_setting_enabled($customization)) {
        $prefix = '_give_';
      }

      $secret_key = give_is_test_mode() ? Chip_Givewp_Helper::get_fields($form_id, 'chip-test-secret-key', $prefix) : Chip_Givewp_Helper::get_fields($form_id, 'chip-secret-key', $prefix);

      $chip = Chip_Givewp_API::get_instance($secret_key, '');
      $payment = $chip->get_payment($payment_id);

      Chip_Givewp_Helper::log($donation_id, LogType::HTTP, __('Successfully get purchases', 'chip-for-givewp'), $payment);
    } else {
      Chip_Givewp_Helper::log($donation_id, LogType::ERROR, __('Unexpected response', 'chip-for-givewp'));
      give_die(__('Unexpected response', 'chip-for-givewp'));
    }

    // if ( give_get_payment_key( $donation_id ) != $payment['reference'] ) {
    //   Chip_Givewp_Helper::log( $donation_id, LogType::ERROR, __('Purchase key does not match!', 'chip-for-givewp'), $payment );
    //   give_die( __('Purchase key does not match!', 'chip-for-givewp'));
    // }

    if (give_get_payment_total($donation_id) != round($payment['purchase']['total'] / 100, give_get_price_decimals($donation_id))) {
      Chip_Givewp_Helper::log($donation_id, LogType::ERROR, __('Payment total does not match!', 'chip-for-givewp'), $payment);
      give_die(__('Payment total does not match!', 'chip-for-givewp'));
    }

    if ($payment['status'] != 'paid') {
      Chip_Givewp_Helper::log($donation_id, LogType::INFO, __('Status updated to failed', 'chip-for-givewp'), $payment);

      give_update_payment_status($donation_id, 'failed');
      wp_safe_redirect(give_get_failed_transaction_uri('?payment-id=' . $donation_id));
      exit;
    }


    $session_donation_id = Give()->session->get('donation_id');
    if ($session_donation_id) {
      Give()->session->set('donation_id', false);
    }

    if (!empty($session_donation_id) && $donation_id != $session_donation_id) {
      Chip_Givewp_Helper::log($donation_id, LogType::ERROR, __('Recurring: Session donation not match with donation id!', 'chip-for-givewp'));
      give_die(__('Recurring: Session donation not match with donation id!', 'chip-for-givewp'));
    }


    $GLOBALS['wpdb']->get_results(
      "SELECT GET_LOCK('gwp_chip_payment_$donation_id', 15);"
    );

  

    // return true if give_is_payment_complete( $donation_id ) is 1
    // $isPaymentComplete = give_is_payment_complete( $donation_id ) == "1" ? true : false;

    // log payment data
    error_log("Payment data");
    error_log(print_r($payment, true));

    if (!give_is_payment_complete($donation_id)) {
      error_log("recurring payment complete");
      if ($payment['status'] == 'paid') {
        error_log("payment paid");

        Chip_Givewp_Helper::log($donation_id, LogType::INFO, __('Status updated to publish', 'chip-for-givewp'), $payment);



        // update donation type to subscription



        // args for create subscription
        $timestamp = $payment['payment']['paid_on'];
        $createdAt = new DateTime('@' . $timestamp); // DateTime object for 'now
        $renewTimestamp = $payment['payment']['paid_on'] + 86400; // 86400 seconds = 1 day
        $renewsAt = new DateTime('@' . $renewTimestamp); // DateTime object for 'now


        $attributes = array(
          'donationFormId' => $form_id,
          'createdAt' => $createdAt,
          'renewsAt' => $renewsAt,
          'donorId' => $donorID,
          'period' => SubscriptionPeriod::DAY(),
          'frequency' => 1,
          'installments' => 0,
          'transactionId' => $payment['id'],
          'mode' => SubscriptionMode::LIVE(),
          'amount' => Money::fromDecimal($payment['payment']['amount'] / 100, 'MYR'),
          'feeAmountRecovered' => Money::fromDecimal(0, 'MYR'),
          'status' => SubscriptionStatus::ACTIVE(),
          'gatewaySubscriptionId' => $payment['id'],
          'gatewayId' => 'CHIP',
        );

        error_log("attributes data");
        error_log(print_r($attributes, true));

        // create subscription with donation
        $subscription = Subscription::create($attributes);

        //find donation
        $donation = Donation::find($donation_id);
        $donation->type = DonationType::SUBSCRIPTION();
        $donation->subscriptionId = $subscription->id;
        $donation->status = DonationStatus::COMPLETE();

        // to save donation
        $donation->save();
        give()->subscriptions->updateLegacyParentPaymentId($subscription->id, $donation_id);



        // error_log("subscription data");
        // error_log(print_r($subscription, true));

        // $args = array(
        //   'amount'         => $payment['payment']['amount'],
        //   'transaction_id' => $payment['id'],
        //   'post_date'      => date_i18n('Y-m-d H:i:s', $payment['payment']['paid_on']),
        // );
        // $subscription->save();
      }
    } else {
      error_log("recurring payment not complete");
    }





    $GLOBALS['wpdb']->get_results(
      "SELECT RELEASE_LOCK('gwp_chip_payment_$donation_id');"
    );
    
    $return = add_query_arg(
      array(
        'payment-confirmation' => 'chip',
        'payment-id' => $donation_id,
      ),
      give_get_success_page_uri()
    );


    
    Chip_Givewp_Helper::log($donation_id, LogType::INFO, __('Processing completed', 'chip-for-givewp'), $payment);
    error_log("payment completed");
    wp_safe_redirect($return);
    exit;
  }
}

Chip_Givewp_Recurring_Listener::get_instance();
