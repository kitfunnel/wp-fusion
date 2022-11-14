<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Woo_Coupons extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'woo-coupons';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WooCommerce Smart Coupons';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/woocommerce-smart-coupons/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// Detect changes
		add_action( 'wc_sc_new_coupon_generated', array( $this, 'new_coupon_generated' ), 10, 1 );

		add_filter( 'wpf_woocommerce_customer_data', array( $this, 'get_coupon_data' ), 10, 2 );

		// Add coupon code to meta fields list
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

	}

	/**
	 * Copy settings to new coupon
	 *
	 * @access public
	 * @return void
	 */

	public function new_coupon_generated( $args ) {

		if ( empty( $args['ref_coupon'] ) ) {
			return;
		}

		$new_coupon_id = $args['new_coupon_id'];
		$ref_coupon    = $args['ref_coupon'];

		$settings = get_post_meta( $ref_coupon->get_id(), 'wpf-settings-woo', true );

		if ( ! empty( $settings ) ) {
			update_post_meta( $new_coupon_id, 'wpf-settings-woo', $settings );
		}

		// Usage restrictions

		$settings = get_post_meta( $ref_coupon->get_id(), 'wpf-settings', true );

		if ( ! empty( $settings ) ) {
			update_post_meta( $new_coupon_id, 'wpf-settings', $settings );
		}

	}

	/**
	 * Send generated coupons to the contact record
	 *
	 * @access public
	 * @return array
	 */

	public function get_coupon_data( $customer_data, $order ) {

		$order_id = $order->get_id();

		$coupon_data = get_post_meta( $order_id, 'sc_coupon_receiver_details', true );

		if ( ! empty( $coupon_data ) ) {
			$customer_data['wc_smart_coupon'] = $coupon_data[0]['code'];
		}

		return $customer_data;

	}

	/**
	 * Add coupon code to meta fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['wc_smart_coupon'] = array(
			'label' => 'Smart Coupon Code',
			'type'  => 'text',
			'group' => 'woocommerce',
		);

		return $meta_fields;

	}

}

new WPF_Woo_Coupons();
