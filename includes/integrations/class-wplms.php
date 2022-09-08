<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}



class WPF_WPLMS extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wplms';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Wplms';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/wplms/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// Meta boxes
		add_action( 'wpf_meta_box_content', array( $this, 'meta_box_content' ), 40, 2 );

		// Completion actions
		add_action( 'wplms_start_course', array( $this, 'start_course' ), 10, 4 );
		add_action( 'wplms_submit_course', array( $this, 'submit_course' ), 10, 2 );
		add_action( 'wplms_unit_complete', array( $this, 'unit_complete' ), 10, 4 );

	}

	/**
	 * Adds WPLMS fields to WPF meta box
	 *
	 * @access public
	 * @return mixed HTML Output
	 */

	public function meta_box_content( $post, $settings ) {

		if ( $post->post_type == 'course' ) {

			echo '<p><label for="wpf-apply-tags-wplms"><small>Apply tags when course begun:</small></label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags_wplms_start'],
					'meta_name' => 'wpf-settings',
					'field_id'  => 'apply_tags_wplms_start',
				)
			);

			echo '</p>';

		}

		if ( $post->post_type == 'unit' || $post->post_type == 'course' ) {

			echo '<p><label for="wpf-apply-tags-wplms"><small>Apply tags when marked complete:</small></label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags_wplms_complete'],
					'meta_name' => 'wpf-settings',
					'field_id'  => 'apply_tags_wplms_complete',
				)
			);

			echo '</p>';

		}

	}

	/**
	 * Apply tags when course begun
	 *
	 * @access public
	 * @return void
	 */

	public function start_course( $course_id, $user_id ) {

		$settings = get_post_meta( $course_id, 'wpf-settings', true );

		if ( ! empty( $settings['apply_tags_wplms_start'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_wplms_start'], $user_id );
		}

	}

	/**
	 * Apply tags when course submitted
	 *
	 * @access public
	 * @return void
	 */

	public function submit_course( $course_id, $user_id ) {

		$settings = get_post_meta( $course_id, 'wpf-settings', true );

		if ( ! empty( $settings['apply_tags_wplms_complete'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_wplms_complete'], $user_id );
		}

	}

	/**
	 * Apply tags when unit completed
	 *
	 * @access public
	 * @return void
	 */

	public function unit_complete( $unit_id, $course_progress, $course_id, $user_id ) {

		$settings = get_post_meta( $unit_id, 'wpf-settings', true );

		if ( ! empty( $settings['apply_tags_wplms_complete'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_wplms_complete'], $user_id );
		}

	}


}

new WPF_WPLMS();
