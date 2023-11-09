<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Meta Box integration.
 *
 * Detects user fields registered via Meta Box and makes them available for sync.
 *
 * @since 3.38.46
 */
class WPF_MetaBox extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.46
	 * @var string $slug
	 */

	public $slug = 'metabox';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.46
	 * @var string $name
	 */
	public $name = 'Meta Box';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.46
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/meta-box/';

	/**
	 * Gets things started.
	 *
	 * @since 3.38.46
	 */

	public function init() {

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 5 );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

	}

	/**
	 * Get user fields.
	 *
	 * @since  3.38.46
	 *
	 * @return array
	 */
	private function get_user_fields() {

		$user_fields = array();

		$args = array(
			'post_type'        => 'meta-box',
			'fields'           => 'ids',
			'limit'            => 100,
			'suppress_filters' => 1,
		);

		$field_groups = get_posts( $args );

		if ( empty( $field_groups ) ) {
			return $user_fields;
		}

		foreach ( $field_groups as $field_group_id ) {

			$settings = get_post_meta( $field_group_id, 'settings', true );

			if ( ! empty( $settings ) && $settings['object_type'] === 'user' ) {
				$user_fields = array_merge( get_post_meta( $field_group_id, 'fields', true ), $user_fields );
			}
		}

		return $user_fields;
	}

	/**
	 * Add Meta Box field group to Contact Fields list
	 *
	 * @since  3.38.46
	 *
	 * @param  array $field_groups The field groups.
	 * @return array  The field groups
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['metabox'] = array(
			'title'  => __( 'Meta Box', 'wp-fusion' ),
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Add Meta Box field group to Contact Fields list.
	 *
	 * @since  3.38.46
	 *
	 * @param array $meta_fields The meta fields.
	 * @return array The meta fields.
	 */

	public function add_meta_fields( $meta_fields ) {

		foreach ( $this->get_user_fields() as $key => $value ) {
			$meta_fields[ $value['id'] ] = array(
				'type'  => $value['type'],
				'label' => $value['name'],
				'group' => 'metabox',
			);
		}

		return $meta_fields;

	}

}

new WPF_MetaBox();
