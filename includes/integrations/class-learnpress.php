<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_LearnPress extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'learnpress';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Learnpress';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/learnpress/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'learnpress/user/course-enrolled', array( $this, 'course_enrolled' ), 10, 3 );
		add_action( 'learn-press/updated-user-item-meta', array( $this, 'updated_user_item_meta' ) );

		add_action( 'learn-press/user-course-finished', array( $this, 'user_finish_course' ), 10, 3 );
		add_action( 'learn-press/user-completed-lesson', array( $this, 'user_complete_lesson' ), 10, 3 );

		// Auto enrollments.
		add_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

		// Access control.
		add_action( 'learn-press/course-item/is-blocked', array( $this, 'course_item_is_blocked' ), 10, 4 );
		add_filter( 'wpf_redirect_post_id', array( $this, 'redirect_post_id' ) );

		add_action( 'learn-press/course-item-content', array( $this, 'restricted_content_message' ), 10 ); // 10 so it runs before the built in blocked message

		add_filter( 'learnpress/course/metabox/tabs', array( $this, 'course_tabs' ), 10, 2 );
		add_action( 'learnpress/course-settings/before-wp_fusion', array( $this, 'course_tab_callback' ), 20, 2 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ), 20, 2 );

	}

	/**
	 * Applies tags when a user enrolls in a LearnPress course.
	 *
	 * @since 3.11.0
	 * @since 3.38.34 Moved from learn-press/user-enrolled-course
	 *                to learnpress/user/course-enrolled.
	 *
	 * @param int $order_id  The order ID.
	 * @param int $course_id The course ID.
	 * @param int $user_id   The user ID.
	 */
	public function course_enrolled( $order_id, $course_id, $user_id ) {

		$wpf_settings = get_post_meta( $course_id, 'wpf_settings_learnpress', true );

		if ( ! empty( $wpf_settings['apply_tags_start'] ) ) {

			remove_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

			wp_fusion()->user->apply_tags( $wpf_settings['apply_tags_start'], $user_id );

			add_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

		}

	}


	/**
	 * With the WooCommerce Payment Methods Integration, the
	 * learn-press/user-enrolled-course hook doesn't fire after a purchase, for
	 * some reason, so this is a fallback.
	 *
	 * @since 3.37.30
	 * @since 3.38.34 Not sure if we still need this since course_enrolled() was
	 *                moved to learnpress/user/course-enrolled, but too lazy to check.
	 *
	 * @param array $item   The item.
	 */
	public function updated_user_item_meta( $item ) {

		if ( ! empty( $item ) && 'lp_course' == $item->item_type && 'purchased' == $item->status ) {
			$this->user_enrolled_course( $item->item_id, $item->user_id );
		}

	}


	/**
	 * Applies tags when a LearnPress course is completed
	 *
	 * @access public
	 * @return void
	 */

	public function user_finish_course( $course_id, $user_id, $return ) {

		$wpf_settings = get_post_meta( $course_id, 'wpf_settings_learnpress', true );

		if ( ! empty( $wpf_settings['apply_tags_complete'] ) ) {
			wp_fusion()->user->apply_tags( $wpf_settings['apply_tags_complete'], $user_id );
		}

	}

	/**
	 * Applies tags when a LearnPress lesson is completed
	 *
	 * @access public
	 * @return void
	 */

	public function user_complete_lesson( $lesson_id, $result, $user_id ) {

		$wpf_settings = get_post_meta( $lesson_id, 'wpf_settings_learnpress', true );

		if ( ! empty( $wpf_settings['apply_tags_complete'] ) ) {
			wp_fusion()->user->apply_tags( $wpf_settings['apply_tags_complete'], $user_id );
		}

	}

	/**
	 * Update user course enrollments when tags are modified.
	 *
	 * @since  3.38.34
	 *
	 * @param int $user_id   The user ID.
	 * @param array $user_tags The user tags.
	 */
	public function update_course_access( $user_id, $user_tags ) {

		$linked_courses = get_posts(
			array(
				'post_type'  => 'lp_course',
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf_settings_learnpress',
						'compare' => 'EXISTS',
					),
				),
				'fields'     => 'ids',
			)
		);

		// Update course access based on user tags.
		if ( ! empty( $linked_courses ) ) {

			$user = learn_press_get_user( $user_id );

			foreach ( $linked_courses as $course_id ) {

				$settings = get_post_meta( $course_id, 'wpf_settings_learnpress', true );

				if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
					continue;
				}

				$tag_id = $settings['tag_link'][0];

				$filter          = new LP_User_Items_Filter();
				$filter->item_id = $course_id;
				$filter->user_id = $user_id;

				$course_item = LP_User_Items_DB::getInstance()->get_user_course_item( $filter );

				if ( in_array( $tag_id, $user_tags ) && ! $user->has_enrolled_course( $course_id ) ) {

					// Needs auto-enrollment.

					wpf_log(
						'info',
						$user_id,
						'User auto-enrolled in LearnPress course <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $course_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>'
					);

					if ( empty( $course_item ) ) {

						// Brand new enrollment.

						$course_item = array(
							'user_id'    => $user_id,
							'item_id'    => $course_id,
							'status'     => LP_COURSE_ENROLLED,
							'graduation' => LP_COURSE_GRADUATION_IN_PROGRESS,
						);

						$user_item_new = new LP_User_Item_Course( $course_item );
						$result        = $user_item_new->update();

					} else {

						// Existing.
						$course_item = new LP_User_Item_Course( $course_item );
						$course_item->set_status( 'enrolled' );
						$course_item->update();
					}

					do_action( 'learnpress/user/course-enrolled', 0, $course_id, $user_id ); // trigger tags to be applied.

				} elseif ( ! in_array( $tag_id, $user_tags ) && $user->has_enrolled_course( $course_id ) ) {

					// Needs un-enrollment.

					$course_item = new LP_User_Item_Course( $course_item );

					// Logger.
					wpf_log(
						'info',
						$user_id,
						'User un-enrolled from LearnPress course <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $course_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>'
					);

					$course_item->set_status( 'not-enrolled' );
					$course_item->update();

				}
			}
		}

	}

	/**
	 * Can a user access a lesson / other item within a course
	 *
	 * @access public
	 * @return bool Is Blocked
	 */

	public function course_item_is_blocked( $is_blocked, $item_id, $course_id, $user_id ) {

		if ( false == wp_fusion()->access->user_can_access( $item_id, $user_id ) ) {
			$is_blocked = true;
		}

		return $is_blocked;

	}

	/**
	 * Make sure we properly check a lesson's access rules, not the course
	 *
	 * @access public
	 * @return int Post ID
	 */

	public function redirect_post_id( $post_id ) {

		global $post;

		if ( 'lp_course' == $post->post_type ) {

			if ( 'lp_course' != get_query_var( 'item-type' ) ) {

				$post_item = learn_press_get_post_by_name( get_query_var( 'course-item' ), get_query_var( 'item-type' ) );

				if ( $post_item ) {
					$post_id = $post_item->ID;
				}
			}
		}

		return $post_id;

	}

	/**
	 * Restricted content message
	 *
	 * @access public
	 * @return mixed HTML Restricted Content Message
	 */

	public function restricted_content_message() {

		$item = LP_Global::course_item();

		if ( ! wp_fusion()->access->user_can_access( $item->get_id() ) ) {

			// This is messy but what can you do...

			echo '<!-- WP Fusion is hiding the LearnPress protected content message in favor of the WP Fusion restricted content message -->';
			echo '<style type="text/css">.learn-press-content-protected-message { display: none; }</style>';

			echo wp_fusion()->access->get_restricted_content_message( $item->get_id() );

		}

		return;

	}


	/**
	 * Adds meta box
	 *
	 * @access public
	 * @return mixed
	 */

	public function add_meta_box( $post_id, $data ) {

		add_meta_box( 'wpf-learnpress-meta', 'WP Fusion - Lesson Settings', array( $this, 'meta_box_callback_lesson' ), 'lp_lesson' );

	}


	/**
	 * Adds WP Fusion tab to course settings.
	 *
	 * @since  3.38.34
	 *
	 * @param  array $tabs      The tabs.
	 * @param  int   $course_id The course ID.
	 * @return array The tabs.
	 */
	public function course_tabs( $tabs, $course_id ) {

		$tabs['wp_fusion'] = array(
			'label'    => esc_html__( 'WP Fusion', 'wp-fusion' ),
			'target'   => 'wp_fusion',
			'priority' => 50,
			'content'  => '',
			'icon'     => 'icon-wp-fusion',
		);

		return $tabs;
	}


	/**
	 * Callback for course settings tab.
	 *
	 * @since 3.38.34
	 */
	public function course_tab_callback() {

		echo '<p>' . sprintf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/learning-management/learnpress/" target="_blank">', '</a>' ) . '</p>';

		global $post;

		$settings = array(
			'apply_tags_start'    => array(),
			'apply_tags_complete' => array(),
			'tag_link'            => array(),
		);

		$settings = wp_parse_args( get_post_meta( $post->ID, 'wpf_settings_learnpress', true ), $settings );

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_start">' . esc_html__( 'Apply Tags - Enrolled', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_start'],
			'meta_name' => 'wpf_settings_learnpress',
			'field_id'  => 'apply_tags_start',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . esc_html( sprintf( __( 'These tags will be applied in %s when someone is enrolled in this course.', 'wp-fusion' ), wp_fusion()->crm->name ) ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">' . esc_html__( 'Link with Tag', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['tag_link'],
			'meta_name' => 'wpf_settings_learnpress',
			'field_id'  => 'tag_link',
			'limit'     => 1,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . esc_html( sprintf( __( 'This tag will be applied in %1$s when a user is enrolled, and will be removed when a user is unenrolled. Likewise, if this tag is applied to a user from within %2$s, they will be automatically enrolled in this course. If this tag is removed, the user will be removed from the course.', 'wp-fusion' ), wp_fusion()->crm->name, wp_fusion()->crm->name ) ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_complete">' . esc_html__( 'Apply Tags - Completed', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_complete'],
			'meta_name' => 'wpf_settings_learnpress',
			'field_id'  => 'apply_tags_complete',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . esc_html__( 'Apply these tags when the course is marked complete.', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

	}

	/**
	 * Displays meta box content (groups)
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback_lesson( $post ) {

		wp_nonce_field( 'wpf_meta_box_learnpress', 'wpf_meta_box_learnpress_nonce' );

		$settings = array(
			'apply_tags_start'    => array(),
			'apply_tags_complete' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf_settings_learnpress', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf_settings_learnpress', true ) );
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">Apply tags when completed:</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_complete'],
			'meta_name' => 'wpf_settings_learnpress',
			'field_id'  => 'apply_tags_complete',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">These tags will be applied to the user when they complete the lesson.</span>';
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

		// Check if our data is set.
		if ( ! isset( $_POST['wpf_settings_learnpress'] ) ) {
			return;
		}

		$data = WPF_Admin_Interfaces::sanitize_tags_settings( wp_unslash( $_POST['wpf_settings_learnpress'] ) );

		if ( ! empty( $data ) ) {
			update_post_meta( $post_id, 'wpf_settings_learnpress', $data );
		} else {
			delete_post_meta( $post_id, 'wpf_settings_learnpress' );
		}

	}


}

new WPF_LearnPress();
