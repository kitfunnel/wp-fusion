<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Pretty Links Integration.
 *
 * @since 3.40.58
 */
class WPF_Pretty_Links extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.58
	 * @var string $slug
	 */

	public $slug = 'pretty-links';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.58
	 * @var string $name
	 */
	public $name = 'Pretty Links';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.58
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/affiliates/pretty-links/';


	/**
	 * Gets things started.
	 *
	 * @since 3.40.58
	 */
	public function init() {

		add_action( 'prli_before_redirect', array( $this, 'before_redirect' ) );

		add_action( 'prli_admin_link_nav', array( $this, 'add_nav_link' ) );
		add_action( 'prli_admin_link_nav_body', array( $this, 'add_nav_body' ) );
		add_action( 'save_post_pretty-link', array( $this, 'save_post' ) );

	}

	/**
	 * Apply tags to user before redirect.
	 *
	 * @since   3.40.58
	 * @param string $redirect_link The redirect link.
	 */
	public function before_redirect( $redirect_link ) {

		if ( ! wpf_is_user_logged_in() ) {
			return;
		}

		// Get link slug.
		$link = $_SERVER['REQUEST_URI'];

		// Remove first slash only.
		$link = ltrim( $link, '/' );

		global $wpdb;
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"
                    SELECT link_cpt_id
                    FROM {$wpdb->prefix}prli_links
                    WHERE slug = %s
                ",
				$link
			)
		);

		if ( intval( $post_id ) === 0 ) {
			return;
		}

		$settings = get_post_meta( $post_id, 'wpf_settings_pretty_links', true );

		if ( empty( $settings ) || empty( $settings['apply_tags'] ) ) {
			return;
		}

		wp_fusion()->user->apply_tags( $settings['apply_tags'] );

	}

	/**
	 * Add nav link in link post type.
	 *
	 * @since 3.40.58
	 */
	public function add_nav_link() {
		echo '<li><a data-id="wpfusion">' . __( 'WP Fusion', 'wp-fusion' ) . '</a></li>';
	}

	/**
	 * Adds nav body to the wpfusion nav link.
	 *
	 * @since 3.40.58
	 */
	public function add_nav_body() {
		global $post;

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wpf_meta_box_pretty_links', 'wpf_meta_box_pretty_links_nonce' );

		$settings = array(
			'apply_tags' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf_settings_pretty_links', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf_settings_pretty_links', true ) );
		}

		// Apply tags.

		echo ' <div class="prli-page" id="wpfusion"><table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags">' . __( 'Apply Tags', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags'],
			'meta_name' => 'wpf_settings_pretty_links',
			'field_id'  => 'apply_tags',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . sprintf( __( 'These tags will be applied in %s when a logged-in user clicks the link.', 'wp-fusion' ), esc_html( wp_fusion()->crm->name ) ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table></div>';
	}

	/**
	 * Saves nav body data.
	 *
	 * @since 3.40.58
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_post( $post_id ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_pretty_links_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_pretty_links_nonce'], 'wpf_meta_box_pretty_links' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! empty( $_POST['wpf_settings_pretty_links'] ) ) {
			update_post_meta( $post_id, 'wpf_settings_pretty_links', $_POST['wpf_settings_pretty_links'] );
		} else {
			delete_post_meta( $post_id, 'wpf_settings_pretty_links' );
		}
	}



}

new WPF_Pretty_Links();
