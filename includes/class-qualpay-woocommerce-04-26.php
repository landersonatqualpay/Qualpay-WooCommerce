<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_Qualpay class.
 *
 * @extends WC_Payment_Gateway_CC
 */
class WC_Gateway_Qualpay extends WC_Payment_Gateway_CC
{

    /**
     * Should we capture Credit cards
     *
     * @var bool
     */
    public $capture;

    /**
     * Alternate credit card statement name
     *
     * @var bool
     */
    public $statement_descriptor;

    /**
     * API access secret key
     *
     * @var string
     */
    public $secret_key;
    public $sandbox_secret_key;

    /**
     * Is test mode active?
     *
     * @var bool
     */
    public $testmode;

    /**
     * Logging enabled?
     *
     * @var bool
     */
    public $debug;

    /**
     * Embedded Transient Key.
     *
     * @var string
     */
    private $transient_key = null;

    public $set_error_sandbox_message;

    public $set_error_production_message;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = 'qualpay';
        $this->method_title = __('Qualpay WooCommerce Plugin', 'qualpay');
        $this->method_description = __('The Qualpay for WooCommerce Plugin uses Qualpay\'s Embedded Fields and Payment Gateway to securely process credit card payments. </br>Support for one-time, installment, and recurring transactions are included. Visit the <a target="_BLANK" href="https://help.qualpay.com/help/how-qualpays-woocommerce-plugin-works-for-you" >Qualpay Knowledgebase</a> for more information.', 'qualpay');
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'refunds',
        );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // masking API keys ( sandbox and production )
        if (esc_attr($this->settings['sandbox_secret_key']) != '') {
            $sandbox_key = $this->get_option('sandbox_secret_key');
            if ($sandbox_key != '') {
                $fist_sandbox = substr($sandbox_key, 0, 4);
                $last_sandbox = substr($sandbox_key, -4);
                $this->settings['sandbox_secret_key'] = $fist_sandbox . '****' . $last_sandbox;
            }
        }
        if (esc_attr($this->settings['secret_key']) != '') {
            $secret_key = $this->get_option('secret_key');
            if ($sandbox_key != '') {
                $fist_secret_key = substr($secret_key, 0, 4);
                $last_secret_key = substr($secret_key, -4);
                $this->settings['secret_key'] = $fist_secret_key . '****' . $last_secret_key;
            }
        }

        // Get setting values.
        $this->enabled_logo = $this->get_option('enabled_logo');

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = $this->get_option('testmode');
        $this->capture = 'yes' === $this->get_option('capture', 'yes');
        $this->merchant_id = $this->get_option('merchant_id', '');
        $this->secret_key = $this->get_option('secret_key');
        $this->debug = $this->get_option('debug');
        $this->sandbox_merchant_id = $this->get_option('sandbox_merchant_id', '');
        $this->sandbox_secret_key = $this->get_option('sandbox_secret_key', '');

        if ($this->testmode) {
            $this->description .= ' ' . sprintf(__('TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the documentation "<a href="%s">Testing Qualpay</a>" for more card numbers.', 'qualpay'), 'https://www.qualpay.com/developer/api/testing#test-card-numbers');
            $this->description = trim($this->description);
        }

        // Hooks.
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'), 20);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Get gateway icon.
     *
     * @return string
     */
    public function get_icon()
    {
        $icon_html = '';
        if ($this->enabled_logo == 'yes') {
            $icon_html .= "<a href='https://www.qualpay.com/security' target='_BLANK'><img src='" . plugin_dir_url(__FILE__) . "../assets/qualpay-secure.png'/></a>";
        }
        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
    }

    /**
     * Get the transient key.
     *
     * @return string
     */
    public function get_transient_key()
    {
        if (null === $this->transient_key) {
            $transient_key = Qualpay_API::get_embedded_fields_token();
            if (!is_wp_error($transient_key)) {
                setcookie("set_transient_key", $transient_key->data->transient_key, time() + (60 * 28), '/MAMP/woo-commerce_plugin/');
                $this->transient_key = $transient_key->data->transient_key;
            } else {
                $this->transient_key = false; // We tried. We failed. Let's put false to use the classic form.
            }
        }
        return $this->transient_key;
    }

    /**
     * Qualpay Form
     */
    public function form()
    {
        $order_id_order_pay = absint(get_query_var('order-pay'));
        if ($order_id_order_pay) {
            $order = new WC_Order($order_id_order_pay);
            $Cart_total = $order->get_total();
        } else {
            $Cart_total = WC()->cart->total;
        }
        if ($Cart_total > 0 || (Qualpay_Cart::recurring_in_cart())) {
            $transient_key = $this->get_transient_key();
            $payment_cards = '';
            if (!$transient_key) {
                parent::form();
            } else {
                if (is_user_logged_in()) {
                    $user_id = get_current_user_id();
                    $mid = Qualpay_API::get_merchant_id();
                    $get_customer_id = get_user_meta($user_id, '_qualpay_customer_id');

                    $customer_id = $this->get_customerid_according_env($get_customer_id);

                    if ($customer_id) {
                        $get_customer_billing_cards = Qualpay_API::get_customer_billing_cards($customer_id, $mid);
                        $billing_cards = $get_customer_billing_cards->data->billing_cards;

                        foreach ($billing_cards as $billing_card) {
                            $card_id = $billing_card->card_id;
                            $last4 = substr($billing_card->card_number, -4, 4);
                            $billing_card = $billing_card->card_type;
                            $billing_card_type = $this->get_billing_card_type($billing_card);
                            $payment_cards .= '<input type="radio" id="qp_payment_cards" name="qp_payment_cards" value="' . $card_id . '"> ' . strtoupper($billing_card_type) . ' ( Ending in ' . $last4 . ')<br>';
                        }
                        if (count($billing_cards) > 0) {
                            $payment_cards .= '<input type="radio" id="qp_payment_cards" name="qp_payment_cards" value="credit_card" checked> New Credit Card';
                        }
                    }
                }
                $get_merchant_settings = Qualpay_API::get_merchant_settings($mid);
                $dba_name = $get_merchant_settings->data->dba_name;
                $merch_phone_number = $get_merchant_settings->data->merch_phone;
                $merch_phone_number = $this->format_phone_us($merch_phone_number);
                $date = date('M jS,Y');
                $payment_cards .= '<div id="qp-embedded-container" align="center" style="display: none;"></div>';
                echo '<input type="hidden" id="qualpay_card_id" name="qualpay_card_id" />';
                echo '<input type="hidden" id="capture_id" name="capture_id" value="' . $this->capture . '" />';
                echo $payment_cards;
                $ach_div .= '<br><div id="ach_container" style="display: none;" align="center">';
                $ach_div .= '<input type="checkbox" id="ach_authorize" name="ach_authorize" value="ach_authorize">';
                $ach_div .= ' I authorize ' . $dba_name . ' to initiate a one-time electronic funds transfer for the payment described above from my bank account under the terms of this authorization on ' . $date . ' which will be processed on the next business day. This payment can be revoked by calling ' . $dba_name;
                if($merch_phone_number) {
                $ach_div .= ' at ' . $merch_phone_number;
                }
                $ach_div .= '</div>';
                echo $ach_div;
            }
        } else {
            echo "To process an order for $0.00, you do not need to enter your credit card information";
        }

    }
     

    /**
     * Enqueuing Scripts for Qualpay Embedded Fields
     */
    public function enqueue_scripts()
    {

        if (!is_checkout()) {
            return;
        }
        global $woocommerce;

        $order_id_order_pay = absint(get_query_var('order-pay'));
        if ($order_id_order_pay) {
            $order = new WC_Order($order_id_order_pay);
            $Cart_total = $order->get_total();
        } else {
            $Cart_total = WC()->cart->total;
        }

        if ($Cart_total > 0 || (Qualpay_Cart::recurring_in_cart())) {

            $transient_key = $this->get_transient_key();
            $options = get_option('woocommerce_qualpay_settings');
            if (isset($options['testmode']) && 'no' === $options['testmode']) {
                $mode = 'prod';
            } else {
                $iniFilename = QUALPAY_PATH . "qp.txt";
                $mode = "test";
                if (file_exists($iniFilename)) {
                    $props = parse_ini_file($iniFilename);
                    if (!empty($props['host'])) {
                        $mode = $props['host'];
                    }
                }
                //$mode = 'test';
            }
            if($mode !='prod')
                $env_mode = '-'.$mode;
            else 
                $env_mode = '';

            if ($transient_key) {
                wp_enqueue_style('qualpay-checkout-css', 'https://app'.$env_mode.'.qualpay.com/hosted/embedded/css/qp-embedded.css');
                wp_enqueue_script('qualpay-checkout-js', 'https://app'.$env_mode.'.qualpay.com/hosted/embedded/js/qp-embedded-sdk.min.js', array('jquery'), '', true);
                //wp_enqueue_script('qualpay-checkout-js', untrailingslashit(QUALPAY_URL) . '/assets/js/test_checkout.js', array('jquery'), '' , true);
                wp_localize_script('qualpay-checkout-js', 'qualpay', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('qualpay_nonce'),
                    'merchant_id' => Qualpay_API::get_merchant_id(),
                    'sandbox_merchant_id' => Qualpay_API::get_sandbox_merchant_id(),
                    'transient_key' => $transient_key,
                    'mode' => $mode,
                    'embedded_css' => str_replace(array("\n\r", "\n", "\r"), "", $this->get_option('custom_css')),
                ));
                wp_enqueue_script('qualpay-checkout-form-js', untrailingslashit(QUALPAY_URL) . '/assets/js/checkout.js', array('jquery', 'qualpay-checkout-js'), '', true);
            }
        }
    }

    /**
     * Check if SSL is enabled and notify the user
     */
    public function admin_notices()
    {
        if ('no' === $this->enabled) {
            return;
        }
        if ($this->set_error_production_message) {
            $class = 'notice notice-error';
            $message = __($this->set_error_production_message, 'qualpay');
            printf('<div class="%1$s" style="background: #ab3d3d;color: white;"><p><b>%2$s</b></p></div>', esc_attr($class), esc_html($message));
        }
        if ($this->set_error_sandbox_message) {
            $class = 'notice notice-error';
            $message = __($this->set_error_sandbox_message, 'qualpay');
            printf('<div class="%1$s" style="background: #ab3d3d;color: white;"><p><b>%2$s</b></p></div>', esc_attr($class), esc_html($message));
        }

    }

    /**
     * Process Admin Options
     * If creating or updating a plan, we won't process the default admin options.
     */
    public function process_admin_options()
    {
        // Checking sandbox and production key has a masking IF yes then fetching original data from database and display accordingly..
        if (strpos($_POST['woocommerce_qualpay_sandbox_secret_key'], '****') !== false) {
            $sandbox_secret_key1 = get_option('woocommerce_qualpay_settings');
            $sandbox_merchant_key = $sandbox_secret_key1['sandbox_secret_key'];
            $_POST['woocommerce_qualpay_sandbox_secret_key'] = $sandbox_merchant_key;
        }
        if (strpos($_POST['woocommerce_qualpay_secret_key'], '****') !== false) {
            $secret_key1 = get_option('woocommerce_qualpay_settings');
            $secret_key = $secret_key1['secret_key'];
            $_POST['woocommerce_qualpay_secret_key'] = $secret_key;
        }

        //my changes
        $api = new Qualpay_API();

        if ((!empty($_POST['woocommerce_qualpay_sandbox_merchant_id'])) && (!empty($_POST['woocommerce_qualpay_sandbox_secret_key']))) {
            $sandbox_response = $api->authentication_id_key($_POST['woocommerce_qualpay_sandbox_merchant_id'], $_POST['woocommerce_qualpay_sandbox_secret_key'], 'sandbox');
        }
        if ((!empty($_POST['woocommerce_qualpay_merchant_id'])) && (!empty($_POST['woocommerce_qualpay_secret_key']))) {
            $production_response = $api->authentication_id_key($_POST['woocommerce_qualpay_merchant_id'], $_POST['woocommerce_qualpay_secret_key'], 'production');
        }

        if (isset($sandbox_response->message) && $sandbox_response->message != 'Success') {
            $this->set_error_sandbox_message = 'Sandbox Merchant ID and API Security key are invalid.';
        }

        if (isset($production_response->message) && $production_response->message != 'Success') {
            $this->set_error_production_message = 'Production Merchant ID and API Security key are invalid.';
        }

        //end my changes

        if (isset($_POST['qualpay_plan'])) {
            return $this->process_plan();
        } else {
            return parent::process_admin_options();
        }
    }

    /**
     * Check if this gateway is enabled
     */
    public function is_available()
    {
        if ('yes' === $this->enabled) {

            if ($this->testmode === 'yes') {
                $this->secret_key = $this->sandbox_secret_key;
                $this->merchant_id = $this->sandbox_merchant_id;
                $this->settings['secret_key'] = $this->sandbox_secret_key;
                $this->settings['merchant_id'] = $this->sandbox_merchant_id;
                //return true;
            }
            //echo "<pre>";
            // print_r($this);
            if (!$this->testmode && is_checkout() && !is_ssl()) {
                return false;
            }
            if (!$this->secret_key) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $webhook_url = get_rest_url() . 'qualpay/v1/webhook';

        $this->form_fields = apply_filters('qualpay_wc_settings',
            array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'qualpay'),
                    'label' => __('Enable Qualpay', 'qualpay'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => __('Title', 'qualpay'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'qualpay'),
                    'default' => __('Credit Card (Qualpay)', 'qualpay'),
                    'desc_tip' => true,
                ),
                'enabled_logo' => array(
                    'title' => __('Logo ', 'qualpay'),
                    'label' => __('Display Qualpay Secure logo to the right of the title', 'qualpay'),
                    'type' => 'checkbox',
                    'description' => __('<img src="' . plugin_dir_url(__FILE__) . '../assets/qualpay-secure.png"/>', 'qualpay'),
                    'default' => 'no',
                ),
                'description' => array(
                    'title' => __('Description', 'qualpay'),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', 'qualpay'),
                    'default' => __('Pay with your credit card via Qualpay.', 'qualpay'),
                    'desc_tip' => true,
                ),

                'testmode' => array(
                    'title' => __('Sandbox mode', 'qualpay'),
                    'label' => __('Enable Sandbox Mode', 'qualpay'),
                    'type' => 'checkbox',
                    'description' => __('<b>Note:</b> Plans and users are not shared between sandbox and production environments.<br> If you create plans in the sandbox environment you will need to create them again in the production environment.', 'qualpay'),
                    //'description' => sprintf(__(' <a target="_BLANK" href="%s">Sign Up for Sandbox Account</a>.', 'qualpay'), 'https://app-test.qualpay.com/login/signup'),
                    'default' => 'yes',
                    'desc_tip' => false,
                ),
                'sandbox_merchant_id' => array(
                    'title' => __('Sandbox Merchant ID <br> <a target="_BLANK" href="https://app-test.qualpay.com/login/signup">Sign Up for Sandbox Account</a>', 'qualpay'),
                    'type' => 'text',
                    'description' => __('Get the Sandbox Merchant ID from your Qualpay account.', 'qualpay'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'sandbox_secret_key' => array(
                    'title' => __('Sandbox API Security Key', 'qualpay'),
                    'type' => 'text',
                    'description' => sprintf(__('<a target="_BLANK" href="%s">Working with Qualpay API security keys. </a>', 'qualpay'), 'https://www.qualpay.com/developer/api/security-key'),
                    'default' => '',
                    'desc_tip' => false,
                ),
                'merchant_id' => array(
                    'title' => __('Production Merchant ID  <br> <a target="_BLANK" href="https://www.qualpay.com/get-started">Sign Up for Production Account</a>', 'qualpay'),
                    'type' => 'text',
                    'description' => __('Get the Production Merchant ID from your Qualpay account.', 'qualpay'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'secret_key' => array(
                    'title' => __('Production API Security Key', 'qualpay'),
                    'type' => 'text',
                    'description' => sprintf(__('<a  target="_BLANK" href="%s">Working with Qualpay API security keys.</a>', 'qualpay'), 'https://www-dev.qualpay.com/developer/api/security-key'),
                    'default' => '',
                    'desc_tip' => false,
                ),
                'capture' => array(
                    'title' => __('Capture', 'qualpay'),
                    'label' => __('Capture charge immediately', 'qualpay'),
                    'type' => 'checkbox',
                    'description' => __('Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. All recurring and one-time payments will be captured immediately.', 'qualpay'),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'recurring' => array(
                    'title' => __('Recurring Payments', 'qualpay'),
                    'label' => __('Activate Qualpay recurring payments functionality', 'qualpay'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'desc_tip' => false,
                ),
                'custom_css' => array(
                    'title' => __('Custom CSS', 'qualpay'),
                    'label' => __('Add Custom CSS for Embedded Fields.', 'qualpay'),
                    'type' => 'textarea',
                    'default' => '',
                    'desc_tip' => false,
                    'css' => 'min-height:200px;',
                ),
                'webhook_title' => array(
                    'title' => __('Webhooks', 'qualpay'),
                    'type' => 'title',
                    'description' => sprintf(__('When creating a Webhook use this Notification URL: %s', 'qualpay'), '<code>' . $webhook_url . '</code>'),
                ),
                'sandbox_webhook_secret' => array(
                    'title' => __('Sandbox Webhook Secret', 'qualpay'),
                    'type' => 'text',
                    'description' => __('Copy the secret you have received when creating the Webhook.', 'qualpay'),
                ),
                'webhook_secret' => array(
                    'title' => __('Production Webhook Secret', 'qualpay'),
                    'type' => 'text',
                    'description' => __('Copy the secret you have received when creating the Webhook.', 'qualpay'),
                ),
                'debug_title' => array(
                    'title' => __('Debug', 'qualpay'),
                    'type' => 'title',
                    'description' => __('Settings related to Debugging.', 'qualpay'),
                ),
                'debug' => array(
                    'title' => __('Debug Log', 'qualpay'),
                    'label' => __('Log debug messages', 'qualpay'),
                    'type' => 'checkbox',
                    'description' => __('Save debug messages to the WooCommerce System Status log.', 'qualpay'),
                    'default' => 'no',
                    'desc_tip' => true,
                ),
            )
        );

    }

    /**
     * Process the payment
     *
     * @param int  $order_id Reference.
     * @param bool $retry Should we retry on fail.
     * @param bool $force_customer Force user creation.
     *
     * @throws \Exception If payment will not be accepted.
     *
     * @return array|
     */
    public function process_payment($order_id, $retry = true, $force_customer = false)
    {

        // print_r($_POST);
        // exit;
        $order = wc_get_order($order_id);

        try {
            $response = null;

            // Handle payment.
            if (($order->get_total() > 0) || (Qualpay_Cart::recurring_in_cart())) {

                $api = new Qualpay_API();
                $this->log("Start processing payment for order $order_id for the amount of {$order->get_total()}");

                $order_id_order_pay = absint(get_query_var('order-pay'));
                if ($order_id_order_pay) {

                    $items = $order->get_items();

                    $flag_1else = 0;
                    $flag_else = 0;
                    foreach ($items as $item) {
                        $product_name = $item->get_name();
                        $product_id = $item->get_product_id();
                        $product_variation_id = $item->get_variation_id();

                        if ('yes' === get_post_meta($product_id, '_qualpay', true)) {
                            if ($flag_1else == 0) {
                                // Adding subscription and customer in VT.
                                $response = $this->generate_payment_request_recurring($order);
                                // $response = $api->do_subscription_add($this->generate_payment_request($order));
                            }
                            $flag_1else = 1;
                        } else {
                            if ($flag_else == 0) {
                                if (!$this->capture) {
                                    // Make the request.
                                    $response = $api->do_authorization($this->generate_payment_request($order));
                                } else {
                                    // Make the request.
                                    $response = $api->do_sale($this->generate_payment_request($order));
                                }
                                $flag_else = 1;
                            }
                        }

                        // print_r($response);
                        // exit;
                        if (property_exists($response, 'data') && property_exists($response->data, 'response')) {
                            $response = $response->data->response;
                        }
                        $response_code = '';

                        if (property_exists($response, 'pg_id')) {
                            add_post_meta($order_id, '_qualpay_pg_id', $response->pg_id);
                        }

                    }
                } else {
                    if (Qualpay_Cart::recurring_in_cart()) {
                        $flag_recurring = 0;
                        $flag_1recurring = 0;
                        foreach (WC()->cart->cart_contents as $cart_item) {
                            if (Qualpay_Cart::is_product_recurring($cart_item['product_id'])) {
                                if ($flag_1recurring == 0) {
                                    // Adding subscription and customer in VT.
                                    $response = $this->generate_payment_request_recurring($order);
                                    // $response = $api->do_subscription_add($this->generate_payment_request($order));
                                }
                                $flag_1recurring = 1;
                            } else {
                                if ($flag_recurring == 0) {
                                    if (!$this->capture) {
                                        // Make the request.
                                        $response = $api->do_authorization($this->generate_payment_request($order));
                                    } else {
                                        // Make the request.
                                        $response = $api->do_sale($this->generate_payment_request($order));
                                    }
                                    $flag_recurring = 1;
                                }
                            }
                            //  print_r($response);
                            //  exit;
                            if (property_exists($response, 'data') && property_exists($response->data, 'response')) {
                                $response = $response->data->response;
                            }
                            if (property_exists($response, 'pg_id')) {
                                add_post_meta($order_id, '_qualpay_pg_id', $response->pg_id);
                            }
                        }

                    } else {

                        $items = $order->get_items();

                        $flag_1else = 0;
                        $flag_else = 0;
                        foreach ($items as $item) {
                            $product_name = $item->get_name();
                            $product_id = $item->get_product_id();
                            $product_variation_id = $item->get_variation_id();

                            if (Qualpay_Cart::is_product_recurring($product_id)) {
                                if ($flag_1else == 0) {
                                    // Adding subscription and customer in VT.
                                    $response = $this->generate_payment_request_recurring($order);
                                    // $response = $api->do_subscription_add($this->generate_payment_request($order));
                                }
                                $flag_1else = 1;
                            } else {
                                if ($flag_else == 0) {
                                    if (!$this->capture) {
                                        // Make the request.
                                        $response = $api->do_authorization($this->generate_payment_request($order));
                                    } else {
                                        // Make the request.
                                        $response = $api->do_sale($this->generate_payment_request($order));
                                    }
                                    $flag_else = 1;
                                }
                            }
                            if (property_exists($response, 'data') && property_exists($response->data, 'response')) {
                                $response = $response->data->response;
                            }
                            $response_code = '';

                            if (property_exists($response, 'pg_id')) {
                                add_post_meta($order_id, '_qualpay_pg_id', $response->pg_id);
                            }

                        }
                    }
                }

                if (is_wp_error($response)) {
                    $message = $response->get_error_message();
                    $order->add_order_note($message);
                    throw new Exception($message);
                }

                // Process valid response.
                $this->log('Processing response: ' . print_r($response, true));

                if (property_exists($response, 'data') && property_exists($response->data, 'response')) {
                    $response = $response->data->response;
                }

                $response_code = '';

                if (property_exists($response, 'rcode')) {
                    $response_code = intval($response->rcode);
                } elseif (property_exists($response, 'code')) {
                    $response_code = intval($response->code);
                }

                $order_id = version_compare(WC_VERSION, '3.0.0', '<') ? $order->id : $order->get_id();

                if (0 === $response_code) {
                    /*if (property_exists($response, 'pg_id')) {
                    update_post_meta($order_id, '_qualpay_pg_id', $response->pg_id);
                    } */

                    if (property_exists($response, 'auth_code')) {
                        update_post_meta($order_id, '_qualpay_auth_code', $response->auth_code);
                    }

                    if (!$this->capture) {
                        $items = $order->get_items();
                        foreach ($items as $item) {
                            $product_id = $item->get_product_id();
                            if (Qualpay_Cart::is_product_recurring($product_id)) {
                                $checking_if_1 = '1';
                            } else {
                                $checking_if_2 = '2';
                            }
                        }
                        if ($checking_if_2) {
                            update_post_meta($order_id, '_qualpay_authorized', '1');
                            $order->update_status('on-hold', __('Order Payment Authorized.', 'qualpay'));
                        } else {
                            $auth_code = property_exists($response, 'auth_code') ? $response->auth_code : '';
                            $order->payment_complete($auth_code);
                        }

                    } else {
                        $auth_code = property_exists($response, 'auth_code') ? $response->auth_code : '';
                        $order->payment_complete($auth_code);
                    }

                    $message = sprintf(__('Qualpay charge complete: %s', 'qualpay'), property_exists($response, 'message') ? $response->message : __('Success', 'qualpay'));
                    $order->add_order_note($message);
                    $this->log('Success: ' . $message);

                    if ($this->testmode == 'no') {
                        $mid = Qualpay_API::get_merchant_id();
                        $order->add_order_note(__('This is a Production order.(' . $mid . ')', 'qualpay'));
                    } else {
                        $iniFilename = QUALPAY_PATH . "qp.txt";
                        $env_name = "test";
                        if (file_exists($iniFilename)) {
                            $props = parse_ini_file($iniFilename);
                            if (!empty($props['host'])) {
                                $env_name = $props['host'];
                                $env_name = strtoupper($env_name);
                            }
                        }
                        $mid = Qualpay_API::get_merchant_id();
                        $order->add_order_note(__('This is a ' . $env_name . ' order.(' . $mid . ')', 'qualpay'));
                    }

                } else {
                    update_post_meta($order_id, '_qualpay_pg_id', $response->pg_id);
                    $message = sprintf(__('Qualpay Error response: rcode: %s, rmsg:%s', 'qualpay'), $response->rcode, $response->rmsg);
                    $order->add_order_note($message);
                    $this->log('Error: ' . $message);

                    wc_add_notice('There was an error processing your request. Please contact us.', 'error');

                    return array(
                        'result' => 'fail',
                        'redirect' => '',
                    );

                }
            } else if ((($order->get_total()) == 0) && !(Qualpay_Cart::recurring_in_cart())) {
                // echo "bbbb";exit;
                $order->payment_complete();
            } else {
                $this->log("fail to processing payment for order $order_id for the amount of {$order->get_total()}");
                // $order->payment_complete();
            }

            // Remove cart.
            if (!$_GET['pay_for_order']) {
                WC()->cart->empty_cart();
            }
            do_action('wc_gateway_qualpay_process_payment', $response, $order);

            // Return thank you page redirect.
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );

        } catch (Exception $e) {

            wc_add_notice($e->getMessage(), 'error');
            $this->log(sprintf(__('Error: %s', 'qualpay'), $e->getMessage()));

            do_action('wc_gateway_qualpay_z_error', $e, $order);

            return array(
                'result' => 'fail',
                'redirect' => '',
            );
        }

    }

    /**
     * Refund a charge
     * @param  int $order_id
     * @param  float $amount
     * @return bool
     */
    /* public function process_refund($order_id, $amount = null, $reason = '')
    {
    $order = wc_get_order($order_id);

    // origional code start from here
    if (!$order || !$order->get_transaction_id()) {
    return false;
    }

    $pg_id = get_post_meta($order_id, '_qualpay_pg_id', true);
    if ('' === $pg_id) {
    return false;
    }
    //exit;

    $this->log("Start refund for order $order_id for the amount of {$amount}");
    $api = new Qualpay_API();

    $args = array(
    'pg_id' => $pg_id,
    'amt_tran' => $amount,
    );

    $response = $api->do_refund($args);

    if (is_wp_error($response)) {
    $this->log('Error: ' . $response->get_error_message());
    return $response;
    } elseif ('000' === $response->rcode) {
    $format = __('Payment refunded via Qualpay. Amount: %s, rcode: %s, rmsg: %s.', 'qualpay');
    $refund_message = sprintf($format, $amount, $response->rcode, $response->rmsg);
    $order->add_order_note($refund_message);
    $this->log('Success: ' . html_entity_decode(strip_tags($refund_message)));
    return true;
    }

    } */

    /**
     * Processing new plan while filtering the save section.
     *
     * If we are posting the new plan, then we won't process the save hooks.
     */
    public function process_plan()
    {

        if (!isset($_POST['qualpay_plan'])) {
            return false;
        }

        $qualpay_plan = $_POST['qualpay_plan'];

        $plan_args = array();
        $plan_args['plan_id'] = $qualpay_plan['id'];
        $plan_args['plan_name'] = $qualpay_plan['name'];
        $plan_args['plan_code'] = $qualpay_plan['code'];
        $plan_args['plan_desc'] = $qualpay_plan['desc'];
        $plan_args['plan_frequency'] = absint($qualpay_plan['frequency']);
        switch ($plan_args['plan_frequency']) {
            case 0:
            case 3:
                $plan_args['interval'] = $qualpay_plan['interval'];
                break;
            default:
                $plan_args['interval'] = 0;
                break;
        }
        $plan_args['bill_specific_day'] = (bool) $qualpay_plan['bill_specific_day'];

        /**
         * Specific Days are set.
         */
        if ($plan_args['bill_specific_day']) {
            // If weekly plans.
            if (0 === $plan_args['plan_frequency'] || 1 === $plan_args['plan_frequency']) {
                $plan_args['day_of_week'] = isset($qualpay_plan['day_of_week']) ? absint($qualpay_plan['day_of_week']) : 0;
            } else {
                $plan_args['day_of_month'] = isset($qualpay_plan['day_of_month']) ? absint($qualpay_plan['day_of_month']) : 0;
                // if not month
                if (3 !== $plan_args['plan_frequency']) {
                    $plan_args['month'] = isset($qualpay_plan['month_' . $plan_args['plan_frequency']]) ? absint($qualpay_plan['month_' . $plan_args['plan_frequency']]) : 0;
                }
            }

            $plan_args['prorate_first_pmt'] = isset($qualpay_plan['prorate_first_pmt']) ? true : false;
            if ($plan_args['prorate_first_pmt']) {
                // Not auto calculate?
                if ('false' === $qualpay_plan['calculate']) {
                    $plan_args['amt_prorate'] = isset($qualpay_plan['amt_prorate']) ? floatval($qualpay_plan['amt_prorate']) : 0.00;
                }
            }
        }

        $plan_args['plan_duration'] = $qualpay_plan['duration'] === 'unlimited' ? '-1' : $qualpay_plan['duration_value'];
        $plan_args['amt_setup'] = $qualpay_plan['amt_setup'];
        $plan_args['amt_tran'] = $qualpay_plan['amt_tran'];

        // We also have a trial period.
        if (isset($qualpay_plan['qualpay_plan_trial'])) {
            $plan_args['amt_trial'] = $qualpay_plan['amt_trial'];
            $plan_args['trial_duration'] = $qualpay_plan['trial_duration'];
        }

        if (absint($plan_args['plan_id']) !== 0) {
            $request = Qualpay_API::update_plan($plan_args);
        } else {
            $request = Qualpay_API::create_plan($plan_args);
        }

        if (is_wp_error($request)) {
            WC_Admin_Settings::add_error($request->get_error_message());
        } else {
            if (absint($plan_args['plan_id']) !== 0) {
                WC_Admin_Settings::add_message(__('Plan Updated.', 'qualpay'));
            } else {
                WC_Admin_Settings::add_message(__('Plan Created.', 'qualpay'));
            }
        }

        return true;
    }

    /**
     * Sends the failed order email to admin
     *
     * @version 1.0.0
     * @since 1.0.0
     * @param int $order_id
     * @return null
     */
    public function send_failed_order_email($order_id)
    {

        $emails = WC()->mailer()->get_emails();
        if (!empty($emails) && !empty($order_id)) {
            $emails['WC_Email_Failed_Order']->trigger($order_id);
        }

    }

    /**
     * Generate the request for the payment.
     *
     * @param  WC_Order $order
     * @return array()
     */
    protected function generate_payment_request($order)
    {
        //  print_r($order);
        //  exit;
        unset($post_data);
        $post_data = array();

        if (isset($_POST['qualpay_card_id'])) {
            $post_data['card_id'] = wc_clean($_POST['qualpay_card_id']);
        } else {
            $post_data['card_number'] = str_replace(' ', '', wc_clean($_POST['qualpay-card-number']));
            $exp_date = wc_clean($_POST['qualpay-card-expiry']);
            $exp_date = substr($exp_date, 0, 2) . substr($exp_date, 5, 2);
            $post_data['exp_date'] = $exp_date;
            $post_data['cvv2'] = wc_clean($_POST['qualpay-card-cvc']);
        }

        ///JANKEE CHANGES
        $current_user = wp_get_current_user();
        $new_user_id = $current_user->ID;

        $get_customer_id = get_user_meta($current_user->ID, '_qualpay_customer_id');

        if (count($get_customer_id) > 0) {

            if ($customer_id) {
                //add or update billing info in Qualpay
                $get_customer_billing_cards = Qualpay_API::get_customer_billing_cards($customer_id, $mid);
                $billing_cards = $get_customer_billing_cards->data->billing_cards;
                $card_id = array();
                $args = array();
                foreach ($billing_cards as $billing_card) {
                    $card_id[] = $billing_card->card_id;
                    $last4 = substr($billing_card->card_number, -4, 4);
                    $card_type = $billing_card->card_type;
                }
                if (in_array($post_data['card_id'], $card_id)) {
                    $args['card_id'] = $post_data['card_id'];
                    $args['customer_id'] = $customer_id;
                    $args['billing_zip'] = $_POST['billing_postcode'];
                    $args['billing_first_name'] = $_POST['billing_first_name'];
                    $args['billing_last_name'] = $_POST['billing_last_name'];
                    $args['merchant_id'] = $mid;
                    $update_billing_card = Qualpay_API::update_customer_billing_cards($args);
                } else {
                    $args['card_id'] = $post_data['card_id'];
                    $args['billing_zip'] = $_POST['billing_postcode'];
                    $args['customer_id'] = $customer_id;
                    $args['billing_first_name'] = $_POST['billing_first_name'];
                    $args['billing_last_name'] = $_POST['billing_last_name'];
                    $args['merchant_id'] = $mid;
                    $add_billing_card = Qualpay_API::add_customer_billing_cards($args);
                }
                //end
            } else {
                $customer_id = $this->create_customer_id_data($order, $post_data, $current_user);
            }

        } else {
           $customer_id =  $this->create_customer_id_data($order, $post_data, $current_user);
        }

        $post_data['customer_id'] = $customer_id;

        ///JANKEE END

        $order = wc_get_order($order->data['id']);
        $cart_items = $order->get_items();
        // Recurring can be multiple in cart. Get the first cart item then.
        $total = $order->get_total('edit');
        $discount_price = $order->get_total_discount();
        $subTotal = 0;
        foreach ($cart_items as $cart_item => $values) {

            $product_id = $values['product_id'];
            // echo "get total=".$total."</br>";
            $signup_fee = '';

            $order_id_order_pay = absint(get_query_var('order-pay'));
            if ($order_id_order_pay) {
                if ('yes' != get_post_meta($product_id, '_qualpay', true)) {
                    $_product = wc_get_product($product_id);

                    $product_price = $values['quantity'] * $_product->get_price();
                    $subTotal = $subTotal + $product_price;
                    $total = $subTotal;
                    $total = $total - $discount_price;
                }
            } else {
                if ('yes' === get_post_meta($product_id, '_qualpay', true)) {

                    if (Qualpay_Cart::recurring_in_cart()) {
                        $use_plan = get_post_meta($product_id, '_qualpay_use_plan', true);
                        if ($use_plan == 'no') {
                            $signup_fee = $signup_fee + get_post_meta($product_id, '_qualpay_setup_fee', true);
                            $signup_fee = $values['quantity'] * $signup_fee;
                        } else {
                            $plan_data = get_post_meta($product_id, '_qualpay_plan_data');
                            $amt_setup = $plan_data[0]->amt_setup;
                            if ($values['quantity'] > 1) {
                                $amt_setup = $values['quantity'] * $amt_setup;
                            }
                            $signup_fee = $signup_fee + $amt_setup;
                        }
                        $total = $total - $signup_fee;
                    }
                    //echo "signup total=".$total."</br>";
                }
            }
        }

        $billing_first_name = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_first_name : $order->get_billing_first_name();
        $billing_last_name = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_last_name : $order->get_billing_last_name();
        $post_data['cardholder_name'] = $billing_first_name . ' ' . $billing_last_name;

        $address = $order->data['billing']['address_1'] . " " . $order->data['billing']['address_2'];
        $address = substr($address, 0, 20);

        //$post_data['amt_tran'] = $order->get_total('edit');
        $post_data['amt_tran'] = $total;
        $post_data['purchase_id'] = $order->get_order_number();
        $post_data['tran_currency'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->get_order_currency() : $order->get_currency();
        //  $post_data['tran_currency'] = '840';
        $post_data['customer_email'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_email : $order->get_billing_email();
        $post_data['avs_address'] = $address;
        $avs_zip = str_replace('-', '', $order->data['billing']['postcode']);
        $post_data['avs_zip'] = $avs_zip;
        $post_data['email_receipt'] = false;
        $post_data['tokenize'] = false;
        $post_data['cart_amt_other'] = 0;
        // TODO add line_items

        /**
         * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
         *
         * @since 3.1.0
         * @param array $post_data
         * @param WC_Order $order
         * @param object $source
         */
        return apply_filters('wc_qualpay_generate_payment_request', $post_data, $order);
        //  exit;
    }

    /**
     * Generate the request for the payment recurring.
     *
     * @param  WC_Order $order
     * @return array()
     */
    protected function generate_payment_request_recurring($order)
    {
        $post_data = array();
        $customer = array();
        $order = wc_get_order($order->data['id']);
        $cart_items = $order->get_items();
        
        // Recurring can be multiple in cart. also multiple recurring same product can be in cart.
        foreach ($cart_items as $cart_item => $values) {

            $product_id = $values['product_id'];
            unset($post_data);
            unset($request);
            $post_data['date_start'] = date('Y-m-d', current_time('timestamp') + DAY_IN_SECONDS);
            $post_data['tran_currency'] = '840';
            $billing_first_name = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_first_name : $order->get_billing_first_name();
            $billing_last_name = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_last_name : $order->get_billing_last_name();

            $post_data['cart_amt_other'] = 0;

            if (Qualpay_Cart::is_product_recurring($product_id)) {
                // echo $values['quantity'];
                if ($values['quantity'] >= 1) {
                    for ($i = 0; $i < $values['quantity']; $i++) {
                        //echo "in";
                        $post_data['product_id'] = $product_id;
                        $product_id = isset($values['product_id']) ? absint($values['product_id']) : 0;

                        if (!$product_id) {
                            return new WP_Error('qualpay_error', __('No Product Selected for a Subscription', 'qualpay'));
                        }

                        if (!isset($args['cart_amt_other'])) {
                            $args['cart_amt_other'] = 0;
                        }
                        $plan_object = get_post_meta($product_id, '_qualpay_plan_data', true);
                        
                        if ($plan_object) {
                            $post_data['subscription_on_plan'] = true;
                            $post_data['interval'] = $plan_object->interval;
                            $post_data['plan_id'] = $plan_object->plan_id;
                            $post_data['plan_code'] = $plan_object->plan_code;
                            $post_data['plan_desc'] = $plan_object->plan_desc;
                            $post_data['amt_setup'] = floatval($plan_object->amt_setup) + $args['cart_amt_other'];
                            $post_data['amt_tran'] = $plan_object->amt_tran;
                            $post_data['plan_frequency'] = $plan_object->plan_frequency;
                        } else {
                            $post_data['plan_frequency'] = get_post_meta($product_id, '_qualpay_frequency', true);
                            $post_data['interval'] = get_post_meta($product_id, '_qualpay_interval', true);
                            $bill_unlimited = get_post_meta($product_id, '_qualpay_bill_until_cancelled', true);
                            if ('yes' === $bill_unlimited) {
                                $post_data['plan_duration'] = '-1';
                            } else {
                                $post_data['plan_duration'] = get_post_meta($product_id, '_qualpay_duration', true);
                            }
                            $setup_fee = (float) get_post_meta($product_id, '_qualpay_setup_fee', true);
                            if (!$setup_fee) {
                                $setup_fee = 0;
                            }
                            $post_data['amt_setup'] = $setup_fee + $args['cart_amt_other'];
                            $post_data['amt_tran'] = get_post_meta($product_id, '_qualpay_amount', true);
                            $post_data['subscription_on_plan'] = false;
                            $post_data['subscription_on_plan'] = false;

                        }

                        if (isset($_POST['qualpay_card_id'])) {
                            $post_data['card_id'] = wc_clean($_POST['qualpay_card_id']);
                        } else {
                            $post_data['card_number'] = str_replace(' ', '', wc_clean($_POST['qualpay-card-number']));
                            $exp_date = wc_clean($_POST['qualpay-card-expiry']);
                            $exp_date = substr($exp_date, 0, 2) . substr($exp_date, 5, 2);
                            $post_data['exp_date'] = $exp_date;
                            $post_data['cvv2'] = wc_clean($_POST['qualpay-card-cvc']);
                        }

                        $current_user = wp_get_current_user();
                        $new_user_id = $current_user->ID;

                        $get_customer_id = get_user_meta($current_user->ID, '_qualpay_customer_id');

                        $customer_id = $this->get_customerid_according_env($get_customer_id);

                        if (!$customer_id) {

                            $customer_billing = $order->get_address('billing');
                            $customer_shipping = $order->get_address('shipping');
                            // cusotmer Not yet on Qualpay, let's create it.
                            $customer_args = array();
                            //$customer_args['auto_generate_customer_id'] = true;
                            $customer_id_dynamic = substr(strtoupper($customer_billing['first_name'] . $customer_billing['last_name']), 0, 27);
                            $customer_id_dynamic = str_replace(' ', '', $customer_id_dynamic);
                            $customer_id_dynamic = preg_replace("/[^A-Za-z0-9]/", "", $customer_id_dynamic);
                            $six_digit_random_number = mt_rand(1000, 9999);
                            $customer_args['customer_id'] = $customer_id_dynamic. "_" .$six_digit_random_number;
                            $customer_args['customer_first_name'] = $customer_billing['first_name'];
                            $customer_args['customer_last_name'] = $customer_billing['last_name'];
                            $customer_args['customer_email'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_email : $order->get_billing_email();
                            $postcode = str_replace('-', '', $customer_billing['postcode']);

                            $customer_args['billing_cards'] = array();
                            $customer_args['billing_cards'][] = array(
                                'card_id' => isset($post_data['card_id']) ? $post_data['card_id'] : '',
                                'card_number' => isset($post_data['card_number']) ? $post_data['card_number'] : '',
                                'exp_date' => isset($exp_date) ? $exp_date : '',
                                'cvv2' => isset($post_data['cvv2']) ? $post_data['cvv2'] : '',
                                'billing_first_name' => $customer_billing['first_name'],
                                'billing_last_name' => $customer_billing['last_name'],
                                'billing_firm_name' => $customer_billing['company'],
                                'billing_addr1' => $customer_billing['address_1'],
                                'billing_city' => $customer_billing['city'],
                                'billing_state' => $customer_billing['state'],
                                'billing_zip' => $postcode,
                                'billing_country' => $customer_billing['country'],
                                'primary' => true,
                            );
                            if (($customer_shipping['first_name'] != '') && ($customer_shipping['last_name'] != '')) {
                                $customer_args['shipping_addresses'] = array();
                                $postcode_shipping = str_replace('-', '', $customer_shipping['postcode']);
                                $customer_args['shipping_addresses'][] = array(
                                    'shipping_first_name' => $customer_shipping['first_name'],
                                    'shipping_last_name' => $customer_shipping['last_name'],
                                    'shipping_firm_name' => $customer_shipping['company'],
                                    'shipping_addr1' => $customer_shipping['address_1'],
                                    'shipping_city' => $customer_shipping['city'],
                                    'shipping_state' => $customer_shipping['state'],
                                    'shipping_zip' => $postcode_shipping,
                                    'shipping_country' => $customer_shipping['country'],
                                );
                            }
                            $post_data['customer'] = $customer_args;
                            $post_data['use_existing_customer'] = true;
                        } else {
                            $post_data['customer_id'] = $customer_id;
                        }

                        $request = $post_data;
                        // Add subscription and customers according to recurring product.
                        $endpoint = untrailingslashit(Qualpay_API::get_endpoint('platform/subscription'));

                        $response = wp_safe_remote_post(
                            $endpoint,
                            array(
                                'headers' => array(
                                    'Authorization' => 'Basic ' . base64_encode(Qualpay_API::get_security_key() . ':'),
                                    'Content-type' => 'application/json',
                                ),
                                'body' => json_encode($request),
                                'timeout' => 70,
                                'user-agent' => Qualpay_API::get_user_agent(),
                            )
                        );

                        $new_customer = Qualpay_API::parse_response($response);

                        if (is_wp_error($new_customer)) {
                            $order->add_order_note($new_customer->get_error_message());
                            throw new Exception($new_customer->get_error_message());
                        }

                        if (!$customer_id) {
                            if (!$current_user->ID) {
                            } else {
                                $user_id = $current_user->ID;
                            }

                            if ($this->testmode == 'yes') {
                                $iniFilename = QUALPAY_PATH . "qp.txt";
                                $mode = strlen('test');
                                $mode_name = "test";
                                if (file_exists($iniFilename)) {
                                    $props = parse_ini_file($iniFilename);
                                    if (!empty($props['host'])) {
                                        $mode = strlen($props['host']);
                                        $mode_name = $props['host'];
                                    }
                                }
                                $cid = strlen($new_customer->data->customer_id);
                                $cid_name = $new_customer->data->customer_id;
                                $mid = Qualpay_API::get_merchant_id();
                                $mid_length = strlen($mid);

                            } else {
                                $mode = strlen('production');
                                $mode_name = "production";
                                $mid = Qualpay_API::get_merchant_id();
                                $cid = strlen($new_customer->data->customer_id);
                                $cid_name = $new_customer->data->customer_id;
                                $mid = Qualpay_API::get_merchant_id();
                                $mid_length = strlen($mid);
                            }

                            $data_customer = 'a:3:{i:0;s:' . $mode . ':"' . $mode_name . '";i:1;s:' . $cid . ':"' . $cid_name . '";i:2;s:' . $mid_length . ':"' . $mid . '";}';
                            add_user_meta($user_id, '_qualpay_customer_id', $data_customer);
                            $customer_id = $new_customer->data->customer_id;

                        }

                        $order_id = version_compare(WC_VERSION, '3.0.0', '<') ? $order->id : $order->get_id();
                        
                        if (isset($new_customer)) {
                            add_post_meta($order_id, '_qualpay_subscription_id', $new_customer->data->subscription_id);
                            add_post_meta($order_id, '_qualpay_subscription_data', $new_customer->data);
                            add_post_meta($order_id, '_qualpay_subscription_customer_id', $new_customer->data->customer_id);
                        }
                    }
                }
            }

        }
       
        /**
         * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request_recurring
         *
         * @since 3.1.0
         * @param array $post_data
         * @param WC_Order $order
         * @param object $source
         */

        // return apply_filters('wc_qualpay_generate_payment_request_recurring', $post_data, $order);
        $parsed_response = json_decode($response['body']);

        // Handle response
        if (!empty($parsed_response->error)) {
            if (!empty($parsed_response->error->code)) {
                $code = $parsed_response->error->code;
            } else {
                $code = 'qualpay_error';
            }
            return new WP_Error($code, $parsed_response->error->message);
        } else {
            return $parsed_response;
        }

    }

    /**
     * Debug log
     *
     * @since 1.0.0
     *
     * @param string $message
     */
    public function log($message)
    {
        if ($this->debug) {
            $log = new WC_Logger();
            $log->add('qualpay', $message);
        }
    }

    /**
     * Output the gateway settings screen.
     */
    public function admin_options()
    {
        $plan = isset($_GET['plan']) ? $_GET['plan'] : false;
        $action = isset($_GET['plan_action']) ? $_GET['plan_action'] : false;
        if (!$plan) {
            parent::admin_options();
        } else {
            $qualpay_settings_link = admin_url('admin.php?page=wc-settings&tab=checkout&section=qualpay');
            $qualpay_plans_link = admin_url('admin.php?page=wc-settings&tab=checkout&section=qualpay&plan=all');
            $qualpay_plans_new_link = admin_url('admin.php?page=wc-settings&tab=checkout&section=qualpay&plan=new');
            switch ($plan) {
                case 'all':
                    global $hide_save_button;
                    $hide_save_button = true;
                    require_once 'class-qualpay-plan-list.php';
                    $plan_table = new Qualpay_Plan_List_Table();
                    $plan_table->prepare_items();
                    echo '<h2>' . sprintf('<a href="' . $qualpay_settings_link . '">%s</a> > %s', __('QualPay', 'qualpay'), __('Plans', 'qualpay')) . sprintf(' <a class="page-title-action" href="' . $qualpay_plans_new_link . '">%s</a>', __('Add New', 'qualpay')) . '</h2>';
                    $plan_table->display();
                    break;
                case 'new':
                    echo '<h2>' . sprintf('<a href="' . $qualpay_settings_link . '">%s</a> > <a href="' . $qualpay_plans_link . '">%s</a>', __('QualPay', 'qualpay'), __('Plans', 'qualpay')) . ' > ' . __('New Plan', 'qualpay') . '</h2>';
                    include_once 'views/admin/html-plan-form.php';
                    break;
                default:
                    if ('delete' === $action) {
                        $plan_request = Qualpay_API::delete_plan($plan, urldecode($_GET['plan_name']));
                        echo '<style type="text/css"> .woocommerce-save-button {display:none !important;}</style>';
                        if (is_wp_error($plan_request)) {
                            echo '<div id="message" class="error inline"><p><strong>' . esc_html($plan_request->get_error_message()) . '</strong></p></div>';
                        } else {
                            echo '<div id="message" class="updated inline"><p><strong>' . __('Plan Deleted', 'qualpay') . '</strong></p></div>';
                        }
                    } else {
                        $plan_request = Qualpay_API::get_plan($plan);
                        if (is_wp_error($plan_request)) {
                            WC_Admin_Settings::add_error($plan_request->get_error_message());
                        } else {
                            $plan_object = $plan_request->data[0];
                            echo '<h2>' . sprintf('<a href="' . $qualpay_settings_link . '">%s</a> > <a href="' . $qualpay_plans_link . '">%s</a>', __('QualPay', 'qualpay'), __('Plans', 'qualpay')) . '>' . $plan . '</h2>';
                            include_once 'views/admin/html-plan-form.php';
                        }
                    }
                    break;
            }
        }
    }

    public function create_customer_id_data($order, $post_data, $current_user)
    {

        $customer_billing = $order->get_address('billing');
        $customer_shipping = $order->get_address('shipping');

        // Not yet on Qualpay, let's create it.
        $customer_args = array();
        //$customer_args['auto_generate_customer_id'] = true;
        $customer_id_dynamic = substr(strtoupper($customer_billing['first_name'] . $customer_billing['last_name']), 0, 27);
        $customer_id_dynamic = str_replace(' ', '', $customer_id_dynamic);
        $customer_id_dynamic = preg_replace("/[^A-Za-z0-9]/", "", $customer_id_dynamic);
        $six_digit_random_number = mt_rand(1000, 9999);
        $customer_args['customer_id'] = $customer_id_dynamic. "_" .$six_digit_random_number;
        $customer_args['customer_first_name'] = $customer_billing['first_name'];
        $customer_args['customer_last_name'] = $customer_billing['last_name'];
        $customer_args['customer_email'] = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_email : $order->get_billing_email();
        $postcode = str_replace('-', '', $customer_billing['postcode']);

        $customer_args['billing_cards'] = array();

        $customer_args['billing_cards'][] = array(
            'card_id' => isset($post_data['card_id']) ? $post_data['card_id'] : '',
            'card_number' => isset($post_data['card_number']) ? $post_data['card_number'] : '',
            'exp_date' => isset($exp_date) ? $exp_date : '',
            'cvv2' => isset($post_data['cvv2']) ? $post_data['cvv2'] : '',
            'billing_first_name' => $customer_billing['first_name'],
            'billing_last_name' => $customer_billing['last_name'],
            'billing_firm_name' => $customer_billing['company'],
            'billing_addr1' => $customer_billing['address_1'],
            'billing_city' => $customer_billing['city'],
            'billing_state' => $customer_billing['state'],
            'billing_zip' => $postcode,
            'billing_country' => $customer_billing['country'],
            'primary' => true,
        );
        if (($customer_shipping['first_name'] != '') && ($customer_shipping['last_name'] != '')) {
            $customer_args['shipping_addresses'] = array();
            $postcode_shipping = str_replace('-', '', $customer_shipping['postcode']);
            $customer_args['shipping_addresses'][] = array(
                'shipping_first_name' => $customer_shipping['first_name'],
                'shipping_last_name' => $customer_shipping['last_name'],
                'shipping_firm_name' => $customer_shipping['company'],
                'shipping_addr1' => $customer_shipping['address_1'],
                'shipping_city' => $customer_shipping['city'],
                'shipping_state' => $customer_shipping['state'],
                'shipping_zip' => $postcode_shipping,
                'shipping_country' => $customer_shipping['country'],
            );
        }

        // $post_data['customer'] = $customer_args;
        $new_customer = Qualpay_API::create_customer($customer_args);

        if (is_wp_error($new_customer)) {
            $order->add_order_note($new_customer->get_error_message());
            throw new Exception($new_customer->get_error_message());
        }

        $user_id = $current_user->ID;

        if ($this->testmode == 'yes') {
            $iniFilename = QUALPAY_PATH . "qp.txt";
            $mode = strlen('test');
            $mode_name = "test";
            if (file_exists($iniFilename)) {
                $props = parse_ini_file($iniFilename);
                if (!empty($props['host'])) {
                    $mode = strlen($props['host']);
                    $mode_name = $props['host'];
                }
            }
            $cid = strlen($new_customer->data->customer_id);
            $cid_name = $new_customer->data->customer_id;
            $mid = Qualpay_API::get_merchant_id();
            $mid_length = strlen($mid);

        } else {
            $mode = strlen('production');
            $mode_name = "production";
            $mid = Qualpay_API::get_merchant_id();
            $cid = strlen($new_customer->data->customer_id);
            $cid_name = $new_customer->data->customer_id;
            $mid = Qualpay_API::get_merchant_id();
            $mid_length = strlen($mid);
        }
        $data_customer = 'a:3:{i:0;s:' . $mode . ':"' . $mode_name . '";i:1;s:' . $cid . ':"' . $cid_name . '";i:2;s:' . $mid_length . ':"' . $mid . '";}';

        add_user_meta($user_id, '_qualpay_customer_id', $data_customer);

        return $customer_id = $new_customer->data->customer_id;

    }
    public function get_billing_card_type($billing_card_type)
    {
        if ($billing_card_type == 'VS') {
            $billing_card_type = 'Visa';
        } else if ($billing_card_type == 'MC') {
            $billing_card_type = 'MasterCard';
        } else if ($billing_card_type == 'AM') {
            $billing_card_type = 'American Express';
        } else if ($billing_card_type == 'DS') {
            $billing_card_type = 'Discover';
        } else if ($billing_card_type == 'PP') {
            $billing_card_type = 'PayPal';
        }
        return $billing_card_type;
    }

    public function adding_customer_addresses($order, $new_user_id)
    {

        $customer_billingAddress = $order->get_address('billing');
        $customer_shippingAddress = $order->get_address('shipping');

        //user's billing data
        update_user_meta($new_user_id, 'billing_address_1', $customer_billingAddress['address_1']);
        update_user_meta($new_user_id, 'billing_address_2', $customer_billingAddress['address_2']);
        update_user_meta($new_user_id, 'billing_city', $customer_billingAddress['city']);
        update_user_meta($new_user_id, 'billing_company', $customer_billingAddress['company']);
        update_user_meta($new_user_id, 'billing_country', $customer_billingAddress['country']);
        update_user_meta($new_user_id, 'billing_email', $customer_billingAddress['email']);
        update_user_meta($new_user_id, 'billing_first_name', $customer_billingAddress['first_name']);
        update_user_meta($new_user_id, 'billing_last_name', $customer_billingAddress['last_name']);
        update_user_meta($new_user_id, 'billing_phone', $customer_billingAddress['phone']);
        update_user_meta($new_user_id, 'billing_postcode', $customer_billingAddress['postcode']);
        update_user_meta($new_user_id, 'billing_state', $customer_billingAddress['state']);

        if (($customer_shippingAddress['first_name'] != '') && ($customer_shippingAddress['last_name'] != '')) {
            //user's shipping address
            update_user_meta($new_user_id, 'shipping_address_1', $customer_shippingAddress['address_1']);
            update_user_meta($new_user_id, 'shipping_address_2', $customer_shippingAddress['address_2']);
            update_user_meta($new_user_id, 'shipping_city', $customer_shippingAddress['city']);
            update_user_meta($new_user_id, 'shipping_company', $customer_shippingAddress['company']);
            update_user_meta($new_user_id, 'shipping_country', $customer_shippingAddress['country']);
            update_user_meta($new_user_id, 'shipping_email', $customer_shippingAddress['email']);
            update_user_meta($new_user_id, 'shipping_first_name', $customer_shippingAddress['first_name']);
            update_user_meta($new_user_id, 'shipping_last_name', $customer_shippingAddress['last_name']);
            update_user_meta($new_user_id, 'shipping_phone', $customer_shippingAddress['phone']);
            update_user_meta($new_user_id, 'shipping_postcode', $customer_shippingAddress['postcode']);
            update_user_meta($new_user_id, 'shipping_state', $customer_shippingAddress['state']);
        }
    }

    public function get_customerid_according_env($get_customer_id)
    {

        if ($this->testmode == 'no') {
            for ($i = 0; $i < count($get_customer_id); $i++) {
                if (strpos($get_customer_id[$i], 'production') !== false) {
                    $mid = Qualpay_API::get_merchant_id();
                    if (strpos($get_customer_id[$i], $mid) !== false) {
                        $mydata = unserialize($get_customer_id[$i]);
                        $customer_id = $mydata[1];
                    }
                }
            }
        } else {
            for ($i = 0; $i < count($get_customer_id); $i++) {
                $iniFilename = QUALPAY_PATH . "qp.txt";
                $env_name = "test";
                if (file_exists($iniFilename)) {
                    $props = parse_ini_file($iniFilename);
                    if (!empty($props['host'])) {
                        $env_name = $props['host'];
                    }
                }
                if (strpos($get_customer_id[$i], $env_name) !== false) {
                    $mid = Qualpay_API::get_merchant_id();
                    if (strpos($get_customer_id[$i], $mid) !== false) {
                        $mydata = unserialize($get_customer_id[$i]);
                        $customer_id = $mydata[1];
                    }
                }
            }
        }
        return $customer_id;
    }
    
    function format_phone_us($phone) {
      // note: making sure we have something
      if(!isset($phone{3})) { return ''; }
      // note: strip out everything but numbers 
      $phone = preg_replace("/[^0-9]/", "", $phone);
      $length = strlen($phone);
      switch($length) {
      case 7:
        return preg_replace("/([0-9]{3})([0-9]{4})/", "$1-$2", $phone);
      break;
      case 10:
       return preg_replace("/([0-9]{3})([0-9]{3})([0-9]{4})/", "($1) $2-$3", $phone);
      break;
      case 11:
      return preg_replace("/([0-9]{1})([0-9]{3})([0-9]{3})([0-9]{4})/", "$1($2) $3-$4", $phone);
      break;
      default:
        return $phone;
      break;
      }
    }
}
