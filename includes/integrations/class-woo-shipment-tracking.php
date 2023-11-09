<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Woo_Shipment_Tracking extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'woo-shipment-tracking';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WooCommerce Shipment Tracking';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/woocommerce-shipment-tracking/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'added_post_meta', array( $this, 'sync_shipping_link' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'sync_shipping_link' ), 10, 4 );

		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

	}


	/**
	 * Sync shipment tracking link when it's saved
	 *
	 * @access public
	 * @return void
	 */

	public function sync_shipping_link( $meta_id, $post_id, $meta_key, $meta_value ) {

		if ( '_wc_shipment_tracking_items' !== $meta_key || ! is_array( $meta_value ) ) {
			return;
		}

		if ( ! class_exists( 'WC_Shipment_Tracking_Actions' ) ) {
			return;
		}

		$st   = WC_Shipment_Tracking_Actions::get_instance();
		$item = $st->get_formatted_tracking_item( $post_id, end( $meta_value ) );

		if ( ! empty( $item['formatted_tracking_link'] ) ) {

			$order      = wc_get_order( $post_id );
			$user_id    = $order->get_user_id();
			$contact_id = get_post_meta( $post_id, WPF_CONTACT_ID_META_KEY, true );

			$update_data = array(
				'wc_shipment_tracking_link' => $item['formatted_tracking_link'],
			);

			if ( ! empty( $user_id ) ) {

				wp_fusion()->user->push_user_meta( $user_id, $update_data );

			} elseif ( ! empty( $contact_id ) ) {

				wpf_log( 'info', 0, 'Syncing shipment tracking link for WooCommerce order <a href="' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . '">#' . $post_id . '</a>:', array( 'meta_array_nofilter' => $update_data ) );

				wp_fusion()->crm->update_contact( $contact_id, $update_data );

			}
		}

	}

	/**
	 * Add field to settings
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['wc_shipment_tracking_link'] = array(
			'label' => 'Shipment Tracking Link',
			'type'  => 'text',
			'group' => 'woocommerce_order',
		);

		return $meta_fields;

	}


}

new WPF_Woo_Shipment_Tracking();
