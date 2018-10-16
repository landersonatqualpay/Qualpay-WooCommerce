<?php
/**
 * Showing all subscriptions per account.
 *
 * @todo Format the amount as on single product pages or similar as on Qualpay Dashboard.
 *       Add cancel button.
 */

if ( $subscriptions ) { ?>
	<table>
		<thead>
			<tr>
				<th>
					<?php esc_html_e( 'ID', 'qualpay' ); ?>
				</th>
				<th>
					<?php esc_html_e( 'Status', 'qualpay' ); ?>
				</th>
				<th>
					<?php esc_html_e( 'Amount', 'qualpay' ); ?>
				</th>
                <th>
					<?php esc_html_e( 'Action', 'qualpay' ); ?>
                </th>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $subscriptions as $subscription ) {
		    $qualpay_subscription = new Qualpay_Subscription( $subscription );
			?>
			<tr>
				<td><?php echo esc_html( $qualpay_subscription->subscription_id ); ?></td>
				<td><?php echo esc_html( $qualpay_subscription->get_status() ); ?></td>
				<td>
                    <?php echo $qualpay_subscription->get_formatted_amount(); ?><br/>
					<?php echo $qualpay_subscription->get_next_date(); ?>
                </td>
                <td><?php echo $qualpay_subscription->get_actions(); ?></td>
			</tr>
			<?php
		}
		?>
		</tbody>
	</table>
    <?php
    if ( $total_pages > 1 ) {
        echo '<div class="qualpay-subscription-pagination">';
        for ( $i = 1; $i <= $total_pages; $i++ ) {
            echo '<a href="' . esc_url( untrailingslashit( $recurring_url ) ) . '/' . $i . '">' . $i . '</a>';
        }
        echo '</div>';
    }
    ?>
<?php } else { ?>
	<p>
		<?php _e( 'No Subscriptions', 'qualpay' ); ?>
	</p>
<?php } ?>