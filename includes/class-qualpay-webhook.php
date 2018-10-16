<?php

/**
 * Created by PhpStorm.
 * User: igor
 * Date: 10/05/18
 * Time: 00:38
 */
class QualPay_Webhook {

	/**
	 * QualPay_Webhook constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'add_routes' ) );
	}

	/**
	 * Add REST API Routes
	 */
	public function add_routes() {
		register_rest_route( 'qualpay/v1', '/webhook', array(
			'methods' => 'POST',
			'callback' => array( $this, 'parse_webhook'),
			)
		);
	}

	/**
	 * Parse the posted Webhook
	 * @param WP_Rest_Request $request
	 */
	public function parse_webhook( $request ) {
	
		//echo "Aa";exit;
		$body   = $request->get_body();
		$api  = new Qualpay_API();
		//$body   = json_decode( $body );
		
			//$secret = '0b63b5aeb5fc11e8b4b20aaca8f8c8fa';
			//echo $computed = base64_encode(hash_hmac('sha256', $body, $secret, true));
			//exit;

		$header = $request->get_headers();
		//print_r($header);
		
		$message = "Webhook Request: \n Method: " . print_r( $request->get_method(), true ) . "\n Header: " . print_r( $header, true ) . "\n Body:" . print_r( $body, true );
		//$this->log( $message );

		$response = '';
		//echo  $this->get_webhook_secret();
		//exit;
		if ( $this->is_valid_webhook( $this->get_webhook_secret(), $header['x_qualpay_webhook_signature'][0], $body ) ) {
			
			$body_array = json_decode( $body, true );

			if ( $this->is_valid_webhook_event( $body_array['event'] ) ) {

				// subscription payment sucess then adding new order.
				if($body_array['event'] == 'subscription_payment_success') {
				
					//create order for subscription
					$data = $body_array['data'];
					//print_R($data);
					//assign variables
					$plan_id = $data['plan_id'];
					$subscription_id = $data['subscription_id'];
					$customer_id = $data['customer_id'];
					$pg_id = $data['pg_id'];
					$rcode = $data['rcode'];
					$amt_tran = $data['amt_tran'];
					$auth_code = $data['auth_code'];
					$trans_status = $data['status'];

					$args1 = array(
						'post_type'		=>	'shop_order',
						'post_status'	=>	'wc-',
						'meta_key'		=>	'_qualpay_subscription_id',
						'meta_value'	=> $subscription_id
					);
					$my_query = new WP_Query( $args1 ); 
					$order_id =$my_query->posts[0]->ID;
					$order = wc_get_order(  $order_id );
					$user_id = get_post_meta( $order_id, '_customer_user', true );
					
					
					if($user_id) {
						$billing_first_name = get_user_meta( $user_id, 'billing_first_name', true );
						$billing_last_name = get_user_meta( $user_id, 'billing_last_name', true );
						$billing_company = get_user_meta( $user_id, 'billing_company', true );
						$billing_address_1 = get_user_meta( $user_id, 'billing_address_1', true );
						$billing_address_2 = get_user_meta( $user_id, 'billing_address_2', true );
						$billing_city =  get_user_meta( $user_id, 'billing_city', true );
						$billing_state = get_user_meta( $user_id, 'billing_state', true );
						$billing_postcode = get_user_meta( $user_id, 'billing_postcode', true );
						$billing_country = get_user_meta( $user_id, 'billing_country', true );
						$billing_email = get_user_meta( $user_id, 'billing_email', true );
						$billing_phone = get_user_meta( $user_id, 'billing_phone', true );

						$shipping_first_name = get_user_meta( $user_id, 'shipping_first_name', true );
						$shipping_last_name = get_user_meta( $user_id, 'shipping_last_name', true );
						$shipping_company = get_user_meta( $user_id, 'shipping_company', true );
						$shipping_address_1 = get_user_meta( $user_id, 'shipping_address_1', true );
						$shipping_address_2 = get_user_meta( $user_id, 'shipping_address_2', true );
						$shipping_city = get_user_meta( $user_id, 'shipping_city', true );
						$shipping_state = get_user_meta( $user_id, 'shipping_state', true );
						$shipping_postcode = get_user_meta( $user_id, 'shipping_postcode', true );
						$shipping_country = get_user_meta( $user_id, 'shipping_country', true );
						$shipping_email = get_user_meta( $user_id, 'shippingg_email', true );
						$shipping_phone = get_user_meta( $user_id, 'shipping_phone', true );

					}  else {
						
						$billing_first_name = $order->billing_first_name;
						$billing_last_name  = $order->billing_last_name;
						$billing_company 	= $order->billing_company;
						$billing_address_1 	= $order->billing_address_1;
						$billing_address_2 	= $order->billing_address_2;
						$billing_city 		= $order->billing_city;
						$billing_state 		= $order->billing_state;
						$billing_postcode 	= $order->billing_postcode;
						$billing_country 	= $order->billing_country;
						$billing_email 		= $order->billing_email;
						$billing_phone		= $order->billing_phone;

						$shipping_first_name 	= $order->shipping_first_name;
						$shipping_last_name 	= $order->shipping_last_name;
						$shipping_company 		= $order->shipping_company;
						$shipping_address_1 	= $order->shipping_address_1;
						$shipping_address_2 	= $order->shipping_address_2;
						$shipping_city 			= $order->shipping_city;
						$shipping_state 		= $order->shipping_state;
						$shipping_postcode 		= $order->shipping_postcode;
						$shipping_country 		= $order->shipping_country;
						$shipping_email 		= $order->shipping_email;
						$shipping_phone 		= $order->shipping_phone;

					}
					$billing_address = array(
						'first_name' => $billing_first_name,
						'last_name'  => $billing_last_name,
						'company'    => $billing_company,
						'email'      => $billing_email,
						'phone'      => $billing_phone,
						'address_1'  => $billing_address_1,
						'address_2'  => $billing_address_2, 
						'city'       => $billing_city,
						'state'      => $billing_state,
						'postcode'   => $billing_postcode,
						'country'    => $billing_country
					);

					$shipping_address = array(
						'first_name' => $shipping_first_name,
						'last_name'  => $shipping_last_name,
						'company'    => $shipping_company,
						'email'      => $shipping_email,
						'phone'      => $shipping_phone,
						'address_1'  => $shipping_address_1,
						'address_2'  => $shipping_address_2, 
						'city'       => $shipping_city,
						'state'      => $shipping_state,
						'postcode'   => $shipping_postcode,
						'country'    => $shipping_country
					);
					if(isset($order)) {
						$items = $order->get_items();
					}
					foreach ( $items as $item ) {
						//echo "aa";
						$product_name = $item['name'];
						$product_id = $item['product_id'];
						$plan_data = get_post_meta( $product_id, '_qualpay_plan_data');
							$product_plan_id = $plan_data[0]->plan_id;
							if($product_plan_id == $plan_id) {
								//get product id for adding product into new order.
								$get_order_product_id = $product_id;
							}
					}

					//with customer detail 
					if($user_id) {
						$order = wc_create_order(array('customer_id' => $user_id));
					} else {
						$order = wc_create_order();
					}
					$order->add_product( get_product( $get_order_product_id ), 1 ); //(get_product with id and next is for quantity)
					$order->set_address( $billing_address, 'billing' );
					$order->set_address( $shipping_address, 'shipping' );
					$order->calculate_totals();
					
					$order_id = $order->id;
					if ($pg_id) {
						add_post_meta($order_id, '_qualpay_pg_id', $pg_id);
						add_post_meta($order_id, '_qualpay_subscription_id', $subscription_id);
						add_post_meta($order_id, '_qualpay_subscription_data', $data);
						add_post_meta($order_id, '_qualpay_subscription_customer_id', $customer_id);
				  	}
					// status managing for orders
					if($trans_status == 'A') {
						$order->update_status("processing", 'Recurring Product Order.', TRUE);
						$order->add_order_note( __( 'Order(Subscription-payment) added from Qualpay.', 'qualpay' ) );
					}

					if($options['testmode'] == 'no') {
                        $mid =Qualpay_API::get_merchant_id();
                        $order->add_order_note( __( 'This is a Production order.('.$mid.')', 'qualpay' ) );
                    } else {
                        $iniFilename = QUALPAY_PATH."qp.txt";
                        $env_name = "test";
                        if( file_exists($iniFilename) ) {
                            $props = parse_ini_file ($iniFilename);
                            if( !empty($props[host]) ) {
								$env_name = $props[host];
								$env_name = strtoupper($env_name);
                            }
                        }
                        $mid =Qualpay_API::get_merchant_id();
                        $order->add_order_note( __( 'This is a '.$env_name.' order.('.$mid.')', 'qualpay' ) );
                    }

				}

				// capture and cancel payment sucess so changing order status according to order id.
				if($body_array['event'] == 'qp_manager_capture_success' || $body_array['event'] == 'qp_manager_void_success') {
					$data = $body_array['data'];
					//$pg_id_amount = $api->get_amount_pg_id($data['pg_id']);
					//print_r($data);

					//$tran_status = $pg_id_amount->tran_status;
					//$purchase_id = $pg_id_amount->purchase_id;
					//$origional_amt_pg_id = $pg_id_amount->amt_tran;
					//$amt_tran = $data['amt_tran'];
					$purchase_id = $data['purchase_id'];
					
					$args = array(
						'post_type'		=>	'shop_order',
						'post_status'	=>	'wc-',
						'meta_key'		=>	'_qualpay_pg_id',
						'meta_value'	=> $data['pg_id']
					);
					$my_query = new WP_Query( $args ); 
					$order_id =$my_query->posts[0]->ID;
					$order = wc_get_order(  $order_id );

					//exit;
					if($order_id == $purchase_id) {
						if($body_array['event'] == 'qp_manager_void_success') {
							$pg_id = get_post_meta( $order_id, '_qualpay_pg_id');
							if(count($pg_id)>1) {
								$order->add_order_note( __( 'Part of Order canceled from Qualpay Manager.', 'qualpay' ) );
								$order->update_status('cancelled', __('Order Payment canceled.', 'qualpay'));
							} else {
								$order->add_order_note( __( 'Order canceled from Qualpay Manager.', 'qualpay' ) );
								$order->update_status('cancelled', __('Order Payment canceled.', 'qualpay'));
							}
						} else {
							$pg_id = get_post_meta( $order_id, '_qualpay_pg_id');
							if(count($pg_id)>1) {
								$order->add_order_note( __( 'Part of Order Captured from Qualpay Manager.', 'qualpay' ) );
								$order->update_status('processing', __('Order Payment Captured.', 'qualpay'));
							} else {
								$order->add_order_note( __( 'Order Captured from Qualpay Manager.', 'qualpay' ) );
								$order->update_status('processing', __('Order Payment Captured.', 'qualpay'));
							}
						}
					} else {
						if($body_array['event'] == 'qp_manager_void_success') {
							$order->add_order_note( __( 'Recurring Product Order canceled from Qualpay Manager.', 'qualpay' ) );
						} else {
							$pg_id = get_post_meta( $order_id, '_qualpay_pg_id');
							if(count($pg_id)>1) {
								$order->add_order_note( __( 'Recurring Product Order Captured from Qualpay Manager.', 'qualpay' ) );
							} else {
								$order->add_order_note( __( 'Recurring Product Order Captured from Qualpay Manager.', 'qualpay' ) );
								$order->update_status('processing', __('Order Payment Captured.', 'qualpay'));
							}
						}
					}
				}

				//Refund Payment from qualpay manager
				if($body_array['event'] == 'qp_manager_refund_success') {
					//pg_id_linked is old pg_id from coming fro refund webhook.. 
					//pg_id is current ( tht is not in woocommerce-pg_id so we are updatin with older one.) 
					
					$data = $body_array['data'];
					$args1 = array(
						'post_type'		=>	'shop_order',
						'post_status'	=>	'wc-',
						'meta_key'		=>	'_qualpay_pg_id',
						'meta_value'	=> $data['pg_id_linked']
					);
					$my_query = new WP_Query( $args1 ); 
					$order_id =$my_query->posts[0]->ID;
					$order = wc_get_order(  $order_id );
					if (isset($order)) {
						$order_total = $order->get_total();
					}
					$amt_tran = abs($data['amt_tran']);
			
					$remaining_refund_amount = $order->get_remaining_refund_amount(); 
					
					if($remaining_refund_amount >= $amt_tran) {
						$args = array(
							'order_id'		=>	$order_id,
							'amount'	=>	$amt_tran
							//'reason'		=>	$body_array['event']
						);
						$result = wc_create_refund($args);
						
						if('000' === $data['rcode']) {
							$remaining_refund_amount1 = $order->get_remaining_refund_amount();
							if($remaining_refund_amount1 > 0) {
								$order->update_status('partial-refund', __('Order Payment Partial Refunded.', 'qualpay'));	
							}  else {
								//update_post_meta($order_id, '_qualpay_pg_id', $data['pg_id'], $data['pg_id_linked']);
								$order->update_status('refunded', __('Order Payment Refunded.', 'qualpay'));	
							}
							//
						}
						
					}
				}

				if($body_array['event'] == 'transaction_status_updated') {
					$data = $body_array['data'];
					$transactions = $data['transactions'];
						//print_r($transactions); 
						//exit;
					for($i=0;$i<count($transactions);$i++) {
						$pg_id = $transactions[$i]['pg_id'];
						$purchase_id = $transactions[$i]['purchase_id'];
						$amt_tran = $transactions[$i]['amt_tran'];
						$tran_currency = $transactions[$i]['tran_currency'];
						$tran_status = $transactions[$i]['tran_status'];
						if($tran_status =='S' || $tran_status =='N' ) {
							if($amt_tran >= 0) {
								$args = array(
									'post_type'		=>	'shop_order',
									'post_status'	=>	'wc-',
									'meta_key'		=>	'_qualpay_pg_id',
									'meta_value'	=> $pg_id
								);
								$my_query = new WP_Query( $args ); 
								$order_id =$my_query->posts[0]->ID;
								$order = wc_get_order(  $order_id );
								if (isset($order)) {
									$order_total = $order->get_total();
								}
								$get_remaining_refund_amount = $order->get_remaining_refund_amount();
								if($get_remaining_refund_amount <= $amt_tran) {
									$order->update_status('completed', __('Order Payment Settled.', 'qualpay'));
								}
							}
						}
					}
					
				}
			
			}
				$response = new WP_REST_Response();
				$response->set_data( array( 'message' => 'All Ok' ) );
				$response->set_status(200 );


		} else {
			
			$response = new WP_Error( 'no-valid', __( 'Not a valid Webhook.', 'qualpay' ) );
			/*
			// Maybe it's just the QualPay Validation Call.
			$body   = json_decode( $body, true );
			$url    = isset( $body['notification_url'] ) ? str_replace( '\\', '', $body['notification_url'] ) : '';
			$status = isset( $body['status'] ) ? strtolower( $body['status'] ) : 'inactive';
			if ( get_rest_url() . 'qualpay/v1/webhook' === $url && 'active' === $status ) {
				$response = new WP_REST_Response();
				$response->set_data( array( 'message' => 'Validated' ) );
				$response->set_status( 200 );
			} else {
				$response = new WP_Error( 'no-valid', __( 'Not a valid Webhook.', 'qualpay' ) );
			}*/
		}
	//	exit;
		$response = rest_ensure_response( $response );

		$message = "Webhook Response: \n " . print_r( $response, true );
		$this->log( $message );

		return $response;
	}

	/**
	 * @param $secret
	 * @param $header
	 * @param $body
	 *
	 * @return bool
	 */
	private function is_valid_webhook( $secret, $header, $body ) {
		
		$isvalid = false;
		$signaturearr = [];
		
		//$secret = '793a08534c4511e780520a3416b2e023';
		if( !is_null($header) ){
			if( preg_match("/,/", $header ) ) {
				$signaturearr = explode(",", $header );
			} else {
				$signaturearr = [$header];
			}
			foreach($signaturearr as $qpsignature) {
				$computed = base64_encode(hash_hmac('sha256', $body, $secret, true));
				
				if( hash_equals($computed, $qpsignature) ) {
					$isvalid = true;
					break;
				}
			}
		}
		return $isvalid;
	}

	/**
	 * Let's validate the webhook event.
	 *
	 * @param string $event
	 */
	private function is_valid_webhook_event( $event ) {
		$available_events = array_keys( $this->get_webhook_events() );
		return in_array( $event, $available_events, true );
	}

	/**
	 * Get all the webhooks events
	 *
	 * @return array The key is the event, the value is the group.
	 */
	private function get_webhook_events() {
		return array(
			'subscription_suspended'       => 'subscription',
			'subscription_complete'        => 'subscription',
			'subscription_payment_success' => 'subscription',
			'subscription_payment_failure' => 'subscription',
			'checkout_payment_success'     => 'checkout',
			'checkout_payment_failure'     => 'checkout',
			'validate_url'                 => 'none',
			'invoice_paid'                 => 'invoice',
			'invoice_payment_success'      => 'invoice_payment',
			'invoice_payment_failure'      => 'invoice_payment',
			'invoice_email_undeliverable'  => 'invoice_email',
			'qp_manager_capture_success'   => 'capture_payment',
			'qp_manager_void_success'	   => 'void_payment',
			'qp_manager_refund_success'    => 'refund_payment',
			'transaction_status_updated'   => 'settled_payment'
		);
	}

	/**
	 * Return the Webhook secret from settings.
	 *
	 * @return string
	 */
	private function get_webhook_secret() {
		$options = get_option( 'woocommerce_qualpay_settings' );
		if (isset($options['testmode']) && 'no' === $options['testmode']) {
			return  isset( $options['webhook_secret'] ) ? $options['webhook_secret'] : '';
        } else {
			return isset( $options['sandbox_webhook_secret'] ) ? $options['sandbox_webhook_secret'] : '';
        }
		 
	}

	/**
	 * Log
	 *
	 * @param string $message
	 */
	private function log( $message ) {
		$options = get_option( 'woocommerce_qualpay_settings' );

		if ( 'yes' === $options['debug'] ) {
			$log = new WC_Logger();
			$log->add( 'qualpay-webhook', $message );
		}
	}
}

new QualPay_Webhook();