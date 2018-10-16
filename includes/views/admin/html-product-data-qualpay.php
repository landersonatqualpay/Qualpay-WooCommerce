<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $thepostid, $post;
?>
<div id="qualpay_product_data" class="panel woocommerce_options_panel hidden">
    <div class="options_group">
        <?php
        $qualpay_use_plan = get_post_meta( $thepostid, '_qualpay_use_plan', true );
        woocommerce_wp_checkbox( array(
            'id'    => '_qualpay_use_plan',
            'label' => __( 'Use Plan?', 'qualpay' ),
            'value' => 'no' === $qualpay_use_plan ? 'no' : 'yes',
        ) );

        if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {

	        $selected_plan = array();
	        if ( 'yes' === $qualpay_use_plan ) {
		        $qualpay_plan_data = get_post_meta( $thepostid, '_qualpay_plan_data', true );
		        if ( $qualpay_plan_data ) {
			        $selected_plan[ $qualpay_plan_data->plan_code ] = $qualpay_plan_data->plan_name;
		        }
	        }
	        $keys = array_keys( $selected_plan );
	        woocommerce_wp_text_input( array(
                'type'              => 'hidden',
		        'id'                => '_qualpay_plan_code',
		        'label'             => __( 'Choose a Plan', 'qualpay' ),
		        'placeholder'       => __( 'Start typing to choose a plan', 'qualpay' ),
		        'class'             => 'wc-product-search',
		        'value'             => $keys[0],
		        'custom_attributes' => array(
			        'data-placeholder' => __( 'Start typing to choose a plan', 'qualpay' ),
			        'data-action'      => 'qualpay_search_plans',
			        'data-selected'    => $selected_plan[ $keys[ 0 ] ],
                    'data-allow_clear' => 'true',
		        ),
	        ) );
        } else {
			$get_all_plans = Qualpay_API::get_plans();
			$plans_data = $get_all_plans->data;
			
        ?>
        <p class="form-field _qualpay_plan_code_field">
            <label for="_qualpay_plan_code"><?php _e( 'Plans', 'woocommerce' ); ?></label>
			<select
                    id="_qualpay_plan_code"
                    name="_qualpay_plan_code"
                    class="wc-product-search"
                    data-placeholder="<?php esc_attr_e( 'Start typing to choose a plan', 'qualpay' ); ?>"
                   data-action="qualpay_search_plans"
                    data-allow_clear="true">
                <?php
                    if ( 'yes' === $qualpay_use_plan ) {
                        $qualpay_plan_data = get_post_meta( $thepostid, '_qualpay_plan_data', true );
                        if ( $qualpay_plan_data ) {
                            echo '<option selected="selected" value="' . $qualpay_plan_data->plan_code . '">' . $qualpay_plan_data->plan_name . '</option>';
                        }
					} /*  else {
							foreach($plans_data as $plan_all_data) {
						  		echo '<option value="' . $plan_all_data->plan_code . '">' . $plan_all_data->plan_name . '</option>';
							}
						} */
					
                ?>
            </select>
        </p>
        <?php } ?>
    </div>
	<div class="options_group" id="qualpay_custom_plan_group">
		<?php

		woocommerce_wp_select( array(
			'id'          => '_qualpay_frequency',
			'label'       => __( 'Frequency', 'qualpay' ),
			'options'     => array(
				0 => __( 'Weekly', 'qualpay' ),
				1 => __( 'Bi-Weekly', 'qualpay' ),
				3 => __( 'Monthly', 'qualpay' ),
				4 => __( 'Quarterly', 'qualpay' ),
				5 => __( 'Bi-Annually', 'qualpay' ),
				6 => __( 'Annually', 'qualpay' ),
			)
		) );

		woocommerce_wp_text_input( array(
			'id'          => '_qualpay_interval',
			'label'       => __( 'Interval', 'qualpay' ),
			'description' => __( 'Interval of Billing.', 'qualpay' ),
			'desc_tip'    => true,
			'type'        => 'number',
			'custom_attributes' => array(
				'min' => '0',
			)
		) );

		$bill_until_cancelled = get_post_meta( $thepostid, '_qualpay_bill_until_cancelled', true );
		woocommerce_wp_checkbox( array(
			'id'    => '_qualpay_bill_until_cancelled',
			'label' => __( 'Bill until cancelled', 'qualpay' ),
            'value' => 'no' === $bill_until_cancelled ? 'no' : 'yes',
		) );

		woocommerce_wp_text_input( array(
			'id'          => '_qualpay_duration',
			'label'       => __( 'Duration', 'qualpay' ),
			'description' => __( 'Billing Cycles', 'qualpay' ),
			'desc_tip'    => false,
			'type'        => 'number',
		) );

		woocommerce_wp_text_input( array(
			'id'          => '_qualpay_amount',
			'label'       => __( 'Amount', 'qualpay' ),
			'description' => __( 'Recurring Amount', 'qualpay' ),
			'desc_tip'    => true,
			'type'        => 'number',
			'data_type'   => 'price',
			'custom_attributes' => array(
				'step' => '0.01',
			)
		) );

		woocommerce_wp_text_input( array(
			'id'          => '_qualpay_setup_fee',
			'label'       => __( 'One Time Fee', 'qualpay' ),
			'description' => __( 'A charge for setting the recurring payment. Charged only on sign up.', 'qualpay' ),
			'desc_tip'    => true,
			'type'        => 'number',
			'data_type'   => 'price',
			'custom_attributes' => array(
				'pattern' => '[0-9]+([\.,][0-9]+)?',
				'step' => '0.01',
			)
		) );
		?>
	</div>
</div>
