<?php

/**
 * Class Qualpay_Product
 * Used for hooking and filtering product parts
 */
class Qualpay_Product {

	/**
	 * Qualpay_Product constructor.
	 */
	public function __construct() {

		if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			add_filter( 'woocommerce_get_price', array( $this, 'get_price' ), 20, 2 );
		} else {
			add_filter( 'woocommerce_product_get_price', array( $this, 'get_price' ), 20, 2 );
		}

		add_filter( 'woocommerce_product_get_price', array( $this, 'get_price' ), 20, 2 );
	//	add_filter( 'woocommerce_is_sold_individually', array( $this, 'is_sold_individually' ), 999, 2 );
		add_filter( 'woocommerce_get_price_html', __CLASS__ . '::get_price_html', 999, 2 );
	}

	/**
	 * Return True if it's a Qualpay recurring product.
	 *
	 * @param boolean    $bool
	 * @param WC_Product $product
	 * @return boolean
	 */
	public function is_sold_individually( $bool, $product ) {

		if ( ! $bool ) {
			$product_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->id : $product->get_id();
			if ( get_post_meta( $product_id, '_qualpay', true ) ) {
				$bool = true;
			}
		}

		return $bool;
	}

	/**
	 * Get Product Price
	 *
	 * @param string $price Price.
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public function get_price( $price, $product ) {

		$product_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->id : $product->get_id();

		if ( get_post_meta( $product_id, '_qualpay', true ) ) {
			$amount = get_post_meta( $product_id, '_qualpay_amount', true );
			$qualpay_use_plan = get_post_meta( $product_id, '_qualpay_use_plan', true );
			if ( 'yes' === $qualpay_use_plan ) {
				$plan_data = get_post_meta( $product_id, '_qualpay_plan_data', true );
				if ( $plan_data ) {
					$amount = (float) $plan_data->amt_tran;
				}
			}
			return $amount;
		}

		return $price;
	}

	/**
	 * Get the formatted price HTML
	 *
	 * @param integer $product_id
	 * @param boolean $with_setup If true, it will show the setup price.
	 * @param array   $data       Array of information.
	 * @return string
	 */
	public static function get_formatted_price_html( $product_id, $with_setup = true, $data = array() ) {
		$qualpay_use_plan = get_post_meta( $product_id, '_qualpay_use_plan', true );
		if ( 'yes' === $qualpay_use_plan ) {
			$plan_data = get_post_meta( $product_id, '_qualpay_plan_data', true );
			if ( $plan_data ) {
				if ( intval( $plan_data->plan_duration ) > -1 ) {
					$billing = 'no';
				} else {
					$billing = 'yes';
				}

				$data_default = array(
					'amount' => (float) $plan_data->amt_tran,
					'setup'  => (float) $plan_data->amt_setup,
					'interval' => (int) $plan_data->interval,
					'frequency' => (int) $plan_data->plan_frequency,
					'billing' => $billing,
					'duration' => (int) $plan_data->plan_duration,
				);

				$data = wp_parse_args( $data, $data_default );
			}
		}
		$amount     = isset( $data['amount'] ) ? $data['amount'] : (float) get_post_meta( $product_id, '_qualpay_amount', true );
		$setup      = isset( $data['setup'] ) ? $data['setup'] : (float) get_post_meta( $product_id, '_qualpay_setup_fee', true );
		$interval   = isset( $data['interval'] ) ? $data['interval'] : (int) get_post_meta( $product_id, '_qualpay_interval', true );
		$frequency  = isset( $data['frequency'] ) ? $data['frequency'] : (int) get_post_meta( $product_id, '_qualpay_frequency', true );
		$billing    = isset( $data['billing'] ) ? $data['billing'] : get_post_meta( $product_id, '_qualpay_bill_until_cancelled', true );

		$html = '';

		if ( $with_setup && $setup ) {
			$html .= sprintf( __( '%s first, then %s', 'qualpay'), wc_price( $amount + $setup ), wc_price( $amount ) );
		} else{
			$html .= wc_price( $amount );
		}

		$frequency_strings = array(
			0 => _n( 'week', 'weeks', $interval, 'qualpay' ),
			1 => __( 'bi-weekly', 'qualpay' ),
			3 => _n( 'month', 'months', $interval, 'qualpay' ),
			4 => __( 'quarterly', 'qualpay' ),
			5 => __( 'bi-annually', 'qualpay' ),
			6 => __( 'year', 'qualpay' ),
		);

		$html .= ' ';

		if ( $frequency === 0 || $frequency === 3 ) {
			$html .= sprintf( __( 'every %1$s %2$s', 'qualpay' ),  $interval, $frequency_strings[ $frequency ] );
		} elseif ( $frequency === 6 ) {
			$html .= sprintf( __( 'every %s', 'qualpay' ), $frequency_strings[ $frequency ] );
		} else {
			$html .= sprintf( __( '%s', 'qualpay' ), $frequency_strings[ $frequency ] );
		}

		if ( 'no' === $billing ) {
			$duration = isset( $data['duration'] ) ? $data['duration'] : get_post_meta( $product_id, '_qualpay_duration', true );
			$html .= ' ' . __( 'for', 'qualpay' ) . ' ' . $duration . ' ' . _n( 'billing cycle', 'billing cycles', $duration, 'qualpay' );
		}

		return $html;
	}

	/**
	 * Return the formatted HTML for price.
	 *
	 * @param string     $html    Formatted Price Tag.
	 * @param WC_Product $product Product Object.
	 * @return string
	 */
	public static function get_price_html( $html, $product ) {
		$product_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->id : $product->get_id();

		if ( get_post_meta( $product_id, '_qualpay', true ) ) {
			$html = self::get_formatted_price_html( $product_id );
		}

		return $html;
	}
}

new Qualpay_Product();