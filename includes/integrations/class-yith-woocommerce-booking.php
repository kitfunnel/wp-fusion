<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * YITH WooCommerce Booking Integration Class.
 *
 * @class   WPF_YITH_Woo_Booking
 * @since   3.40.50
 */
class WPF_YITH_Woo_Booking extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.50
	 * @var string $slug
	 */

	public $slug = 'yith-woo-booking';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.50
	 * @var string $name
	 */
	public $name = 'YITH WooCommerce Booking';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.50
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/yith-woocommerce-booking/';

	/**
	 * Gets things started.
	 */
	public function init() {

		add_filter( 'wpf_meta_fields', array( $this, 'set_contact_field_names' ), 30 );

		add_filter( 'wpf_woocommerce_customer_data', array( $this, 'send_booking_date' ) );

	}

	/**
	 * Add dates to the order.
	 *
	 * @since 3.40.50
	 *
	 * @param array $customer_data The customer data.
	 * @return array The customer data.
	 */
	public function send_booking_date( $customer_data ) {

		if ( empty( $customer_data['yith_booking_data'] ) ) {
			return $customer_data;
		}

		$yith_data = $customer_data['yith_booking_data'];

		if ( ! empty( $yith_data['from'] ) ) {
			$customer_data['yith_booking_start_date'] = gmdate( wpf_get_datetime_format(), $yith_data['from'] );
		}

		if ( ! empty( $yith_data['to'] ) ) {
			$customer_data['yith_booking_end_date'] = gmdate( wpf_get_datetime_format(), $yith_data['to'] );
		}

		return $customer_data;

	}

	/**
	 * Adds booking dates field to contact fields list
	 *
	 * @since 3.40.50
	 *
	 * @param array $meta_fields The meta fields.
	 * @return array Meta fields
	 */
	public function set_contact_field_names( $meta_fields ) {

		$meta_fields['yith_booking_start_date'] = array(
			'label'  => 'Booking Start Date',
			'type'   => 'date',
			'pseudo' => true,
			'group'  => 'woocommerce',
		);

		$meta_fields['yith_booking_end_date'] = array(
			'label'  => 'Booking End Date',
			'type'   => 'date',
			'pseudo' => true,
			'group'  => 'woocommerce',
		);

		return $meta_fields;

	}

}

new WPF_YITH_Woo_Booking();
