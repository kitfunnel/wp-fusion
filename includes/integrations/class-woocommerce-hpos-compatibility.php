<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooCommerce High Performance Order Storage (HPOS) Support.
 *
 * @since 3.42.9
 */
class WPF_WooCommerce_HPOS_Support {


	/**
	 * Constructor.
	 *
	 * Enable HPOS support for WooCommerce.
	 *
	 * @since 3.42.9.
	 */
	public function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_support' ) );
	}

	/**
	 * Declare support for High Performance Order Storage (HPOS).
	 *
	 * @since 3.40.45
	 *
	 * @link https://woocommerce.wordpress.com/2021/03/02/high-performance-order-storage-in-woocommerce-5-5/
	 */
	public function declare_hpos_support() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'wp-fusion/wp-fusion.php', true );
		}
	}

}

new WPF_WooCommerce_HPOS_Support();
