<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * GeoDirectory integration
 *
 * @since 3.41.41
 */
class WPF_GeoDirectory extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.41.41
	 * @var string $slug
	 */

	public $slug = 'geodirectory';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.41.41
	 * @var string $name
	 */
	public $name = 'GeoDirectory';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.41.41
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/geodirectory/';

	/**
	 * Gets things started.
	 *
	 * @since 3.41.41
	 */
	public function init() {
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		add_action( 'geodir_post_saved', array( $this, 'listing_published' ), 10, 4 );
		add_action( 'geodir_pricing_post_expired', array( $this, 'listing_expired' ) );
	}

	/**
	 * Runs after a listing is expired.
	 *
	 * @since 3.41.41
	 * @param object $gd_post
	 */
	public function listing_expired( $gd_post ) {

		// Get latest version of the post so it will have expiry status.
		$gd_post = geodir_get_post_info( $gd_post->ID );

		if ( ! geodir_pricing_post_is_expired( $gd_post ) ) {
			return;
		}

		$apply_tags = wpf_get_option( 'geodirectory_apply_tags_expired' );

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $gd_post->post_author );
		}
	}

	/**
	 * Runs after a listing is published.
	 *
	 * @since 3.41.41
	 * @param array   $postarr
	 * @param array   $gd_post
	 * @param array   $post
	 * @param boolean $update
	 */
	public function listing_published( $postarr, $gd_post, $post, $update ) {
		if ( $postarr['post_status'] !== 'publish' ) {
			return;
		}

		$user_id = intval( $gd_post['post_author'] );
		if ( $user_id === 0 ) {
			return;
		}

		$apply_tags = wpf_get_option( 'geodirectory_apply_tags' );

		if ( ! empty( $apply_tags ) ) {
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

		$settings['geodirectory_header'] = array(
			'title'   => __( 'GeoDirectory', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['geodirectory_apply_tags'] = array(
			'title'   => __( 'Apply Tags - Listing Approved', 'wp-fusion' ),
			'desc'    => __( 'Select tags to be applied when a listing is approved.', 'wp-fusion' ),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['geodirectory_apply_tags_expired'] = array(
			'title'   => __( 'Apply Tags - Listing Expired', 'wp-fusion' ),
			'desc'    => __( 'Select tags to be applied when a listing is expired.', 'wp-fusion' ),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		return $settings;
	}
}

new WPF_GeoDirectory();
