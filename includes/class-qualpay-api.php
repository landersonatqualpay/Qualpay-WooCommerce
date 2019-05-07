<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qualpay_API {
	
	//private static $test_endpoint = 'https://api-test.qualpay.com';

	/**
	 * Qualpay test endpoint
	 * @var string
	 */
	//
	private $test_endpoint;

	public function __construct(){
		$iniFilename = QUALPAY_PATH."qp.txt";
		$testUrl = "https://app-test.qualpay.com";    // default

		if( file_exists($iniFilename) ) {
			
			$props = parse_ini_file ($iniFilename);
			if( !empty($props['host']) ) {
				$testUrl = "https://app-" . $props['host'] . ".qualpay.com";
			}
		}
		 $this->test_endpoint = $testUrl;
		//exit;
	}
	
	
	/**
	 * Qualpay production endpoint
	 * @var string
	 */
	private static $endpoint = 'https://api.qualpay.com';

	/**
	 * @var
	 */
	private static $merchant_id = '';
	private static $sandbox_merchant_id = '';

	/**
	 * Secret API Key.
	 * @var string
	 */
	private static $security_key = '';

	/**
	 * Set secret API Key.
	 * @param string $merchant_id
	 */
	public static function set_merchant_id( $merchant_id ) {
		self::$merchant_id = $merchant_id;
	}

	public static function set_sandbox_merchant_id( $sandbox_merchant_id ) {
		self::$sandbox_merchant_id = $sandbox_merchant_id;
	}


	/**
	 * Set secret API Key.
	 * @param string $security_key
	 */
	public static function set_security_key( $security_key ) {
		self::$security_key = $security_key;
	}

	/**
	 * Get the user agent that is used for every API call.
	 *
	 * @return string
	 */
	public static function get_user_agent() {
		return 'WooCommerce' . QUALPAY_VERSION;
	}

	/**
	 * Get Merchant ID.
	 * @return string
	 */
	public static function get_merchant_id() {
		if ( ! self::$merchant_id ) {
			$options = get_option( 'woocommerce_qualpay_settings' );
			if('yes' ==  $options['testmode']) {
				if('' == $options['sandbox_merchant_id'])
				{
					return new WP_Error( 'qualpay_error', __( 'Merchant ID is not valid.', 'qualpay' ) );
				} 
				else {
					$options['merchant_id'] = $options['sandbox_merchant_id'];
				}
			} else {
				if ('' == $options['merchant_id'] ) {
					return new WP_Error( 'qualpay_error', __( 'Merchant ID is not valid.', 'qualpay' ) );
				} 
			}
			self::set_merchant_id( $options['merchant_id'] );
		}
		return self::$merchant_id;
	}

	public static function get_sandbox_merchant_id() {
		if ( ! self::$merchant_id ) {
			$options = get_option( 'woocommerce_qualpay_settings' );
			if ('' == $options['sandbox_merchant_id'] ) {
				return new WP_Error( 'qualpay_error', __( 'Sandbox Merchant ID is not valid.', 'qualpay' ) );

			}
			self::set_sandbox_merchant_id( $options['sandbox_merchant_id'] );
		}
		return self::$sandbox_merchant_id;
	}

	/**
	 * Get security key.
	 * @return string
	 */
	public static function get_security_key() {
		if ( ! self::$security_key ) {
			$options = get_option( 'woocommerce_qualpay_settings' );
			// first test mode check for secret key then check seret_key blank
			if('yes' ==  $options['testmode']) {
				if('' == $options['sandbox_secret_key'])
				{
					return new WP_Error( 'qualpay_error', __( 'Security key is not valid.', 'qualpay' ) );
				} 
				else {
					$options['secret_key'] = $options['sandbox_secret_key'];
				}
			} else {
				if ('' == $options['secret_key'] ) {
					return new WP_Error( 'qualpay_error', __( 'Security key is not valid.', 'qualpay' ) );
				} 
			}
			self::set_security_key( $options['secret_key'] );
		}
		return self::$security_key;
	}

	/**
	 * Get endpoint.
	 * @param string $type The endpoint type. 'pg' - payment gateway; 'platform' - plans, subscriptions.
	 * @return string
	 */
	public static function get_endpoint( $type = 'pg' ) {
		$options = get_option( 'woocommerce_qualpay_settings' );
		$cons_testendpoint = new Qualpay_API();
		
		if ( isset( $options['testmode'] ) && 'no' === $options['testmode'] ) {
			return self::$endpoint . '/' . $type;
		} else {
			//return self::$test_endpoint . '/' . $type;
			return $cons_testendpoint->test_endpoint . '/' . $type;
		}
	}

	/**
	 * Authenticate security key and id.
	 * @return string
	 */
	public static function authentication_id_key($mid, $key, $mode) {
		$cons_testendpoint = new Qualpay_API();
		if($mode == 'sandbox') {
			//$endpoint =  self::$test_endpoint .'/platform/vendor/settings/'.$mid ;
			$endpoint =  $cons_testendpoint->test_endpoint .'/platform/vendor/settings/'.$mid ;
			
		} else {
			$endpoint =  self::$endpoint .'/platform/vendor/settings/'.$mid ;
		}
		
			$args = array(
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( $key . ':' ),
					'Content-type'   => 'application/json',
				),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			);
	
			$response = wp_safe_remote_get(
				$endpoint,
				$args
			);
			
			$parsed_response = json_decode( $response['body'] );
			return $parsed_response;
		
	}

	/**
	 * Authorize a sale without capturing it
	 *
	 * @param $args
	 * @return array|mixed|object|WP_Error
	 */
	public static function do_authorization( $args ) {
		
		$endpoint = self::get_endpoint( 'pg/auth' );

		$request = array();
		$request['merchant_id']  = self::get_merchant_id();
		$request['sandbox_merchant_id']  = self::get_sandbox_merchant_id();
		$request['developer_id'] = self::get_user_agent();

		if ( isset( $args['card_id'] ) ) {
			$request['card_id'] = $args['card_id'];
		} else {
			if ( ! isset( $args['card_number'] ) ) {
				return new WP_Error( 'qualpay_error', __( 'Card Number is required.', 'qualpay' ) );
			}
			if ( ! isset( $args['exp_date'] )  ) {
				return new WP_Error( 'qualpay_error', __( 'Card expiration date is required.', 'qualpay' ) );
			}
			$request['card_number'] = $args['card_number'];
			$request['exp_date'] = $args['exp_date'];
		}
		if ( isset( $args['cardholder_name'] ) ) {
			$request['cardholder_name'] = $args['cardholder_name'];
		}

		if ( isset( $args['cvv2'] ) ) {
			$request['cvv2'] = $args['cvv2'];
		}

		if ( isset( $args['amt_tran'] ) ) {
			$request['amt_tran'] = $args['amt_tran'];
		}

		if ( isset( $args['purchase_id'] ) ) {
			$request['purchase_id'] = $args['purchase_id'];
		}

		if ( isset( $args['customer_code'] ) ) {
			$request['customer_code'] = $args['customer_code'];
		}

		if ( isset( $args['avs_address'] ) ) {
			$request['avs_address'] = $args['avs_address'];
		}

		if ( isset( $args['avs_zip'] ) ) {
			$request['avs_zip'] = $args['avs_zip'];
		}

		if ( isset( $args['customer_id'] ) ) {
			$request['customer_id'] = $args['customer_id'];
		}

		$debug = $request;
		unset( $debug['card_number'] );
		unset( $debug['exp_date'] );

		self::log( 'do_auth request: ' . "\n" .
		           'endpoint: ' . $endpoint . "\n" .
		           print_r ( $debug, true ) );

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'method'        => 'POST',
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}


	}

	/**
	 * Capture a previously authorized transaction.
	 *
	 * @param $args
	 * @return array|mixed|object|WP_Error
	 */
	public static function do_capture( $args ) {
		$endpoint = self::get_endpoint( 'pg/capture');

		$request = array();
		$request['merchant_id']  = self::get_merchant_id();
		$request['sandbox_merchant_id']  = self::get_sandbox_merchant_id();
		$request['developer_id'] = self::get_user_agent();

		if ( isset( $args['pg_id'] ) ) {
			$request['pg_id'] = $args['pg_id'];
		}

		if ( isset( $args['amt_tran'] ) ) {
			$request['amt_tran'] = $args['amt_tran'];
		}

		$debug = $request;

		self::log( 'do_capture request: ' . "\n" .
		           'endpoint: ' . $endpoint . '/' . $request['pg_id'] . "\n" .
		           print_r ( $debug, true ) );

		$response = wp_safe_remote_post(
			$endpoint . '/' . $request['pg_id'],
			array(
				'method'        => 'POST',
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		
		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}

	}

	public static function do_credit( $args ) {

	}

	/**
	 * Force a previously declined transaction
	 *
	 * @param $args
	 * @return array|mixed|object|WP_Error
	 */
	public static function do_force( $args ) {

	}

	/**
	 * Refund a charge in the Qualpay API
	 * @param $args
	 * @return bool|Mixed
	 */
	public static function do_refund( $args ) {

		$endpoint = self::get_endpoint();
	
		$request = array();
		$request['merchant_id']  = self::get_merchant_id();
		$request['developer_id'] = self::get_user_agent();

		if ( isset( $args['pg_id'] ) ) {
			//$request['pg_id'] = '9f854ea09c1511e89b890adff05dfb52';
			$request['pg_id'] = $args['pg_id'];
		}
		
		if ( isset( $args['amt_tran'] ) ) {
			$request['amt_tran'] = $args['amt_tran'];
		}

		/*if ( ! isset( $args['tran_currency'] ) ) {
			$args['tran_currency'] = get_woocommerce_currency();
		}

		if ( isset( $args['avs_address'] ) ) {
			$request['avs_address'] = $args['avs_address'];
		}

		if ( isset( $args['avs_zip'] ) ) {
			$request['avs_zip'] = $args['avs_zip'];
		} 

		$request['tran_currency'] = self::currency_iso_numeric( $args['tran_currency'] );

		if ( ! $request['tran_currency'] ) {
			self::log( 'Error Response: ' . sprintf( __( 'Currency ISO Numeric Code not found for %s', 'qualpay' ) , $args['tran_currency'] ) );
			return new WP_Error( 'qualpay_error', __( 'Currency ISO Numeric Code not found.', 'qualpay' ) );
		} */

		$debug = $request;

		self::log( 'do_refund request: ' . "\n" .
		           'endpoint: ' . $endpoint . "\n" .
		           print_r ( $debug, true ) );

		
		$response = wp_safe_remote_post(
			$endpoint . '/refund/' . $request['pg_id'],
			array(
				'method'        => 'POST',
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);
		
		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

	/**
	 * Send the request to the Qualpay API
	 *
	 * @param array $args
	 * @return array|mixed|object|WP_Error
	 */
	public static function do_sale( $args ) {

		//print_r($args);
		//exit;
		$endpoint = self::get_endpoint();

		$request = array();
		$request['merchant_id']    = self::get_merchant_id();
		$request['sandbox_merchant_id']    = self::get_sandbox_merchant_id();
		$request['moto_ecomm_ind'] = 7;
		$request['developer_id']   = self::get_user_agent();

		if ( ! isset( $args['tran_currency'] ) ) {
			$args['tran_currency'] = get_woocommerce_currency();
		}

		$request['tran_currency'] = self::currency_iso_numeric( $args['tran_currency'] );

		if ( ! $request['tran_currency'] ) {
			self::log( 'Error Response: ' . sprintf( __( 'Currency ISO Numeric Code not found for %s', 'qualpay' ) , $args['tran_currency'] ) );
			return new WP_Error( 'qualpay_error', __( 'Currency ISO Numeric Code not found.', 'qualpay' ) );
		}

		if ( isset( $args['card_id'] ) ) {
			$request['card_id'] = $args['card_id'];
		} else {
			if ( ! isset( $args['card_number'] ) ) {
				return new WP_Error( 'qualpay_error', __( 'Card Number is required.', 'qualpay' ) );
			}
			if ( ! isset( $args['exp_date'] )  ) {
				return new WP_Error( 'qualpay_error', __( 'Card expiration date is required.', 'qualpay' ) );
			}
			$request['card_number'] = $args['card_number'];
			$request['exp_date'] = $args['exp_date'];
		}
		if ( isset( $args['cardholder_name'] ) ) {
			$request['cardholder_name'] = $args['cardholder_name'];
		}

		if ( isset( $args['cvv2'] ) ) {
			$request['cvv2'] = $args['cvv2'];
		}

		if ( isset( $args['amt_tran'] ) ) {
			$request['amt_tran'] = $args['amt_tran'];
		}

		if ( isset( $args['avs_address'] ) ) {
			$request['avs_address'] = $args['avs_address'];
		}

		if ( isset( $args['avs_zip'] ) ) {
			$request['avs_zip'] = $args['avs_zip'];
		}

		if ( isset( $args['purchase_id'] ) ) {
			$request['purchase_id'] = $args['purchase_id'];
		}

		if ( isset( $args['customer_code'] ) ) {
			$request['customer_code'] = $args['customer_code'];
		}

		if ( isset( $args['tokenize'] ) ) {
			$request['tokenize'] = $args['tokenize'];
		}
		
		if ( isset( $args['customer_id'] ) ) {
			$request['customer_id'] = $args['customer_id'];
		}
		$debug = $request;

		unset( $debug['card_number'] );
		unset( $debug['exp_date'] );

		self::log( 'do_sale request: ' . "\n" .
		           'endpoint: ' . $endpoint . "\n" .
		           print_r ( $debug, true ) );

		$response = wp_safe_remote_post(
			$endpoint . '/sale',
			array(
				'method'        => 'POST',
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}

	}

	public static function do_verify( $args ) {

	}


	/**
	 * Void a previously authorized transaction.
	 *
	 * @param $args
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public static function get_amount_pg_id( $args ) {
	
		$endpoint = self::get_endpoint();

		$endpoint = self::get_endpoint( 'platform/reporting/transactions/bypgid/'.$args );
	
		$security_key = self::get_security_key();
		if ( is_wp_error( $security_key ) ) {
			return $security_key;
		}
		$args = array(
			'headers'       => array(
				'Authorization'  => 'Basic ' . base64_encode( $security_key . ':' ),
				'Content-type'   => 'application/json',
			),
			'timeout'    => 70,
			'user-agent' => self::get_user_agent(),
		);
		 $response = wp_safe_remote_get(
					$endpoint,
					$args
		);

		
		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );
	//	echo "<pre>";
		//print_r($parsed_response);
		//exit;
		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response->data;
		}
	}


	/**
	 * Void a previously authorized transaction.
	 *
	 * @param $args
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public static function do_void( $args ) {

		$endpoint = self::get_endpoint();

		$request = array();
		$request['merchant_id']  = self::get_merchant_id();
		$request['sandbox_merchant_id']    = self::get_sandbox_merchant_id();
		$request['developer_id'] = self::get_user_agent();

		if ( isset( $args['pg_id'] ) ) {
			$request['pg_id'] = $args['pg_id'];
		}

		if ( isset( $args['amt_tran'] ) ) {
			$request['amt_tran'] = $args['amt_tran'];
		}

		$debug = $request;

		self::log( 'do_void request: ' . "\n" .
		           'endpoint: ' . $endpoint . "\n" .
		           print_r ( $debug, true ) );

		$response = wp_safe_remote_post(
			$endpoint . '/void/' . $request['pg_id'],
			array(
				'method'        => 'POST',
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}


	/** Recurring Billing Endpoints */

	/**
	 * Add a Qualpay subscription
	 *
	 * @param $args
	 */
	public static function do_subscription_add ( $args ) {
		
		$request = array();
		$request['date_start']   = $args['date_start'];
		$request['customer_id']  = $args['customer_id'];
		$request['developer_id'] = self::get_user_agent();
		if ( ! isset( $args['tran_currency'] ) ) {
			$args['tran_currency'] = get_woocommerce_currency();
		}
		$request['tran_currency'] = self::currency_iso_numeric( $args['tran_currency'] );

		$product_id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;

		if( ! $product_id ) {
			return new WP_Error( 'qualpay_error', __( 'No Product Selected for a Subscription', 'qualpay' ) );
		}

		if ( ! isset( $args['cart_amt_other'] ) ) {
			$args['cart_amt_other'] = 0;
		}

		$plan_object = get_post_meta( $product_id, '_qualpay_plan_data', true );
		if ( $plan_object ) {
			$request['subscription_on_plan'] = true;
			$request['interval']  = $plan_object->interval;
			$request['plan_id']   = $plan_object->plan_id;
			$request['plan_code'] = $plan_object->plan_code;
			$request['plan_desc'] = $plan_object->plan_desc;
			$request['amt_setup'] = floatval( $plan_object->amt_setup ) + $args['cart_amt_other'];
			$request['amt_tran']  = $plan_object->amt_tran;
			$request['plan_frequency'] = $plan_object->plan_frequency;
		} else {
			$request['plan_frequency'] = get_post_meta( $product_id, '_qualpay_frequency', true );
			$request['interval']       = get_post_meta( $product_id, '_qualpay_interval', true );
			$bill_unlimited            = get_post_meta( $product_id, '_qualpay_bill_until_cancelled', true );
			if ( 'yes' === $bill_unlimited ) {
				$request['plan_duration'] = '-1';
			} else {
				$request['plan_duration'] = get_post_meta( $product_id, '_qualpay_duration', true );
			}
			$setup_fee = (float) get_post_meta( $product_id, '_qualpay_setup_fee', true );
			if ( ! $setup_fee ) {
				$setup_fee = 0;
			}
			$request['amt_setup'] = $setup_fee + $args['cart_amt_other'];
			$request['amt_tran']  = get_post_meta( $product_id, '_qualpay_amount', true );
			$request['subscription_on_plan'] = false;
		}

		$endpoint = untrailingslashit( self::get_endpoint( 'platform/subscription' ) );

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		return self::parse_response( $response );
	}

	/**
	 * Get all subscriptions or a single subscription by subscription ID
	 * @param $args
	 */
	public static function do_subscription_get ( $args ) {
		$args = wp_parse_args( $args, $defaults = array(
			'count' => 10,
			'page'  => 0,
			'order_on' => 'plan_code',
			'order_by' => 'asc',
			'filter'   => array( 'status,IS_NOT,D' ) // D - deleted plans, E - active plans, A - archived plans.
		) );

		$filters = [];
		$filters_query = '';

		if( is_array( $args['filter'] ) ) {
			$filters = $args['filter'];
			unset( $args['filter'] );
		}
		foreach ( $filters as $filter ) {
			$filters_query .= '&filter=' . $filter;
		}
		$endpoint = self::get_endpoint( 'platform/subscription' );
		$endpoint = add_query_arg( $args, $endpoint );

		if( false === strpos( $endpoint, '?' ) ) {
			$endpoint .= '?' . substr( $filters_query, 1 );
		} else {
			$endpoint .= $filters_query;
		}

		$security_key = self::get_security_key();
		if ( is_wp_error( $security_key ) ) {
			return $security_key;
		}
		
		$args = array(
			'headers'       => array(
				'Authorization'  => 'Basic ' . base64_encode( $security_key . ':' ),
				'Content-type'   => 'application/json',
			),
			'timeout'    => 70,
			'user-agent' => self::get_user_agent(),
		);

		$response = wp_safe_remote_get(
			$endpoint,
			$args
		);

		return self::parse_response( $response );
	}

	/**
	 * Cancel a Subscription.
	 *
	 * @param $args
	 * @return mixed
	 */
	public static function do_subscription_cancel ( $args ) {
		return self::do_subscription_action( $args, 'cancel' );
	}

	/**
	 * Pause a Subscription.
	 *
	 * @param $args
	 * @return mixed
	 */
	public static function do_subscription_pause( $args ) {
		return self::do_subscription_action( $args, 'pause' );
	}

	/**
	 * Resume a Subscription.
	 *
	 * @param $args
	 * @return mixed
	 */
	public static function do_subscription_resume( $args ) {
		return self::do_subscription_action( $args, 'resume' );
	}

	/**
	 * Processing Subscription Actions
	 *
	 * @param array $args
	 * @param string $action
	 */
	public static function do_subscription_action( $args, $action ) {
		$endpoint = untrailingslashit( self::get_endpoint( 'platform/subscription' ) );

		$subscription_id = isset( $args['subscription_id'] ) ? $args['subscription_id'] : false;

		if( ! $subscription_id ) {
			return new WP_Error( 'no-subscription-id', __( 'No Subscription ID.', 'qualpay' ) );
		}

		$endpoint .= '/' . $subscription_id . '/' . $action;
		
		$customer_id = isset( $args['customer_id'] ) ? $args['customer_id'] : false;

		if ( ! $customer_id ) {
			return new WP_Error( 'no-customer-id', __( 'No Customer ID.', 'qualpay' ) );
		}

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( array( 'customer_id' => $customer_id, 'developer_id' => self::get_user_agent() ) ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		return self::parse_response( $response );
	}

	/**
	 * Embedded Fields Token
	 */
	public static function get_embedded_fields_token() {
		$endpoint = self::get_endpoint( 'platform/embedded' );

		$security_key = self::get_security_key();
		if ( is_wp_error( $security_key ) ) {
			return $security_key;
		}

		$args = array(
			'headers'       => array(
				'Authorization'  => 'Basic ' . base64_encode( $security_key . ':' ),
				'Content-type'   => 'application/json',
			),
			'timeout'    => 70,
			'user-agent' => self::get_user_agent(),
		);

		$response = wp_safe_remote_get(
			$endpoint,
			$args
		);

		return self::parse_response( $response );
	}

	/********************************
	 * Related to Plans
	 ********************************/

	/**
	 * Get the plans from the account.
	 * @todo Prepare for production. Add querystrings as args and then build the URL from there.
	 * @return array|mixed|object|WP_Error
	 */
	public static function get_plans( $args = array() ) {
		$args = wp_parse_args( $args, $defaults = array(
			'count' => 10,
			'page'  => 0,
			'order_on' => 'plan_code',
			'order_by' => 'asc',
			'filter'   => array( 'status,IS,E' ) // D - deleted plans, E - active plans, A - archived plans.
		) );
		$filters = [];
		$filters_query = '';

		if( is_array( $args['filter'] ) ) {
			$filters = $args['filter'];
			unset( $args['filter'] );
		}
		foreach ( $filters as $filter ) {
			$filters_query .= '&filter=' . $filter;
		}
		$endpoint = self::get_endpoint( 'platform/plan' );
		$endpoint = add_query_arg( $args, $endpoint );

		if( false === strpos( $endpoint, '?' ) ) {
			$endpoint .= '?' . substr( $filters_query, 1 );
		} else {
			$endpoint .= $filters_query;
		}

		$security_key = self::get_security_key();
		if ( is_wp_error( $security_key ) ) {
			return $security_key;
		}
		$args = array(
			'headers'       => array(
				'Authorization'  => 'Basic ' . base64_encode( $security_key . ':' ),
				'Content-type'   => 'application/json',
			),
			'timeout'    => 70,
			'user-agent' => self::get_user_agent(),
		);
		$response = wp_safe_remote_get(
			$endpoint,
			$args
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		if ( isset( $response['response'] ) && 200 !== $response['response']['code'] ) {
			$errors = json_decode( $response['body'], true );
			$data   = isset( $errors['data'] ) ? $errors['data'] : array();
			$message = '';
			if ( $data ) {
				foreach ( $data as $error_type => $error ) {
					$message .= $error . '. ';
				}
			}

			self::log( 'Error Response: ' . print_r( $response, true ) );

			if ( $message ) {
				return new WP_Error( 'qualpay_error', $message );
			}
			return new WP_Error( 'qualpay_error', sprintf( __( 'Something went wrong. Code: %s Message: %s', 'qualpay' ), $response['response']['code'], $response['response']['message'] ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

	/**
	 * Create a plan.
	 *
	 * @param $args
	 */
	public static function create_plan( $args ) {
		$endpoint = self::get_endpoint( 'platform/plan' );

		$request = $args;
		$request['merchant_id']  = self::get_merchant_id();
		$request['sandbox_merchant_id']  = self::get_sandbox_merchant_id();
		$request['developer_id'] = self::get_user_agent();

		if ( ! isset( $request['plan_id'] ) ) {
			self::log( 'Error Response: ' . __( 'Plan ID was not set.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Plan ID was not set. Something went wrong. Try editing an existing one or adding a new plan.', 'qualpay' ) );
		}

		if ( ! isset( $request['plan_code'] ) ) {
			self::log( 'Error Response: ' . __( 'Plan Code was not set.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Enter the Plan Code.', 'qualpay' ) );
		}

		if ( ! isset( $request['plan_name'] ) ) {
			self::log( 'Error Response: ' . __( 'Plan Name was not set.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Enter the Plan Name.', 'qualpay' ) );
		}

		if ( ! isset( $request['plan_desc'] ) ) {
			self::log( 'Error Response: ' . __( 'Plan Description was not set.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Enter the Plan Description.', 'qualpay' ) );
		}

		if ( ! isset( $request['plan_frequency'] ) ) {
			self::log( 'Error Response: ' . __( 'Plan Frequency was not set.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Enter the Plan Frequency.', 'qualpay' ) );
		}

		if ( ! isset( $request['interval'] ) ) {
			self::log( 'Error Response: ' . __( 'Plan Interval was not set.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Select the Plan Interval.', 'qualpay' ) );
		}

		if ( ! isset( $request['plan_duration'] ) ) {
			self::log( 'Error Response: ' . __( 'Plan Duration was not set.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Enter the Plan Duration.', 'qualpay' ) );
		}

		if ( ! isset( $request['amt_tran'] ) ) {
			self::log( 'Error Response: ' . __( 'Transaction Amount was not set.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Enter the Transaction Amount.', 'qualpay' ) );
		}

		$debug = $request;

		self::log( 'create_plan request: ' . "\n" .
		           'endpoint: ' . $endpoint . "\n" .
		           print_r ( $debug, true ) );

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		if( isset( $response['response'] ) && 200 !== $response['response']['code'] ) {
			$errors = json_decode( $response['body'], true );
			$data   = isset( $errors['data'] ) ? $errors['data'] : array();
			$message = '';
			if ( $data ) {
				foreach ( $data as $error_type => $error ) {
					$message .= $error . '. ';
				}
			}

			self::log( 'Error Response: ' . print_r( $response, true ) );

			if ( $message ) {
				return new WP_Error( 'qualpay_error', $message );
			}
			return new WP_Error( 'qualpay_error', sprintf( __( 'Something went wrong. Code: %s Message: %s', 'qualpay' ), $response['response']['code'], $response['response']['message'] ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

	/**
	 * Update a plan.
	 *
	 * @param array $args Arguments.
	 */
	public static function update_plan( $args ) {
		$endpoint = self::get_endpoint( 'platform/plan' );

		$request = $args;
		$request['merchant_id']  = self::get_merchant_id();
		$request['sandbox_merchant_id']    = self::get_sandbox_merchant_id();
		$request['developer_id'] = self::get_user_agent();

		if ( ! isset( $request['plan_code'] ) ) {
			self::log( 'Error Response: ' . __( 'Plan Code was not set.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Plan Code was not set. Something went wrong. Try editing an existing one or adding a new plan.', 'qualpay' ) );
		}

		$endpoint = untrailingslashit( $endpoint ) . '/' . $request['plan_code'];

		$debug = $request;

		self::log( 'update_plan request: ' . "\n" .
		           'endpoint: ' . $endpoint . "\n" .
		           print_r ( $debug, true ) );

		$response = wp_safe_remote_request(
			$endpoint,
			array(
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'method'     => 'PUT',
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		if( isset( $response['response'] ) && 200 !== $response['response']['code'] ) {
			$errors = json_decode( $response['body'], true );
			$data   = isset( $errors['data'] ) ? $errors['data'] : array();
			$message = '';
			if ( $data ) {
				foreach ( $data as $error_type => $error ) {
					$message .= $error . '. ';
				}
			}

			self::log( 'Error Response: ' . print_r( $response, true ) );

			if ( $message ) {
				return new WP_Error( 'qualpay_error', $message );
			}
			return new WP_Error( 'qualpay_error', sprintf( __( 'Something went wrong. Code: %s Message: %s', 'qualpay' ), $response['response']['code'], $response['response']['message'] ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

	/**
	 * Get a plan
	 *
	 * @param string $plan_code Plan Code.
	 */
	public static function get_plan( $plan_code ) {
		$endpoint = self::get_endpoint( 'platform/plan' );
		
		//$endpoint = untrailingslashit( $endpoint ) . '/' . $plan_code;
		$endpoint = untrailingslashit( $endpoint ) . '/' . $plan_code.'?filter=status,IS,E';
	
		$response = wp_safe_remote_get(
			$endpoint,
			array(
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		if( isset( $response['response'] ) && 200 !== $response['response']['code'] ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', sprintf( __( 'Something went wrong. Code: %s Message: %s', 'qualpay' ), $response['response']['code'], $response['response']['message'] ) );
		}

		$parsed_response = json_decode( $response['body'] );
		
		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

	/**
	 * Delete a plan
	 *
	 * @param string $plan_code Plan Code.
	 */
	public static function delete_plan( $plan_id, $plan_name ) {
		$endpoint = untrailingslashit( self::get_endpoint( 'platform/plan' ) );

		$endpoint .= '/' . $plan_id . '/delete';

		$response = wp_safe_remote_request(
			$endpoint,
			array(
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( array( 'plan_name' => $plan_name, 'developer_id' => self::get_user_agent() ) ),
				'method'     => 'DELETE',
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		if( isset( $response['response'] ) && 200 !== $response['response']['code'] ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', sprintf( __( 'Something went wrong. Code: %s Message: %s', 'qualpay' ), $response['response']['code'], $response['response']['message'] ) );
		}

		$parsed_response = json_decode( $response['body'] );
		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

	/********************************
	 * Related to Customers
	 ********************************/

	/**
	 * Creating a Customer
	 *
	 * @param array $args
	 * @return mixed
	 */
	public static function create_customer( $args ) {
		$endpoint = untrailingslashit( self::get_endpoint( 'platform/vault/customer' ) );

		// if ( ! isset( $args['customer_id'] ) || '' === $args['customer_id'] ) {
		// 	self::log( 'Error Response: ' . __( 'No Customer ID set when creating a customer.', 'qualpay' ) );
		// 	return new WP_Error( 'qualpay_error', __( 'Could not create a Customer.', 'qualpay' ) );
		// }

		if ( ! isset( $args['customer_first_name'] ) || '' === $args['customer_first_name'] ) {
			self::log( 'Error Response: ' . __( 'No Customer First Name set when creating a customer.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Could not create a Customer. Missing First Name.', 'qualpay' ) );
		}

		if ( ! isset( $args['customer_last_name'] ) || '' === $args['customer_last_name'] ) {
			self::log( 'Error Response: ' . __( 'No Customer Last Name set when creating a customer.', 'qualpay' ) );
			return new WP_Error( 'qualpay_error', __( 'Could not create a Customer. Missing Last Name.', 'qualpay' ) );
		}

		$request = $args;
		$request['merchant_id']  = self::get_merchant_id();
		$request['developer_id'] = self::get_user_agent();

		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem creating a customer.', 'qualpay' ) );
		}

		if( isset( $response['response'] ) && 200 !== $response['response']['code'] ) {
			$errors = json_decode( $response['body'], true );
			$data   = isset( $errors['data'] ) ? $errors['data'] : array();
			$message = '';
			if ( $data ) {
				foreach ( $data as $error_type => $error ) {
					$message .= $error . '. ';
				}
			}

			self::log( 'Error Response: ' . print_r( $response, true ) );

			if ( $message ) {
				return new WP_Error( 'qualpay_error', $message );
			}
			return new WP_Error( 'qualpay_error', sprintf( __( 'Something went wrong. Code: %s Message: %s', 'qualpay' ), $response['response']['code'], $response['response']['message'] ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

	/********************************
	 * Misc
	 ********************************/


	/**
	 * Parse a response returned from the Qualpay Service.
	 *
	 * @param array|mixed|object|WP_Error $response
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public static function parse_response( $response ) {

		if( isset( $response['response'] ) && 200 !== $response['response']['code'] ) {
			$errors = json_decode( $response['body'], true );
			$data   = isset( $errors['data'] ) ? $errors['data'] : array();
			$message = '';
			if ( $data ) {
				foreach ( $data as $error_type => $error ) {
					$message .= $error . '. ';
				}
			}

			self::log( 'Error Response: ' . print_r( $response, true ) );

			if ( $message ) {
				return new WP_Error( 'qualpay_error', $message );
			}
			return new WP_Error( 'qualpay_error', sprintf( __( 'Something went wrong. Code: %s Message: %s', 'qualpay' ), $response['response']['code'], $response['response']['message'] ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

	/**
	 * Logs
	 *
	 * @since 1.0.0
	 *
	 * @param string $message
	 */
	public static function log( $message ) {
		$options = get_option( 'woocommerce_qualpay_settings' );

		if ( 'yes' === $options['debug'] ) {
			$log = new WC_Logger();
			$log->add( 'qualpay', $message );
		}
	}

	/**
	 * Return the ISO numeric code.
	 * The default ISO numeric code is 840 which is USD.
	 *
	 * @param string $alpha
	 *
	 * return string
	 */
	public static function currency_iso_numeric( $alpha = 'USD' ) {

		$currencies = array_unique(
			apply_filters( 'qualpay_iso_numeric_currencies',
				array(
					'AED' => '784',
					'AFA' => '004',
					'AFN' => '971',
					'ALL' => '008',
					'AMD' => '051',
					'ANG' => '532',
					'AOA' => '973',
					'ARS' => '032',
					'AUD' => '036',
					'AWG' => '533',
					'AZN' => '944',
					'BAM' => '977',
					'BBD' => '052',
					'BDT' => '050',
					'BGN' => '975',
					'BHD' => '048',
					'BIF' => '108',
					'BMD' => '060',
					'BND' => '096',
					'BOB' => '068',
					'BRL' => '986',
					'BSD' => '044',
					'BTN' => '064',
					'BWP' => '072',
					'BYR' => '974',
					'BYN' => '933',
					'BZD' => '084',
					'CAD' => '124',
					'CDF' => '976',
					'CHF' => '756',
					'CLP' => '152',
					'CNY' => '156',
					'COP' => '170',
					'CRC' => '188',
					'CUC' => '931',
					'CUP' => '192',
					'CVE' => '132',
					'CZK' => '203',
					'DJF' => '262',
					'DKK' => '208',
					'DOP' => '214',
					'DZD' => '012',
					'EGP' => '818',
					'ERN' => '232',
					'ETB' => '230',
					'EUR' => '978',
					'FJD' => '242',
					'FKP' => '238',
					'GBP' => '826',
					'GEL' => '981',
					'GHS' => '936',
					'GIP' => '292',
					'GMD' => '270',
					'GNF' => '324',
					'GTQ' => '320',
					'GYD' => '328',
					'HKD' => '344',
					'HNL' => '340',
					'HRK' => '191',
					'HTG' => '332',
					'HUF' => '348',
					'IDR' => '360',
					'ILS' => '376',
					'INR' => '356',
					'IQD' => '368',
					'IRR' => '364',
					'ISK' => '352',
					'JMD' => '388',
					'JOD' => '400',
					'JPY' => '392',
					'KES' => '404',
					'KGS' => '417',
					'KHR' => '116',
					'KMF' => '174',
					'KPW' => '408',
					'KRW' => '410',
					'KWD' => '414',
					'KYD' => '136',
					'KZT' => '398',
					'LAK' => '418',
					'LBP' => '422',
					'LKR' => '144',
					'LRD' => '430',
					'LSL' => '426',
					'LYD' => '434',
					'MAD' => '504',
					'MDL' => '498',
					'MGA' => '969',
					'MKD' => '807',
					'MMK' => '104',
					'MNT' => '496',
					'MOP' => '446',
					'MRO' => '478',
					'MUR' => '480',
					'MVR' => '462',
					'MWK' => '454',
					'MXN' => '484',
					'MYR' => '458',
					'MZN' => '943',
					'NAD' => '516',
					'NGN' => '566',
					'NIO' => '558',
					'NOK' => '578',
					'NPR' => '524',
					'NZD' => '554',
					'OMR' => '512',
					'PAB' => '590',
					'PEN' => '604',
					'PGK' => '598',
					'PHP' => '608',
					'PKR' => '586',
					'PLN' => '985',
					'PYG' => '600',
					'QAR' => '634',
					'RON' => '946',
					'RSD' => '941',
					'RUB' => '643',
					'RWF' => '646',
					'SAR' => '682',
					'SBD' => '090',
					'SCR' => '690',
					'SDG' => '938',
					'SEK' => '752',
					'SGD' => '702',
					'SHP' => '654',
					'SLL' => '694',
					'SOS' => '706',
					'SRD' => '968',
					'SSP' => '728',
					'STD' => '678',
					'SYP' => '760',
					'SZL' => '748',
					'THB' => '764',
					'TJS' => '972',
					'TMT' => '934',
					'TND' => '788',
					'TOP' => '776',
					'TRY' => '949',
					'TTD' => '780',
					'TWD' => '901',
					'TZS' => '834',
					'UAH' => '980',
					'UGX' => '800',
					'USD' => '840',
					'UYU' => '858',
					'UZS' => '860',
					'VEF' => '937',
					'VND' => '704',
					'VUV' => '548',
					'WST' => '882',
					'XAF' => '950',
					'XCD' => '951',
					'XOF' => '952',
					'XPF' => '953',
					'YER' => '886',
					'ZAR' => '710',
					'ZMW' => '967',
				)
			)
		);

		return isset( $currencies[ $alpha ] ) ? $currencies[ $alpha ] : false;
	}

	/**
	 * Send the request to the Qualpay API
	 *
	 * @param array $args
	 * @return array|mixed|object|WP_Error
	 */
	public static function get_customer_billing_cards( $customer_id, $merchant_id ) {
		
		$endpoint = untrailingslashit( self::get_endpoint( 'platform/vault/customer' ) );
	
		$response = wp_safe_remote_get(
			$endpoint . '/'.$customer_id.'/billing?merchant_id='.$merchant_id,
			array(
				'method'        => 'GET',
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );
		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}

	}

	/**
	 * Send the request to the Qualpay API
	 *
	 * @param array $args
	 * @return array|mixed|object|WP_Error
	 */
	public static function add_customer_billing_cards( $args) {
		$request =  array();

		if ( isset( $args['card_id'] ) ) {
			$request['card_id'] = $args['card_id'];
		}

		if ( isset( $args['billing_zip'] ) ) {
			$request['billing_zip'] = $args['billing_zip'];
		}

		if ( isset( $args['billing_first_name'] ) ) {
			$request['billing_first_name'] = $args['billing_first_name'];
		}

		if ( isset( $args['billing_last_name'] ) ) {
			$request['billing_last_name'] = $args['billing_last_name'];
		}

		$endpoint = untrailingslashit( self::get_endpoint( 'platform/vault/customer' ) );
	
		$response = wp_safe_remote_post(
			$endpoint . '/'.$args['customer_id'].'/billing',
			array(
				'method'        => 'POST',
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );
		
		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}

	}

		/**
	 * Send the request to the Qualpay API
	 *
	 * @param array $args
	 * @return array|mixed|object|WP_Error
	 */
	public static function update_customer_billing_cards( $args) {
		$request =  array();

		if ( isset( $args['card_id'] ) ) {
			$request['card_id'] = $args['card_id'];
		}

		if ( isset( $args['billing_zip'] ) ) {
			$request['billing_zip'] = $args['billing_zip'];
		}

		if ( isset( $args['billing_first_name'] ) ) {
			$request['billing_first_name'] = $args['billing_first_name'];
		}

		if ( isset( $args['billing_last_name'] ) ) {
			$request['billing_last_name'] = $args['billing_last_name'];
		}
		// if ( isset( $args['customer_id'] ) ) {
		// 	$request['customer_id'] = $args['customer_id'];
		// }
		if ( isset( $args['merchant_id'] ) ) {
			$request['merchant_id'] = $args['merchant_id'];
		}

		$endpoint = untrailingslashit( self::get_endpoint( 'platform/vault/customer' ) );
		$http= new WP_Http();	
			
		$response = $http->request(
			$endpoint . '/'.$args['customer_id'].'/billing',
			array(
				'method'        => 'PUT',
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
				'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );
		
		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}

	}
	function get_merchant_settings($merchant_id)
	{
		$endpoint = untrailingslashit( self::get_endpoint( 'platform/vendor/settings' ) );
	
		$response = wp_safe_remote_get(
			$endpoint . '/'.$merchant_id,
			array(
				'method'        => 'GET',
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_security_key() . ':' ),
					'Content-type'   => 'application/json',
				),
			//	'body'       => json_encode( $request ),
				'timeout'    => 70,
				'user-agent' => self::get_user_agent(),
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'qualpay_error', __( 'There was a problem connecting to the payment gateway.', 'qualpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );
		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'qualpay_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}
}