<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Download Monitor integration.
 *
 * @since 3.38.9
 *
 * @link https://wpfusion.com/documentation/other/download-monitor/
 */
class WPF_Download_Monitor extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'download-monitor';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Download monitor';

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
	 * @since 3.38.9
	 */
	public function init() {

		add_filter( 'dlm_can_download', array( $this, 'check_downloads' ), 10, 2 );

		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		add_filter( 'dlm_shortcode_downloads_downloads', array( $this, 'filter_members_only_downloads' ) );

		// Access metabox.
		add_filter( 'wpf_meta_box_post_types', array( $this, 'add_post_type' ) );
		add_filter( 'wpf_restrict_content_checkbox_label', array( $this, 'checkbox_label' ), 10, 2 );

	}

	/**
	 * Hide and filter downloads in the [downloads] shortcode based on user's
	 * CRM tags.
	 *
	 * @since  3.38.9
	 *
	 * @param  array $downloads The downloads.
	 * @return array The downloads.
	 */
	public function filter_members_only_downloads( $downloads ) {

		if ( ! wpf_get_option( 'download_monitor_members_only' ) ) {
			return $downloads;
		}
		if ( ! is_user_logged_in() || empty( $downloads ) ) {
			return $downloads;
		}

		foreach ( $downloads as $key => $download ) {
			if ( ! wpf_user_can_access( $download->get_id() ) ) {
				unset( $downloads[ $key ] );
			}
		}

		return $downloads;

	}

	/**
	 * Add a custom field to the Integrations tab in the WP Fusion settings.
	 *
	 * @since  3.38.9
	 *
	 * @param  array $settings The registered settings.
	 * @param  array $options  The options in the database.
	 * @return array The registered settings.
	 */
	public function register_settings( $settings, $options ) {

		$settings['download_monitor_header'] = array(
			'title'   => __( 'Download Monitor', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['download_monitor_members_only'] = array(
			'title'   => __( 'Filter Members Only', 'wp-fusion' ),
			'desc'    => sprintf( __( 'If checked, <code>[downloads]</code> shortcodes using the <code>members_only</code> attribute will only show downloads that the current user can access, based on their %s tags.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;

	}

	/**
	 * Check downloads based on CRM Tags.
	 *
	 * @since  3.38.9
	 *
	 * @param  bool   $can_download Whether or not the user can download the
	 *                              content.
	 * @param  object $download     The requested download.
	 * @return bool   Can access.
	 */
	public function check_downloads( $can_download, $download ) {

		if ( false !== $can_download ) {

			// Check if current user have access through tags to that download.
			if ( ! wpf_user_can_access( $download->get_id() ) ) {

				$can_download = false;

				// Get redirect URL for the post.
				add_filter(
					'dlm_access_denied_redirect',
					function( $redirect ) use ( &$download ) {
						return wp_fusion()->access->get_redirect( $download->get_id() );
					}
				);

				// Or, display the access denied message.
				add_filter(
					'option_dlm_no_access_error',
					function( $value ) use ( &$download ) {
						return wp_fusion()->access->get_restricted_content_message( $download->get_id() );
					}
				);
			}
		}

		return $can_download;

	}

	/**
	 * Register the WPF access control meta box on the download post type.
	 *
	 * @since  3.38.9
	 *
	 * @param  array $post_types The post types to show the metabox on.
	 * @return array The post types.
	 */
	public function add_post_type( $post_types ) {
		$post_types[] = 'dlm_download';

		// If we're currently editing one, hide the settings that relate to
		// applying tags.

		global $post;

		if ( 'dlm_download' === $post->post_type ) {
			remove_action( 'wpf_meta_box_content', array( wp_fusion()->admin_interfaces, 'apply_tags_select' ), 30, 2 );
			remove_action( 'wpf_meta_box_content', array( wp_fusion()->admin_interfaces, 'apply_to_children' ), 40, 2 );
		}

		return $post_types;
	}

	/**
	 * Filters the checkbox label in the WPF meta box when editing a download.
	 *
	 * @since  3.38.9
	 *
	 * @param  string  $message The message.
	 * @param  WP_Post $post    The post being edited in the admin.
	 * @return string  The message.
	 */
	public function checkbox_label( $message, $post ) {

		if ( 'dlm_download' === $post->post_type ) {
			$message = __( 'Users must be logged in to download this file.', 'wp-fusion' );
		}

		return $message;

	}

}
new WPF_Download_Monitor();
