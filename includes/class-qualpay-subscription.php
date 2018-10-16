<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Qualpay_Subscription
 */
class Qualpay_Subscription {

	/**
	 * Subscription Data.
	 *
	 * @var null|array {
	 *      @type int       $subscription_id    Subscription integer.
	 *      @type int       $merchant_id        Merchant ID.
	 *      @type int       $sandbox_merchant_id   sandbox Merchant ID.
	 *      @type string    $date_sztart         Date Start. Format Y-m-d.
	 *      @type string    $date_next          Next date of billing. Format Y-m-d.
	 *      @type string    $date_end           End date of Subscription. Format Y-m-d.
	 *      @type string    $prorate_date_start Start date of prorate payment.
	 *      @type float     $prorate_amt        Prorate Amount.
	 *      @type string    $trial_date_start   Trial Start Date. Format Y-m-d.
	 *      @tpye string    $trial_date_end     Trial End Date. Format Y-m-d.
	 *      @type float     $trial_amt          Trial Amount.
	 *      @type string    $recur_date_start
	 *      @type string    $recur_date_end
	 *      @type float     $recur_amt
	 *      @type float     $amt_setup
	 *      @type string    $status
	 *      @type string    $profile_id
	 *      @type string    $customer_id
	 *      @type string    $customer_first_name
	 *      @type string    $customer_last_name
	 *      @type int       $plan_id
	 *      @type string    $plan_name
	 *      @type string    $plan_code
	 *      @type string    $tran_currency
	 *      @type int       $plan_frequency
	 *      @type int       $plan_duration
	 *      @type string    $plan_desc
	 *      @type bool      $subscription_on_plan
	 *      @type int       $interval
	 * }
	 */
	private $data = null;

	/**
	 * Qualpay_Subscription constructor.
	 *
	 * @param object $subscription_data
	 */
	public function __construct( $subscription_data ) {
		$this->data = (array) $subscription_data;
	}

	/**
	 * Getter Method for getting Subscription Data.
	 * @param $name
	 *
	 * @return mixed|null
	 */
	public function __get( $name ) {
		if ( array_key_exists( $name, $this->data ) ) {
			return $this->data[$name];
		}

		return null;
	}

	/**
	 * @param string $context If 'view', we will show the text. Otherwise, we get the actual string.
	 *
	 * @return string
	 */
	public function get_status( $context = 'view' ) {
		if( 'view' === $context ) {
			switch ( $this->status ) {
				case 'A':
					return __( 'Active', 'qualpay' );
					break;
				case 'D':
					return __( 'Complete', 'qualpay' );
					break;
				case 'P':
					return __( 'Paused', 'qualpay' );
					break;
				case 'C':
					return __( 'Canceled', 'qualpay' );
					break;
				case 'S':
					return __( 'Suspended', 'qualpay' );
					break;
			}
		}
		return $this->status;
	}

	/**
	 * Get the Subscription Formatted Amount.
	 *
	 * @return string.
	 */
	public function get_formatted_amount() {
		return Qualpay_Product::get_formatted_price_html( 0, false, array(
			'amount'    => $this->recur_amt,
			'setup'     => $this->amt_setup,
			'frequency' => $this->plan_frequency,
			'interval'  => $this->interval,
			'billing'   => -1 === $this->plan_duration ? 'yes' : 'no',
			'duration'  => $this->plan_duration,
		));
	}

	/**
	 * Returning the next date. If there is no date, we return an empty string.
	 *
	 * @return string
	 */
	public function get_next_date() {
		if( ! $this->date_next ) {
			return '';
		}
		return '<em>' . esc_html( sprintf( __( 'Next Date: %s', 'qualpay' ), $this->date_next ) ) . '</em>';
	}

	/**
	 * Return the actions based on the Subscription Status.
	 */
	public function get_actions() {
		$status  = $this->get_status( 'edit' );
		$actions = '<form method="POST">';
		$actions .= '<input type="hidden" name="qualpay_subscription_id" value="' . $this->subscription_id . '" />';
		$actions .= '<input type="hidden" name="qualpay_subscription_nonce" value="' . wp_create_nonce( 'qualpay_subscription_' . $this->subscription_id . get_current_user_id() ) . '" />';
		if( 'P' === $status || 'S' === $status ) {
			$actions .= '<button type="submit" name="qualpay_subscription_action" value="resume">' . __( 'Resume', 'qualpay' ) . '</button>';
		}

		if( 'A' === $status ) {
			$actions .= '<button type="submit" name="qualpay_subscription_action" value="pause">' . __( 'Pause', 'qualpay' ) . '</button>';
		}

		if( 'A' === $status || 'P' === $status || 'S' === $status ) {
			$actions .= '<button type="submit" name="qualpay_subscription_action" value="cancel">' . __( 'Cancel', 'qualpay' ) . '</button>';
		}
		$actions .= '</form>';
		return $actions;
	}
}