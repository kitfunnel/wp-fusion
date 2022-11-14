<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WPML integration.
 *
 * @since 3.15.3
 *
 * @link https://wpfusion.com/documentation/multilingual/wpml/
 */
class WPF_WPML extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wpml';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Wpml';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/multilingual/wpml/';

	/**
	 * Gets things started.
	 *
	 * @since 3.15.3
	 */
	public function init() {

		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 10 );
		add_action( 'init', array( $this, 'sync_language' ) );

		add_filter( 'wpf_api_add_contact_args', array( $this, 'merge_language_guest' ) );
		add_filter( 'wpf_api_update_contact_args', array( $this, 'merge_language_guest' ) );

	}


	/**
	 * Suppress filters when automated enrollments are happening due to tag
	 * changes (this fixes a bug where LearnDash doesn't see the user enrolled
	 * in a French course if an update_tags webhook comes in on the English
	 * site)
	 *
	 * @since 3.35.7
	 *
	 * @param WP_Query $query  The query.
	 */
	public function pre_get_posts( $query ) {

		if ( is_object( $query ) && doing_action( 'wpf_tags_modified' ) ) {
			$query->set( 'suppress_filters', true );
		}

	}

	/**
	 * Add WPML field group to contact fields list.
	 *
	 * @return  array Field Groups.
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['wpml'] = array(
			'title'  => 'WPML',
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
			'group' => 'wpml',
		);
		$meta_fields['language_name'] = array(
			'label' => 'Language Name',
			'type'  => 'text',
			'group' => 'wpml',
		);

		return $meta_fields;

	}

	/**
	 * Detects current language and syncs if it's changed.
	 *
	 * @since   3.15.3
	 * @return  void
	 */

	public function sync_language() {

		if ( ! wpf_is_user_logged_in() || is_admin() ) {
			return;
		}

		$language_code = get_user_meta( wpf_get_current_user_id(), 'language_code', true );
		$language_name = get_user_meta( wpf_get_current_user_id(), 'language_name', true );

		if ( $language_code != ICL_LANGUAGE_CODE || $language_name != ICL_LANGUAGE_NAME ) {

			update_user_meta( wpf_get_current_user_id(), 'language_code', ICL_LANGUAGE_CODE );
			update_user_meta( wpf_get_current_user_id(), 'language_name', ICL_LANGUAGE_NAME );

			wp_fusion()->user->push_user_meta(
				wpf_get_current_user_id(),
				array(
					'language_code' => ICL_LANGUAGE_CODE,
					'language_name' => ICL_LANGUAGE_NAME,
				)
			);

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

		if ( wpf_is_field_active( 'language_code' ) || wpf_is_field_active( 'language_name' ) ) {

			$update_data = array(
				'language_code' => ICL_LANGUAGE_CODE,
				'language_name' => ICL_LANGUAGE_NAME,
			);

			if ( is_array( $args[0] ) ) {

				// Add contact.

				wpf_log( 'info', 0, 'Syncing language preference data:', array( 'meta_array' => $update_data ) );

				$args[0] += wp_fusion()->crm->map_meta_fields( $update_data );

			} else {

				// Update.
				$args[1] += wp_fusion()->crm->map_meta_fields( $update_data ); // don't overwrite data that might already be there.

			}
		}

		return $args;

	}

}

new WPF_WPML();
