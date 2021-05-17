<?php
/**
 * Hooks and functionality for Orders
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Class Qualpay_Order
 */
class Qualpay_Order {

	/**
	 * Qualpay_Order constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_order_actions', array( $this, 'order_actions' ) );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ), 99 );

		add_action( 'woocommerce_order_action_qualpay_order_capture', array( $this, 'order_capture' ) );

		add_action( 'woocommerce_order_action_qualpay_order_void', array( $this, 'order_void' ) );

		add_action( 'woocommerce_order_action_qualpay_order_refund', array( $this, 'order_refund' ) );

		add_action( 'updated_post_meta', array( $this, 'update_mp_sync_on_product_save' ) , 10, 4 );
		//add_action( 'added_post_meta', array( $this, 'mp_sync_on_product_save' ) , 99, 4 );
		//add_filter( 'woocommerce_order_item_name', array( $this,'cfwc_cart_item_name' ), 10, 3 );
		
		add_filter( 'wc_order_statuses', array( $this,'change_statuses_order') );
		
		//add status for partial refund
		add_action( 'init', array( $this,'register_awaiting_shipment_order_status') );
		add_filter( 'wc_order_statuses', array( $this,'add_awaiting_shipment_to_order_statuses') );
		//end		
	}


	function register_awaiting_shipment_order_status($wc_statuses_arr1) {
		
		register_post_status( 'wc-partial-refund', array(
			'label'                     => 'Partial Refund',
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Partial Refund (%s)', 'Partial Refund (%s)' )
		) );		
	}
	

	function add_awaiting_shipment_to_order_statuses( $order_statuses ) {
		
		$new_order_statuses = array();
	 
		// add new order status after processing
		foreach ( $order_statuses as $key => $status ) {
	 
			$new_order_statuses[ $key ] = $status;
	 
			if ( 'wc-processing' === $key ) {
				$new_order_statuses['wc-partial-refund'] = 'Partial Refund';
			}
		}
		return $new_order_statuses;
	}
	

	function change_statuses_order( $wc_statuses_arr ){
		
		$wc_statuses_arr['wc-processing'] =  'Captured (Processing)';
		$wc_statuses_arr['wc-completed'] = 'Settled (Completed)';
		$wc_statuses_arr['wc-cancelled'] = 'Void (Canceled)';
		$wc_statuses_arr['wc-on-hold'] = 'Authorized (On-Hold)';
		return $wc_statuses_arr;
	}
	 
	
	function update_mp_sync_on_product_save( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ($meta_key == '_order_total' ) {	 // we've been editing the post
			$signup_fee1 = 0;
			$order = wc_get_order( $post_id );
			foreach( $order->get_items() as $item_id => $item_product ){
					//Get the product ID

					$product_id1 = $item_product->get_product_id();
					if ( Qualpay_Cart::is_product_recurring( $product_id1) ) {
						$use_plan1 = get_post_meta($product_id1, '_qualpay_use_plan', true );
						if($use_plan1 == 'no') {
							$signup_fee1 = $signup_fee1 + get_post_meta($product_id1, '_qualpay_setup_fee', true );
						} else {
							$plan_data1 = get_post_meta($product_id1, '_qualpay_plan_data');
							//print_r($plan_data1);
							$amt_setup1 = $plan_data1[0]->amt_setup;
							$signup_fee1 = $signup_fee1 + $amt_setup1;
						}
					}
			}
			if ( $signup_fee1 > 0 ) { ?><script>window.alert("This order includes set up fee. Set up fees are not supported for manual order creation. it will auto run from Qualpay.");</script><?php
			}
		}	
	}

	/**
	 * Adding the Capture Payment action if the order was only authorized.
	 *
	 * @param array $actions
	 * @return array
	 */
	public function order_actions( $actions ) {
		global $theorder;

		$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $theorder->id : $theorder->get_id();
		$order = wc_get_order( $order_id );
		$order_data = $order->get_data();
		$total = $order->get_total();
		$remaining_refund_amount = $order->get_remaining_refund_amount();
		if($order_data['status'] == 'on-hold')  {
			$actions['qualpay_order_capture'] = __( 'Capture Payment', 'qualpay' );
			if ( '1' === get_post_meta( $order_id, '_qualpay_authorized', true ) ) {
				$actions['qualpay_order_capture'] = __( 'Capture Payment', 'qualpay' );
			}
		}
		if($order_data['status'] == 'processing'  || $order_data['status'] == 'on-hold') {
			$actions['qualpay_order_void'] = __( 'Void Payment', 'qualpay' );
			if ( '1' === get_post_meta( $order_id, '_qualpay_authorized', true ) ) {
				$actions['qualpay_order_void'] = __( 'Void Payment', 'qualpay' );
			}
		}

		if($order_data['status'] == 'completed' ) {
			$actions['qualpay_order_refund'] = __( 'Refund Payment', 'qualpay' );
			if ( '1' === get_post_meta( $order_id, '_qualpay_authorized', true ) ) {
				$actions['qualpay_order_refund'] = __( 'Refund Payment', 'qualpay' );
			}
		} else {
			//echo $remaining_refund_amount;
			if($order_data['status'] == 'partial-refund') {
				if($remaining_refund_amount > 0) {
					$actions['qualpay_order_refund'] = __( 'Refund Payment', 'qualpay' );
					if ( '1' === get_post_meta( $order_id, '_qualpay_authorized', true ) ) {
						$actions['qualpay_order_refund'] = __( 'Refund Payment', 'qualpay' );
					}
				}
			}
		}
		
		if($order_data['status'] != 'completed')  { 
		//	if( $total <= 0 ) { ?>
			<script>
      			jQuery(function () {
					jQuery('.refund-items').hide();
				  });
			</script>
		<?php // }
		}
		return $actions;
	}

	/**
	 * Adding our Payment Captured message.
	 *
	 * @param array $messages Array of messages.
	 *
	 * @return mixed
	 */
	public function post_updated_messages( $messages ) {
	//	print_r($messages);
	//	exit;
		$messages['shop_order'][100] = __( 'Order updated.', 'qualpay' );
		return $messages;
	}

	/**
	 * Capture a previously authorized order.
	 *
	 * @param WC_Order $order Order Object.
	 */
	public function order_capture( $order ) {
		
		$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();
		
		$order = wc_get_order( $order_id );
			foreach( $order->get_items() as $item_id => $item_product ) {
				//Get the product ID
				$product_id1 = $item_product->get_product_id();
				if ( Qualpay_Cart::is_product_recurring( $product_id1) ) {
					$use_plan1 = get_post_meta($product_id1, '_qualpay_use_plan', true );
					if($use_plan1 == 'no') {
						$signup_fee1 = $signup_fee1 + get_post_meta($product_id1, '_qualpay_setup_fee', true );
					} else {
						$plan_data1 = get_post_meta($product_id1, '_qualpay_plan_data');
						$amt_setup1 = $plan_data1[0]->amt_setup;
						$signup_fee1 = $signup_fee1 + $amt_setup1;
					}
				}
			}
		
		if ( '1' === get_post_meta( $order_id, '_qualpay_authorized', true ) ) {
			$api  = new Qualpay_API();
			$args = array();
			
			//$args['amt_tran'] = $order->get_total();
			//$args['pg_id']  = get_post_meta( $order_id, '_qualpay_pg_id', true );
			$pg_id = get_post_meta( $order_id, '_qualpay_pg_id');
			//print_r($pg_id);
			for($i=0;$i<count($pg_id);$i++)
			{
				$pg_id_amount = $api->get_amount_pg_id($pg_id[$i]);
				$args['amt_tran'] = $pg_id_amount->amt_tran;
				$purchase_id = $pg_id_amount->purchase_id;
				$args['pg_id'] =$pg_id[$i];
				//exit;
				if($args['amt_tran'] != $signup_fee1 && $order_id == $purchase_id) {
						$response = $api->do_capture( $args );
						if ( ! is_wp_error( $response ) ) {
							$order->payment_complete( get_post_meta( $order_id, '_qualpay_auth_code', true ) );
							$order->add_order_note( __( 'Payment Captured', 'qualpay' ) );
							add_filter( 'redirect_post_location', array( __CLASS__, 'set_order_captured_message' ) );
						} else {
							$order->add_order_note( $response->get_error_message() );
						} 
					} else {
						$order->update_status('processing', __('Order Payment Captured.', 'qualpay'));
						$order->add_order_note( __( 'Payment Captured but sign up fee is already captured when transaction happened.', 'qualpay' ) );
						
					}
			}
			//exit;
		}
	}

	/**
	 * Void a previously authorized order.
	 *
	 * @param WC_Order $order Order Object.
	 */
	public function order_void( $order ) {
		//print_r($order);
		
		 $order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();
	
		 //if ( '1' === get_post_meta( $order_id, '_qualpay_authorized', true ) ) {
			$api  = new Qualpay_API();
			$args = array();
			$pg_id = get_post_meta( $order_id, '_qualpay_pg_id');
			
			for($i=0;$i<count($pg_id);$i++)
			{
				$pg_id_amount = $api->get_amount_pg_id($pg_id[$i]);
				$args['amt_tran'] = $pg_id_amount->amt_tran;
				$args['pg_id'] =$pg_id[$i];
				$response = $api->do_void( $args );
				
			}
			$subscription_id = get_post_meta( $order_id, '_qualpay_subscription_id');
			$customer_id = get_post_meta($order_id, '_qualpay_subscription_customer_id', true);
			if($subscription_id && $customer_id) {
				for($i=0;$i<count($subscription_id);$i++)
				{
					$args1['subscription_id'] = $subscription_id[$i];
					$args1['customer_id'] =$customer_id;
					$response1 = $api->do_subscription_cancel( $args1 );
					$order->add_order_note( __( 'Subscription Canceled', 'qualpay' ) );
				}
			}
			
			if ( ! is_wp_error( $response ) ) {
				$order->payment_complete( get_post_meta( $order_id, '_qualpay_auth_code', true ) );
				$order->add_order_note( __( 'Order Canceled', 'qualpay' ) );
				add_filter( 'redirect_post_location', array( __CLASS__, 'set_order_void_message' ) );
				$order->update_status('cancelled', __('Order Payment canceled.', 'qualpay'));
			} else {
				$order->add_order_note( $response->get_error_message() );
			}
			//exit;
	}

	/**
	 * Refund a previously authorized order.
	 *
	 * @param WC_Order $order Order Object.
	 */
	public function order_refund( $order ) {
		//print_r($order);
		//exit;
		 $order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();
	
		 //if ( '1' === get_post_meta( $order_id, '_qualpay_authorized', true ) ) {
			$api  = new Qualpay_API();
			$args = array();
			$pg_id = get_post_meta( $order_id, '_qualpay_pg_id');
			//print_r($pg_id);
			for($i=0;$i<count($pg_id);$i++)
			{
				$pg_id_amount = $api->get_amount_pg_id($pg_id[$i]);
				$args['amt_tran'] = $pg_id_amount->amt_tran;
				$args['pg_id'] =$pg_id[$i];
				$response = $api->do_refund($args);
				//print_r($response);
				if('000' === $response->rcode) {
					update_post_meta($order_id, '_qualpay_pg_id', $response->pg_id, $pg_id[$i]);
				}
				//exit;
			}
			
			if ( ! is_wp_error( $response ) ) {
				//$order->payment_complete( get_post_meta( $order_id, '_qualpay_auth_code', true ) );
				
				$order->payment_complete( get_post_meta( $order_id, '_qualpay_auth_code', true ) );
				$order->add_order_note( __( 'Full Order Refunded', 'qualpay' ) );
				add_filter( 'redirect_post_location', array( __CLASS__, 'set_order_refunded_message' ) );
				$order->update_status('refunded', __('Order Payment Refunded.', 'qualpay'));
			} else {
				$order->add_order_note( $response->get_error_message() );
			}
	}


	/**
	 * @param $location
	 *
	 * @return string
	 */
	public static function set_order_captured_message( $location ) {
		return add_query_arg( 'message', 100, $location );
	}

	public static function set_order_void_message( $location ) {
		return add_query_arg( 'message', 100, $location );
	}

	public static function set_order_refunded_message( $location ) {
		return add_query_arg( 'message', 100, $location );
	}
}

new Qualpay_Order();
?>