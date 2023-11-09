<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_WCFF extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wcff';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Wc Fields Factory';

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

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'set_contact_field_names' ), 30 );

	}


	/**
	 * Adds WCFF field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['wcff'] ) ) {
			$field_groups['wcff'] = array(
				'title'  => 'WooCommerce Fields Factory',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Loads WCFF fields for inclusion in Contact Fields table
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function set_contact_field_names( $meta_fields ) {

		$args = array(
			'post_type' => 'wccpf',
			'fields'    => 'ids',
			'nopaging'  => true,
		);

		$field_groups = get_posts( $args );

		if ( ! empty( $field_groups ) ) {

			foreach ( $field_groups as $group_id ) {

				$fields = wcff()->dao->load_fields( $group_id );

				if ( ! empty( $fields ) ) {

					foreach ( $fields as $field ) {

						$meta_fields[ $field['name'] ] = array(
							'label' => $field['label'],
							'type'  => $field['type'],
							'group' => 'wcff',
						);

					}
				}
			}
		}

		return $meta_fields;

	}

}

new WPF_WCFF();
