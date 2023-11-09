<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Download Manager integration.
 *
 * @since 3.41.13
 */
class WPF_Download_Manager extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.41.13
	 * @var string $slug
	 */

	public $slug = 'download-manager';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.41.13
	 * @var string $name
	 */
	public $name = 'Download Manager';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.41.13
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/download-manager/';

	/**
	 * Gets things started.
	 *
	 * @since 3.41.13
	 */
	public function init() {

		add_filter( 'wpdm_before_download', array( $this, 'check_downloads' ) );

		// Access metabox.
		add_filter( 'wpf_meta_box_post_types', array( $this, 'add_post_type' ) );
		add_filter( 'wpf_restrict_content_checkbox_label', array( $this, 'checkbox_label' ), 10, 2 );

	}


	/**
	 * Check downloads based on CRM Tags.
	 *
	 * @since  3.41.13
	 *
	 * @param  array $download     The requested download.
	 * @return bool   Can access.
	 */
	public function check_downloads( $download ) {
		if ( ! wpf_user_can_access( $download['ID'] ) ) {
			$redirect = wp_fusion()->access->get_redirect( $download['ID'] );
			if ( ! empty( $redirect ) ) {
				wp_redirect( $redirect, 302, 'WP Fusion' );
				exit();
			} else {
				wp_die( wp_fusion()->access->get_restricted_content_message( $download['ID'] ) );
			}
		}

		return $download;
	}


	/**
	 * Register the WPF access control meta box on the download post type.
	 *
	 * @since  3.41.13
	 *
	 * @param  array $post_types The post types to show the metabox on.
	 * @return array The post types.
	 */
	public function add_post_type( $post_types ) {

		$post_types[] = 'wpdmpro';

		return $post_types;
	}

	/**
	 * Filters the checkbox label in the WPF meta box when editing a download.
	 *
	 * @since  3.41.13
	 *
	 * @param  string  $message The message.
	 * @param  WP_Post $post    The post being edited in the admin.
	 * @return string  The message.
	 */
	public function checkbox_label( $message, $post ) {

		if ( 'wpdmpro' === $post->post_type ) {
			$message = __( 'Users must be logged in to download this file', 'wp-fusion' );
		}

		return $message;

	}

}
new WPF_Download_Manager();
