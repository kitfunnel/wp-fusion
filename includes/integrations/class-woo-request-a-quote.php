<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Woo_Request_A_Quote extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'woo-request-a-quote';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WooCommerce request a quote';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.30.1
	 * @return  void
	 */

	public function init() {

		add_action( 'save_post_addify_quote', array( $this, 'save_post' ), 10, 3 );

		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

	}

	/**
	 * Apply tags when a request for quote is submitted
	 *
	 * @access public
	 * @return void
	 */

	public function save_post( $post_id, $post, $update ) {

		$apply_tags = wpf_get_option( 'woo_quote_apply_tags' );
		$user_id    = get_post_meta( $post_id, '_customer_user', true );

		if ( ! empty( $apply_tags ) && ! empty( $user_id ) ) {

			wp_fusion()->user->apply_tags( $apply_tags, $user_id );

		}

	}


	/**
	 * Add fields to settings page
	 *
	 * @access public
	 * @return array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['woo_quote_header'] = array(
			'title'   => __( 'WooCommerce Request A Quote', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['woo_quote_apply_tags'] = array(
			'title'   => __( 'Apply Tags', 'wp-fusion' ),
			'desc'    => __( 'Select tags to be applied when a user submits a request for a quote.', 'wp-fusion' ),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		return $settings;

	}


}

new WPF_Woo_Request_A_Quote();
