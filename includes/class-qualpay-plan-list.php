<?php

/**
 * List Table for Plans
 */

if ( !class_exists('WP_List_Table') ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Qualpay_Plan_List_Table extends WP_List_Table {

	public $plans_result = null;

	public function prepare_items()
	{
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$per_page = 10;
		$currentPage = $this->get_pagenum() - 1;
		$order_on = isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'plan_code';
		$orderby = isset( $_GET['order'] ) ? $_GET['order'] : 'asc';
		$data = $this->get_data( array( 'page' => $currentPage, 'order_on' => $order_on, 'order_by' => $orderby ) );
		$total_items = 0;

		if ( ! is_wp_error( $this->plans_result ) ) {
			$total_items = $this->plans_result->totalRecords;
		}

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
		) );

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items = $data;
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return Array
	 */
	public function get_columns()
	{
		$columns = array(
			'plan_id'   => __( 'ID', 'qualpay' ),
			'plan_name' => __( 'Name', 'qualpay' ),
			'plan_code' => __( 'Code', 'qualpay'),
			'plan'      => __( 'Plan', 'qualpay' ),
			'cost'      => __( 'Cost', 'qualpay' ),
		);

		return $columns;
	}

	/**
	 * Define which columns are hidden
	 *
	 * @return Array
	 */
	public function get_hidden_columns()
	{
		return array();
	}

	/**
	 * Define the sortable columns
	 *
	 * @return Array
	 */
	public function get_sortable_columns()
	{
		return array( 'plan_id' => array( 'plan_id', false ) );
	}

	/**
	 * Get the table data
	 *
	 * @return Array
	 */
	private function get_data( $args )
	{
		if ( ! $this->plans_result ) {
			$this->plans_result = Qualpay_API::get_plans( $args );
		}

		if ( is_wp_error( $this->plans_result ) || ! $this->plans_result ) {
			return array();
		}

		$data = array();

		foreach ( $this->plans_result->data as $plan_object ) {
			$data[] = (array) $plan_object;
		}

		return $data;
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  Array $item        Data
	 * @param  String $column_name - Current column name
	 *
	 * @return Mixed
	 */
	public function column_default( $item, $column_name )
	{
		switch( $column_name ) {
			case 'plan_id':
				$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=qualpay&plan=' . $item[ $column_name ] . '&plan_action=delete&plan_name=' . urlencode( $item['plan_name'] ) );
				return $item[ $column_name ] . '<br/><a class="button button-small" href="' . $url . '">' . __( 'Delete', 'qualpay' ) . '</a>';
			case 'plan_name':
				return $item[ $column_name ];
			case 'plan_code':
				$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=qualpay&plan=' . $item[ $column_name ] );
				return '<a href="' . $url . '">' . $item[ $column_name ] . '</a>';
			case 'plan':
				$output = '<p>' . __( 'Frequency', 'qualpay' ) . ': ' . $item['plan_frequency'] . '</p>';
				$output .= '<p>' . __( 'Interval', 'qualpay' ) . ': ' . $item['interval'] . '</p>';
				$output .= '<p>' . __( 'Duration', 'qualpay' ) . ': ' . $item['plan_duration'] . '</p>';
				return $output;
			case 'cost':
				$output = '<p>' . __( 'Setup', 'qualpay' ) . ': ' . wc_price( $item['amt_setup'] ) . '</p>';
				$output .= '<p>' . __( 'Transaction', 'qualpay' ) . ': ' . wc_price( $item['amt_tran'] ) . '</p>';
				return $output;
			default:
				return print_r( $item, true ) ;
		}
	}
}