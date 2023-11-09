<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_CRED extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'cred';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Cred';

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
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'set_contact_field_names' ), 30 );
		add_filter( 'wpf_user_register', array( $this, 'filter_form_fields' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'filter_form_fields' ), 10, 2 );
		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );

		add_action( 'cred_submit_complete', array( $this, 'update_profile' ), 10, 2 );

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

			$meta_fields[ $key ] = array(
				'label' => $field['name'],
				'type'  => $field['type'],
				'group' => 'cred',
			);

		}

		return $meta_fields;

	}


	/**
	 * Removes standard WPF meta boxes from Toolset related post types
	 *
	 * @access  public
	 * @return  array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['cred-user-form'] );

		return $post_types;

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

				$post_data[ $key ] = $value;

			}
		}

		return $post_data;

	}


}

new WPF_CRED();
