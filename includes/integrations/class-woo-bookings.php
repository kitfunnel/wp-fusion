<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Woo_Bookings extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'woo-bookings';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WooCommerce bookings';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/woocommerce-bookings/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_meta_fields', array( $this, 'set_contact_field_names' ), 30 );

		add_action( 'wpf_woocommerce_payment_complete', array( $this, 'send_booking_date' ), 10, 2 );

	}

	/**
	 * Sends booking info so that date can be extracted
	 *
	 * @access public
	 * @return array Settings
	 */

	public function send_booking_date( $order_id, $contact_id ) {

		$booking_data = new WC_Booking_Data_Store();
		$booking_ids  = $booking_data->get_booking_ids_from_order_id( $order_id );

		if ( ! empty( $booking_ids ) ) {

			$booking    = get_wc_booking( $booking_ids[0] );
			$start      = $booking->get_start();
			$start_time = date( wpf_get_datetime_format(), $start );

			wp_fusion()->crm->update_contact( $contact_id, array( 'booking_date' => $start_time ) );

		}

	}

	/**
	 * Adds booking date field to contact fields list
	 *
	 * @access public
	 * @return array Settings
	 */

	public function set_contact_field_names( $meta_fields ) {

		$meta_fields['booking_date'] = array(
			'label' => 'Booking Date',
			'type'  => 'date',
			'group' => 'woocommerce',
		);

		return $meta_fields;

	}

}

new WPF_Woo_Bookings();
