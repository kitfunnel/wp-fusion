<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Profile_Builder extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'profile-builder';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Profile builder';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/profile-builder-pro/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		// Profile updates
		add_filter( 'wpf_user_update', array( $this, 'profile_update' ), 10, 2 );

		// WPF stuff
		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

	}

	/**
	 * Format profile update post data
	 *
	 * @access  public
	 * @return  array User Meta
	 */

	public function profile_update( $user_meta, $user_id ) {

		$field_map = array(
			'email'   => 'user_email',
			'passw1'  => 'user_pass',
			'website' => 'user_url',
		);

		$user_meta = $this->map_meta_fields( $user_meta, $field_map );

		return $user_meta;

	}


	/**
	 * Removes standard WPF meta boxes from Profile Builder admin pages
	 *
	 * @access  public
	 * @return  array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['wppb-roles-editor'] );

		return $post_types;

	}


	/**
	 * Adds Profile Builder field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['profile_builder'] ) ) {
			$field_groups['profile_builder'] = array(
				'title'  => 'Profile Builder',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Adds User Meta meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$fields = get_option( 'wppb_manage_fields', array() );

		foreach ( $fields as $field ) {

			if ( empty( $field['meta-name'] ) ) {
				continue;
			}

			if ( $field['field'] == 'Checkbox' ) {
				$field['field'] = 'checkboxes';
			}

			$meta_fields[ $field['meta-name'] ] = array(
				'label' => $field['field-title'],
				'type'  => strtolower( $field['field'] ),
				'group' => 'profile_builder',
			);

		}

		return $meta_fields;

	}

}

new WPF_Profile_Builder();
