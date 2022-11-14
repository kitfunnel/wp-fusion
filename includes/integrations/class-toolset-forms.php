<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Toolset_Forms extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'toolset-forms';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Toolset forms';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/toolset/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.26.4
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'set_contact_field_names' ), 30 );
		add_filter( 'wpf_user_register', array( $this, 'filter_form_fields' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'filter_form_fields' ), 10, 2 );
		add_filter( 'wpf_pulled_user_meta', array( $this, 'pulled_user_meta' ), 10, 2 );

	}

	/**
	 * Update profile info through CRED form.
	 *
	 * @access public
	 * @return array Settings
	 */

	public function update_profile( $user_id, $current_form ) {

		if ( $current_form['post_type'] == 'user' ) {

			wp_fusion()->user->push_user_meta( $user_id );

		}

		if ( isset( $_POST['user_email'] ) ) {

			wp_fusion()->user->push_user_meta( $user_id, array( 'user_email' => $_POST['user_email'] ) );

		}

	}

	/**
	 * Add meta field group
	 *
	 * @access public
	 * @return array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['cred'] ) ) {
			$field_groups['cred'] = array(
				'title'  => 'Toolset',
				'fields' => array(),
			);
		}

		return $field_groups;

	}


	/**
	 * Set field labels from CRED field labels
	 *
	 * @access public
	 * @return array Settings
	 */

	public function set_contact_field_names( $meta_fields ) {

		$cred_fields = get_option( 'wpcf-usermeta', array() );

		foreach ( (array) $cred_fields as $key => $field ) {

			if ( $field['type'] == 'checkboxes' ) {
				$field['type'] = 'multiselect';
			}

			$meta_fields[ $key ] = array(
				'label' => $field['name'],
				'type'  => $field['type'],
				'group' => 'cred',
			);

		}

		return $meta_fields;

	}


	/**
	 * Filters registration / update data before sending to the CRM
	 *
	 * @access public
	 * @return array Registration data
	 */

	public function filter_form_fields( $post_data, $user_id ) {

		foreach ( $post_data as $key => $value ) {

			if ( strpos( $key, 'wpcf-' ) !== false ) {

				$key               = str_replace( 'wpcf-', '', $key );
				$post_data[ $key ] = $value;

			}
		}

		if ( isset( $post_data['wpcf'] ) && is_array( $post_data['wpcf'] ) ) {

			foreach ( $post_data['wpcf'] as $key => $value ) {

				if ( ! is_array( $value ) ) {

					$post_data[ $key ] = $value;

				} else {

					// Checkbox fields

					if ( ! isset( $post_data[ $key ] ) ) {
						$post_data[ $key ] = array();
					}

					foreach ( $value as $checkbox_value ) {
						$post_data[ $key ] = array_merge( $post_data[ $key ], $checkbox_value );
					}
				}
			}
		}

		return $post_data;

	}

	/**
	 * Filters data loaded from the CRM before it's saved to the database
	 *
	 * @access public
	 * @return array User Meta
	 */

	public function pulled_user_meta( $user_meta, $user_id ) {

		$toolset_fields = get_option( 'wpcf-usermeta', array() );

		foreach ( $user_meta as $key => $value ) {

			if ( isset( $toolset_fields[ $key ] ) ) {

				$user_meta[ $toolset_fields[ $key ]['meta_key'] ] = $value;
				unset( $user_meta[ $key ] );

			}
		}

		return $user_meta;

	}


}

new WPF_Toolset_Forms();
