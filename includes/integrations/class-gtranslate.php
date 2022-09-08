<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_GTranslate extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'gtranslate';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Gtranslate';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/multilingual/gtranslate/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_api_add_contact_args', array( $this, 'merge_language_code' ) );
		add_filter( 'wpf_api_update_contact_args', array( $this, 'merge_language_code' ) );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 10 );

	}


	/**
	 * Filters registration / update data before sending to the CRM
	 *
	 * @access public
	 * @return array Args
	 */

	public function merge_language_code( $args ) {

		$crm_field = wpf_get_crm_field( 'language_code' );

		if ( false === $crm_field ) {
			return $args;
		}

		$language_code = false;

		if ( isset( $_COOKIE['googtrans'] ) ) {

			$selected = explode( '/', $_COOKIE['googtrans'] );

			if ( is_array( $selected ) && ! empty( $selected[2] ) ) {

				$language_code = $selected[2];

			}
		} elseif ( isset( $_SERVER['HTTP_X_GT_LANG'] ) ) {

			$language_code = $_SERVER['HTTP_X_GT_LANG'];

		} else {

			$data = get_option( 'GTranslate' );

			$language_code = $data['default_language'];

		}

		if ( is_array( $args[0] ) && ! isset( $args[0][ $crm_field ] ) ) {

			// Add contact.
			$args[0][ $crm_field ] = $language_code;

		} elseif ( is_array( $args[1] ) && ! isset( $args[1][ $crm_field ] ) ) {

			// Update contact.
			$args[1][ $crm_field ] = $language_code;
		}

		return $args;

	}

	/**
	 * Add Language Code field to contact fields list
	 *
	 * @access  public
	 * @return  array Field Groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['gtranslate'] = array(
			'title'  => 'GTranslate',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Adds meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['language_code'] = array(
			'label' => 'Language Code',
			'type'  => 'text',
			'group' => 'gtranslate',
		);

		return $meta_fields;

	}


}

new WPF_GTranslate();
