<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Tutor_LMS extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'tutor-lms';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Tutor LMS';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/tutor-lms/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'tutor_course_complete_after', array( $this, 'course_complete' ) );
		add_action( 'tutor_after_enroll', array( $this, 'after_enroll' ), 10, 2 );

		add_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'save_post_courses', array( $this, 'save_meta_box_data' ) );

	}

	/**
	 * Apply tags when course marked complete
	 *
	 * @access public
	 * @return void
	 */

	public function course_complete( $course_id ) {

		$settings = get_post_meta( $course_id, 'wpf-settings', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_complete'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_complete'] );

		}

	}


	/**
	 * Maybe apply linked tags after someone is enrolled in a course
	 *
	 * @access public
	 * @return void
	 */

	public function after_enroll( $course_id, $enrollment_id ) {

		$settings = get_post_meta( $course_id, 'wpf-settings-tutorlms', true );

		if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
			return;
		}

		remove_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

		$post = get_post( $enrollment_id );

		wp_fusion()->user->apply_tags( $settings['tag_link'], $post->post_author );

		add_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

	}


	/**
	 * Update user course enrollment when tags are modified
	 *
	 * @access public
	 * @return void
	 */

	public function update_course_access( $user_id, $user_tags ) {

		$linked_courses = get_posts(
			array(
				'post_type'  => 'courses',
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf-settings-tutorlms',
						'compare' => 'EXISTS',
					),
				),
				'fields'     => 'ids',
			)
		);

		// Update course access based on user tags
		if ( ! empty( $linked_courses ) ) {

			foreach ( $linked_courses as $course_id ) {

				$settings = get_post_meta( $course_id, 'wpf-settings-tutorlms', true );

				if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
					continue;
				}

				$tag_id = $settings['tag_link'][0];

				$is_enrolled = tutor_utils()->is_enrolled( $course_id, $user_id );

				if ( in_array( $tag_id, $user_tags ) && empty( $is_enrolled ) ) {

					// Logger
					wpf_log( 'info', $user_id, 'User auto-enrolled in TutorLMS course <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $course_id ) . '</a> by tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

					// Prevent looping
					remove_action( 'tutor_after_enroll', array( $this, 'after_enroll' ), 10, 2 );

					// Make TutorLMS think this is a free course, so we can enroll them without a purchase
					add_filter(
						'is_course_purchasable',
						function() {
							return false;
						}
					);

					tutor_utils()->do_enroll( $course_id, 0, $user_id );

					add_action( 'tutor_after_enroll', array( $this, 'after_enroll' ), 10, 2 );

				} elseif ( ! in_array( $tag_id, $user_tags ) && ! empty( $is_enrolled ) ) {

					// Logger
					wpf_log( 'info', $user_id, 'User un-enrolled from TutorLMS course <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $course_id ) . '</a> by tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

					$post = array(
						'ID'          => $is_enrolled->ID,
						'post_status' => 'trash',
					);

					wp_update_post( $post );

				}
			}
		}

	}

	/**
	 * Adds meta boxes
	 *
	 * @access public
	 * @return void
	 */

	public function add_meta_boxes( $post_id, $data ) {

		add_meta_box( 'wpf-tutor-meta', 'WP Fusion - Course Settings', array( $this, 'meta_box_callback' ), 'courses' );

	}


	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback( $post ) {

		$settings = array(
			'apply_tags_complete' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings', true ) );
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_complete">' . __( 'Apply tags' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_complete'],
			'meta_name' => 'wpf-settings',
			'field_id'  => 'apply_tags_complete',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'Apply these tags when marked complete' ) . ':</span>';
		echo '</td>';

		echo '</tr>';

		$settings = array(
			'tag_link' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings-tutorlms', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings-tutorlms', true ) );
		}

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">' . __( 'Link with Tag', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'     => $settings['tag_link'],
			'meta_name'   => 'wpf-settings-tutorlms',
			'field_id'    => 'tag_link',
			'placeholder' => 'Select Tag',
			'limit'       => 1,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'Select a tag to link with this course. When the tag is applied, the user will automatically be enrolled. When this tag is removed, the user will be un-enrolled.', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

	}

	/**
	 * Runs when WPF meta box is saved
	 *
	 * @access public
	 * @return void
	 */

	public function save_meta_box_data( $post_id ) {

		if ( isset( $_POST['wpf-settings-tutorlms'] ) ) {
			update_post_meta( $post_id, 'wpf-settings-tutorlms', $_POST['wpf-settings-tutorlms'] );
		} else {
			delete_post_meta( $post_id, 'wpf-settings-tutorlms' );
		}

	}


}

new WPF_Tutor_LMS();
