<?php

if( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Defining the cart functionalities
 */
class Qualpay_Cart {

	public function __construct() {

		add_action( 'woocommerce_cart_needs_payment', array( $this,'filter_woocommerce_cart_needs_payment' ) , 10, 2 );

		add_action( 'woocommerce_add_to_cart_validation', array( $this, 'cart_validate' ), 10, 3 );
		add_action( 'woocommerce_payment_gateways', array( $this, 'filter_payment_gateways' ), 99 );
		//add_action( 'woocommerce_before_checkout_form', array( $this, 'make_checkout_registration_possible' ), -1 );
		//add_action( 'woocommerce_before_checkout_process',array( $this, 'force_registration_during_checkout' ), 10 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'make_checkout_account_fields_required' ) );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'set_quantity_to_one' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_signup_fee' ) );
		add_action( 'woocommerce_cart_product_subtotal', array( $this, 'cart_product_subtotal' ), 99, 2 );

		add_action( 'woocommerce_calculated_total', array( $this,'custom_calculated_total' ) , 10, 2 );	
		// change subtotal for product
		add_filter( 'woocommerce_cart_item_subtotal', array( $this,'filter_woocommerce_cart_item_subtotal' ), 10, 3 ); 
			 
	}

	public function filter_woocommerce_cart_item_subtotal( $wc, $cart_item, $cart_item_key ) { 
		// make filter magic happen here... 
		if ( !WC()->cart->is_empty() ) {
			if( 'yes' === get_post_meta( $cart_item['product_id'], '_qualpay', true ) ) {
				$wc = "$0.00";
			} else {
					$wc = $wc;
			}
		}

		return $wc;
	} 
	
	public function filter_woocommerce_cart_needs_payment( $this_total_0, $instance)
	{
		$total =  $instance->get_total();
		if($total >= 0) {
			return $total;
		}
	}

	public function custom_calculated_total( $total, $cart ){
		
		if ( is_admin() && ! defined( 'DOING_AJAX' ) )
		return;
		global $woocommerce;
		$items = $woocommerce->cart->get_cart(); // get cart item
	
		if ( !WC()->cart->is_empty() ) {
			foreach($items as $item => $values) {
				if( 'yes' === get_post_meta( $values['product_id'], '_qualpay', true ) ) {
					if('yes' === get_post_meta( $values['product_id'], '_qualpay_use_plan', true ) ) {
						$mydata = get_post_meta($values['product_id'] , '_qualpay_plan_data', true); // get price
						$price = $mydata->amt_tran;
					} else {
						$mydata = get_post_meta($values['product_id'] , '_qualpay_amount', true); // get price
						$price = $mydata;
						
					}
					$qty = $values['quantity']; // get qty
					$total = $total - ($price * $qty); // reduce recurring product price from total
					$cart->subtotal = $cart->subtotal - $values['line_subtotal'];
					$cart->cart_contents[$item]['line_subtotal'] = '0';
					$cart->cart_contents[$item]['line_total'] = '0';
					$cart->cart_contents[$item]['subtotal'] = '0';
					$original_name = method_exists( $values['data'], 'get_name' ) ?  $values['data']->get_name() : $values['data']->post->post_title;
					$add_name = get_post_meta( $values['product_id'], 'qualpay_amount_data', true );
					//$original_price = method_exists( $values['data'], 'get_price' ) ? $values['data']->get_price() : $values['data']->post->post_title;
					$new_name = $original_name." (".$add_name.")";
					//$new_name = $original_name ."(Your first billing of $".$original_price." on your subscription's start date.)";
						if( method_exists( $values['data'], 'set_name' ) ) {
						
							$values['data']->set_name( $new_name );
							$values['data']->set_price('0.00');
						} else {
							$values['data']->post->post_title =  $new_name;
						}
						
				} 
				else{
					//$values['data']->set_price('0.00');
					$cart->subtotal = $cart->subtotal;
				} 
				
			}
			
			return $total; // return updated total
		}
   }

	/**
	 * Cart Product Subtotal
	 *
	 * @param string     $subtotal
	 * @param WC_Product $product
	 *
	 * @return mixed
	 */
	public function cart_product_subtotal( $subtotal, $product ) {
		$product_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->id : $product->get_id();

		if( self::is_product_recurring( $product_id ) ) {
			$subtotal = Qualpay_Product::get_formatted_price_html( $product_id, false );
		}
		return $subtotal;
	}

	/**
	 * Set the Item Quantity to 1 if the subscription gets updates to more than 1.
	 *
	 * @param string $cart_item_key Cart Item Key.
	 */
	public function set_quantity_to_one( $cart_item_key ) {
		if ( self::recurring_in_cart() ) {
			if( isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
				$cart_item = WC()->cart->cart_contents[ $cart_item_key ];
				if ( self::is_product_recurring( $cart_item['data']->get_id() ) ) {
				//	WC()->cart->cart_contents[ $cart_item_key ]['quantity'] = 1;
				}
			}
		} 
	}

	/**
	 * Adding signup fee if there is a subscription in cart.
	 *
	 * @param $cart
	 */
	public function add_signup_fee( $cart ) {
		//echo "inn";
		if ( self::recurring_in_cart() ) {
			if ( ! empty( WC()->cart->cart_contents )  ) {
				$signup_fee = 0;
				$quantity =0;
				foreach ( WC()->cart->cart_contents as $cart_item ) {
					if ( self::is_product_recurring( $cart_item['data']->get_id() ) ) {
						$use_plan = get_post_meta( $cart_item['data']->get_id(), '_qualpay_use_plan', true );
						if($use_plan == 'no') {
							$signup_fee = $signup_fee + get_post_meta( $cart_item['data']->get_id(), '_qualpay_setup_fee', true );
							$signup_fee = $cart_item['quantity'] * $signup_fee;
						} else {
							$plan_data = get_post_meta( $cart_item['data']->get_id(), '_qualpay_plan_data');
							$amt_setup = $plan_data[0]->amt_setup;
							if($cart_item['quantity'] > 1) {
								 $amt_setup = $cart_item['quantity'] * $amt_setup;
							} 
							$signup_fee = $signup_fee + $amt_setup;
						}
						
					}
				}
				if ( $signup_fee ) {
					$cart->add_fee( __( 'One-time Fee', 'qualpay' ), $signup_fee );
				}
				//exit;
			}
		}
		//exit;
	}

	/**
	 * During the checkout process, force registration when the cart contains a subscription.
	 */
	public function force_registration_during_checkout() {

		if ( self::recurring_in_cart() && ! is_user_logged_in() ) {
			$_POST['createaccount'] = 1;
		}

	}

	/**
	 * Make sure account fields display the required "*" when they are required.
	 */
	public function make_checkout_account_fields_required( $checkout_fields ) {

		if ( self::recurring_in_cart() && ! is_user_logged_in() ) {

			$account_fields = array(
				'account_username',
				'account_password',
				'account_password-2',
			);

			foreach ( $account_fields as $account_field ) {
				if ( isset( $checkout_fields['account'][ $account_field ] ) ) {
					$checkout_fields['account'][ $account_field ]['required'] = true;
				}
			}
		}

		return $checkout_fields;
	}

	/**
	 * Make Registration Required.
	 *
	 * @param $checkout
	 */
	public function make_checkout_registration_possible( $checkout ) {
		if ( self::recurring_in_cart() && ! is_user_logged_in() ) {

			// Make sure users are required to register an account
			if ( true === $checkout->enable_guest_checkout ) {
				$checkout->enable_guest_checkout = false;
				$checkout->must_create_account = true;
				add_action( 'woocommerce_after_checkout_form',array( $this, 'restore_checkout_registration_settings' ), 100 );
			}
		}
	}

	/**
	 * Resetting the checkout registration settings.
	 *
	 * @param $checkout
	 */
	public function restore_checkout_registration_settings( $checkout ) {
		$checkout->enable_guest_checkout = true;
		if ( ! is_user_logged_in() ) { // Also changed must_create_account
			$checkout->must_create_account = false;
		}
	}

	/**
	 * Filter the gateways if a Qualpay recurring product is in the cart.
	 *
	 * @param array $gateways
	 *
	 * @return array
	 */
	public function filter_payment_gateways( $gateways ) {
		if ( ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) ) {
			if( self::recurring_in_cart() ) {
				$gateways = array( 'WC_Gateway_Qualpay' );
			}
		}

		return $gateways;
	}

	/**
	 * Validating the Cart
	 *
	 * @param boolean $passed
	 * @param integer $product_id
	 * @param integer $quantity
	 *
	 * @return boolean
	 */
	public function cart_validate( $passed, $product_id, $quantity ) {
		if( $passed ) {

			if( self::is_product_recurring( $product_id ) ) {
			//	if ( ! WC()->cart->is_empty() && self::recurring_in_cart() ) {
			//		wc_add_notice( __( 'You can\'t have more than 1 Subscription in the Cart.', 'qualpay' ), 'error' );

			//		return false;
			//	}
			}
		}
		return $passed;
	}

	/**
	 * Check if a recurring product is in cart.
	 *
	 * @return boolean
	 */
	public static function recurring_in_cart() {

		$in_cart = false;

		if ( ! empty( WC()->cart->cart_contents )  ) {
			$order_id_order_pay = absint( get_query_var( 'order-pay' ) );
                if(!$order_id_order_pay) {
					foreach ( WC()->cart->cart_contents as $cart_item ) {
						if( self::is_product_recurring( $cart_item['data']->get_id() ) ) {
							$in_cart = true;
							break;
						}
					}
				}
				else {
					$order = wc_get_order($order_id_order_pay);
					$items = $order->get_items();
					foreach ( $items as $cart_item ) {
						if( self::is_product_recurring( $cart_item->get_product_id() ) ) {
							$in_cart = true;
							break;
						}
					}
				}
        }

		return $in_cart;

	}

	/**
	 * Product ID.
	 *
	 * @param $product_id
	 */
	public static function is_product_recurring( $product_id ) {
		if( 'yes' === get_post_meta( $product_id, '_qualpay', true ) ) {
			return true;
		}

		return false;
	}
}

new Qualpay_Cart();