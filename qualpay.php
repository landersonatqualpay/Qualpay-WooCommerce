<?php
/**
 * Plugin Name:     Qualpay
 * Plugin URI:      https://www.qualpay.com/developer/libs/plugins
 * Description:     Qualpay Payment Gateway for WooCommerce
 * Author:          Qualpay
 * Author URI:      https://qualpay.com
 * Text Domain:     qualpay
 * Domain Path:     /languages
 * Version:         3.0.4
 * WC requires at least: 2.6.14
 * WC tested up to: 3.5.0
 * WP tested up to 5.0
 * Wp Requires at least : 4.4.0
 * @package         Qualpay
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


define( 'QUALPAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'QUALPAY_URL', plugin_dir_url( __FILE__ ) );
define( 'QUALPAY_FILE', __FILE__ );
define( 'QUALPAY_VERSION', '3.0.4' );
define( 'QUALPAY_REQ_WC_VERSION', '2.6.14' );
define( 'QUALPAY_REQ_WCS_VERSION', '2.1.0' );
define( 'QUALPAY_OPTION_PREFIX', 'qualpay_' );

/**
 * Qualpay Main Class
 *
 * @package  Qualpay
 */

class Qualpay {

	protected static $instance = null;

	/** plugin version */
	const VERSION = '3.0.4';

	/** plugin text domain */
	const TEXT_DOMAIN = 'qualpay';

	/**
	 *  Constructor
	 */
	function __construct() {

		if ( $this->check_prerequisites() ) {

			load_plugin_textdomain( 'woocommerce-gateway-qualpay', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
			add_action( 'init', array( $this, 'check_recurring' ), 1 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'wp_ajax_qualpay_search_plans', array( $this, 'search_plans' ) );
			add_action( 'wp_ajax_qualpay_get_embedded_token', array( $this, 'ajax_get_transient_key_for_embedded_form' ) );
			add_action( 'wp_ajax_nopriv_qualpay_get_embedded_token', array( $this, 'ajax_get_transient_key_for_embedded_form' ) );

			require_once 'includes/class-qualpay-api.php';
			require_once 'includes/class-qualpay-cart.php';
			require_once 'includes/class-qualpay-order.php';
			require_once 'includes/class-qualpay-product.php';
			require_once 'includes/class-qualpay-subscription.php';
			require_once 'includes/class-qualpay-woocommerce.php';
			require_once 'includes/class-qualpay-woocommerce-subscriptions.php';
			require_once 'includes/class-qualpay-webhook.php';

		}

	}

	/**
	 * Get the Transient Key for Form reloads.
	 */
	public function ajax_get_transient_key_for_embedded_form() {
		check_ajax_referer( 'qualpay_nonce', 'nonce' );

		$transient_key = Qualpay_API::get_embedded_fields_token();
		if ( ! is_wp_error( $transient_key ) ) {
			wp_send_json_success( array( 'transient_key' => $transient_key->data->transient_key ) );
		} else {
			wp_send_json_error( array( 'message' => $transient_key->get_error_message() ) );
		}
		wp_die();
	}

	/**
	 * Searching for plans and returning JSON
	 */
	public function search_plans() {
		$plans = [];
		$term = isset( $_REQUEST['term'] ) ? $_REQUEST['term'] : false;

		if( $term ) {
			$args = array(
				'filter'   => array(
					'status,IS_NOT,D',
					'plan_name,CONTAINS,' . $term . '|plan_code,CONTAINS,' . $term ),
			);
			$request = Qualpay_API::get_plans( $args );
			if ( ! is_wp_error( $request ) ) {
				foreach ( $request->data as $plan ) {
					$plans[ $plan->plan_code ] = $plan->plan_name;
				}
			}
		}

		wp_send_json( $plans );
	}

	/**
	 * Start the Class when called
	 *
	 * @return Qualpay
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Wrapper function to check whether WooCommerce and Subscriptions are active.
	 */
	public function check_prerequisites() {

		// WC version check. version compare returns -1 if the first version is lower, 0 if they're equal, 1 if the second is higher
		if ( ! class_exists( 'WooCommerce' ) || version_compare( WC()->version, QUALPAY_REQ_WC_VERSION ) < 0 ) {
			add_action( 'admin_notices', array( $this, 'wc_admin_notice' ) );

			return false;
		}

		return true;
	}

	/**
	 * @param $methods
	 *
	 * @return array
	 */
	public function add_gateways( $methods ) {

		if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
			$methods[] = 'WC_Gateway_Qualpay_Subscriptions';
		} else {
			$methods[] = 'WC_Gateway_Qualpay';
		}
		return $methods;

	}

	/**
	 * @param $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$setting_link = $this->get_setting_link();

		$plugin_links = array(
			'<a href="' . $setting_link . '">' . __( 'Settings', 'qualpay' ) . '</a>',
			'<a target="_BLANK" href="https://www.qualpay.com/developer/libs/plugins/woocommerce">' . __( 'Docs', 'qualpay' ) . '</a>',   // TODO: Update with text from Qualpay
			'<a target="_BLANK"  href="https://www.qualpay.com/contact">' . __( 'Support', 'qualpay' ) . '</a>', // TODO: Update with text from Qualpay
		);
		return array_merge( $plugin_links, $links );

	}

	/**
	 * @return string|void
	 */
	public function get_setting_link() {
		$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

		$section_slug = $use_id_as_section ? 'qualpay' : strtolower( 'WC_Gateway_Qualpay' );

		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
	}

	/**
	 * Display a warning message if Subs version check fails.
	 *
	 * @return void
	 */
	public function wc_admin_notice() {
		// translators: placeholder is required WooCommerce version (eg 3.0.0)
		echo '<div class="error"><p>' . esc_html( sprintf( __( 'Qualpay requires at least WooCommerce %s in order to function. Please activate or upgrade WooCommerce.', 'qualpay' ), QUALPAY_REQ_WC_VERSION ) ) . '</p></div>';
	}

	/**
	 * Checking if we want the Qualpay Recurring.
	 */
	public function check_recurring() {

		$qualpay_settings = get_option( 'woocommerce_qualpay_settings', array() );

		if ( ! $qualpay_settings ) {
			return;
		}

		if ( ! isset( $qualpay_settings['enabled'] ) ) {
			return;
		}

		if( 'yes' !== $qualpay_settings['enabled']) {
			return;
		}

		if ( ! isset( $qualpay_settings['recurring'] ) ) {
			return;
		}

		if( 'yes' !== $qualpay_settings['recurring'] ) {
			return;
		}

		require_once 'includes/class-qualpay-recurring.php';
		new Qualpay_Recurring();
	}

	public function admin_enqueue_scripts( $hook ) {
		if( 'woocommerce_page_wc-settings' === $hook && isset( $_GET['section'] ) && 'qualpay' === $_GET['section'] ) {
			wp_enqueue_style( 'qualpay-admin', QUALPAY_URL . '/assets/css/admin/admin.css' );
			wp_enqueue_script( 'qualpay-admin-js', QUALPAY_URL . '/assets/js/admin/settings.js' );
		}

	}
}

add_action( 'plugins_loaded', array( 'Qualpay', 'get_instance' ) );
