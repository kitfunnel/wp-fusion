<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ARMember integration.
 *
 * @since 3.38.37
 *
 * @link https://wpfusion.com/documentation/membership/armember/
 */
class WPF_ARMember extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.37
	 * @var string $slug
	 */

	public $slug = 'armember';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.37
	 * @var string $name
	 */
	public $name = 'ARMember';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.37
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/armember/';

	/**
	 * Gets things started.
	 *
	 * @since 3.38.37
	 */
	public function init() {

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

	}



	/**
	 * Adds field group for ARMember to contact fields list.
	 *
	 * @since 3.38.37
	 *
	 * @param  array $field_groups The field groups.
	 * @return array  Meta fields
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['armember'] = array(
			'title'  => 'ARMember',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Get the available fields.
	 *
	 * @since  3.38.37
	 *
	 * @param  array $meta_fields The meta fields.
	 * @return array  Meta fields.
	 */
	public function prepare_meta_fields( $meta_fields ) {

		global $wpdb;
		$default = array( 'user_login', 'first_name', 'last_name', 'user_email', 'user_pass', 'repeat_pass', 'rememberme' );
		$results = $wpdb->get_results( "SELECT arm_form_field_option FROM {$wpdb->prefix}arm_form_field WHERE arm_form_field_slug NOT IN ('" . implode( "','", $default ) . "') AND arm_form_field_slug <>''" );

		if ( empty( $results ) ) {
			return $meta_fields;
		}

		foreach ( $results as $result ) {

			if ( ! empty( $result->arm_form_field_option ) ) {

				$options = unserialize( $result->arm_form_field_option );

				$meta_fields[ $options['meta_key'] ] = array(
					'label' => $options['label'],
					'type'  => $options['type'],
					'group' => 'armember',
				);
			}
		}

		return $meta_fields;
	}

}

new WPF_ARMember();
