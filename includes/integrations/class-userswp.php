<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WPF_UsersWP.
 *
 * UsersWP integration class.
 *
 * @since 3.41.15
 */
class WPF_UsersWP extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.41.15
	 * @var string $slug
	 */

	public $slug = 'userswp';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.41.15
	 * @var string $name
	 */
	public $name = 'UsersWP';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.41.15
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/userswp/';

	/**
	 * Gets things started
	 *
	 * @since   3.41.15
	 */
	public function init() {

		// Profile updates.
		add_filter( 'wpf_user_update', array( $this, 'profile_update' ), 10, 2 );

		// WPF stuff.
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

	}

	/**
	 * Format profile update post data.
	 *
	 * @since   3.41.15
	 *
	 * @param   array $user_meta The User meta.
	 * @param   int   $user_id The User ID.
	 * @return  array User Meta
	 */
	public function profile_update( $user_meta, $user_id ) {

		$field_map = array(
			'email'    => 'user_email',
			'password' => 'user_pass',
			'username' => 'user_login',

		);

		$user_meta = $this->map_meta_fields( $user_meta, $field_map );

		return $user_meta;

	}

	/**
	 * Adds UsersWP field group to meta fields list.
	 *
	 * @since   3.41.15
	 *
	 * @param   array $field_groups The Field groups.
	 * @return  array Field groups
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['userswp'] = array(
			'title'  => 'UsersWP',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Adds UsersWP meta fields to WPF contact fields list
	 *
	 * @since   3.41.15
	 *
	 * @param   array $meta_fields The Meta fields to add.
	 * @return  array Meta Fields
	 */
	public function prepare_meta_fields( $meta_fields ) {

		$forms = new UsersWP_Form_Builder();

		$fields = $forms->get_form_existing_fields( 'account', 'array' ); // Get existing fields.

		foreach ( $fields as $field ) {

			// Skip if there is no meta name.
			if ( empty( $field['htmlvar_name'] ) ) {
				continue;
			}

			// Add the UsersWP field to the meta fields list.
			$meta_fields[ $field['htmlvar_name'] ] = array(
				'label' => $field['site_title'],
				'type'  => strtolower( $field['field_type'] ),
				'group' => 'userswp',
			);

		}

		return $meta_fields;

	}

}

new WPF_UsersWP();
