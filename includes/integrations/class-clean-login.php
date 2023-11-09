<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Clean Login integration class
 *
 * @since 3.21.2
 */

class WPF_Clean_Login extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'clean-login';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Clean Login';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Get things started.
	 *
	 * @since 3.21.2
	 */
	public function init() {

		add_action( 'init', array( $this, 'sync_password_changes' ) );
		add_filter( 'wpf_user_register', array( $this, 'filter_form_fields' ) );
		add_filter( 'wpf_user_update', array( $this, 'filter_form_fields' ) );

	}

	/**
	 * Sync password changes.
	 *
	 * @since 3.40.33
	 */
	public function sync_password_changes() {

		if ( isset( $_GET['pass_changed'] ) ) {

			$user_id      = absint( $_GET['user_id'] );
			$new_password = sanitize_text_field( get_transient( 'cl_temporary_pass_' . $user_id ) );

			if ( ! empty( $new_password ) ) {
				wp_fusion()->user->push_user_meta( $user_id, array( 'user_pass' => $new_password ) );
			}

		}

	}

	/**
	 * Filters registration data before sending to the CRM.
	 *
	 * @since  3.21.2
	 *
	 * @param  array   $post_data  The post data.
	 * @param  integer $user_id    The user ID.
	 * @return array    Registration / Update data.
	 */
	public function filter_form_fields( $post_data ) {

		$field_map = array(
			'pass1'    => 'user_pass',
			'email'    => 'user_email',
			'username' => 'user_login',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		return $post_data;

	}

}

new WPF_Clean_Login();
