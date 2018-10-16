<?php
/**
 * QualPay Form
 */
$plan_id = 0;
$plan_code = '';
$plan_name = '';
$plan_desc = '';
$amt_tran  = 0;
$plan_frequency = 0;
$interval = 0;
$bill_specific_day = false;
$day_of_week = 0;
$month = 0;
$day_of_month = 0;
$prorate_first_pmt = false;
$amt_prorate = 0;
$plan_duration = 0;
$amt_setup = 0;
$trial_duration = 0;
$amt_trial = 0;

if ( isset( $plan_object) && $plan_object ) {
    $plan_id   = $plan_object->plan_id;
    $plan_code = $plan_object->plan_code;
    $plan_name = $plan_object->plan_name;
    $plan_desc = $plan_object->plan_desc;
    $amt_tran  = $plan_object->amt_tran;
    $plan_frequency = $plan_object->plan_frequency;
    $interval  = $plan_object->interval;
    $bill_specific_day = $plan_object->bill_specific_day;
    $day_of_week = $plan_object->day_of_week;
    $month = $plan_object->month;
    $day_of_month = $plan_object->day_of_month;
    $prorate_first_pmt = $plan_object->prorate_first_pmt;
    $amt_prorate = $plan_object->amt_prorate;
    $plan_duration = $plan_object->plan_duration;
    $amt_setup = $plan_object->amt_setup;
	$trial_duration = $plan_object->trial_duration;
	$amt_trial = $plan_object->amt_trial;
}


?>
<input type="hidden" name="qualpay_plan[id]" value="<?php echo $plan_id; ?>" />
<table class="form-table qualpay-form">
	<tbody>
	<tr valign="top">
		<th>
			<?php _e( 'Code', 'qualpay' ); ?>
		</th>
		<td>
			<input type="text" value="<?php echo $plan_code; ?>" <?php if( $plan_code ) { echo 'disabled="disabled"'; } ?> name="qualpay_plan[code]" class="widefat" />
		</td>
	</tr>
	<tr valign="top">
		<th>
			<?php _e( 'Name', 'qualpay' ); ?>
		</th>
		<td>
			<input type="text" value="<?php echo $plan_name; ?>" <?php if( $plan_name ) { echo 'disabled="disabled"'; } ?> name="qualpay_plan[name]"  class="widefat" />
		</td>
	</tr>
	<tr valign="top">
		<th>
			<?php _e( 'Description', 'qualpay' ); ?>
		</th>
		<td>
			<textarea name="qualpay_plan[desc]"  class="widefat"><?php echo $plan_desc; ?></textarea>
		</td>
	</tr>
    <tr valign="top">
        <th>
			<?php _e( 'Plan Amount', 'qualpay' ); ?>
        </th>
        <td>
            <input type="number" step="0.01" value="<?php echo $amt_tran; ?>" name="qualpay_plan[amt_tran]" />
        </td>
    </tr>
	<tr valign="top">
		<th>
			<label for="frequency"><?php _e( 'Frequency', 'qualpay' ); ?></label>
		</th>
		<td>
			<select id="frequency" name="qualpay_plan[frequency]">
				<option <?php selected( $plan_frequency, 0, true ); ?> value="0"><?php _e( 'Weekly', 'qualpay' ); ?></option>
				<option <?php selected( $plan_frequency, 1, true ); ?> value="1"><?php _e( 'Bi-Weekly', 'qualpay' ); ?></option>
				<option <?php selected( $plan_frequency, 3, true ); ?> value="3"><?php _e( 'Monthly', 'qualpay' ); ?></option>
				<option <?php selected( $plan_frequency, 4, true ); ?> value="4"><?php _e( 'Quarterly', 'qualpay' ); ?></option>
				<option <?php selected( $plan_frequency, 5, true ); ?> value="5"><?php _e( 'Bi-Annually', 'qualpay' ); ?></option>
				<option <?php selected( $plan_frequency, 6, true ); ?> value="6"><?php _e( 'Annually', 'qualpay' ); ?></option>
			</select>
			<span data-show="0,3" class="interval">
				<label>
					<?php _e( 'Every', 'qualpay' ); ?>
					<input type="number" name="qualpay_plan[interval]"  style="width:auto"  min="1" max="99" value="<?php echo $interval; ?>"/>
					<span data-show="0"><?php _e( 'Week(s)', 'qualpay' ); ?></span>
                    <span data-show="3" class="hidden"><?php _e( 'Month(s)', 'qualpay' ); ?></span>
				</label>
			</span>
			<div class="specific_day">
				<p>
					<label>
						<input <?php checked( false, $bill_specific_day, true ); ?> type="radio" id="start_date" name="qualpay_plan[bill_specific_day]" value="false" />
						<?php esc_html_e( 'On the subscription start date', 'qualpay' ); ?>
					</label>
				</p>

                <!-- HTML for Weekly and Bi-Weekly -->
				<p data-show="0,1" <?php if( $plan_frequency !== 1 && $plan_frequency !== 0 ) { echo 'style="display:none;"'; } ?>>
					<label>
						<input <?php if( $plan_frequency === 1 || $plan_frequency === 0 ) { checked( true, $bill_specific_day, true ); } ?> type="radio" name="qualpay_plan[bill_specific_day]" value="true" />
						<?php esc_html_e( 'On the same day every', 'qualpay' ); ?>
                        <span data-show="1"><?php esc_html_e( 'other', 'qualpay' ); ?></span>
					</label>
					<select name="qualpay_plan[day_of_week]" style="width:auto">
						<option <?php selected( $day_of_week, 1, true ); ?> value="1"><?php _e( 'Sunday', 'qualpay' ); ?></option>
						<option <?php selected( $day_of_week, 2, true ); ?> value="2"><?php _e( 'Monday', 'qualpay' ); ?></option>
						<option <?php selected( $day_of_week, 3, true ); ?> value="3"><?php _e( 'Tuesday', 'qualpay' ); ?></option>
						<option <?php selected( $day_of_week, 4, true ); ?> value="4"><?php _e( 'Wednesday', 'qualpay' ); ?></option>
						<option <?php selected( $day_of_week, 5, true ); ?> value="5"><?php _e( 'Thursday', 'qualpay' ); ?></option>
						<option <?php selected( $day_of_week, 6, true ); ?> value="6"><?php _e( 'Friday', 'qualpay' ); ?></option>
						<option <?php selected( $day_of_week, 7, true ); ?> value="7"><?php _e( 'Saturday', 'qualpay' ); ?></option>
					</select>
				</p>

                <p data-show="3,4,5,6" <?php if( $plan_frequency === 1 || $plan_frequency === 0 ) { echo 'style="display:none;"'; } ?>>
                    <label>
                        <input <?php if( $plan_frequency !== 1 && $plan_frequency !== 0 ) { checked( true, $bill_specific_day, true ); } ?> type="radio" name="qualpay_plan[bill_specific_day]" value="true" />
                        <!-- Monthly -->
                        <span data-show="3" <?php if( $plan_frequency !== 3 ) { echo 'style="display:none;"'; } ?>><?php esc_html_e( 'On the same day each month', 'qualpay' ); ?></span>
                        <!-- Quarterly, Bi-Annually, Annually -->
                        <span data-show="4,5,6" <?php if( $plan_frequency !== 4 && $plan_frequency !== 5 && $plan_frequency !== 6) { echo 'style="display:none;"'; } ?>><?php esc_html_e( 'Every', 'qualpay' ); ?></span>
                    </label>
                    <!-- Quarterly -->
                    <select data-show="4" name="qualpay_plan[month_4]" style="width:auto;<?php if( $plan_frequency !== 4 ) { echo 'display:none;'; } ?>">
                        <option <?php if( $plan_frequency === 4 ) { selected( $month, 1, true ); } ?> value="1"><?php _e( 'Jan,Apr,Jul,Oct', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 4 ) { selected( $month, 2, true ); } ?> value="2"><?php _e( 'Feb,May,Aug,Nov', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 4 ) { selected( $month, 3, true ); } ?> value="3"><?php _e( 'Mar,Jun,Sep,Dec', 'qualpay' ); ?></option>
                    </select>
                    <!-- Bi-Annually -->
                    <select data-show="5" name="qualpay_plan[month_5]" style="width:auto;<?php if( $plan_frequency !== 5 ) { echo 'display:none;'; } ?>">
                        <option <?php if( $plan_frequency === 5 ) { selected( $month, 1, true ); } ?> value="1"><?php _e( 'Jan,Jul', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 5 ) { selected( $month, 2, true ); } ?> value="2"><?php _e( 'Feb,Aug', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 5 ) { selected( $month, 3, true ); } ?> value="3"><?php _e( 'Mar,Sep', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 5 ) { selected( $month, 4, true ); } ?> value="4"><?php _e( 'Apr,Oct', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 5 ) { selected( $month, 5, true ); } ?> value="5"><?php _e( 'May,Nov', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 5 ) { selected( $month, 6, true ); } ?> value="6"><?php _e( 'Jun,Dec', 'qualpay' ); ?></option>
                    </select>
                    <!-- Annually -->
                    <select data-show="6" name="qualpay_plan[month_6]" style="width:auto;<?php if( $plan_frequency !== 6 ) { echo 'display:none;'; } ?>">
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 1, true ); } ?> value="1"><?php _e( 'Jan', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 2, true ); } ?> value="2"><?php _e( 'Feb', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 3, true ); } ?> value="3"><?php _e( 'Mar', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 4, true ); } ?> value="4"><?php _e( 'Apr', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 5, true ); } ?> value="5"><?php _e( 'May', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 6, true ); } ?> value="6"><?php _e( 'Jun', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 7, true ); } ?> value="7"><?php _e( 'Jul', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 8, true ); } ?> value="8"><?php _e( 'Aug', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 9, true ); } ?> value="9"><?php _e( 'Sep', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 10, true ); } ?> value="10"><?php _e( 'Oct', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 11, true ); } ?> value="11"><?php _e( 'Nov', 'qualpay' ); ?></option>
                        <option <?php if( $plan_frequency === 6 ) { selected( $month, 12, true ); } ?> value="12"><?php _e( 'Dec', 'qualpay' ); ?></option>
                    </select>

                    <select name="qualpay_plan[day_of_month]" style="width:auto">
                        <?php
                            for( $i = 1; $i <= 32; $i++ ) { ?>
                                <option <?php selected( $day_of_month, $i, true ); ?> value="<?php echo $i; ?>"><?php echo $i === 32 ? __( 'Last Day', 'qualpay' ) : $i; ?></option>
                            <?php }
                        ?>
                    </select>
                </p>
			</div>
			<div class="prorate" <?php if ( ! $bill_specific_day ) { echo 'style="display:none"'; } ?>>
				<label>
					<input <?php checked( $prorate_first_pmt, true, true ); ?> type="checkbox" name="qualpay_plan[prorate_first_pmt]" value="true"/>
					<?php esc_html_e( 'Prorate partial month payment on subscription start date', 'qualpay' ); ?>
				</label>
				<p <?php if ( ! $prorate_first_pmt ) { echo 'style="display:none"'; } ?>>
					<label>
						<input <?php checked( $amt_prorate, 0, true ); ?> type="radio" checked="checked" name="qualpay_plan[calculate]" value="true" />
						<?php esc_html_e( 'Calculate it for me', 'qualpay' ); ?>
					</label>
				</p>
				<p <?php if ( ! $prorate_first_pmt ) { echo 'style="display:none"'; } ?>>
					<label>
						<input <?php if( $amt_prorate > 0 ) { echo 'checked="checked"'; } ?> type="radio" name="qualpay_plan[calculate]" value="false" />

						<?php esc_html_e( 'Fixed Prorate ', 'qualpay' ); ?>
						<input type="number" size="10" value="<?php echo $amt_prorate; ?>" name="qualpay_plan[amt_prorate]" />
					</label>
				</p>
			</div>
		</td>
	</tr>

	<tr valign="top">
		<th>
			<?php _e( 'Duration', 'qualpay' ); ?>
		</th>
		<td>
            <p>
                <label>
                    <input checked="checked" <?php checked( $plan_duration, 0, true ); ?> type="radio" name="qualpay_plan[duration]" value="unlimited" />
                    <?php esc_html_e( 'Bill until cancelled', 'qualpay' ); ?>
                </label>
            </p>
            <p>
                <label>
                    <input <?php if( $plan_duration > 0) { echo 'checked="checked"'; } ?> type="radio" name="qualpay_plan[duration]" value="limited" />
                    <?php esc_html_e( 'Bill for', 'qualpay' ); ?>
                    <input type="number"  style="width:auto" name="qualplay_plan[duration_value]" value="<?php echo $plan_duration; ?>" />
                    <span data-show="0,1,3,4,5"><?php esc_html_e( 'billing cycle(s)', 'qualpay' ); ?></span>
                    <span data-show="6"><?php esc_html_e( 'year(s)', 'qualpay' ); ?></span>
                </label>
            </p>
		</td>
	</tr>
	<tr valign="top">
		<th>
			<?php _e( 'One Time Fee', 'qualpay' ); ?>
		</th>
		<td>
			<input type="number" step="0.01" value="<?php echo $amt_setup; ?>" name="qualpay_plan[amt_setup]"   />
		</td>
	</tr>
    <tr valign="top">
        <th>
			<?php _e( 'Trial Period', 'qualpay' ); ?>
        </th>
        <td class="qualpay_trials">
            <input <?php if( $trial_duration > 0 ) { echo 'checked="checked"'; } ?>  type="checkbox" value="true" name="qualpay_plan[qualpay_plan_trial]" />
            <table class="form-table" <?php if( $trial_duration === 0 ) { echo 'style="display:none;"'; } ?>>
                <tr>
                    <th>
                        <label for="qualpay_trial_amount"><?php _e( 'Amount', 'qualpay' ); ?></label>
                    </th>
                    <td>
                        <input id="qualpay_trial_amount" type="number" name="qualpay_plan[amt_trial]" value="<?php echo $amt_trial; ?>" size="5" />
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="qualpay_trial_duration"><?php _e( 'Duration', 'qualpay' ); ?></label>
                    </th>
                    <td>
                        <input id="qualpay_trial_duration" type="number" name="qualpay_plan[amt_trial]" value="<?php echo $trial_duration; ?>" size="5" />
                    </td>
                </tr>
            </table>
        </td>
    </tr>
	</tbody>
</table>
