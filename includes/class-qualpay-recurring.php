<?php

/**
 * Handling everything for the Recurring Payments from Qualpay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qualpay_Recurring {

	/**
	 * @var array
	 */
	public $endpoints = array();

	/**
	 * Qualpay_Recurring constructor.
	 */
	public function __construct() {

		add_filter( 'product_type_options', array( $this, 'product_type_options' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'get_product_data_tabs' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'product_data_panels' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_product_meta' ) );
		add_action( 'init', array( $this, 'subscription_actions' ) );

		/**
		 * Account Related
		 */
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_action( 'woocommerce_settings_account_endpoint_options_end', array( $this, 'account_endpoint_option' ) );
		add_action( 'woocommerce_account_recurring-payments_endpoint', array( $this, 'recurring_content' ) );
		add_filter( 'woocommerce_endpoint_recurring-payments_title', array( $this, 'recurring_title' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'account_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ), 5, 1 );
		add_action( 'woocommerce_settings_save_account', array( $this, 'save_fields' ) );
		add_action( 'wp_head', array( $this, 'inline_styles' ) );
	}

	public function inline_styles() {
		if ( is_account_page() ) {
			?>
			<style>
				.qualpay-subscription-pagination a {
					display: inline-block;
					vertical-align: middle;
					padding: 0.25em 0.5em;
				}
			</style>
			<?php
		}
	}

	/**
	 * Enqueue Admin Scripts
	 */
	public function enqueue() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		if ( in_array( $screen_id, array( 'product', 'edit-product' ) ) ) {
			wp_enqueue_script( 'wc-qualpay-product', QUALPAY_URL . '/assets/js/admin/product.js', array( 'jquery' ), '', true );
		}
	}

	/**
	 * Adding Recurring  as an option.
	 *
	 * @param array $options Options.
	 *
	 * @return mixed
	 */
	public function product_type_options( $options ) {
		$options['qualpay'] = array(
			'id'            => '_qualpay',
			'wrapper_class' => 'show_if_simple hide_if_subscription',
			'label'         => __( 'Qualpay Recurring', 'qualpay' ),
			'description'   => __( 'Sell subscription-based or installment products through Qualpay.', 'qualpay' ),
			'default'       => 'no',
		);
		return $options;
	}

	/**
	 * Adding the MasteryNet Product Data
	 *
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function get_product_data_tabs( $tabs ) {

		$tabs['qualpay'] = array(
			'label'    => __( 'Qualpay Recurring', 'qualpay' ),
			'target'   => 'qualpay_product_data',
			'class'    => array( 'show_if_qualpay', 'hide_if_external', 'hide_if_grouped', 'hide_if_variable' ),
			'priority' => 11
		);

		return $tabs;
	}

	/**
	 * MasteryNet LMS Panel
	 */
	public function product_data_panels() {
		global $post, $thepostid, $product_object;
		include_once( 'views/admin/html-product-data-qualpay.php' );
	}

	/**
	 * Saving the product meta.
	 *
	 * @param \WC_Product $product Object of WC_Product.
	 */
	public function save_product_meta( $product_id ) {
		if( isset( $_POST['_qualpay'] ) ) {
			update_post_meta( $product_id, '_qualpay', 'yes' );
		} else {
			delete_post_meta( $product_id, '_qualpay' );
		}

		if( isset( $_POST['_qualpay_use_plan'] ) ) {
			update_post_meta( $product_id, '_qualpay_use_plan', 'yes' );

			if( isset( $_POST['_qualpay_plan_code'] ) ) {
				update_post_meta( $product_id, '_qualpay_plan_code', sanitize_text_field( $_POST['_qualpay_plan_code'] ) );
				$plan_request = Qualpay_API::get_plan( sanitize_text_field( $_POST['_qualpay_plan_code'] ) );
				if ( is_wp_error( $plan_request ) ) {
					WC_Admin_Settings::add_error( $plan_request->get_error_message() );
				} else {
					update_post_meta( $product_id, '_qualpay_plan_data', $plan_request->data[0] );
				}
			}

			// Removing unrelated data when using a plan.
			delete_post_meta( $product_id, '_qualpay_frequency' );
			delete_post_meta( $product_id, '_qualpay_interval' );
			delete_post_meta( $product_id, '_qualpay_bill_until_cancelled' );
			delete_post_meta( $product_id, '_qualpay_duration' );
			delete_post_meta( $product_id, '_qualpay_amount' );
			delete_post_meta( $product_id, '_qualpay_setup_fee' );
		} else {
			update_post_meta( $product_id, '_qualpay_use_plan', 'no' );

			// Removing unrelated data when not using the plan.
			delete_post_meta( $product_id, '_qualplay_plan_code' );
			delete_post_meta( $product_id, '_qualpay_plan_data' );

			if ( isset( $_POST['_qualpay_frequency'] ) ) {
				$frequency = absint( $_POST['_qualpay_frequency'] );
				update_post_meta( $product_id, '_qualpay_frequency', $frequency );
				if ( isset( $_POST['_qualpay_interval'] ) && ( 0 === $frequency || 3 === $frequency ) ) {
					update_post_meta( $product_id, '_qualpay_interval', absint( $_POST['_qualpay_interval'] ) );
				} else {
					update_post_meta( $product_id, '_qualpay_interval', 1 );
				}
			}

			if ( isset( $_POST['_qualpay_bill_until_cancelled'] ) ) {
				update_post_meta( $product_id, '_qualpay_bill_until_cancelled', 'yes' );
				delete_post_meta( $product_id, '_qualpay_duration' );
			} else {
				update_post_meta( $product_id, '_qualpay_bill_until_cancelled', 'no' );

				if ( isset( $_POST['_qualpay_duration'] ) ) {
					update_post_meta( $product_id, '_qualpay_duration', absint( $_POST['_qualpay_duration'] ) );
				}
			}

			if ( isset( $_POST['_qualpay_amount'] ) ) {
				update_post_meta( $product_id, '_qualpay_amount',  wc_format_decimal( $_POST['_qualpay_amount'] ) );
			}

			if ( isset( $_POST['_qualpay_setup_fee'] ) ) {
				update_post_meta( $product_id, '_qualpay_setup_fee', wc_format_decimal( $_POST['_qualpay_setup_fee'] ) );
			}
		}
	}

	/**
	 * Save Fields
	 */
	public function save_fields() {
		$settings = $this->get_account_settings();
		\WC_Admin_Settings::save_fields( $settings );
	}

	/**
	 * Settings related to Account part for MasteryNet
	 *
	 * @return array
	 */
	public function get_account_settings() {
		return array(
			array(
				'title'    => __( 'My Recurring Payments', 'qualpay' ),
				'desc'     => __( 'Endpoint for showing the recurring payments of an account', 'qualpay' ),
				'id'       => 'wc_qualpay_recurring_payments_endpoint',
				'type'     => 'text',
				'default'  => 'recurring-payments',
				'desc_tip' => true,
			),
		);
	}

	/**
	 * It will get a set endpoint, if it does not exist, it will look in option and set it.
	 *
	 * @param string $endpoint Endpoint such as my-courses. If not exists, used as default also.
	 * @param string $option Option name.
	 *
	 * @return string
	 */
	private function get_endpoint( $endpoint, $option ) {
		if ( ! isset( $this->endpoints[ $endpoint ] ) ) {
			$endpoint_value = get_option( $option, $endpoint );
			$this->endpoints[ $endpoint ] = $endpoint_value;
		}

		return $this->endpoints[ $endpoint ];
	}

	/**
	 * Processing Subscription Actions.
	 */
	public function subscription_actions() {
		if ( isset( $_POST['qualpay_subscription_action'] ) && '' !== $_POST['qualpay_subscription_action'] ) {
			if ( ! isset( $_POST['qualpay_subscription_nonce'] )
				|| ! wp_verify_nonce( $_POST['qualpay_subscription_nonce'], 'qualpay_subscription_' . $_POST['qualpay_subscription_id'] . get_current_user_id() ) ) {
				wp_die( __( 'No Hacking Please!', 'qualpay' ) );
				return;
			}

			$customer_id = get_user_meta( get_current_user_id(), '_qualpay_customer_id', true );
			$response    = null;
			switch ( $_POST['qualpay_subscription_action'] ) {
				case 'cancel':
					$response = Qualpay_API::do_subscription_cancel( array(
						'subscription_id' => $_POST['qualpay_subscription_id'],
						'customer_id'     => $customer_id,
					));
					break;
				case 'pause':
					$response = Qualpay_API::do_subscription_pause( array(
						'subscription_id' => $_POST['qualpay_subscription_id'],
						'customer_id'     => $customer_id,
					));
					break;
				case 'resume':
					$response = Qualpay_API::do_subscription_resume( array(
						'subscription_id' => $_POST['qualpay_subscription_id'],
						'customer_id'     => $customer_id,
					));
					break;
			}

			if ( is_wp_error( $response ) ) {
				wc_add_notice( $response->get_error_message(), 'error' );
			}

		}
	}

	/**
	 * Adding the my-courses endpoint for the account
	 */
	public function account_endpoint_option() {
		$settings = $this->get_account_settings();
		\WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Adding My Courses to the Account Query Vars for Endpoint
	 *
	 * @param array $query_vars Query Vars to check for endpoints.
	 *
	 * @return mixed
	 */
	public function account_query_vars( $query_vars ) {
		$query_vars['recurring-payments'] = $this->get_endpoint( 'recurring-payments', 'wc_qualpay_recurring_payments_endpoint' );
		return $query_vars;
	}

	/**
	 * My Courses Title
	 *
	 * @param string $title Title.
	 *
	 * @return string
	 */
	public function recurring_title( $title ) {
		return __( 'My Recurring Payments', 'qualpay' );
	}

	/**
	 * Adding the endpoint
	 */
	public function add_endpoint() {
		$endpoint = $this->get_endpoint( 'recurring-payments', 'wc_qualpay_recurring_payments_endpoint' );
		add_rewrite_endpoint( $endpoint, EP_PAGES );
	}

	/**
	 * Adding My Courses to Menu Items
	 *
	 * @param array $items Menu Items.
	 *
	 * @return mixed
	 */
	public function menu_items( $items ) {
		$keys          = array_keys( $items );
		$last_title    = array_pop( $items );
		$last_endpoint = array_pop( $keys );
		$endpoint      = $this->get_endpoint( 'recurring-payments', 'wc_qualpay_recurring_payments_endpoint' );
		// Adding our endpoint and the last one (Hopefully Logout).
		$items[ $endpoint ]      = __( 'My Recurring Payments', 'qualpay' );
		$items[ $last_endpoint ] = $last_title;
		return $items;
	}

	/**
	 * Displaying the My Courses Content
	 *
	 * @param string $value Appended Value.
	 */
	public function recurring_content( $value ) {

		$subscriptions = array();
		$page          = 0;
		$total_pages   = 0;
		$total_records = 0;

		if ( $value && is_numeric( $value ) ) {
			$page = absint( $value ) - 1; //Starting from zero so $value = 1 will be $page = 0.
		}

		$user_id = get_current_user_id();
		$customer_id = get_user_meta( $user_id, '_qualpay_customer_id', true );
		if( $customer_id ) {
			$customer_subscriptions = Qualpay_API::do_subscription_get( array(
				'page'   => $page,
				'filter' => array(
					'customer_id,IS,' . $customer_id
				)
			) );

			if ( ! is_wp_error( $customer_subscriptions ) ) {
				$subscriptions = $customer_subscriptions->data;
				$total_pages   = $customer_subscriptions->totalPages;
				$total_records = $customer_subscriptions->totalRecords;
			}
		}

		wc_get_template(
			'myaccount/my-recurring-payments.php',
			array(
				'current_user'  => get_current_user_id(),
				'subscriptions' => $subscriptions,
				'total_pages'   => $total_pages,
				'total_records' => $total_records,
				'recurring_url' => wc_get_account_endpoint_url( $this->get_endpoint( 'recurring-payments', 'wc_qualpay_recurring_payments_endpoint' ) ),
			),
			'',
			QUALPAY_PATH . '/templates/'
		);
	}
}