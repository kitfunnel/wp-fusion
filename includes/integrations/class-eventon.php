<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * EventON integration.
 *
 * @since 3.38.5
 *
 * @link https://wpfusion.com/documentation/events/eventon/
 */
class WPF_EventON extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'eventon';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Eventon';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started.
	 *
	 * @since 3.38.5
	 */
	public function init() {

		$this->name = 'EventON';

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

		add_filter( 'wpf_woocommerce_customer_data', array( $this, 'merge_custom_fields' ), 10, 2 );

	}


	/**
	 * Merges custom fields for the primary contact on the order
	 *
	 * @since  3.38.5
	 *
	 * @param  array    $customer_data The customer data to sync to the CRM.
	 * @param  WC_Order $order         The WooCommerce order.
	 * @return array    Customer data.
	 */
	public function merge_custom_fields( $customer_data, $order ) {

		foreach ( $order->get_items() as $item_id => $item ) {

			$product_id = $item->get_product_id();
			$event_id   = get_post_meta( $product_id, '_eventid', true );

			if ( empty( $event_id ) ) {
				continue;
			}

			$event_fields = array(
				'event_name'       => get_the_title( $event_id ),
				'event_start_date' => get_post_meta( $event_id, 'evcal_srow', true ),
				'event_end_date'   => get_post_meta( $event_id, 'evcal_erow', true ),
				'event_start_time' => get_post_meta( $event_id, '_start_hour', true ) . ':' . get_post_meta( $event_id, '_start_minute', true ) . ' ' . get_post_meta( $event_id, '_start_ampm', true ),
				'event_end_time'   => get_post_meta( $event_id, '_end_hour', true ) . ':' . get_post_meta( $event_id, '_end_minute', true ) . ' ' . get_post_meta( $event_id, '_end_ampm', true ),
			);

			$customer_data = array_merge( $customer_data, $event_fields );

			break;

		}

		return $customer_data;

	}

	/**
	 * Adds EventON field group to meta fields list.
	 *
	 * @since  3.38.5
	 *
	 * @param  array $field_groups The field groups.
	 * @return array  Field groups.
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['eventon'] = array(
			'title'  => 'EventON',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Loads EventON fields for inclusion in Contact Fields table.
	 *
	 * @since  3.38.5
	 *
	 * @param  array $meta_fields The meta fields.
	 * @return array The meta fields.
	 */
	public function add_meta_fields( $meta_fields ) {

		$meta_fields['event_name'] = array(
			'label'  => 'Event Name',
			'type'   => 'text',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		$meta_fields['event_start_date'] = array(
			'label'  => 'Event Start Date',
			'type'   => 'date',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		$meta_fields['event_start_time'] = array(
			'label'  => 'Event Start Time',
			'type'   => 'date',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		$meta_fields['event_end_date'] = array(
			'label'  => 'Event End Date',
			'type'   => 'date',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		$meta_fields['event_end_time'] = array(
			'label'  => 'Event End Time',
			'type'   => 'date',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		return $meta_fields;

	}


}

new WPF_EventON();
