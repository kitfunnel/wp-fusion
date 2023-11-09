<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ThirstyAffiliates integration.
 *
 * @since 3.40.58
 */
class WPF_ThirstyAffiliates extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.58
	 * @var string $slug
	 */

	public $slug = 'thirstyaffiliates';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.58
	 * @var string $name
	 */
	public $name = 'ThirstyAffiliates';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.58
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/affiliates/thirstyaffiliates/';


	/**
	 * Gets things started
	 *
	 * @since   3.40.58
	 */
	public function init() {

		// Redirect.
		add_action( 'ta_before_link_redirect', array( $this, 'before_redirect' ) );

		// Metabox in links.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_thirstylink', array( $this, 'save_meta_box_data' ) );

	}

	/**
	 * Apply tags to user before redirect.
	 *
	 * @since 3.40.58
	 *
	 * @param Affiliate_Link $thirstylink The link.
	 */
	public function before_redirect( $thirstylink ) {

		if ( ! wpf_is_user_logged_in() ) {
			return;
		}

		$settings = get_post_meta( $thirstylink->get_id(), 'wpf_settings_thirstyaff', true );

		if ( empty( $settings ) || empty( $settings['apply_tags'] ) ) {
			return;
		}

		wp_fusion()->user->apply_tags( $settings['apply_tags'] );

	}

	/**
	 * Adds meta box on the thirsty affiliate links post type.
	 *
	 * @since 3.40.58
	 */
	public function add_meta_box() {

		add_meta_box( 'thirsty-aff-wp-fusion', 'WP Fusion - Link Settings', array( $this, 'meta_box_callback' ), 'thirstylink', 'normal', 'default' );

	}

	/**
	 * Displays meta box content.
	 *
	 * @since 3.40.58
	 *
	 * @param WP_Post $post   The post.
	 */
	public function meta_box_callback( $post ) {

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wpf_meta_box_thirstyaff', 'wpf_meta_box_thirstyaff_nonce' );

		$settings = array(
			'apply_tags' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf_settings_thirstyaff', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf_settings_thirstyaff', true ) );
		}

		// Apply tags.

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags">' . __( 'Apply Tags', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags'],
			'meta_name' => 'wpf_settings_thirstyaff',
			'field_id'  => 'apply_tags',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . sprintf( __( 'These tags will be applied in %s when a logged-in user clicks the link.', 'wp-fusion' ), esc_html( wp_fusion()->crm->name ) ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

	}

	/**
	 * Saves metabox data.
	 *
	 * @since 3.40.58
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_meta_box_data( $post_id ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_thirstyaff_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_thirstyaff_nonce'], 'wpf_meta_box_thirstyaff' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! empty( $_POST['wpf_settings_thirstyaff'] ) ) {
			update_post_meta( $post_id, 'wpf_settings_thirstyaff', $_POST['wpf_settings_thirstyaff'] );
		} else {
			delete_post_meta( $post_id, 'wpf_settings_thirstyaff' );
		}
	}



}

new WPF_ThirstyAffiliates();
