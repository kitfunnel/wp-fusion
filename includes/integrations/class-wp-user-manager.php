<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_WP_User_Manager extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.41.46
	 * @var string $slug
	 */

	public $slug = 'wp-user-manager';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.41.46
	 * @var string $name
	 */
	public $name = 'WP User Manager';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.41.46
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/wp-user-manager/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.41.46
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );
		add_action( 'wpum_after_registration', array( $this, 'user_registered' ), 10, 3 );
		add_action( 'wpumuv_after_user_verification', array( $this, 'email_verified' ) );
		add_action( 'wpumuv_after_user_approval', array( $this, 'user_approved' ) );

		// WPF stuff.
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 5 );

		// Settings.
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

	}

	/**
	 * User Register.
	 * Don't sync the user to the CRM if Defer Until Activation is enabled.
	 *
	 * @since 3.41.46
	 *
	 * @param array $post_data The user meta submitted at registration.
	 * @param int   $user_id The ID of the user who registered.
	 *
	 * @return array|null User meta submitted at registration or null.
	 */
	public function user_register( $post_data, $user_id ) {

		// Defer until activation.
		if ( wpum_get_option( 'user_verification_method' ) && wpf_get_option( 'wp_user_manager_defer' ) ) {
			if ( ! did_action( 'wpumuv_after_user_approval' ) && ! did_action( 'wpumuv_after_user_verification' ) ) {
				return null;
			}
		}

		return $post_data;
	}

	/**
	 * User Approved
	 * Triggered after admin approval, syncs the new user to the CRM.
	 *
	 * @since 3.41.46
	 *
	 * @param int $user_id The ID of the user who registered.
	 */
	public function user_approved( $user_id ) {
		wp_fusion()->user->user_register( $user_id );
	}

	/**
	 * Email Verified
	 * Triggered after email verification, syncs the new user to the CRM.
	 *
	 * @since 3.41.46
	 *
	 * @param int $user_id The ID of the user who registered.
	 */
	public function email_verified( $user_id ) {
		wp_fusion()->user->user_register( $user_id );
	}

	/**
	 * User Activated.
	 * Triggered after registration, syncs the new user to the CRM.
	 *
	 * @since 3.41.46
	 *
	 * @param int   $user_id The ID of the user who registered.
	 * @param array $fields The user meta submitted at registration.
	 * @param array $form The registration form.
	 *
	 * @return void
	 */
	public function user_registered( $user_id, $fields, $form ) {
		wp_fusion()->user->user_register( $user_id );
	}

	/**
	 * Add Meta Field Group.
	 * Adds WP User Manager field group to meta fields list.
	 *
	 * @since 3.41.46
	 *
	 * @param array $field_groups The field groups array.
	 *
	 * @return  array Field groups
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['wp_user_manager'] = array(
			'title'  => 'WP User Manager',
			'fields' => array(),
		);

		return $field_groups;
	}

	/**
	 * Prepare Meta Fields.
	 * Adds WP User Manager meta fields to WPF contact fields list.
	 *
	 * @since 3.41.46
	 *
	 * @param array $meta_fields The meta fields array.
	 *
	 * @return array Meta Fields
	 */
	public function prepare_meta_fields( $meta_fields ) {

		/**
		 * WP User Manager profile fields.
		 *
		 * @var $wpum_profile_fields.
		 *
		 * @since 3.41.46
		 *
		 * WP User Manager uses a looping class to get the profile fields. It does this by accessing and storing the $wpum_profile_fields global variable.
		 * wpum_profile_field_groups sets up the loop for the field groups, and wpum_profile_fields does the same for the profile fields.
		 * Once there are no more field groups or profile fields to loop through, the while loop exits.
		 * We can achieve the same result by using WPUM_Fields_Query to get the profile fields. But this method is more efficient.
		 *
		 * The meta key for the fields is not stored in the field object so we need to use WPUM_Field to get the meta key.
		 */

		// Starting the loop.
		wpum_has_profile_fields();

		// We need to check every field group for fields to sync.
		while ( wpum_profile_field_groups() ) {
			wpum_the_profile_field_group();

			while ( wpum_profile_fields() ) {
				wpum_the_profile_field();

				$field_id = wpum_get_field_id();
				$field    = new WPUM_Field( $field_id );
				$meta_key = $field->get_meta( 'user_meta_key' );

				// user_nickname & user_displayname aren't used in the registration form, so we skip them.
				// We can also skip firstname, lastname and username as they are already synced by WP User Register.
				$skip_fields = array( 'username', 'firstname', 'lastname', 'user_nickname', 'user_displayname', 'user_avatar', 'current_user_avatar', 'user_cover' );

				if ( ! empty( $meta_key ) && ! in_array( $meta_key, $skip_fields, true ) ) {
					$meta_fields[ $meta_key ] = array(
						'label' => wpum_get_field_name(),
						'type'  => wpum_get_field_type(),
						'group' => 'wp_user_manager',
					);
				}
			}
		}

		return $meta_fields;
	}

	/**
	 * Register Settings.
	 * Add fields to settings page.
	 *
	 * @since 3.41.46
	 *
	 * @param array $settings The settings array.
	 * @param array $options The options array.
	 *
	 * @return array Settings
	 */
	public function register_settings( $settings, $options ) {

		$settings['wp_user_manager_header'] = array(
			'title'   => __( 'WP User Manager Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['wp_user_manager_defer'] = array(
			'title'   => __( 'Defer Until Activation', 'wp-fusion' ),
			'desc'    => sprintf( __( 'Don\'t send any data to %s until the account has been activated, either by an admin or via email confirmation.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;
	}
}

new WPF_WP_User_Manager();
