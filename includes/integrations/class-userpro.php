<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}



class WPF_UserPro extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'userpro';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Userpro';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/userpro/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'userpro_form_validation', array( $this, 'form_submitted' ), 50, 2 );
		add_action( 'userpro_after_profile_head', array( $this, 'load_new_values' ) );

		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 10, 2 );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'set_contact_field_names' ), 30 );
		add_filter( 'wpf_user_register', array( $this, 'filter_form_fields' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'filter_form_fields' ), 10, 2 );

	}

	/**
	 * Add fields to settings page
	 *
	 * @access public
	 * @return array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['userpro_header'] = array(
			'title'   => __( 'UserPro Integration', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['userpro_pull'] = array(
			'title'   => __( 'Pull', 'wp-fusion' ),
			'desc'    => __( 'Update the local profile data for a given user from the CRM before displaying. May slow down profile load times.', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;

	}

	/**
	 * Adds UserPro field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['userpro'] ) ) {
			$field_groups['userpro'] = array(
				'title'  => 'UserPro',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Set field labels from UM field labels
	 *
	 * @access public
	 * @return array Settings
	 */

	public function set_contact_field_names( $meta_fields ) {

		$userpro_fields = get_option( 'userpro_fields' );

		foreach ( (array) $userpro_fields as $key => $field ) {

			if ( ! is_array( $field ) ) {
				$field = array(
					'label' => '',
					'type'  => 'text',
				);
			}

			if ( ! isset( $field['label'] ) ) {
				$field['label'] = '';
			}

			if ( ! isset( $field['type'] ) ) {
				$field['type'] = 'text';
			}

			$meta_fields[ $key ] = array(
				'label' => $field['label'],
				'type'  => $field['type'],
				'group' => 'userpro',
			);

		}

		return $meta_fields;

	}

	/**
	 * Filters registration data before sending to the CRM
	 *
	 * @access public
	 * @return array Registration data
	 */

	public function filter_form_fields( $post_data, $user_id ) {

		if ( ! isset( $post_data['unique_id'] ) ) {
			return $post_data;
		}

		$unique_id = '-' . $post_data['unique_id'];

		foreach ( $post_data as $key => $value ) {

			if ( substr( $key, - strlen( $unique_id ) ) == $unique_id ) {

				// Trim the unique ID from the end of the string
				$key = substr( $key, 0, - strlen( $unique_id ) );

				$post_data[ $key ] = $value;

			}
		}

		return $post_data;

	}


	/**
	 * Sends UserPro submitted form data to the CRM
	 *
	 * @access public
	 * @return array Errors
	 */

	public function form_submitted( $errors, $form ) {

		if ( empty( $errors ) && wpf_get_option( 'push' ) == true ) {
			wp_fusion()->user->push_user_meta( wpf_get_current_user_id(), $form );
		}

		return $errors;

	}


	/**
	 * Update UserPro form fields before displaying
	 *
	 * @access public
	 * @return void
	 */

	public function load_new_values( $args ) {

		if ( wpf_get_option( 'userpro_pull' ) == true ) {

			wp_fusion()->user->pull_user_meta( $args['user_id'] );

		}

	}

}

new WPF_UserPro();
