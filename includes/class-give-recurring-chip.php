<?php

use Give\Log\ValueObjects\LogType;

class Give_Recurring_Chip extends Give_Recurring_Gateway
{

  public $chip_purchase;

  private static $_instance;

  public static function get_instance()
  {
    if (static::$_instance == null) {
      static::$_instance = new static();
    }

    return static::$_instance;
  }

  public function init()
  {

    $this->id = 'chip';
    $this->offsite = true;

    add_action("give_recurring_cancel_{$this->id}_subscription", array($this, 'cancel'), 10, 2);
  }

  public function cancel($subscription, $valid)
  {

    // Bailout, if no access cancel subscription.
    if (empty($valid)) {
      return false;
    }

    // Call CHIP cancel recurring with:
    // $subscription->profile_id;
    // return true if success and false if otherwise
    return true;
  }

  public function can_cancel($ret, $subscription)
  {

    if (
      $subscription->gateway === $this->id &&
      !empty($subscription->profile_id) &&
      'active' === $subscription->status
    ) {
      $ret = true;
    }

    return $ret;
  }

  public function create_payment_profiles()
  {
    error_log("subsciption");
    error_log(print_r($this->subscriptions, true));
    error_log('create_payment_profiles');

    $error_id = 1;
    $form_id = $this->subscriptions['form_id'];
    $payment_data = $this->purchase_data;
    $customization = give_get_meta($form_id, '_give_customize_chip_donations', true);

    $prefix = '';
    if (give_is_setting_enabled($customization)) {
      $prefix = '_give_';
    }

    $secret_key = give_is_test_mode() ? Chip_Givewp_Helper::get_fields($form_id, 'chip-test-secret-key', $prefix) : Chip_Givewp_Helper::get_fields($form_id, 'chip-secret-key', $prefix);
    $brand_id  = Chip_Givewp_Helper::get_fields($form_id, 'chip-brand-id', $prefix);
    // $chip = Chip_Givewp_API::get_instance('', '');
    $chip = Chip_Givewp_API::get_instance($secret_key, $brand_id);

    $get_client_by_email = $chip->get_client_by_email($this->purchase_data['user_email']);

    if (array_key_exists('__all__', $get_client_by_email)) {
      give_set_error($error_id++, __('Invalid Secret Key', 'chip-for-givewp'));
      return;
    }

    if (is_array($get_client_by_email['results']) and !empty($get_client_by_email['results'])) {
      $client = $get_client_by_email['results'][0];
    } else {
      $client = $chip->create_client(array(
        'full_name' => substr($this->purchase_data['post_data']['give_first'] . ' ' . $this->purchase_data['post_data']['give_last'], 0, 30),
        'email' => $this->purchase_data['post_data']['give_email'],
      ));
    }

    if (array_key_exists('__all__', $client)) {
      give_set_error($error_id++, __('Failed to retrieve client', 'chip-for-givewp'));
      return;
    }
    $donation_id = $this->payment_id;

    $redirect_url = $listener->get_redirect_url(array('donation_id' => $this->payment_id));
    $callback_url = $listener->get_callback_url(array('donation_id' => $this->payment_id));

    // default recurring
    // $due_strict   = Chip_Givewp_Helper::get_fields($form_id, 'chip-due-strict', $prefix);
    // $send_receipt = Chip_Givewp_Helper::get_fields($form_id, 'chip-send-receipt', $prefix);


    // from normal payment
    $due_strict        = Chip_Givewp_Helper::get_fields($form_id, 'chip-due-strict', $prefix);
    $due_strict_timing = Chip_Givewp_Helper::get_fields($form_id, 'chip-due-strict-timing', $prefix);
    $send_receipt      = Chip_Givewp_Helper::get_fields($form_id, 'chip-send-receipt', $prefix);
    $billing_fields    = Chip_Givewp_Helper::get_fields($form_id, 'chip-enable-billing-fields', $prefix);
    $currency        = give_get_currency($form_id, $payment_data);

    // $billing_template_params = array(
    //   'success_redirect' => $redirect_url,
    //   'failure_redirect' => $redirect_url,
    //   'cancel_redirect'  => $redirect_url,
    //   'purchase' => array(
    //     'currency' => give_get_currency( $form_id, $this->purchase_data ),
    //     'products' => array(
    //       array(
    //         'name'  => give_payment_gateway_item_title( $this->purchase_data, 256 ),
    //         'price' => round( $this->purchase_data['price'] * 100 ),
    //       )
    //     ),
    //     'notes'      => 'Purchase: ' . $this->purchase_data['purchase_key'],
    //     'timezone'   => 'Asia/Kuala_Lumpur',
    //     'due_strict' => give_is_setting_enabled( $due_strict )
    //   ),
    //   'creator_agent'   => 'GiveWP Recurring: ' . GWP_CHIP_MODULE_VERSION,
    //   'platform'        => 'givewp',
    //   'brand_id'        => $brand_id,
    //   'title'           => substr( give_payment_gateway_item_title( $this->purchase_data, 128 ) . ' ' . $this->purchase_data['post_data']['give_first'] . ' ' . $this->purchase_data['post_data']['give_last'], 0, 256 ),
    //   'is_subscription' => true,
    //   'subscription_period' => $this->get_subscription_period($this->subscriptions['period'], $this->subscriptions['frequency']),
    //   'subscription_period_units' => $this->get_subscription_period_units($this->subscriptions['period']),
    //   'subscription_due_period' => $this->get_subscription_period($this->subscriptions['period'], $this->subscriptions['frequency']),
    //   'subscription_due_period_units' => $this->get_subscription_period_units($this->subscriptions['period']),
    //   'subscription_charge_period_end' => false,
    //   'subscription_trial_periods' => 0,
    //   'subscription_active' => true,
    //   'force_recurring' => true,
    //   'number_of_billing_cycles' => $this->purchase_data['times']
    // );

    // $billing_templates = $chip->create_billing_templates($billing_template_params);

    // $purchase = $chip->add_subscriber($billing_templates['id'], array(
    //   'client_id' => $client['id'],
    //   'send_invoice_on_charge_failure' => true,
    //   'send_invoice_on_add_subscriber' => true,
    //   'send_receipt' => give_is_setting_enabled( $send_receipt ),
    //   'payment_method_whitelist' => ['visa', 'mastercard'],
    // ));

    // This is a temporary ID used to look it up later during webhook events
    // $this->subscriptions['profile_id'] = $billing_templates['id'];
    // $this->subscriptions['transaction_id'] = $purchase['purchase']['id'];

    // $this->chip_purchase = $purchase;

    // Give()->session->set('chip_id', $purchase['purchase']['id']);
    // Give()->session->set('donation_id', $this->payment_id);



    $listener     = Chip_Givewp_Recurring_Listener::get_instance();
    // from normal payment
    $params = array(
      'success_callback' => $listener->get_callback_url(array('donation_id' => $donation_id, 'status' => 'paid')),
      'success_redirect' => $listener->get_redirect_url(array('donation_id' => $donation_id, 'nonce' => $payment_data['gateway_nonce'])),
      'failure_redirect' => $listener->get_redirect_url(array('donation_id' => $donation_id, 'status' => 'error')),
      'creator_agent'    => 'GiveWP: ' . GWP_CHIP_MODULE_VERSION,
      'reference'        => substr($donation_id, 0, 128),
      'platform'         => 'givewp',
      'send_receipt'     => give_is_setting_enabled($send_receipt),
      'due'              => time() + (absint($due_strict_timing) * 60),
      'brand_id'         => $brand_id,
      'client'           => [
        'email'          => $payment_data['user_email'],
        'full_name'      => substr($payment_data['user_info']['first_name'] . ' ' . $payment_data['user_info']['last_name'], 0, 30),
      ],
      'purchase'         => array(
        'timezone'   => apply_filters('gwp_chip_purchase_timezone', $this->get_timezone()),
        'currency'   => $currency,
        'due_strict' => give_is_setting_enabled($due_strict),
        'products'   => array([
          'name'     => substr(give_payment_gateway_item_title($payment_data), 0, 256),
          'price'    => round($payment_data['price'] * 100),
          'quantity' => '1',
        ]),
      ),
    );

    error_log("params");
    error_log(print_r($params, true));

    if (give_is_setting_enabled($billing_fields)) {
      $params['client']['street_address'] = substr($payment_data['post_data']['card_address'] ?? 'Address' . ' ' . ($payment_data['post_data']['card_address_2'] ?? ''), 0, 128);
      $params['client']['country']        = $payment_data['post_data']['billing_country'] ?? 'MY';
      $params['client']['city']           = $payment_data['post_data']['card_city'] ?? 'Kuala Lumpur';
      $params['client']['zip_code']       = $payment_data['post_data']['card_zip'] ?? '10000';
      $params['client']['state']          = substr($payment_data['post_data']['card_state'], 0, 2) ?? 'KL';
    }

    $params = apply_filters('gwp_chip_purchase_params', $params, $payment_data, $this);

    $chip = Chip_Givewp_API::get_instance($secret_key, $brand_id);

    $payment = $chip->create_payment($params);

    Give()->session->set('donation_id', $payment['id']);

    if (!array_key_exists('id', $payment)) {

      Chip_Givewp_Helper::log($form_id, LogType::ERROR, sprintf(__('Unable to create purchases: %s', 'chip-for-givewp'), print_r($payment, true)));

      give_insert_payment_note($donation_id, __('Failed to create purchase.', 'chip-for-givewp'));
      give_send_back_to_checkout('?payment-mode=chip');
    }

    Chip_Givewp_Helper::log($form_id, LogType::HTTP, sprintf(__('Recurring: Create purchases success for donation id %1$s', 'chip-for-givewp'), $donation_id), $payment);

    give_update_meta($donation_id, '_chip_purchase_id', $payment['id'], '', 'donation');


    if (give_is_test_mode()) {
      give_insert_payment_note($donation_id, __('This is test environment where payment status is simulated.', 'chip-for-givewp'));
    }
    give_insert_payment_note($donation_id, sprintf(__('URL: %1$s', 'chip-for-givewp'), $payment['checkout_url']));

    wp_redirect(esc_url_raw(apply_filters('gwp_chip_checkout_url', $payment['checkout_url'], $payment, $payment_data)));
    give_die();
  }

  private function get_subscription_period($subscription, $frequency)
  {
    if (in_array($subscription, array('day', 'week', 'month'))) {
      return $frequency;
    } elseif ($subscription == 'quarter') {
      return $frequency * 3;
    }

    return $frequency * 12;
  }

  private function get_subscription_period_units($subscription)
  {
    if ($subscription == 'day') {
      return 'days';
    } elseif ($subscription == 'week') {
      return 'weeks';
    }

    return 'months';
  }

  public function link_profile_id($profile_id, $subscription)
  {

    if (!empty($profile_id)) {
      $html       = '<a href="%s" target="_blank">' . $profile_id . '</a>';
      $base_url   = 'https://gate.chip-in.asia/t/';
      $link       = esc_url($base_url . '56d72c15-c42b-4214-877d-38b811f20392/billing/' . $profile_id . '/');
      $profile_id = sprintf($html, $link);
    }

    return $profile_id;
  }

  public function complete_signup()
  {
    wp_redirect($this->chip_purchase['purchase']['checkout_url']);
    exit;
  }

  private function get_timezone()
  {
    if (preg_match('/^[A-z]+\/[A-z\_\/\-]+$/', wp_timezone_string())) {
      return wp_timezone_string();
    }

    return 'UTC';
  }
}

Give_Recurring_Chip::get_instance();
