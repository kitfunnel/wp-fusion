<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Sensei extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'sensei';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Sensei';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/sensei/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'sensei_user_lesson_end', array( $this, 'apply_tags_complete' ), 10, 2 );
		add_action( 'sensei_user_course_end', array( $this, 'apply_tags_complete' ), 10, 2 );

		add_action( 'wpf_meta_box_content', array( $this, 'meta_box_content' ), 30, 2 );
		add_action( 'wpf_meta_box_save', array( $this, 'meta_box_save' ), 20, 2 );

	}


	/**
	 * Shows select field with tags to apply on page load, with delay
	 *
	 * @access public
	 * @return void
	 */

	public function meta_box_content( $post, $settings ) {

		if ( $post->post_type != 'lesson' && $post->post_type != 'course' ) {
			return;
		}

		echo '<p><label for="wpf-apply-tags"><small>Apply tags when a user completes this ' . $post->post_type . ':</small></label>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_sensei'],
				'meta_name' => 'wpf-settings',
				'field_id'  => 'apply_tags_sensei',
			)
		);
		echo '</p>';

		if ( $post->post_type == 'course' ) {

			$children = get_posts(
				array(
					'posts_per_page' => - 1,
					'post_type'      => array( 'lesson' ),
					'meta_key'       => '_lesson_course',
					'meta_value'     => $post->ID,
					'post_status'    => 'any',
				)
			);

			if ( count( $children ) > 0 ) {
				echo '<p><input class="checkbox" type="checkbox" id="wpf-apply-children-courses" name="wpf-settings[apply_children_courses]" value="1" /> <small>Apply to ' . count( $children ) . ' related lessons.</small></p>';
			}
		}

	}

	/**
	 * Runs when WPF meta box is saved
	 *
	 * @access public
	 * @return void
	 */

	public function meta_box_save( $post_id, $data ) {

		if ( isset( $data['apply_children_courses'] ) ) {

			$children = get_posts(
				array(
					'posts_per_page' => - 1,
					'post_type'      => array( 'lesson' ),
					'meta_key'       => '_lesson_course',
					'meta_value'     => $post_id,
					'post_status'    => 'any',
				)
			);

		}

		if ( isset( $children ) ) {

			foreach ( $children as $child ) {
				update_post_meta( $child->ID, 'wpf-settings', $data );
			}
		}

	}


	/**
	 * Triggered when a user completes a lesson or course
	 *
	 * @access public
	 * @return void
	 */

	public function apply_tags_complete( $user_id, $post_id ) {

		$wpf_settings = get_post_meta( $post_id, 'wpf-settings', true );

		if ( ! empty( $wpf_settings ) && isset( $wpf_settings['apply_tags_sensei'] ) ) {
			wp_fusion()->user->apply_tags( $wpf_settings['apply_tags_sensei'], $user_id );
		}

	}


}

new WPF_Sensei();
