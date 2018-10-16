<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Qualpay_Subscriptions class.
 *
 * @extends WC_Gateway_Qualpay
 */
class WC_Gateway_Qualpay_Subscriptions extends WC_Gateway_Qualpay {

	public function __construct() {

		parent::__construct();

		$this->supports             = array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_amount_changes',
			'subscription_payment_method_change_admin',
			'subscription_date_changes',
			'subscription_reactivation',
			'subscription_suspension',
			//'tokenization',
			//'subscription_payment_method_change_customer',
			//'multiple_subscriptions',
			//'add_payment_method',
		);

		// Hooks
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'process_subscription_payment' ), 10, 2 );
		add_action( 'wcs_renewal_order_created', array( $this, 'delete_renewal_meta' ), 10 );

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
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {

		// Check for subscription products in the order
		if ( wcs_order_contains_subscription( $order_id ) ) {

			$order  = wc_get_order( $order_id );

			try {
				$response = null;

				// Handle payment.
				if ( $order->get_total() > 0 ) {

					$api = new Qualpay_API();

					$this->log( "Start processing payment for order $order_id for the amount of {$order->get_total()}" );

					// Make the request.
					$response = $api->do_sale( $this->generate_payment_request( $order ) );

					if ( is_wp_error( $response ) ) {

						$message = $response->get_error_message();
						$order->add_order_note( $message );
						throw new Exception( $message );
					}

					// Process valid response.
					$this->log( 'Processing response: ' . print_r( $response, true ) );

					$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

					if ( '000' === $response->rcode ) {
						update_post_meta( $order_id, '_qualpay_pg_id', $response->pg_id );
						update_post_meta( $order_id, '_qualpay_auth_code', $response->auth_code );

						// save token
						update_post_meta( $order_id, '_qualpay_payment_id', $response->card_id );
						update_post_meta( $order_id, '_qualpay_payment_number', $response->card_number );

						// save token on the subscriptions being purchased or paid for in the order
						if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
							$subscriptions = wcs_get_subscriptions_for_order( $order_id );
						} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
							$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
						} else {
							$subscriptions = array();
						}

						foreach ( $subscriptions as $subscription ) {
							$subscription_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $subscription->id : $subscription->get_id();
							update_post_meta( $subscription_id, '_qualpay_payment_id', $response->card_id );
							update_post_meta( $subscription_id, '_qualpay_payment_number', $response->card_number );
						}

						$order->payment_complete( $response->auth_code );

						$message = sprintf( __( 'Qualpay charge complete: %s', 'qualpay' ), $response->rmsg );
						$order->add_order_note( $message );
						$this->log( 'Success: ' . $message );
					} else {
						update_post_meta( $order_id, '_qualpay_pg_id', $response->pg_id );
						$message = sprintf( __( 'Qualpay Error response: rcode: %s, rmsg:%s', 'qualpay' ), $response->rcode, $response->rmsg );
						$order->add_order_note( $message );
						$this->log( 'Error: ' . $message );

						wc_add_notice( 'There was an error processing your request. Please contact us.', 'error' );

						return array(
							'result'   => 'fail',
							'redirect' => '',
						);

					}

				} else {
					$order->payment_complete();
				}

				// Remove cart.
				WC()->cart->empty_cart();

				do_action( 'wc_gateway_qualpay_process_payment', $response, $order );

				// Return thank you page redirect.
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);

			} catch ( Exception $e ) {

				wc_add_notice( $e->getMessage(), 'error' );
				$this->log( sprintf( __( 'Error: %s', 'qualpay' ), $e->getMessage() ) );

				do_action( 'wc_gateway_qualpay_process_payment_error', $e, $order );

				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}

		} else {
			$result = parent::process_payment( $order_id, $retry, $force_customer );

			return $result;
		}
	}

	/**
	 * @param $amount float
	 * @param $order WC_Order
	 *
	 * @return array
	 */
	public function process_subscription_payment( $amount, $order ) {

		try {
			$response = null;

			// Handle payment.
			$api = new Qualpay_API();

			$this->log( "Start processing payment for order " . $order->get_id() . " for the amount of {$amount}" );

			// Make the request.
			$response = $api->do_sale( $this->generate_payment_request( $order, true ) );

			if ( is_wp_error( $response ) ) {

				$message = $response->get_error_message();
				$order->add_order_note( $message );
				throw new Exception( $message );
			}

			// Process valid response.
			$this->log( 'Processing response: ' . print_r( $response, true ) );

			$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

			if ( '000' === $response->rcode ) {
				update_post_meta( $order_id, '_qualpay_pg_id', $response->pg_id );
				update_post_meta( $order_id, '_qualpay_auth_code', $response->auth_code );

				// save token
				update_post_meta( $order_id, '_qualpay_payment_id', $response->card_id );
				update_post_meta( $order_id, '_qualpay_payment_number', $response->card_number );

				$order->payment_complete( $response->auth_code );

				$message = sprintf( __( 'Qualpay charge complete: %s', 'qualpay' ), $response->rmsg );
				$order->add_order_note( $message );
				$this->log( 'Success: ' . $message );
			} else {
				update_post_meta( $order_id, '_qualpay_pg_id', $response->pg_id );
				$message = sprintf( __( 'Qualpay Error response: rcode: %s, rmsg:%s', 'qualpay' ), $response->rcode, $response->rmsg );
				$order->add_order_note( $message );
				$this->log( 'Error: ' . $message );

				wc_add_notice( 'There was an error processing your request. Please contact us.', 'error' );

				return false;

			}

			do_action( 'wc_gateway_qualpay_process_subscription_payment', $response, $order );

			// Return thank you page redirect.
			return true;

		} catch ( Exception $e ) {

			wc_add_notice( $e->getMessage(), 'error' );
			$this->log( sprintf( __( 'Error: %s', 'qualpay' ), $e->getMessage() ) );

			do_action( 'wc_gateway_qualpay_process_payment_error', $e, $order );

			return false;
		}

	}

	/**
	 * @param $renewal_order WC_Order
	 *
	 * @return mixed
	 */
	public function delete_renewal_meta( $renewal_order ) {
		delete_post_meta( $renewal_order->get_id(), '_qualpay_pg_id' );
		delete_post_meta( $renewal_order->get_id(), '_qualpay_auth_code' );
		return $renewal_order;
	}

	/**
	 * Generate the request for the payment including tokenization.
	 *
	 * @param WC_Order $order
	 * @param bool|false $renewal
	 *
	 * @return mixed|void
	 */
	protected function generate_payment_request( $order, $renewal=false ) {

		$post_data                    = array();
		if ( $renewal ) {
			$card_id = get_post_meta( $order->get_id(), '_qualpay_payment_id', true );
			$post_data['card_id']       = $card_id;
		} else {
			if ( isset( $_POST['qualpay_card_id'] ) ) {
				$post_data['card_id']         = wc_clean( $_POST['qualpay_card_id'] );
			} else {

				$post_data['card_number']     = str_replace( ' ', '', wc_clean( $_POST['qualpay-card-number'] ) );
				$exp_date                     = wc_clean( $_POST['qualpay-card-expiry'] );
				$exp_date                     = substr( $exp_date, 0, 2 ) . substr( $exp_date, 5, 2 );
				$post_data['exp_date']        = $exp_date;
				$post_data['cvv2']            = wc_clean( $_POST['qualpay-card-cvc'] );

			}
		}
		$billing_first_name = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_last_name : $order->get_billing_last_name();
		$post_data['cardholder_name'] = $billing_first_name . ' ' . $billing_last_name;
		$post_data['amt_tran']        = $order->get_total( 'edit' );
		$post_data['purchase_id']     = $order->get_order_number();
		$post_data['tran_currency']   = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency();

		$post_data['customer_email']  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_email : $order->get_billing_email();
		$post_data['developer_id']    = 'Qualpay';
		$post_data['email_receipt']   = false;
		$post_data['tokenize']        = true;

		// TODO add customer array
		$customer = array();

		// TODO add shipping array
		$shipping_address = array();

		// TODO add line_items

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
		 *
		 * @since 3.1.0
		 * @param array $post_data
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_qualpay_generate_payment_request', $post_data, $order );
	}

}
