<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_User_Meta extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'user-meta';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'User meta';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/user-meta/';


	/**
	 * Gets things started
	 *
	 * @access public
	 * @since  1.0
	 * @return void
	 */

	public function init() {

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		add_filter( 'wpf_pulled_user_meta', array( $this, 'pulled_user_meta' ), 10, 2 );

		// Settings
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

		// Defer until activation
		add_filter( 'user_meta_pre_user_register', array( $this, 'before_user_registration' ) );
		add_action( 'user_meta_user_activate', array( $this, 'after_user_activation' ) );
		add_action( 'user_meta_email_verified', array( $this, 'after_user_activation' ) );
		add_action( 'user_meta_user_approved', array( $this, 'after_user_activation' ) );

		// User Meta hooks
		add_action( 'user_meta_after_user_update', array( $this, 'user_update' ), 10, 2 );
		add_action( 'user_meta_after_user_register', array( $this, 'user_update' ), 10 );

	}

	/**
	 * Adds User Meta field group to meta fields list
	 *
	 * @access public
	 * @return array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['usermeta'] ) ) {

			$field_groups['usermeta'] = array(
				'title'  => 'User Meta',
				'fields' => array(),
			);

		}

		return $field_groups;

	}

	/**
	 * Adds User Meta meta fields to WPF contact fields list
	 *
	 * @access public
	 * @return array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		global $userMeta;

		// Get shared fields
		$fields = $userMeta->getData( 'fields' );

		if ( ! empty( $fields ) ) {

			foreach ( (array) $fields as $field ) {

				if ( ! isset( $field['meta_key'] ) ) {
					continue;
				}

				if ( 'datetime' == $field['field_type'] ) {
					$field['field_type'] = 'date';
				}

				$meta_fields[ $field['meta_key'] ] = array(
					'label' => $field['field_title'],
					'type'  => $field['field_type'],
					'group' => 'usermeta',
				);

			}
		}

		// Get form specific fields
		$forms = $userMeta->getData( 'forms' );

		if ( ! empty( $forms ) ) {

			foreach ( $forms as $form ) {

				foreach ( $form['fields'] as $field ) {

					if ( ! isset( $field['meta_key'] ) ) {
						continue;
					}

					if ( 'datetime' == $field['field_type'] ) {
						$field['field_type'] = 'date';
					}

					$meta_fields[ $field['meta_key'] ] = array(
						'label' => $field['field_title'],
						'type'  => $field['field_type'],
						'group' => 'usermeta',
					);

				}
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

		$settings['ump_header'] = array(
			'title'   => __( 'User Meta Pro Integration', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['ump_defer'] = array(
			'title'   => __( 'Defer Until Activation', 'wp-fusion' ),
			'desc'    => sprintf( __( 'Don\'t send any data to %s until the account has been activated, either by an administrator or via email activation.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'std'     => 0,
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;

	}

	/**
	 * Triggered before registration, allows removing WPF create_user hook
	 *
	 * @access public
	 * @return void
	 */

	public function before_user_registration( $registration_data ) {

		if ( wpf_get_option( 'ump_defer' ) == true ) {
			remove_action( 'user_register', array( wp_fusion()->user, 'user_register' ), 20 );
		}

		return $registration_data;

	}

	/**
	 * Triggered after activation, syncs the new user to the CRM
	 *
	 * @access public
	 * @return void
	 */

	public function after_user_activation( $user_id ) {

		if ( wpf_get_option( 'ump_defer' ) == true ) {
			wp_fusion()->user->user_register( $user_id );
		}

	}

	/**
	 * Format date fields when data is loaded
	 *
	 * @access public
	 * @return array Meta data
	 */

	public function pulled_user_meta( $user_meta, $user_id ) {

		global $userMeta;

		// Get shared fields
		$fields = $userMeta->getData( 'fields' );

		if ( ! empty( $fields ) ) {

			foreach ( (array) $fields as $field ) {

				if ( ! isset( $field['meta_key'] ) ) {
					continue;
				}

				if ( ! empty( $user_meta[ $field['meta_key'] ] ) && 'datetime' == $field['field_type'] ) {

					if ( ! isset( $field['date_format'] ) ) {
						$format = 'Y-m-d';
					} else {
						$format = $field['date_format'];
					}

					$user_meta[ $field['meta_key'] ] = date( $format, strtotime( $user_meta[ $field['meta_key'] ] ) );

				}
			}
		}

		// Get form specific fields
		$forms = $userMeta->getData( 'forms' );

		if ( ! empty( $forms ) ) {

			foreach ( $forms as $form ) {

				foreach ( $form['fields'] as $field ) {

					if ( ! isset( $field['meta_key'] ) ) {
						continue;
					}

					if ( ! empty( $user_meta[ $field['meta_key'] ] ) && 'datetime' == $field['field_type'] ) {

						if ( ! isset( $field['date_format'] ) ) {
							$format = 'Y-m-d';
						} else {
							$format = $field['date_format'];
						}

						$user_meta[ $field['meta_key'] ] = date( $format, strtotime( $user_meta[ $field['meta_key'] ] ) );

					}
				}
			}
		}

		return $user_meta;

	}


	/**
	 * Push changes to user meta on profile update and registration
	 *
	 * @access public
	 * @return array Meta Fields
	 */

	public function user_update( $response, $formname = false ) {

		$user_meta = array();

		foreach ( $response as $key => $value ) {
			$user_meta[ $key ] = $value;
		}

		wp_fusion()->user->push_user_meta( $user_meta['ID'], $user_meta );

	}

}

new WPF_User_Meta();
