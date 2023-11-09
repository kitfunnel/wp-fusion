<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}



class WPF_Apprentice extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'thrive-apprentice';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Thrive Apprentice';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/thrive-apprentice/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'wpf_meta_box_content', array( $this, 'meta_box_content' ), 40, 2 );
		add_action( 'wp_ajax_thrive_appr_set_progress', array( $this, 'lesson_complete' ), 5 );

	}

	/**
	 * Adds Apprentice fields to WPF meta box
	 *
	 * @access public
	 * @return void
	 */

	public function meta_box_content( $post, $settings ) {

		if ( $post->post_type != 'appr_lesson' ) {
			return;
		}

		echo '<p><label for="wpf-apply-tags-complete"><small>Apply these tags when lesson marked complete:</small></label>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_complete'],
				'meta_name' => 'wpf-settings',
				'field_id'  => 'apply_tags_complete',
			)
		);

	}

	/**
	 * Track lesson completion
	 *
	 * @access public
	 * @return void
	 */

	public function lesson_complete() {

		$post_id = $_POST['post_id'];
		$status  = $_POST['status'];

		if ( $status == 'completed' ) {

			$wpf_settings = get_post_meta( $post_id, 'wpf-settings', true );
			if ( empty( $wpf_settings ) || empty( $wpf_settings['apply_tags_complete'] ) ) {
				return;
			}

			wp_fusion()->user->apply_tags( $wpf_settings['apply_tags_complete'] );

		}

	}

}

new WPF_Apprentice();
