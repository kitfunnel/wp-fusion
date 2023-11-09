<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_WP_Members extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wp-members';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WP Members';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/wp-members/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.33.2
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );
		add_action( 'wpmem_user_activated', array( $this, 'user_activated' ) );

		// WPF stuff
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 5 ); // 5 so other plugins can set their own groups

		// Settings
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

	}

	/**
	 * Don't sync the user to the CRM if Defer Until Activation is enabled and
	 * the user isn't activated yet.
	 *
	 * @since 3.40.23
	 *
	 * @return array User meta submitted at registration.
	 */
	public function user_register( $post_data, $user_id ) {

		if ( wpf_get_option( 'wp_members_defer' ) && ! wpmem_is_user_activated( $user_id ) ) {
			return null;
		}

		return $post_data;

	}

	/**
	 * Triggered after activation, syncs the new user to the CRM
	 *
	 * @access public
	 * @return void
	 */

	public function user_activated( $user_id ) {

		if ( wpf_get_option( 'wp_members_defer' ) ) {

			wp_fusion()->user->user_register( $user_id );

		}

	}


	/**
	 * Adds WP Members field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['wp_members'] = array(
			'title'  => 'WP Members',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Adds WP Members meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$fields = wpmem_fields();

		foreach ( $fields as $key => $field ) {

			$skip_fields = array( 'username', 'confirm_email', 'password', 'confirm_password' );

			if ( ! in_array( $key, $skip_fields ) ) {

				$meta_fields[ $key ] = array(
					'label' => $field['label'],
					'type'  => $field['type'],
					'group' => 'wp_members',
				);

			}
		}

		return $meta_fields;

	}


	/**
	 * Add fields to settings page
	 *
	 * @access public
	 * @return array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['wp_members_header'] = array(
			'title'   => __( 'WP-Members Integration', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['wp_members_defer'] = array(
			'title'   => __( 'Defer Until Activation', 'wp-fusion' ),
			'desc'    => sprintf( __( 'Don\'t send any data to %s until the account has been activated.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'std'     => 0,
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;

	}

}

new WPF_WP_Members();
