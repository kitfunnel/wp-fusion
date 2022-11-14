<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Weglot extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'weglot';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Weglot';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/multilingual/weglot/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 10 );
		add_action( 'init', array( $this, 'sync_language' ) );

		add_filter( 'wpf_api_add_contact_args', array( $this, 'merge_language_guest' ) );
		add_filter( 'wpf_api_update_contact_args', array( $this, 'merge_language_guest' ) );

	}

	/**
	 * Add Language Code field to contact fields list
	 *
	 * @access  public
	 * @since   1.0
	 * @return  array Field Groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['weglot'] = array(
			'title'  => 'Weglot',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Adds WPML meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['language_code'] = array(
			'label' => 'Language Code',
			'type'  => 'text',
			'group' => 'weglot',
		);

		return $meta_fields;

	}

	/**
	 * Detects current language and syncs if necessary
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function sync_language() {

		if ( ! wpf_is_user_logged_in() || is_admin() ) {
			return;
		}

		$language_code         = get_user_meta( wpf_get_current_user_id(), 'language_code', true );
		$current_language_code = weglot_get_current_language();

		if ( $language_code !== $current_language_code ) {

			update_user_meta( wpf_get_current_user_id(), 'language_code', $current_language_code );

			wp_fusion()->user->push_user_meta( wpf_get_current_user_id(), array( 'language_code' => $current_language_code ) );

		}

	}


	/**
	 * Merge the language choice data when WPF creates a guest contact record.
	 *
	 * @since  3.40.0
	 *
	 * @param  array $args   The API args.
	 * @return array The API args.
	 */
	public function merge_language_guest( $args ) {

		$crm_field = wpf_get_crm_field( 'language_code' );

		if ( false === $crm_field ) {
			return $args;
		}

		$language_code = weglot_get_current_language();

		if ( ! empty( $language_code ) ) {

			if ( is_array( $args[0] ) && ! isset( $args[0][ $crm_field ] ) ) {

				// Add contact.

				wpf_log( 'info', 0, 'Creating contact with language code <strong>' . $language_code . '</strong>' );

				$args[0][ $crm_field ] = $language_code;

			} elseif ( is_array( $args[1] ) && ! isset( $args[1][ $crm_field ] ) ) {

				// Update contact.
				$args[1][ $crm_field ] = $language_code;

			}
		}

		return $args;

	}
}

new WPF_Weglot();
