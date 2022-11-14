<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_CoursePress extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'coursepress';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Coursepress';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/coursepress/';

	/**
	 * Gets things started.
	 *
	 * @since 3.23.1
	 */

	public function init() {

		add_action( 'coursepress_student_unit_completed', array( $this, 'unit_completed' ), 10, 4 );

		add_action( 'coursepress_student_course_completed', array( $this, 'course_completed' ), 10, 3 );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20 );

		add_action( 'wp_ajax_wpf_coursepress_save', array( $this, 'save_meta_box_data' ) );

	}

	/**
	 * Apply tags on unit completion.
	 *
	 * @since 3.23.1
	 *
	 * @param int    $user_id    The user ID.
	 * @param int    $unit_id    The unit ID.
	 * @param string $unit_title The unit title.
	 * @param int    $course_id  The course ID.
	 */
	public function unit_completed( $user_id, $unit_id, $unit_title, $course_id ) {

		$settings = get_post_meta( $unit_id, 'wpf_settings_coursepress', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_complete'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_complete'], $user_id );

		}

	}


	/**
	 * Apply tags on course completion.
	 *
	 * @since 3.23.1
	 *
	 * @param int    $user_id    The user ID.
	 * @param int    $course_id  The course ID.
	 * @param string $unit_title The course title.
	 */

	public function course_completed( $user_id, $course_id, $course_title ) {

		$settings = get_post_meta( $course_id, 'wpf_settings_coursepress', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_complete'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_complete'], $user_id );

		}

	}


	/**
	 * Adds meta box to Course post type.
	 *
	 * @since 3.23.1
	 */

	/**
	 * Adds meta box to Course post type.
	 *
	 * @since 3.23.1
	 *
	 * @param int $post_id The post ID.
	 */
	public function add_meta_box( $post_id ) {

		add_meta_box( 'wpf-coursepress-meta', 'WP Fusion - Course Settings', array( $this, 'meta_box_callback' ), 'course' );

	}


	/**
	 * Displays meta box content.
	 *
	 * @since 3.23.1
	 *
	 * @param WP_Post $post   The post object.
	 * @return mixed HTML settings output.
	 */
	public function meta_box_callback( $post ) {

		wp_nonce_field( 'wpf_meta_box_coursepress', 'wpf_meta_box_coursepress_nonce' );

		$settings = array(
			'apply_tags_complete' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf_settings_coursepress', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf_settings_coursepress', true ) );
		}

		echo '<h3>Course settings:</h3>';

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_complete">Apply tags:</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_complete'],
			'meta_name' => 'wpf_settings_coursepress',
			'field_id'  => 'apply_tags_complete',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">These tags will be applied when the course is marked complete.</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

		echo '<hr />';

		echo '<h3>Unit Settings:</h3>';

		$units = get_post_meta( $post->ID, 'cp_structure_visible_units', true );

		if ( ! empty( $units ) ) {

			echo '<table class="form-table"><tbody>';

			foreach ( $units as $unit_id => $visible ) {

				$title = get_the_title( $unit_id );

				if ( empty( $title ) ) {
					continue;
				}

				$settings = get_post_meta( $unit_id, 'wpf_settings_coursepress', true );

				if ( empty( $settings ) ) {
					$settings = array();
				}

				if ( ! isset( $settings['apply_tags_complete'] ) ) {
					$settings['apply_tags_complete'] = array();
				}

				echo '<tr>';

				echo '<th scope="row"><label for="apply_tags_complete">' . $title . ':</label></th>';
				echo '<td>';

				$args = array(
					'setting'   => $settings['apply_tags_complete'],
					'meta_name' => 'wpf_settings_coursepress',
					'field_id'  => 'apply_tags_unit_' . $unit_id,
				);

				wpf_render_tag_multiselect( $args );

				echo '<span class="description">These tags will be applied when the unit <strong>' . $title . '</strong> is marked complete.</span>';
				echo '</td>';

				echo '</tr>';

			}

			echo '</tbody></table>';

		} else {

			echo '<br/><br/>Create some units to configure unit tagging.';

		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"></th>';
		echo '<td>';

			echo '<input type="hidden" id="wpf-coursepress-postid" value="' . $post->ID . '">';

			echo '<a href="#" id="wpf-coursepress-update" class="button">Update</a>';

		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

	}


	/**
	 * Save the settings to the course.
	 *
	 * @since 3.23.1
	 */

	public function save_meta_box_data() {

		$post_id = $_POST['id'];

		$update_values = array();

		foreach ( $_POST['data'] as $setting ) {

			if ( 'wpf_settings_coursepress[apply_tags_complete][]' == $setting['name'] ) {

				if ( ! isset( $update_values[ $post_id ] ) ) {
					$update_values[ $post_id ] = array();
				}

				$update_values[ $post_id ][] = $setting['value'];

			} else {

				$unit_id = str_replace( 'wpf_settings_coursepress[apply_tags_unit_', '', $setting['name'] );

				$unit_id = str_replace( '][]', '', $unit_id );

				if ( ! isset( $update_values[ $unit_id ] ) ) {
					$update_values[ $unit_id ] = array();
				}

				$update_values[ $unit_id ][] = $setting['value'];

			}
		}

		foreach ( $update_values as $post_id => $tags ) {

			// Update the meta fields in the database.
			update_post_meta( $post_id, 'wpf_settings_coursepress', array( 'apply_tags_complete' => $tags ) );

		}

	}


}

new WPF_CoursePress();
