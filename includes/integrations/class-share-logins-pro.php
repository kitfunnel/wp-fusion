<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Share_Logins_Pro extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'share-logins-pro';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Share logins pro';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/share-logins-pro/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'option_share-logins_basics', array( $this, 'register_meta_fields' ), 10, 2 );
		add_action( 'wpf_tags_applied', array( $this, 'tags_modified' ), 10, 2 );
		add_action( 'wpf_tags_removed', array( $this, 'tags_modified' ), 10, 2 );

		// Catch incoming tag changes
		add_action( 'updated_user_meta', array( $this, 'incoming_tags_modified' ), 10, 4 );
		add_action( 'added_user_meta', array( $this, 'incoming_tags_modified' ), 10, 4 );

	}


	/**
	 * Register contact ID and tags fields for automatic sync on profile update
	 *
	 * @access public
	 * @return array Option
	 */

	public function register_meta_fields( $value, $option ) {

		if ( empty( $value ) ) {
			$value = array();
		}

		if ( ! isset( $value['share-meta_keys'] ) ) {
			$value['share-meta_keys'] = array();
		}

		if ( ! in_array( WPF_CONTACT_ID_META_KEY, $value['share-meta_keys'] ) ) {
			$value['share-meta_keys'][] = WPF_CONTACT_ID_META_KEY;
			$value['share-meta_keys'][] = WPF_TAGS_META_KEY;
		}

		return $value;

	}

	/**
	 * Sync changed tags to other connected sites
	 *
	 * @access public
	 * @return void
	 */

	public function tags_modified( $user_id, $user_tags ) {

		$plugin  = codexpert\Share_Logins_Pro\Plugin::instance();
		$request = new codexpert\Share_Logins_Pro\Request( $plugin->plugin );
		$request->update_user( $user_id );

	}

	/**
	 * Trigger appropriate actions when tags are modified via incoming request
	 *
	 * @access public
	 * @return void
	 */

	public function incoming_tags_modified( $meta_id, $object_id, $meta_key, $user_tags ) {

		if ( ! defined( 'REST_REQUEST' ) || REST_REQUEST != true ) {
			return;
		}

		if ( WPF_TAGS_META_KEY != $meta_key ) {
			return;
		}

		do_action( 'wpf_tags_modified', $object_id, $user_tags );

	}


}

new WPF_Share_Logins_Pro();
