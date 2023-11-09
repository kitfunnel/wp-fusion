<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WP All Import Integration Class.
 *
 * @class   WPF_WP_All_Import
 * @since   3.40.58
 */
class WPF_WP_All_Import extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.58
	 * @var string $slug
	 */

	public $slug = 'wp-all-import';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.58
	 * @var string $name
	 */
	public $name = 'WP All Import';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.58
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/wp-all-import/';

	/**
	 * The next password to sync.
	 *
	 * @since 3.40.58
	 * @var string $next_pass
	 */
	public $next_pass = false;

	/**
	 * Gets things started.
	 *
	 * @since 3.40.58
	 */
	public function init() {

		add_filter( 'pmxi_article_data', array( $this, 'article_data' ), 10, 2 );
		add_filter( 'wpf_user_register', array( $this, 'sync_generated_password' ) );

	}

	/**
	 * Prepare the password for sync.
	 *
	 * @since 3.40.58
	 *
	 * @param array              $article_data The article data.
	 * @param PMXI_Import_Record $record_class The record class.
	 * @return array The article data.
	 */
	public function article_data( $article_data, $record_class ) {

		if ( 'import_users' === $record_class->options['custom_type'] && wpf_is_field_active( 'user_pass' ) ) {

			if ( empty( $article_data['user_pass'] ) ) {
				$article_data['user_pass'] = wp_generate_password();
			}

			$this->next_pass = $article_data['user_pass'];

		}

		return $article_data;

	}

	/**
	 * Merge the generated password into the user meta.
	 *
	 * @since 3.40.58
	 *
	 * @param array $user_meta The user meta.
	 * @return array The user meta.
	 */
	public function sync_generated_password( $user_meta ) {

		if ( $this->next_pass ) {
			$user_meta['user_pass'] = $this->next_pass;
			$this->next_pass        = false;
		}

		return $user_meta;

	}

}

new WPF_WP_All_Import();
