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
		add_action('admin_head', array($this, 'hide_wc_refund_button'), 20, 2 );
	}

	/**
	 * Hide refund button when manual order create and add a product
	 */
	function hide_wc_refund_button() {
		global $post;
		if ( is_admin() && isset( $post ) && ! empty( $post )) {
			if($post->post_status == 'auto-draft') { ?><script>
			jQuery(function () {
					jQuery('.refund-items').hide();
					jQuery(document).ajaxComplete(function() {
							jQuery('.refund-items').css("display","none");})
					});
			</script><?php
			}
		}
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
					'amt_trial' => (float) $plan_data->amt_trial,
					'trial_duration' => (int) $plan_data->trial_duration,
					'bill_specific_day' => (int) $plan_data->bill_specific_day,
					'month' => (int) $plan_data->month,
					'day_of_month' => (int) $plan_data->day_of_month,
					'day_of_week' => (int) $plan_data->day_of_week,
				);

				$data = wp_parse_args( $data, $data_default );
			}
		}
		$amount     = isset( $data['amount'] ) ? $data['amount'] : (float) get_post_meta( $product_id, '_qualpay_amount', true );
		$setup      = isset( $data['setup'] ) ? $data['setup'] : (float) get_post_meta( $product_id, '_qualpay_setup_fee', true );
		$interval   = isset( $data['interval'] ) ? $data['interval'] : (int) get_post_meta( $product_id, '_qualpay_interval', true );
		$frequency  = isset( $data['frequency'] ) ? $data['frequency'] : (int) get_post_meta( $product_id, '_qualpay_frequency', true );
		$billing    = isset( $data['billing'] ) ? $data['billing'] : get_post_meta( $product_id, '_qualpay_bill_until_cancelled', true );
		$amt_trial  = isset( $data['amt_trial'] ) ? $data['amt_trial'] : (float) get_post_meta( $product_id, '_qualpay_amt_trial', true );
		$trial_duration = isset( $data['trial_duration'] ) ? $data['trial_duration'] : (int) get_post_meta( $product_id, '_qualpay_trial_duration', true );
		$bill_specific_day = isset( $data['bill_specific_day'] ) ? $data['bill_specific_day'] : 0;
		$month = isset( $data['month'] ) ? $data['month'] : 0;
		$day_of_month = isset( $data['day_of_month'] ) ? $data['day_of_month'] : 0;
		$day_of_week = isset( $data['day_of_week'] ) ? $data['day_of_week'] : 0;

		$html = '';

		// if ( $with_setup && $setup ) {
		// 	$html .= sprintf( __( '%s first, then %s', 'qualpay'), wc_price( $amount + $setup ), wc_price( $amount ) );
		// } else{
		// 	$html .= wc_price( $amount );
		// }
		
		$frequency_strings = array(
			0 => _n( 'week', 'weeks', $interval, 'qualpay' ),
			1 => __( 'bi-weekly', 'qualpay' ),
			3 => _n( 'month', 'months', $interval, 'qualpay' ),
			4 => __( 'quarterly', 'qualpay' ),
			5 => __( 'bi-annually', 'qualpay' ),
			6 => __( 'year', 'qualpay' ),
			7 => __( 'daily', 'qualpay')
		);

		$now = date('m/d/Y');

		if ( $frequency === 6 && $bill_specific_day && $month && $day_of_month) {
			$trialStartDate =  date('m/d/Y', strtotime(date($month.'/'.$day_of_month.'/Y')));;
			if(strtotime($now)>strtotime($trialStartDate)) {
				$trialStartDate = date('m/d/Y', strtotime("+12 months", strtotime($trialStartDate)));
			}
			$trial_duration = 12*$trial_duration;
			$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration." months", strtotime($trialStartDate)));
		} else if($frequency === 5 && $bill_specific_day && $month && $day_of_month) {
			$trialStartDate =  date('m/d/Y', strtotime(date($month.'/'.$day_of_month.'/Y')));
			$trialStartDate1 = date('m/d/Y', strtotime("+6 months", strtotime($trialStartDate)));
			if(strtotime($now)>strtotime($trialStartDate) && strtotime($now)>strtotime($trialStartDate1)){
				$trialStartDate = date('m/d/Y', strtotime("+6 months", strtotime($trialStartDate1)));
			} else if(strtotime($now)>strtotime($trialStartDate)){
				$trialStartDate = $trialStartDate1;
			} else {
				$trialStartDate;
			}
			$trial_duration = 6*$trial_duration;
			$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration." months", strtotime($trialStartDate)));
		} else if($frequency === 4 && $bill_specific_day && $month  && $day_of_month) {
			$trialStartDate =  date('m/d/Y', strtotime(date($month.'/'.$day_of_month.'/Y')));
			while(strtotime($trialStartDate) < strtotime(date('m/d/Y'))) {
				$trialStartDate = date('m/d/Y', strtotime("+3 months", strtotime($trialStartDate)));
			}	
			$trial_duration = 3*$trial_duration;
			$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration." months", strtotime($trialStartDate)));

		} else if($frequency === 3 && $bill_specific_day && $interval && $day_of_month) {
			$trialStartDate =  date('m/d/Y', strtotime(date('m/'.$day_of_month.'/Y')));
			if(strtotime($trialStartDate) < strtotime(date('m/d/Y'))) {
				$trialStartDate = date('m/d/Y', strtotime("+1 months", strtotime($trialStartDate)));
			}	
			$trial_duration = $interval*$trial_duration;
			$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration."months", strtotime($trialStartDate)));
		} else if($frequency === 1 && $bill_specific_day && $day_of_week) {
			$weekdays="Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday";
			$arr_weekdays=explode(",", $weekdays);
			$trialStartDate = date('m/d/Y', strtotime($arr_weekdays[$day_of_week-1]." this week"));
			if(strtotime($trialStartDate) < strtotime(date('m/d/Y'))) {
				$trialStartDate = date('m/d/Y', strtotime($arr_weekdays[$day_of_week-1]." next week"));
			}
			$trial_duration = 2*$trial_duration;
			$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration." weeks", strtotime($trialStartDate)));
		} else if($frequency === 0 && $bill_specific_day && $interval && $day_of_week) {
			$weekdays="Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday";
			$arr_weekdays=explode(",", $weekdays);
			$trialStartDate = date('m/d/Y', strtotime($arr_weekdays[$day_of_week-1]." this week"));
			if(strtotime($trialStartDate) < strtotime(date('m/d/Y'))) {
				$trialStartDate = date('m/d/Y', strtotime($arr_weekdays[$day_of_week-1]." next week"));
			}
			$trial_duration = $interval*$trial_duration;
			$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration." weeks", strtotime($trialStartDate)));
		} else {
			$trialStartDate =  date('m/d/Y', strtotime("+1 day", strtotime($now)));
			if ( $frequency === 0 || $frequency === 3 ) {
				if($interval)
					$trial_duration = $interval*$trial_duration;
				$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration." ".$frequency_strings[ $frequency ], strtotime($trialStartDate)));
			} else if ( $frequency === 1) {
				$trial_duration = 2*$trial_duration;
				$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration." weeks", strtotime($trialStartDate)));
			} else if ( $frequency === 4) {
				$trial_duration = 3*$trial_duration;
				$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration." months", strtotime($trialStartDate)));
			} else if ( $frequency === 5) {
				$trial_duration = 6*$trial_duration;
				$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration." months", strtotime($trialStartDate)));
			} else if ( $frequency === 6) {
				$trial_duration = 12*$trial_duration;
				$trialEndDate = date('m/d/Y', strtotime("+".$trial_duration." months", strtotime($trialStartDate)));
			}
		}
		
	
		if( $with_setup && $setup && $amt_trial && $trial_duration && $trial_duration > 0) {
			$html .= sprintf( __('One time fee %1$s billed Now, Trial amount %2$s for %3$s to %4$s. After %5$s rate is %6$s', 'qualpay'),wc_price($setup), wc_price($amt_trial), $trialStartDate, $trialEndDate, $trialEndDate, wc_price( $amount ) );
		} else if($amt_trial && $trial_duration && $trial_duration > 0) {
			$html .= sprintf( __('Trial amount %1$s for %2$s to %3$s. After %4$s rate is %5$s', 'qualpay'),wc_price($amt_trial), $trialStartDate, $trialEndDate, $trialEndDate, wc_price( $amount ) );
		} else if($with_setup && $setup ) {
			$html .= sprintf( __( '%s first, then %s', 'qualpay'), wc_price( $amount + $setup ), wc_price( $amount ) );
		} else{
			$html .= wc_price( $amount );
		}

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

		update_post_meta( $product_id, 'qualpay_amount_data', $html );
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