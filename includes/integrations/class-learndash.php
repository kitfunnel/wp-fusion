<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * LearnDash integration.
 *
 * @since 1.0.0
 *
 * @link https://wpfusion.com/documentation/learning-management/learndash/
 */
class WPF_LearnDash extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'learndash';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'LearnDash';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/learndash/';

	/**
	 * Contains any course sections modified by query filtering or Filter Course
	 * Steps.
	 *
	 * @since 3.38.39
	 * @var  array The sections.
	 */
	public $filtered_course_sections = array();

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'learndash_course_completed', array( $this, 'course_completed' ), 5 );
		add_action( 'learndash_lesson_completed', array( $this, 'lesson_completed' ), 5 );
		add_action( 'learndash_quiz_submitted', array( $this, 'quiz_submitted' ), 5, 2 );
		add_action( 'learndash_topic_completed', array( $this, 'topic_completed' ), 5 );
		add_action( 'learndash_new_essay_submitted', array( $this, 'essay_submitted' ), 5, 2 );
		add_action( 'ldadvquiz_answered', array( $this, 'quiz_answered' ), 10, 3 );
		add_action( 'learndash_assignment_uploaded', array( $this, 'assignment_uploaded' ), 10, 2 );
		add_action( 'learndash_update_user_activity', array( $this, 'update_user_activity' ) );
		add_filter( 'learndash_access_redirect', array( $this, 'lesson_access_redirect' ), 10, 2 );

		// ThriveCart.
		add_action( 'learndash_thrivecart_after_create_user', array( $this, 'thrivecart_after_create_user' ), 10, 3 );

		// Content filtering.
		add_filter( 'learndash_content', array( $this, 'content_filter' ), 10, 2 );
		add_filter( 'learndash-lesson-row-class', array( $this, 'lesson_row_class' ), 10, 2 );
		add_filter( 'learndash-topic-row-class', array( $this, 'lesson_row_class' ), 10, 2 );
		add_filter( 'learndash-nav-widget-lesson-class', array( $this, 'lesson_row_class' ), 10, 2 );
		add_filter( 'learndash_lesson_attributes', array( $this, 'lesson_attributes' ), 10, 2 );

		// Topic locking message.
		add_action( 'learndash-topic-row-title-after', array( $this, 'topic_locked_message' ), 10, 3 );

		// Filter Course Steps.
		add_filter( 'get_post_metadata', array( $this, 'filter_course_steps' ), 10, 4 );
		add_filter( 'sfwd_lms_has_access', array( $this, 'has_access' ), 10, 3 );
		add_filter( 'learndash_can_user_read_step', array( $this, 'can_user_read_step' ), 10, 3 );

		// Settings.
		add_action( 'add_meta_boxes', array( $this, 'configure_meta_box' ) );
		add_action( 'wpf_meta_box_content', array( $this, 'meta_box_notice' ), 5, 2 );
		add_action( 'wpf_meta_box_content', array( $this, 'meta_box_content' ), 40, 2 );

		add_filter( 'learndash_settings_fields_wpf', array( $this, 'course_settings_fields' ), 10, 2 );

		// Assignment settings.
		add_filter( 'learndash_settings_fields', array( $this, 'lesson_settings_fields' ), 10, 2 );

		// WPF stuff.
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );
		add_filter( 'wpf_watched_meta_fields', array( $this, 'watch_meta_fields' ) );
		add_filter( 'wpf_apply_tags_on_view', array( $this, 'maybe_stop_apply_tags_on_view' ), 10, 2 );
		add_filter( 'wpf_post_access_meta', array( $this, 'inherit_permissions_from_course' ), 10, 2 );

		// Meta boxes.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20, 2 );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ), 20 );

		add_action( 'load-post.php', array( $this, 'register_metabox' ), 1 );
		add_action( 'load-post-new.php', array( $this, 'register_metabox' ), 1 );
		add_filter( 'learndash_header_tab_menu', array( $this, 'add_metabox_tab' ) );

		// Admin course table.
		add_filter( 'display_post_states', array( $this, 'admin_table_post_states' ), 10, 2 );

		// Auto enrollments.
		add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 9, 2 ); // This is 9 so that the user is in the correct groups by the time we go to update their courses.
		add_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

		// Group linking.
		add_filter( 'learndash_settings_fields', array( $this, 'group_settings_fields' ), 10, 2 );
		add_action( 'ld_added_group_access', array( $this, 'added_group_access' ), 10, 2 );
		add_action( 'ld_removed_group_access', array( $this, 'removed_group_access' ), 10, 2 );

		// Group Leader linking.
		add_action( 'ld_added_leader_group_access', array( $this, 'added_group_leader_access' ), 10, 2 );
		add_action( 'ld_removed_leader_group_access', array( $this, 'removed_group_leader_access' ), 10, 2 );

		// Course linking.
		add_action( 'learndash_update_course_access', array( $this, 'updated_course_access' ), 10, 4 );

		// Send auto-generated passwords on user registration.
		add_filter( 'random_password', array( $this, 'push_password' ) );

		// Detect LearnDash for WooCommerce plugin.
		add_action( 'added_user_meta', array( $this, 'maybe_add_learndash_woocommerce_plugin_source' ), 10, 4 );
		add_action( 'updated_user_meta', array( $this, 'maybe_add_learndash_woocommerce_plugin_source' ), 10, 4 );

		// Uncanny Toolkit Pro compatibility.
		add_action( 'wp_fusion_init', array( $this, 'uncanny_toolkit_pro_compatibility' ) );

		// Export functions.
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_filter( 'wpf_batch_learndash_courses_init', array( $this, 'batch_init' ) );
		add_filter( 'wpf_batch_learndash_progress_init', array( $this, 'batch_init' ) );
		add_filter( 'wpf_batch_learndash_groups_init', array( $this, 'batch_init' ) );

		add_action( 'wpf_batch_learndash_courses', array( $this, 'batch_step_courses' ) );
		add_action( 'wpf_batch_learndash_progress', array( $this, 'batch_step_progress' ) );
		add_action( 'wpf_batch_learndash_groups', array( $this, 'batch_step_groups' ) );

	}


	/**
	 * Applies tags when a LearnDash course is completed
	 *
	 * @access public
	 * @return void
	 */

	public function course_completed( $data ) {

		$settings = get_post_meta( $data['course']->ID, 'wpf-settings', true );

		if ( ! empty( $settings['apply_tags_ld'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_ld'], $data['user']->ID );
		}

		update_user_meta( $data['user']->ID, 'ld_last_course_completed', get_the_title( $data['course']->ID ) );
		update_user_meta( $data['user']->ID, 'ld_last_course_completed_date', current_time( 'timestamp' ) );

	}

	/**
	 * Applies tags when a LearnDash lesson is completed
	 *
	 * @access public
	 * @return void
	 */

	public function lesson_completed( $data ) {

		$settings = get_post_meta( $data['lesson']->ID, 'wpf-settings', true );

		if ( ! empty( $settings['apply_tags_ld'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_ld'], $data['user']->ID );
		}

		update_user_meta( $data['user']->ID, 'ld_last_lesson_completed', get_the_title( $data['lesson']->ID ) );
		update_user_meta( $data['user']->ID, 'ld_last_lesson_completed_date', current_time( 'timestamp' ) );

	}

	/**
	 * Applies tags when a LearnDash quiz is completed
	 *
	 * @access public
	 * @return void
	 */

	public function quiz_submitted( $data, $user ) {
		if ( isset( $data['quiz']->ID ) ) {
			$quiz_id = $data['quiz']->ID;
		} else {
			// For grading in the admin
			$quiz_id = $data['quiz'];
		}

		$settings = get_post_meta( $quiz_id, 'wpf-settings', true );

		if ( empty( $settings ) ) {
			return;
		}

		$update_data = array();

		// Add final score to CRM field.
		if ( ! empty( $settings['final_score_field'] ) && ! empty( $settings['final_score_field']['crm_field'] ) ) {
			$update_data[ $settings['final_score_field']['crm_field'] ] = $data['score'];
		}

		// Add final points to CRM field.
		if ( ! empty( $settings['final_points_field'] ) && ! empty( $settings['final_points_field']['crm_field'] ) ) {
			$update_data[ $settings['final_points_field']['crm_field'] ] = $data['points'];
		}

		$contact_id = wp_fusion()->user->get_contact_id( $user->ID );

		if ( empty( $contact_id ) ) {
			return;
		}

		if ( ! empty( $update_data ) ) {

			// Sync fields.

			wpf_log( 'info', $user->ID, 'Syncing <a href="' . admin_url( 'post.php?post=' . $quiz_id . '&action=edit' ) . '">' . get_the_title( $quiz_id ) . '</a> quiz fields to ' . wp_fusion()->crm->name . ':', array( 'meta_array_nofilter' => $update_data ) );

			wp_fusion()->crm->update_contact( $contact_id, $update_data, false );

		}

		// Apply tags.

		if ( $data['pass'] == true && ! empty( $settings['apply_tags_ld'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_ld'], $user->ID );

		} elseif ( $data['pass'] == false && ! empty( $settings['apply_tags_ld_quiz_fail'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_ld_quiz_fail'], $user->ID );

		}

	}


	/**
	 * Applies tags when a LearnDash topic is completed
	 *
	 * @access public
	 * @return void
	 */

	public function topic_completed( $data ) {

		$settings = get_post_meta( $data['topic']->ID, 'wpf-settings', true );

		if ( ! empty( $settings['apply_tags_ld'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_ld'], $data['user']->ID );
		}

	}

	/**
	 * Applies tags when a LearnDash essay is submitted
	 *
	 * @access public
	 * @return void
	 */

	public function essay_submitted( $essay_id, $essay_args ) {

		$quid_pro_id = get_post_meta( $essay_id, 'quiz_id', true );

		$args = array(
			'post_type'  => 'sfwd-quiz',
			'fields'     => 'ids',
			'meta_key'   => 'quiz_pro_id',
			'meta_value' => $quid_pro_id,
		);

		$quizzes = get_posts( $args );

		if ( empty( $quizzes ) ) {
			return;
		}

		$settings = get_post_meta( $quizzes[0], 'wpf-settings', true );

		if ( ! empty( $settings['apply_tags_ld_essay_submitted'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_ld_essay_submitted'], $essay_args['post_author'] );

		}

	}

	/**
	 * Sync quiz question answers to custom fields when quiz answered
	 *
	 * @access public
	 * @return void
	 */

	public function quiz_answered( $results, $quiz, $question_models ) {

		$contact_id = wp_fusion()->user->get_contact_id();

		if ( empty( $contact_id ) ) {
			return;
		}

		$questions_and_answers = array();

		foreach ( $results as $key => $result ) {

			if ( ! empty( $result['e']['r'] ) ) {

				$questions_and_answers[ $key ] = $result['e']['r'];

			} else {

				// Essay questions
				$questions_and_answers[ $key ] = $_POST['data']['responses'][ $key ]['response'];

			}
		}

		// Map the question IDs into post IDs
		foreach ( $question_models as $post_id => $model ) {

			$answerData = $model->getAnswerData();

			foreach ( $questions_and_answers as $key => $result ) {

				if ( $key == $model->getId() ) {

					// Convert multiple choice from true / false into the selected option
					if ( is_array( $result ) ) {

						foreach ( $result as $n => $multiple_choice_answer ) {

							if ( true == $multiple_choice_answer ) {

								$answers = $model->getAnswerData();

								foreach ( $answers as $x => $answer ) {

									if ( $x == $n ) {

										$result = $answer->getAnswer();
										break 2;

									}
								}
							}
						}
					}

					$questions_and_answers[ $post_id ] = $result;
					unset( $questions_and_answers[ $key ] );
				}
			}
		}

		$update_data = array();

		foreach ( $questions_and_answers as $post_id => $answer ) {

			$settings = get_post_meta( $post_id, 'wpf-settings-learndash', true );

			if ( ! empty( $settings ) && ! empty( $settings['crm_field'] ) ) {

				$update_data[ $settings['crm_field'] ] = $answer;

			}
		}

		if ( ! empty( $update_data ) ) {

			wpf_log( 'info', wpf_get_current_user_id(), 'Syncing <a href="' . get_edit_post_link( $quiz->getPostId() ) . '">' . $quiz->getName() . '</a> quiz answers to ' . wp_fusion()->crm->name . ':', array( 'meta_array_nofilter' => $update_data ) );

			wp_fusion()->crm->update_contact( $contact_id, $update_data, false );

		}

	}

	/**
	 * Apply tags when an assignment has been uploaded
	 *
	 * @access public
	 * @return void
	 */

	public function assignment_uploaded( $assignment_post_id, $assignment_meta ) {

		$settings = get_post_meta( $assignment_meta['lesson_id'], 'wpf-settings-learndash', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_assignment_upload'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_assignment_upload'], $assignment_meta['user_id'] );

		}

	}

	/**
	 * Sync the last course progressed
	 *
	 * @access public
	 * @return void
	 */

	public function update_user_activity( $args ) {

		// Save the user activity.

		if ( ! empty( $args['course_id'] ) ) {

			remove_action( 'learndash_update_user_activity', array( $this, 'update_user_activity' ), 10, 1 );

			// Stop special chars in title name getting HTML encoded.
			remove_filter( 'the_title', 'wptexturize' );

			update_user_meta( $args['user_id'], 'ld_last_course_progressed', get_the_title( $args['course_id'] ) );

		}

		// Sync the course progress with the CRM.

		if ( ! empty( $args['course_id'] ) && ! empty( $args['user_id'] ) ) {

			$settings = get_post_meta( $args['course_id'], 'wpf-settings-learndash', true );

			if ( empty( $settings ) ) {
				return;
			}
			if ( empty( $settings['progress_field']['crm_field'] ) ) {
				return;
			}

			$progress = learndash_course_progress(
				array(
					'user_id'   => $args['user_id'],
					'course_id' => $args['course_id'],
					'array'     => true,
				)
			);

			if ( empty( $progress ) ) {
				return;
			}

			$update_data = array(
				$settings['progress_field']['crm_field'] => $progress['percentage'],
			);

			wpf_log( 'info', $args['user_id'], 'Syncing <a href="' . admin_url( 'post.php?post=' . $args['course_id'] . '&action=edit' ) . '">' . get_the_title( $args['course_id'] ) . '</a> course progress to ' . wp_fusion()->crm->name . ':', array( 'meta_array_nofilter' => $update_data ) );

			$contact_id = wpf_get_contact_id( $args['user_id'] );

			wp_fusion()->crm->update_contact( $contact_id, $update_data, false );

		}

	}

	/**
	 * Class SFWD_CPT_Instance hooks into the_content filter to rebuild the
	 * course steps cache when a user views a course. If the cache is being
	 * rebuilt while WPF is filtering the content, this can cause course steps
	 * to be deleted. This bypasses the pre-processing while WPF is filtering
	 * the content.
	 *
	 * @since  3.38.11
	 *
	 * @param  int $post_id The post ID.
	 */
	public function filtering_page_content( $post_id ) {

		add_filter( 'learndash_template_preprocess_filter', '__return_false' );

	}

	/**
	 * Hide LD content if user doesn't have access
	 *
	 * @access public
	 * @return mixed Content
	 */

	public function content_filter( $content, $post ) {

		if ( ! wp_fusion()->access->user_can_access( $post->ID ) ) {
			$content = wp_fusion()->access->get_restricted_content_message();
		}

		return $content;

	}

	/**
	 * If query filtering is enabled, hide course steps from the course
	 * navigation.
	 *
	 * @since  3.36.16
	 *
	 * @param  bool $can_read  Can the user read the step?
	 * @param  int  $step_id   The step ID.
	 * @param  int  $course_id The course ID.
	 * @return bool  True if user able to read step, False otherwise.
	 */
	public function can_user_read_step( $can_read, $step_id, $course_id ) {

		if ( ! $can_read ) {
			return $can_read;
		}

		if ( learndash_is_course_shared_steps_enabled() ) {
			return $can_read; // this is redundant with shared course steps sice it's already filtered out the disabled steps on get_post_metadata.
		}

		if ( is_admin() || wpf_admin_override() ) {
			return $can_read; // This can mess with the Builder if its allowed to run in the admin.
		}

		if ( wpf_get_option( 'hide_archives' ) ) {

			// If query filtering is on

			$post_types = wpf_get_option( 'query_filter_post_types', array() );

			if ( empty( $post_types ) || in_array( get_post_type( $step_id ), $post_types ) ) {

				if ( ! wpf_user_can_access( $step_id ) ) {
					$can_read = false;
				}
			}
		} else {

			// If Filter Course Steps is on.

			$settings = get_post_meta( $course_id, 'wpf-settings-learndash', true );

			if ( ! empty( $settings ) ) {

				if ( empty( $settings['step_display'] ) && ! empty( $settings['filter_steps'] ) ) {
					$settings['step_display'] = 'filter_steps'; // pre 3.38.5 data storage.
				}

				if ( ! empty( $settings['step_display'] ) && 'filter_steps' === $settings['step_display'] && ! wpf_user_can_access( $step_id ) ) {
					$can_read = false;
				}
			}
		}

		if ( false === $can_read ) {

			// Reduce the course step count.

			add_filter(
				'learndash-course-progress-stats',
				function( $progress ) {

					if ( is_array( $progress ) && ! empty( $progress['total'] ) ) {
						$progress['total'] -= 1;
					}

					return $progress;

				}
			);

		}

		return $can_read;

	}


	/**
	 * If query filtering is enabled, hide course steps from the course
	 * navigation.
	 *
	 * @since  3.38.11
	 *
	 * @param  null|array $value    The postmeta value.
	 * @param  int        $post_id  The post ID.
	 * @param  string     $meta_key The meta key.
	 * @param  bool       $single   Whether to return a single value or array.
	 * @return null|array The modified course steps array.
	 */
	public function filter_course_steps( $value, $post_id, $meta_key, $single ) {

		if ( doing_action( 'wp' ) ) {
			return $value; // This can cause an infinite loop during learndash_check_course_step().
		}

		if ( 'ld_course_steps' === $meta_key && learndash_is_course_shared_steps_enabled() ) { // only works with shared course steps.

			if ( is_admin() || wpf_admin_override() || wp_doing_cron() ) {
				return $value; // Don't need to do anything.
			}

			$should_filter = false;

			// If Filter Course Steps is on.

			$settings = get_post_meta( $post_id, 'wpf-settings-learndash', true );

			if ( ! empty( $settings ) && isset( $settings['step_display'] ) && 'filter_steps' === $settings['step_display'] ) {
				$should_filter = true;
			}

			// If query filtering is on.

			if ( false === $should_filter && wpf_get_option( 'hide_archives' ) ) {

				$post_types = wpf_get_option( 'query_filter_post_types', array() );

				if ( empty( $post_types ) || in_array( get_post_type( $post_id ), $post_types, true ) ) {
					$should_filter = true;
				}
			}

			if ( true === $should_filter ) {

				// Prevent looping.
				remove_filter( 'get_post_metadata', array( $this, 'filter_course_steps' ), 10, 4 );
				remove_filter( 'get_post_metadata', array( $this, 'filter_course_steps' ), 10, 4 );

				// Get the current value (up until this point $value is null).
				$value = get_post_meta( $post_id, 'ld_course_steps', true );

				if ( ! empty( $value ) ) {

					$sections = get_post_meta( $post_id, 'course_sections', true );

					if ( ! empty( $sections ) ) {
						$sections = json_decode( $sections, true );
					}

					// Remove any content the user can't access.

					foreach ( $value['steps']['h'] as $post_type => $posts ) {

						// Track the current step in the course navigation so we can adjust
						// the "order" on any sections if needed.
						$step_id = 0;

						foreach ( $posts as $post_id => $sub_posts ) {

							if ( ! empty( $sections ) ) {

								foreach ( $sections as $i => $section ) {
									if ( $section['order'] === $step_id ) {
										$step_id++; // Increment the step ID each time we pass a section heading.
									}
								}
							}

							if ( ! wpf_user_can_access( $post_id ) ) {

								unset( $value['steps']['h'][ $post_type ][ $post_id ] ); // remove the restricted lesson.

								$value['steps_count']--; // This makes the progress bar calculate correctly.

								// If it's a lesson, removing it could potentially affect the
								// section positions, so we'll account for that here.

								if ( ! empty( $sections ) ) {

									foreach ( $sections as $i => $section ) {

										if ( $section['order'] >= $step_id ) {

											// Sections are stored with an order relative to their position in the course.
											$sections[ $i ]['order']--;

										}
									}
								}

								continue;
							}

							// Maybe deal with topics and quizzes inside of lessons.

							foreach ( $sub_posts as $sub_post_type => $sub_posts_of_type ) {

								foreach ( $sub_posts_of_type as $sub_post_id => $sub_post_of_type ) {

									if ( ! wpf_user_can_access( $sub_post_id ) ) {
										unset( $value['steps']['h'][ $post_type ][ $post_id ][ $sub_post_type ][ $sub_post_id ] );
										$value['steps_count']--;
									}
								}
							}

							$step_id++;

						}
					}

					if ( $single && is_array( $value ) ) {
						$value = array( $value ); // get_metadata_raw will return $value[0] if $single is true and the response from the filter is non-null.
					}
				}

				if ( ! empty( $sections ) ) {

					// Add the filter back so it can run for the sections.
					add_filter( 'get_post_metadata', array( $this, 'filter_course_steps' ), 10, 4 );
					$this->filter_course_sections = $sections;

				}
			}
		} elseif ( 'course_sections' === $meta_key && ! empty( $this->filter_course_sections ) ) {

			$value = wp_json_encode( $this->filter_course_sections );

			if ( $single ) {
				$value = array( $value ); // get_metadata_raw will return $value[0] if $single is true and the response from the filter is non-null.
			}

		}

		return $value;

	}

	/**
	 * This works to hide the lessons in focus mode with the BuddyBoss theme
	 * when Filter Course Steps is on.
	 *
	 * @since  3.38.22
	 *
	 * @param  bool $has_access Indicates if the user has access.
	 * @param  int  $post_id    The course ID.
	 * @param  int  $user_id    The user ID.
	 * @return bool  Whether or not the user can access the lesson.
	 */
	public function has_access( $has_access, $post_id, $user_id ) {

		if ( true === $has_access ) {

			$course_id = learndash_get_course_id( $post_id );
			$settings  = get_post_meta( $course_id, 'wpf-settings-learndash', true );

			if ( ! empty( $settings ) && isset( $settings['step_display'] ) && 'filter_steps' === $settings['step_display'] ) {

				if ( ! wpf_user_can_access( $post_id, $user_id ) ) {
					$has_access = false;
				}
			}
		}

		return $has_access;

	}


	/**
	 * If lock lessons is enabled, disable clicking on the lesson or topic.
	 *
	 * @since  3.37.4
	 *
	 * @param  string       $class  The row classes.
	 * @param  array|object $item   The item, either the lesson or topic.
	 * @return string       The row classes.
	 */
	public function lesson_row_class( $class, $item = false ) {

		if ( false === $item ) {
			return $class;  // at the moment learndash-nav-widget-lesson-class is hooked to this
							// function but we're waiting for an update from LD to pass the
							// second parameter.
		}

		if ( is_a( $item, 'WP_Post' ) ) {
			$id = $item->ID; // Topics
		} else {
			$id = ( isset( $item['id'] ) ? $item['id'] : 0 ); // Lessons
		}

		$course_id = learndash_get_course_id( $id );

		$settings = get_post_meta( $course_id, 'wpf-settings-learndash', true );

		if ( empty( $settings ) ) {
			return $class;
		}

		if ( empty( $settings['step_display'] ) && ! empty( $settings['lock_lessons'] ) ) {
			$settings['step_display'] = 'lock_lessons'; // pre 3.38.5 data storage.
		}

		if ( ! empty( $settings['step_display'] ) && 'lock_lessons' === $settings['step_display'] ) {
			if ( ! wpf_user_can_access( $id ) ) {
				$class .= ' learndash-not-available';
			}
		}

		return $class;

	}



	/**
	 * Add restricted content attributes to restricted lessons.
	 *
	 * @since  3.37.4
	 *
	 * @param  array $attributes The attributes.
	 * @param  array $lesson     The lesson.
	 * @return array The attributes.
	 */
	public function lesson_attributes( $attributes, $lesson ) {
		$lesson_id = ( isset( $lesson['id'] ) ? $lesson['id'] : 0 );
		$course_id = learndash_get_course_id( $lesson_id );
		$settings  = get_post_meta( $course_id, 'wpf-settings-learndash', true );

		if ( empty( $settings ) ) {
			return $attributes;
		}

		if ( ! sfwd_lms_has_access( $lesson['id'] ) ) {
			return $attributes; // only applies to content not protected by LearnDash.
		}

		if ( empty( $settings['step_display'] ) && ! empty( $settings['lock_lessons'] ) ) {
			$settings['step_display'] = 'lock_lessons'; // pre 3.38.5 data storage.
		}

		if ( ! empty( $settings['step_display'] ) && 'lock_lessons' === $settings['step_display'] ) {

			if ( ! wpf_user_can_access( $lesson_id ) ) {

				$attribute = array(
					'label' => isset( $settings['lesson_locked_text'] ) ? $settings['lesson_locked_text'] : '',
					'icon'  => 'ld-icon-unlocked',
					'class' => 'ld-status-locked ld-primary-color',
				);

				// Classes can be ld-status-complete, ld-status-waiting, ld-status-unlocked, ld-status-incomplete
				// Icons can be any of the ld-icon-* classes, for example ld-icon-calendar.

				$attributes[] = apply_filters( 'wpf_learndash_lesson_locked_attributes', $attribute, $lesson_id );

			}
		}

		return $attributes;
	}


	/**
	 * Add locked topic message
	 *
	 * @since 3.38.27
	 *
	 * @param int $topic_id  The topic ID.
	 * @param int $course_id The course ID.
	 * @param int $user_id   The user ID.
	 * @return mxied HTML output.
	 */
	public function topic_locked_message( $topic_id, $course_id, $user_id ) {

		$settings = get_post_meta( $course_id, 'wpf-settings-learndash', true );

		if ( empty( $settings ) ) {
			return;
		}

		if ( ! sfwd_lms_has_access( $topic_id ) ) {
			return; // only applies to content not protected by LearnDash.
		}

		if ( ! empty( $settings['step_display'] ) && 'lock_lessons' === $settings['step_display'] ) {

			if ( ! wpf_user_can_access( $topic_id ) ) {
				echo '
				<span style="margin-left: 10px;margin-top: 3px;" class="ld-item-components">
					<span class="ld-status ld-status-unlocked ld-tertiary-background">
						<span class="ld-icon ld-icon-unlocked"></span>' . esc_html( $settings['lesson_locked_text'] ) . '
					</span>
				</span>';
			}
		}
	}

	/**
	 * Remove standard "Apply to children" field from meta box
	 *
	 * @access public
	 * @return void
	 */

	public function configure_meta_box() {

		global $post;

		if ( empty( $post ) ) {
			return;
		}

		if ( $post->post_type == 'sfwd-courses' || $post->post_type == 'sfwd-lessons' || $post->post_type == 'sfwd-topic' ) {
			remove_action( 'wpf_meta_box_content', 'apply_to_children', 35 );
		}

	}

	/**
	 * Adds notice about inherited rules
	 *
	 * @access public
	 * @return void
	 */

	public function meta_box_notice( $post, $settings ) {

		if ( 'sfwd-lessons' != $post->post_type && 'sfwd-topic' != $post->post_type && 'sfwd-quiz' != $post->post_type ) {
			return;
		}

		$parent_settings = false;

		$lesson_id = learndash_get_lesson_id( $post->ID );

		if ( ! empty( $lesson_id ) ) {
			$parent_settings = get_post_meta( $lesson_id, 'wpf-settings', true );
		}

		if ( empty( $parent_settings ) || empty( $parent_settings['lock_content'] ) ) {

			// Maybe try the course

			$course_id       = learndash_get_course_id( $post->ID );
			$parent_settings = get_post_meta( $course_id, 'wpf-settings', true );

		}

		if ( ! empty( $parent_settings ) && ! empty( $parent_settings['lock_content'] ) ) {

			$post_type_object = get_post_type_object( $post->post_type );

			echo '<div class="wpf-metabox-notice">';

			if ( isset( $course_id ) ) {
				printf( __( 'If no access rules are specified here, this %1$s will inherit permissions from the course %2$s.', 'wp-fusion' ), strtolower( $post_type_object->labels->singular_name ), '<a href="' . get_edit_post_link( $course_id ) . '">' . get_the_title( $course_id ) . '</a>' );
			} elseif ( $lesson_id !== $post->ID ) {
				printf( __( 'If no access rules are specified here, this %1$s will inherit permissions from the lesson %2$s.', 'wp-fusion' ), strtolower( $post_type_object->labels->singular_name ), '<a href="' . get_edit_post_link( $lesson_id ) . '">' . get_the_title( $lesson_id ) . '</a>' );
			}

			$required_tags = array();

			if ( ! empty( $parent_settings['allow_tags'] ) ) {
				$required_tags = array_merge( $required_tags, $parent_settings['allow_tags'] );
			}

			if ( ! empty( $parent_settings['allow_tags_all'] ) ) {
				$required_tags = array_merge( $required_tags, $parent_settings['allow_tags_all'] );
			}

			if ( ! empty( $required_tags ) ) {

				$required_tags = array_map( array( wp_fusion()->user, 'get_tag_label' ), $required_tags );

				echo '<span class="notice-required-tags">' . sprintf( __( '(Required tag(s): %s)', 'wp-fusion' ), implode( ', ', $required_tags ) ) . '</span>';
			}

			echo '</div>';

		}

	}


	/**
	 * Adds LearnDash fields to WPF meta box
	 *
	 * @access public
	 * @return void
	 */

	public function meta_box_content( $post, $settings ) {

		if ( $post->post_type != 'sfwd-courses' && $post->post_type != 'sfwd-lessons' && $post->post_type != 'sfwd-topic' && $post->post_type != 'sfwd-quiz' ) {
			return;
		}

		$defaults = array(
			'apply_tags_ld'                 => array(),
			'apply_tags_ld_essay_submitted' => array(),
			'apply_tags_ld_quiz_fail'       => array(),
			'final_score_field'             => array( 'crm_field' => false ),
			'final_points_field'            => array( 'crm_field' => false ),
		);

		$settings = array_merge( $defaults, $settings );

		echo '<p><label for="wpf-apply-tags-ld"><small>';

		if ( 'sfwd-quiz' == $post->post_type ) {
			_e( 'Apply these tags when quiz passed', 'wp-fusion' ); // quizzes
		} else {
			_e( 'Apply these tags when marked complete', 'wp-fusion' ); // everything else
		}

		echo ':</small></label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_ld'],
				'meta_name' => 'wpf-settings',
				'field_id'  => 'apply_tags_ld',
			)
		);

		echo '</p>';

		if ( 'sfwd-quiz' == $post->post_type ) {

			echo '<p><label for="wpf-apply-tags-ld-quiz-fail"><small>' . __( 'Apply these tags when essay submitted', 'wp-fusion' ) . ':</small></label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags_ld_essay_submitted'],
					'meta_name' => 'wpf-settings',
					'field_id'  => 'apply_tags_ld_essay_submitted',
				)
			);

			echo '</p>';

			echo '<p><label for="wpf-apply-tags-ld-quiz-fail"><small>' . __( 'Apply these tags when quiz failed', 'wp-fusion' ) . ':</small></label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags_ld_quiz_fail'],
					'meta_name' => 'wpf-settings',
					'field_id'  => 'apply_tags_ld_quiz_fail',
				)
			);

			echo '</p>';

			// Add final score field
			echo '<p><label for="final_score_field"><small>' . sprintf( __( 'Sync final score for this quiz to a custom field in %s', 'wp-fusion' ), wp_fusion()->crm->name ) . ':</small></label>';

			wpf_render_crm_field_select( $settings['final_score_field']['crm_field'], 'wpf-settings', 'final_score_field' );

			echo '</p>';

			// Add final points field
			echo '<p><label for="final_score_field"><small>' . sprintf( __( 'Sync final points for this quiz to a custom field in %s', 'wp-fusion' ), wp_fusion()->crm->name ) . ':</small></label>';

			wpf_render_crm_field_select( $settings['final_points_field']['crm_field'], 'wpf-settings', 'final_points_field' );

			echo '</p>';

		}

	}


	/**
	 * Adds meta boxes
	 *
	 * @access public
	 * @return mixed
	 */

	public function add_meta_box( $post_id, $data ) {

		$admin_permissions = wpf_get_option( 'admin_permissions' );

		if ( true == $admin_permissions && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_meta_box( 'wpf-learndash-meta', __( 'WP Fusion - Question Settings', 'wp-fusion' ), array( $this, 'meta_box_callback_question' ), 'sfwd-question' );

	}

	/**
	 * Add metabox tab to LD course page.
	 *
	 * @since  3.38.28
	 *
	 * @param  array $tabs   The tabs.
	 * @return array Tabs
	 */
	public function add_metabox_tab( $tabs ) {
		$screen = get_current_screen();

		if ( 'sfwd-courses' !== $screen->id ) {
			return $tabs;
		}

		if ( ( isset( $_GET['post'] ) && isset( $_GET['action'] ) ) || isset( $_GET['post_type'] ) && $_GET['post_type'] === 'sfwd-courses' ) {
			$tabs[] = array(
				'id'                  => 'wp-fusion-settings',
				'name'                => __( 'WP Fusion', 'wp-fusion' ),
				'metaboxes'           => array( 'learndash-course-wpf' ),
				'showDocumentSidebar' => 'false',
			);
		}
		return $tabs;
	}

	/**
	 * Require LearnDash metabox file.
	 *
	 * @since 3.38.28
	 */
	public function register_metabox() {
		require_once WPF_DIR_PATH . 'includes/integrations/class-learndash-metabox-course-settings.php';
	}

	/**
	 * Displays progress field on the course settings panel.
	 *
	 * @since 3.38.16
	 *
	 * @param array $field_args The field arguments.
	 * @return mixed HTML Output.
	 */
	public function display_crm_field_dropdown( $field_args ) {

		$settings = get_post_meta( get_the_ID(), 'wpf-settings-learndash', true );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		if ( ! isset( $settings[ $field_args['name'] ] ) ) {
			$settings[ $field_args['name'] ] = array( 'crm_field' => false );
		}

		wpf_render_crm_field_select( $settings[ $field_args['name'] ]['crm_field'], 'wpf-settings-learndash', $field_args['name'] );

		echo '<p style="margin-top:5px;" class="description">' . esc_html( $field_args['desc'] ) . '</p>';

	}

	/**
	 * Display tags select input for assignment upload setting
	 *
	 * @access public
	 * @return mixed HTML output
	 */

	public function display_wpf_tags_select( $field_args ) {

		global $post;

		$settings = get_post_meta( $post->ID, 'wpf-settings-learndash', true );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		if ( ! isset( $settings[ $field_args['name'] ] ) ) {
			$settings[ $field_args['name'] ] = array();
		}

		$args = array(
			'setting'   => $settings[ $field_args['name'] ],
			'meta_name' => 'wpf-settings-learndash',
			'field_id'  => $field_args['name'],
		);

		if ( isset( $field_args['limit'] ) ) {
			$args['limit'] = $field_args['limit'];
		}

		if ( 'apply_tags_enrolled' == $field_args['name'] ) {
			$args['no_dupes'] = array( 'wpf-settings-learndash-tag_link' );
		} elseif ( 'tag_link' == $field_args['name'] ) {
			$args['no_dupes'] = array( 'wpf-settings-learndash-apply_tags_enrolled' );
		}

		wpf_render_tag_multiselect( $args );

		echo '<p style="margin-top:5px;" class="description">' . $field_args['desc'] . '</p>';

	}

	/**
	 * Displays meta box content (question)
	 *
	 * @access public
	 * @return mixed HTML Output
	 */

	public function meta_box_callback_question( $post ) {

		wp_nonce_field( 'wpf_meta_box_learndash', 'wpf_meta_box_learndash_nonce' );

		$settings = array(
			'crm_field' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings-learndash', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings-learndash', true ) );
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">' . __( 'Sync to field', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		wpf_render_crm_field_select( $settings['crm_field'], 'wpf-settings-learndash' );

		echo '<span class="description">' . sprintf( __( 'Sync answers to this question the selected custom field in %s.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

	}


	/**
	 * Registers LD course fields
	 *
	 * @access public
	 * @return array Fields
	 */

	public function course_settings_fields( $fields, $metabox_key ) {
		$admin_permissions = wpf_get_option( 'admin_permissions' );

		if ( true == $admin_permissions && ! current_user_can( 'manage_options' ) ) {
			return $fields;
		}

		$settings = array(
			'apply_tags_enrolled' => array(),
			'tag_link'            => array(),
			'lesson_locked_text'  => __( 'Not Available', 'wp-fusion' ),
			'step_display'        => false,
		);

		global $post;

		if ( is_object( $post ) ) {
			$settings = wp_parse_args( get_post_meta( $post->ID, 'wpf-settings-learndash', true ), $settings );
		}

		// Migrate settings.
		if ( empty( $settings['step_display'] ) ) {

			if ( ! empty( $settings['lock_lessons'] ) ) {
				$settings['step_display'] = 'lock_lessons';
			}

			if ( ! empty( $settings['filter_steps'] ) ) {
				$settings['step_display'] = 'filter_steps';
			}
		}

		$filter_steps_subfields = array(
			'lesson_locked_text' => array(
				'name' => 'lesson_locked_text',
				'id'   => 'learndash-course-access-settings_course_step_display_lesson_locked_text',
				'args' => array(
					'id'               => 'learndash-course-access-settings_course_step_display_lesson_locked_text',
					'label_for'        => 'lesson_locked_text',
					'name'             => 'learndash-course-wpf[lesson_locked_text]',
					'label'            => sprintf( __( 'Locked %s Text', 'wp-fusion' ), LearnDash_Custom_Label::get_label( 'lesson' ) ),
					'type'             => 'text',
					'class'            => 'full-text',
					'default'          => __( 'Not Available', 'wp-fusion' ),
					'value'            => $settings['lesson_locked_text'],
					'help_text'        => sprintf( __( 'Enter a message to be displayed on locked %s.', 'wp-fusion' ), learndash_get_custom_label_lower( 'lessons' ) ),
					'input_show'       => true,
					'display_callback' => LearnDash_Settings_Fields::get_field_instance( 'text' )->get_creation_function_ref(),
				),
			),
		);

		$new_options = array(
			'apply_tags_enrolled' => array(
				'name'             => 'apply_tags_enrolled',
				'label'            => __( 'Apply Tags - Enrolled', 'wp-fusion' ),
				'type'             => 'multiselect',
				'multiple'         => 'true',
				'display_callback' => array( $this, 'display_wpf_tags_select' ),
				'desc'             => sprintf( __( 'These tags will be applied in %s when someone is enrolled in this course.', 'wp-fusion' ), wp_fusion()->crm->name ),
				'help_text'        => sprintf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/learning-management/learndash/#course-specific-settings" target="_blank">', '</a>' ),
			),
			'tag_link'            => array(
				'name'             => 'tag_link',
				'label'            => __( 'Link with Tag', 'wp-fusion' ),
				'type'             => 'multiselect',
				'multiple'         => 'true',
				'display_callback' => array( $this, 'display_wpf_tags_select' ),
				'desc'             => sprintf( __( 'This tag will be applied in %1$s when a user is enrolled, and will be removed when a user is unenrolled. Likewise, if this tag is applied to a user from within %2$s, they will be automatically enrolled in this course. If this tag is removed, the user will be removed from the course.', 'wp-fusion' ), wp_fusion()->crm->name, wp_fusion()->crm->name ),
				'limit'            => 1,
				'help_text'        => sprintf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/learning-management/learndash/#course-specific-settings" target="_blank">', '</a>' ),
			),
			'step_display'        => array(
				'name'    => 'step_display',
				'label'   => sprintf( __( 'WP Fusion - %s Navigation', 'wp-fusion' ), LearnDash_Custom_Label::get_label( 'course' ) ),
				'type'    => 'radio',
				'value'   => $settings['step_display'],
				'default' => 'default',
				'options' => array(
					'default'      => array(
						'label'       => esc_html__( 'Default', 'wp-fusion' ),
						'description' => sprintf(
							// translators: placeholder: course.
							esc_html_x( 'The %1$s navigation will show all content, regardless of of the user\'s %2$s tags.', 'placeholder: course, crm name ', 'wp-fusion' ),
							learndash_get_custom_label_lower( 'course' ),
							wp_fusion()->crm->name
						),
					),
					'lock_lessons' => array(
						'label'               => sprintf( __( 'Lock %s', 'wp-fusion' ), learndash_get_custom_label_lower( 'lessons' ) ),
						'description'         => sprintf(
							// translators: placeholder: course.
							esc_html_x( 'Content that a user cannot access will show as disabled in the %s navigation.', 'placeholder: course', 'wp-fusion' ),
							learndash_get_custom_label_lower( 'course' )
						),
						'inline_fields'       => array(
							'step_display_lock_lessons' => $filter_steps_subfields,
						),
						'inner_section_state' => ( 'lock_lessons' === $settings['step_display'] ) ? 'open' : 'closed',
					),
					'filter_steps' => array(
						'label'       => sprintf( __( 'Filter %s steps', 'wp-fusion' ), learndash_get_custom_label_lower( 'course' ) ),
						'description' => sprintf(
							// translators: placeholder: course, course.
							esc_html_x( '%1$s, topics, and quizzes that a user doesn\'t have access to will be removed from the %2$s navigation, and won\'t be required for course completion.', 'placeholder: lessons, course', 'wp-fusion' ),
							LearnDash_Custom_Label::get_label( 'lessons' ),
							learndash_get_custom_label_lower( 'course' )
						),
					),
				),
			),
			'progress_field'      => array(
				'name'             => 'progress_field',
				'label'            => __( 'WP Fusion - Course Progress', 'wp-fusion' ),
				'type'             => 'select',
				'display_callback' => array( $this, 'display_crm_field_dropdown' ),
				'desc'             => sprintf( __( 'As the user progresses through the course, their course completion percentage will be synced to the selected custom field in %s.', 'wp-fusion' ), wp_fusion()->crm->name ),
			),
		);

		// Warning if course is open and a linked tag is set

		if ( is_object( $post ) ) {

			if ( ! empty( $settings['tag_link'] ) ) {

				$course_settings = get_post_meta( $post->ID, '_sfwd-courses', true );

				if ( ! empty( $course_settings ) && isset( $course_settings['sfwd-courses_course_price_type'] ) ) {

					if ( 'free' == $course_settings['sfwd-courses_course_price_type'] || 'open' == $course_settings['sfwd-courses_course_price_type'] ) {

						$new_options['tag_link']['desc'] .= '<br /><br/><div class="ld-settings-info-banner ld-settings-info-banner-alert"><p>' . sprintf( __( '<strong>Note:</strong> Your course Access Mode is currently set to <strong>%s</strong>, for auto-enrollments to work correctly your course Access Mode should be set to "closed".', 'wp-fusion' ), $course_settings['sfwd-courses_course_price_type'] ) . '</p></div>';

					}
				}
			}
		}

		// Warning if LD - Woo plugin is active

		if ( class_exists( 'Learndash_WooCommerce' ) ) {

			$new_options['tag_link']['desc'] .= '<br /><br/><div class="ld-settings-info-banner ld-settings-info-banner-alert"><p>';
			$new_options['tag_link']['desc'] .= __( '<strong>Warning:</strong> The <strong>LearnDash - WooCommerce</strong> plugin is active. If access to this course is managed by that plugin, you should <em>not</em> use the Link With Tag setting, as it will cause your students to become unenrolled from the course when their renewal payments are processed.', 'wp-fusion' );
			$new_options['tag_link']['desc'] .= '</p></div>';

		}

		$fields = wp_fusion()->settings->insert_setting_after( 'course_access_list', $fields, $new_options );

		return $fields;

	}



	/**
	 * Registers LD group fields
	 *
	 * @access public
	 * @return array Fields
	 */

	public function group_settings_fields( $fields, $metabox_key ) {

		if ( 'learndash-group-access-settings' == $metabox_key ) {

			$admin_permissions = wpf_get_option( 'admin_permissions' );

			if ( true == $admin_permissions && ! current_user_can( 'manage_options' ) ) {
				return $fields;
			}

			$new_options = array(
				'apply_tags_enrolled' => array(
					'name'             => 'apply_tags_enrolled',
					'label'            => __( 'Apply Tags - Enrolled', 'wp-fusion' ),
					'type'             => 'multiselect',
					'multiple'         => 'true',
					'display_callback' => array( $this, 'display_wpf_tags_select' ),
					'desc'             => sprintf( __( 'These tags will be applied in %s when someone is enrolled in this group.', 'wp-fusion' ), wp_fusion()->crm->name ),
					'help_text'        => sprintf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/learning-management/learndash/#groups" target="_blank">', '</a>' ),
				),
				'tag_link'            => array(
					'name'             => 'tag_link',
					'label'            => __( 'Link with Tag', 'wp-fusion' ),
					'type'             => 'multiselect',
					'multiple'         => 'true',
					'display_callback' => array( $this, 'display_wpf_tags_select' ),
					'desc'             => sprintf( __( 'This tag will be applied in %1$s when a user is enrolled, and will be removed when a user is unenrolled. Likewise, if this tag is applied to a user from within %2$s, they will be automatically enrolled in this group. If this tag is removed, the user will be removed from the group.', 'wp-fusion' ), wp_fusion()->crm->name, wp_fusion()->crm->name ),
					'limit'            => 1,
					'help_text'        => sprintf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/learning-management/learndash/#groups" target="_blank">', '</a>' ),
				),
				'leader_tag'          => array(
					'name'             => 'leader_tag',
					'label'            => __( 'Link with Tag - Group Leader', 'wp-fusion' ),
					'type'             => 'multiselect',
					'multiple'         => 'true',
					'display_callback' => array( $this, 'display_wpf_tags_select' ),
					'desc'             => sprintf( __( 'This tag will be applied in %1$s when a group leader is assigned, and will be removed when a group leader is removed. Likewise, if this tag is applied to a user from within %2$s, they will be automatically assigned as the leader of this group. If this tag is removed, the user will be removed from leadership of the group.', 'wp-fusion' ), wp_fusion()->crm->name, wp_fusion()->crm->name ),
					'limit'            => 1,
					'help_text'        => sprintf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/learning-management/learndash/#groups" target="_blank">', '</a>' ),
				),
			);

			// Warning if LD - Woo plugin is active

			if ( class_exists( 'Learndash_WooCommerce' ) ) {

				$new_options['tag_link']['desc'] .= '<br /><br/><div class="ld-settings-info-banner ld-settings-info-banner-alert"><p>';
				$new_options['tag_link']['desc'] .= __( '<strong>Warning:</strong> The <strong>LearnDash - WooCommerce</strong> plugin is active. If access to this group is managed by that plugin, you should <em>not</em> use the Link With Tag setting, as it will cause your students to become unenrolled from the course when their renewal payments are processed.', 'wp-fusion' );
				$new_options['tag_link']['desc'] .= '</p></div>';

			}

			$fields = $fields + $new_options;

		}

		return $fields;

	}


	/**
	 * Adds WPF settings to assignment upload section in lesson settings
	 *
	 * @access public
	 * @return array Options Fields
	 */

	public function lesson_settings_fields( $options_fields, $metabox_key ) {

		if ( 'learndash-lesson-display-content-settings' == $metabox_key || 'learndash-topic-display-content-settings' == $metabox_key ) {

			$new_options = array(
				'apply_tags_assignment_upload' => array(
					'name'             => 'apply_tags_assignment_upload',
					'label'            => esc_html__( 'Apply Tags', 'learndash' ),
					'type'             => 'multiselect',
					'multiple'         => 'true',
					'display_callback' => array( $this, 'display_wpf_tags_select' ),
					'parent_setting'   => 'lesson_assignment_upload',
					'desc'             => sprintf( __( 'Select tags to be applied to the student in %s when an assigment is uploaded.', 'wp-fusion' ), wp_fusion()->crm->name ),
				),
			);

			$options_fields = wp_fusion()->settings->insert_setting_after( 'assignment_upload_limit_size', $options_fields, $new_options );

		}

		return $options_fields;

	}



	/**
	 * Show post access controls in the posts table
	 *
	 * @access public
	 * @return array Post States
	 */

	public function admin_table_post_states( $post_states, $post ) {

		if ( ! is_object( $post ) ) {
			return $post_states;
		}

		if ( 'sfwd-courses' != $post->post_type && 'groups' != $post->post_type ) {
			return $post_states;
		}

		$wpf_settings = get_post_meta( $post->ID, 'wpf-settings-learndash', true );

		if ( ! empty( $wpf_settings ) && ! empty( $wpf_settings['tag_link'] ) ) {

			$post_type_object = get_post_type_object( $post->post_type );

			$content = sprintf( __( 'This %1$s is linked for auto-enrollment with %2$s tag: ', 'wp-fusion' ), strtolower( $post_type_object->labels->singular_name ), wp_fusion()->crm->name );

			$content .= '<strong>' . wpf_get_tag_label( $wpf_settings['tag_link'][0] ) . '</strong>';

			$post_states['wpf_learndash'] = '<span class="dashicons dashicons-admin-links wpf-tip wpf-tip-bottom" data-tip="' . $content . '"></span>';

		}

		return $post_states;

	}

	/**
	 * Runs when WPF meta box is saved on a course, lesson, or question
	 *
	 * @access public
	 * @return void
	 */

	public function save_meta_box_data( $post_id ) {

		if ( empty( $_POST['post_type'] ) || ! in_array( $_POST['post_type'], array( 'sfwd-courses', 'groups', 'sfwd-question', 'sfwd-topic', 'sfwd-lessons' ) ) ) {
			return;
		}

		// As of LD 3.2.2 this runs on every lesson in the builder when the course is saved, so we'll check for that here to avoid having the lesson settings overwritten by the course
		if ( $_POST['post_ID'] != $post_id ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$data = array();

		if ( ! empty( $_POST['wpf-settings-learndash'] ) ) {
			$data = $_POST['wpf-settings-learndash'];
		}

		// Some special course fields

		if ( ! empty( $_POST['learndash-course-wpf'] ) ) {

			if ( isset( $_POST['learndash-course-wpf']['step_display'] ) ) {
				$data['step_display'] = $_POST['learndash-course-wpf']['step_display'];
				unset( $_POST['learndash-course-wpf']['step_display'] );
			}

			if ( isset( $_POST['learndash-course-wpf']['lesson_locked_text'] ) ) {
				$data['lesson_locked_text'] = sanitize_text_field( $_POST['learndash-course-wpf']['lesson_locked_text'] );
				unset( $_POST['learndash-course-wpf']['lesson_locked_text'] );
			}
		}

		if ( ! empty( $data ) ) {

			$data = WPF_Admin_Interfaces::sanitize_tags_settings( $data );
			update_post_meta( $post_id, 'wpf-settings-learndash', $data );

		} else {
			delete_post_meta( $post_id, 'wpf-settings-learndash' );
		}

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
				'post_type'  => 'sfwd-courses',
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf-settings-learndash',
						'compare' => 'EXISTS',
					),
				),
				'fields'     => 'ids',
			)
		);

		// Update course access based on user tags
		if ( ! empty( $linked_courses ) ) {

			$user_tags = wp_fusion()->user->get_tags( $user_id ); // Get them here for cases where the tags might have changed since wpf_tags_modified was triggered

			// See if user is enrolled
			$enrolled_courses = learndash_user_get_enrolled_courses( $user_id, array() );

			// We won't look at courses a user is in because of a group
			$groups_courses = learndash_get_user_groups_courses_ids( $user_id );

			// Don't bother with open courses since users are enrolled in them by default
			$open_courses = learndash_get_open_courses();

			$enrolled_courses = array_diff( $enrolled_courses, $open_courses );

			foreach ( $linked_courses as $course_id ) {

				$settings = get_post_meta( $course_id, 'wpf-settings-learndash', true );

				if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
					continue;
				}

				$tag_id = $settings['tag_link'][0];

				if ( in_array( $course_id, $enrolled_courses ) ) {
					$is_enrolled = true;
				} else {
					$is_enrolled = false;
				}

				if ( in_array( $tag_id, $user_tags ) && ! $is_enrolled && ! user_can( $user_id, 'manage_options' ) ) {

					if ( in_array( $course_id, $groups_courses ) ) {

						// We can't add someone to a course that they already have access to as part of a group

						wpf_log( 'notice', $user_id, 'User could not be auto-enrolled in LearnDash course <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $course_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong> because they already have access to that course as part of a LearnDash group.', array( 'source' => 'learndash' ) );
						continue;

					}

					// Logger
					wpf_log( 'info', $user_id, 'User auto-enrolled in LearnDash course <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $course_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'learndash' ) );

					ld_update_course_access( $user_id, $course_id, $remove = false );

				} elseif ( ! in_array( $tag_id, $user_tags ) && $is_enrolled && ! user_can( $user_id, 'manage_options' ) ) {

					if ( in_array( $course_id, $groups_courses ) ) {

						// We can't add someone to a course that they already have access to as part of a group

						wpf_log( 'notice', $user_id, 'User could not be un-enrolled from LearnDash course <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $course_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong> because they have access to that course as part a LearnDash group.', array( 'source' => 'learndash' ) );
						continue;

					}

					// Logger
					wpf_log( 'info', $user_id, 'User un-enrolled from LearnDash course <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $course_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'learndash' ) );

					ld_update_course_access( $user_id, $course_id, $remove = true );

				}
			}
		}

	}

	/**
	 * Update user group enrollment when tags are modified
	 *
	 * @access public
	 * @return void
	 */

	public function update_group_access( $user_id, $user_tags ) {

		// Possibly update groups
		$linked_groups = get_posts(
			array(
				'post_type'  => 'groups',
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf-settings-learndash',
						'compare' => 'EXISTS',
					),
				),
				'fields'     => 'ids',
			)
		);

		$updated = false;

		if ( ! empty( $linked_groups ) ) {

			$user_tags = wp_fusion()->user->get_tags( $user_id ); // Get them here for cases where the tags might have changed since wpf_tags_modified was triggered

			foreach ( $linked_groups as $group_id ) {

				$settings = get_post_meta( $group_id, 'wpf-settings-learndash', true );

				if ( empty( $settings ) ) {
					continue;
				}

				if ( ! empty( $settings['tag_link'] ) ) {

					// Group member auto-enrollment

					$tag_id = $settings['tag_link'][0];

					if ( in_array( $tag_id, $user_tags ) && learndash_is_user_in_group( $user_id, $group_id ) == false ) {

						wpf_log( 'info', $user_id, 'User added to LearnDash group <a href="' . admin_url( 'post.php?post=' . $group_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $group_id ) . '</a> by tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

						// Prevent looping.
						remove_action( 'ld_added_group_access', array( $this, 'added_group_access' ), 10, 2 );

						ld_update_group_access( $user_id, $group_id, $remove = false );

						add_action( 'ld_added_group_access', array( $this, 'added_group_access' ), 10, 2 );

						$updated = true;

					} elseif ( ! in_array( $tag_id, $user_tags ) && learndash_is_user_in_group( $user_id, $group_id ) != false ) {

						wpf_log( 'info', $user_id, 'User removed from LearnDash group <a href="' . admin_url( 'post.php?post=' . $group_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $group_id ) . '</a> by tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

						// Prevent looping.
						remove_action( 'ld_removed_group_access', array( $this, 'removed_group_access' ), 10, 2 );

						ld_update_group_access( $user_id, $group_id, $remove = true );

						add_action( 'ld_removed_group_access', array( $this, 'removed_group_access' ), 10, 2 );

						$updated = true;

					}
				}

				if ( ! empty( $settings['leader_tag'] ) ) {

					// Group leader auto-enrollment

					$tag_id = $settings['leader_tag'][0];

					// Get list of group leader IDs - so we can check later if the user is a leader in the group
					// and we need to remove the user from the leader of that group accordingly.

					$group_leader_ids = learndash_get_groups_administrator_ids( $group_id, $bypass_transient = true );

					if ( in_array( $tag_id, $user_tags ) && ! in_array( $user_id, $group_leader_ids ) ) {

						wpf_log( 'info', $user_id, 'User added as leader to LearnDash group <a href="' . admin_url( 'post.php?post=' . $group_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $group_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

						// Prevent looping.
						remove_action( 'ld_added_leader_group_access', array( $this, 'added_group_leader_access' ), 10, 2 );

						ld_update_leader_group_access( $user_id, $group_id, $remove = false );

						add_action( 'ld_added_leader_group_access', array( $this, 'added_group_leader_access' ), 10, 2 );

					} elseif ( ! in_array( $tag_id, $user_tags ) && in_array( $user_id, $group_leader_ids ) ) {

						wpf_log( 'info', $user_id, 'User removed as leader from LearnDash group <a href="' . admin_url( 'post.php?post=' . $group_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $group_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

						remove_action( 'ld_removed_leader_group_access', array( $this, 'removed_group_leader_access' ), 10, 2 );

						ld_update_leader_group_access( $user_id, $group_id, $remove = true );

						add_action( 'ld_removed_leader_group_access', array( $this, 'removed_group_leader_access' ), 10, 2 );

					}
				}
			}
		}

		// Clear the courses / groups transients

		if ( $updated ) {

			delete_transient( 'learndash_user_courses_' . $user_id );
			delete_transient( 'learndash_user_groups_' . $user_id );

		}

	}

	/**
	 * Don't apply tags on view when a LD-restricted lesson is viewed
	 *
	 * @access public
	 * @return bool Proceed
	 */

	public function maybe_stop_apply_tags_on_view( $proceed, $post_id ) {

		if ( get_post_type( $post_id ) == 'sfwd-lessons' ) {

			$access_from = ld_lesson_access_from( $post_id, wpf_get_current_user_id() );

			if ( $access_from > time() ) {
				$proceed = false;
			}
		}

		return $proceed;

	}

	/**
	 * LearnDash lessons and topics should inherit permissions from the parent course
	 *
	 * @access public
	 * @return array Access Meta
	 */

	public function inherit_permissions_from_course( $access_meta, $post_id ) {

		if ( empty( $access_meta ) || empty( $access_meta['lock_content'] ) ) {

			$post_type = get_post_type( $post_id );

			if ( 'sfwd-lessons' == $post_type || 'sfwd-topic' == $post_type || 'sfwd-quiz' == $post_type ) {

				$parent_settings = false;

				// Inherit the settings from the parent lesson, for quizzes and topics

				if ( 'sfwd-lessons' !== $post_type ) {

					$lesson_id = learndash_get_lesson_id( $post_id );

					if ( ! empty( $lesson_id ) ) {
						$parent_settings = get_post_meta( $lesson_id, 'wpf-settings', true );
					}
				}

				if ( empty( $parent_settings ) || empty( $parent_settings['lock_content'] ) ) {

					// Maybe try the course

					$course_id       = learndash_get_course_id( $post_id );
					$parent_settings = get_post_meta( $course_id, 'wpf-settings', true );

				}

				if ( ! empty( $parent_settings ) ) {
					$access_meta = $parent_settings;
				}
			}
		}

		return $access_meta;

	}

	/**
	 * Runs after a new user is inserted by ThriveCart, syncs the generated
	 * password to the CRM.
	 *
	 * @since 3.36.8
	 *
	 * @param ID          $user_id  The new user ID.
	 * @param array       $customer The ThriveCart customer data.
	 * @param string|bool $password The generated password.
	 */
	public function thrivecart_after_create_user( $user_id, $customer, $password = false ) {

		if ( false !== $password ) {

			$password_field = wpf_get_option( 'return_password_field', array() );

			if ( wpf_get_option( 'return_password' ) == true && ! empty( $password_field['crm_field'] ) ) {

				wpf_log( 'info', $user_id, 'Syncing LearnDash-generated password <strong>' . $password . '</strong>' );

				$update_data = array(
					$password_field['crm_field'] => $password,
				);

				$contact_id = wpf_get_contact_id( $user_id );
				$result     = wp_fusion()->crm->update_contact( $contact_id, $update_data, false );

			}
		}

	}

	/**
	 * Run WPF's redirects on restricted LD lessons instead of letting LD take them to the course, so our login redirects work
	 *
	 * @access public
	 * @return string Redirect Link
	 */

	public function lesson_access_redirect( $link, $lesson_id ) {

		$course_id = learndash_get_course_id( $lesson_id );

		if ( ! wp_fusion()->access->user_can_access( $course_id ) ) {

			$redirect = wp_fusion()->access->get_redirect( $course_id );

			if ( ! empty( $redirect ) ) {

				wp_fusion()->access->set_return_after_login( $lesson_id );

				wp_redirect( $redirect, 302, 'WP Fusion; Post ID ' . $lesson_id );
				exit();

			}
		}

		return $link;

	}


	/**
	 * Applies group link tag when user added to group
	 *
	 * @access public
	 * @return void
	 */

	public function added_group_access( $user_id, $group_id ) {

		$settings = get_post_meta( $group_id, 'wpf-settings-learndash', true );

		if ( empty( $settings ) ) {
			return;
		}

		$apply_tags = array();

		if ( ! empty( $settings['tag_link'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['tag_link'] );
		}

		if ( ! empty( $settings['apply_tags_enrolled'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_enrolled'] );
		}

		if ( ! empty( $apply_tags ) ) {

			// Prevent looping
			remove_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

			wpf_log( 'info', $user_id, 'User was enrolled in LearnDash group <a href="' . admin_url( 'post.php?post=' . $group_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $group_id ) . '</a>. Applying tags.' );

			wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		}

	}

	/**
	 * Removes group link tag when user removed from group
	 *
	 * @access public
	 * @return void
	 */

	public function removed_group_access( $user_id, $group_id ) {

		$settings = get_post_meta( $group_id, 'wpf-settings-learndash', true );

		if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
			return;
		}

		// Prevent looping
		remove_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		wpf_log( 'info', $user_id, 'User was un-enrolled from LearnDash group <a href="' . admin_url( 'post.php?post=' . $group_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $group_id ) . '</a>. Removing linked tag.' );

		wp_fusion()->user->remove_tags( $settings['tag_link'], $user_id );

		add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

	}

	/**
	 * Applies the linked tags when a user is added as a group leader.
	 *
	 * @since 3.36.8
	 *
	 * @param int $user_id  The user ID.
	 * @param int $group_id The group ID.
	 */
	public function added_group_leader_access( $user_id, $group_id ) {

		$settings = get_post_meta( $group_id, 'wpf-settings-learndash', true );

		if ( empty( $settings ) || empty( $settings['leader_tag'] ) ) {
			return;
		}

		// Prevent looping
		remove_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		wpf_log( 'info', $user_id, 'User was enrolled as group leader in LearnDash group <a href="' . admin_url( 'post.php?post=' . $group_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $group_id ) . '</a>. Applying linked tag.' );

		wp_fusion()->user->apply_tags( $settings['leader_tag'], $user_id );

		add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

	}

	/**
	 * Removes the linked tags when a user is added as a group leader.
	 *
	 * @since 3.36.8
	 *
	 * @param int $user_id  The user ID.
	 * @param int $group_id The group ID.
	 */

	public function removed_group_leader_access( $user_id, $group_id ) {

		$settings = get_post_meta( $group_id, 'wpf-settings-learndash', true );

		if ( empty( $settings ) || empty( $settings['leader_tag'] ) ) {
			return;
		}

		// Prevent looping
		remove_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		wpf_log( 'info', $user_id, 'User was removed as Leader from LearnDash group <a href="' . admin_url( 'post.php?post=' . $group_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $group_id ) . '</a>. Removing linked tag.' );

		wp_fusion()->user->remove_tags( $settings['leader_tag'], $user_id );

		add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

	}

	/**
	 * Applies / removes linked tags when user added to / removed from course
	 *
	 * @access public
	 * @return void
	 */

	public function updated_course_access( $user_id, $course_id, $access_list = array(), $remove = false ) {

		// Sync the name

		if ( false == $remove ) {
			update_user_meta( $user_id, 'ld_last_course_enrolled', get_the_title( $course_id ) );
		}

		// Apply the tags

		$settings = get_post_meta( $course_id, 'wpf-settings-learndash', true );

		if ( empty( $settings ) ) {
			return;
		}

		remove_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

		if ( false === $remove ) {

			$apply_tags = array();

			if ( ! empty( $settings['tag_link'] ) && ! doing_action( 'wpf_tags_modified' ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['tag_link'] );
			}

			if ( ! empty( $settings['apply_tags_enrolled'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_enrolled'] );
			}

			if ( ! empty( $apply_tags ) ) {

				$message = 'User was enrolled in LearnDash course <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $course_id ) . '</a>';

				// Safety check to see if this was triggered by the LD + Woo plugin.

				$ld_woo_courses = get_user_meta( $user_id, '_learndash_woocommerce_enrolled_courses_access_counter', true );

				if ( ! empty( $ld_woo_courses ) && isset( $ld_woo_courses[ $course_id ] ) ) {
					$message .= ' by the <strong>LearnDash - WooCommerce plugin</strong>';
				}

				wpf_log( 'info', $user_id, $message . '. Applying tags.' );

				wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			}
		} elseif ( ! empty( $settings['tag_link'] ) && ! doing_action( 'wpf_tags_modified' ) ) {

			wpf_log( 'info', $user_id, 'User was unenrolled from LearnDash course <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $course_id ) . '</a>. Removing linked tag.' );

			wp_fusion()->user->remove_tags( $settings['tag_link'], $user_id );

		}

		add_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

	}

	/**
	 * Adds randomly generates passwords to POST data so it can be picked up by user_register()
	 *
	 * @access public
	 * @return string Password
	 */

	public function push_password( $password ) {

		if ( ! empty( $_POST ) ) {
			$_POST['user_pass'] = $password;
		}

		return $password;

	}

	/**
	 * If the LearnDash for WooCommerce plugin is triggering an enrollment, make
	 * sure it's recorded in the WPF logs.
	 *
	 * @since 3.38.17
	 *
	 * @param int    $meta_id     The meta ID.
	 * @param int    $user_id     The user ID.
	 * @param string $meta_key    The meta key.
	 * @param mixes  $_meta_value The meta value.
	 */
	public function maybe_add_learndash_woocommerce_plugin_source( $meta_id, $user_id, $meta_key, $_meta_value ) {

		if ( '_learndash_woocommerce_enrolled_courses_access_counter' === $meta_key ) {
			wp_fusion()->logger->add_source( 'learndash-woocommerce' );
		}

	}

	/**
	 * Uncanny Toolkit Pro compatibility.
	 *
	 * The autocomplete lessons module in Uncanny Toolkit Pro runs at shutdown
	 * on priority 10, which means any tags to be applied aren't picked up by
	 * the WPF queue (which runs on priority 1).
	 *
	 * This adds a new shutdown handler at priority 15 to pick up any tags that
	 * were queued up by autocompleted lessons.
	 *
	 * @since 3.38.23
	 *
	 * @see   \uncanny_pro_toolkit\LessonTopicAutoComplete
	 * @see   WPF_CRM_Base::shutdown
	 */
	public function uncanny_toolkit_pro_compatibility() {

		if ( class_exists( '\uncanny_pro_toolkit\LessonTopicAutoComplete' ) && method_exists( wp_fusion()->crm, 'shutdown' ) ) {
			add_action( 'shutdown', array( wp_fusion()->crm, 'shutdown' ), 15 );
		}

	}

	/**
	 * Adds LearnDash field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['learndash_progress'] = array(
			'title'  => 'LearnDash Progress',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Adds LearnDash meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['ld_last_course_enrolled'] = array(
			'label' => 'Last Course Enrolled',
			'type'  => 'text',
			'group' => 'learndash_progress',
		);

		$meta_fields['ld_last_lesson_completed'] = array(
			'label' => 'Last Lesson Completed',
			'type'  => 'text',
			'group' => 'learndash_progress',
		);

		$meta_fields['ld_last_lesson_completed_date'] = array(
			'label' => 'Last Lesson Completed Date',
			'type'  => 'date',
			'group' => 'learndash_progress',
		);

		$meta_fields['ld_last_course_completed'] = array(
			'label' => 'Last Course Completed',
			'type'  => 'text',
			'group' => 'learndash_progress',
		);

		$meta_fields['ld_last_course_completed_date'] = array(
			'label' => 'Last Course Completed Date',
			'type'  => 'date',
			'group' => 'learndash_progress',
		);

		$meta_fields['ld_last_course_progressed'] = array(
			'label' => 'Last Course Progressed',
			'type'  => 'text',
			'group' => 'learndash_progress',
		);

		return $meta_fields;

	}

	/**
	 * Sets up last lesson / last course fields for automatic sync
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function watch_meta_fields( $meta_fields ) {

		$meta_fields[] = 'ld_last_lesson_completed';
		$meta_fields[] = 'ld_last_lesson_completed_date';
		$meta_fields[] = 'ld_last_course_completed';
		$meta_fields[] = 'ld_last_course_completed_date';
		$meta_fields[] = 'ld_last_course_enrolled';
		$meta_fields[] = 'ld_last_course_progressed';

		return $meta_fields;

	}


	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds LearnDash courses to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['learndash_courses'] = array(
			'label'   => __( 'LearnDash course enrollment statuses', 'wp-fusion' ),
			'title'   => __( 'Users', 'wp-fusion' ),
			'tooltip' => sprintf( __( 'For each user on your site, applies tags in %s based on their current LearnDash course enrollments, using the settings configured on each course. <br /><br />Note that this does not apply to course enrollments that have been granted via Groups. <br /><br />Note that this does not enroll or unenroll any users from courses, it just applies tags based on existing course enrollments.' ), wp_fusion()->crm->name ),
		);

		$options['learndash_progress'] = array(
			'label'   => __( 'LearnDash course progress', 'wp-fusion' ),
			'title'   => __( 'Users', 'wp-fusion' ),
			'tooltip' => sprintf( __( 'For each user on your site, applies tags in %s based on their current course progress, based on the <em>Apply tags when marked complete</em> settings on every course, lesson, topic, and quiz.' ), wp_fusion()->crm->name ),
		);

		$options['learndash_groups'] = array(
			'label'   => __( 'LearnDash group enrollment statuses', 'wp-fusion' ),
			'title'   => __( 'Users', 'wp-fusion' ),
			'tooltip' => sprintf( __( 'For each user on your site, applies tags in %s based on their current LearnDash group enrollments, using the settings configured on each group.<br /><br />Note that this does not enroll or unenroll any users from groups, it just applies tags based on existing group enrollments.' ), wp_fusion()->crm->name ),
		);

		return $options;

	}

	/**
	 * Gets users to be processed
	 *
	 * @access public
	 * @return int Count
	 */

	public function batch_init() {

		$args = array( 'fields' => 'ID' );

		$users = get_users( $args );

		return $users;

	}

	/**
	 * Process user enrollments one at a time
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step_courses( $user_id ) {

		// Get courses
		$enrolled_courses = learndash_user_get_enrolled_courses( $user_id, array() );

		// We won't look at courses a user is in because of a group
		$groups_courses = learndash_get_user_groups_courses_ids( $user_id );

		$enrolled_courses = array_diff( $enrolled_courses, $groups_courses );

		if ( ! empty( $enrolled_courses ) ) {

			foreach ( $enrolled_courses as $course_id ) {

				wpf_log( 'info', $user_id, 'Processing LearnDash course enrollment status for <a href="' . admin_url( 'post.php?post=' . $course_id . '&action=edit' ) . '">' . get_the_title( $course_id ) . '</a>' );

				$this->updated_course_access( $user_id, $course_id );

			}
		}
	}


	/**
	 * Apply tags for a single user's course progress.
	 *
	 * @since 3.37.12
	 *
	 * @param int $user_id The user identifier.
	 */
	public function batch_step_progress( $user_id ) {

		$enrolled_courses = learndash_user_get_enrolled_courses( $user_id, array() );

		$apply_tags = array();

		foreach ( $enrolled_courses as $course_id ) {

			$progress_all = learndash_user_get_course_progress( $user_id, $course_id, 'co' );
			$progress     = array_filter( $progress_all );

			// If the number of completed = the number of steps, we'll consider the course complete

			if ( $progress_all == $progress ) {

				$settings = get_post_meta( $course_id, 'wpf-settings', true );

				if ( ! empty( $settings ) && ! empty( $settings['apply_tags_ld'] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_ld'] );
				}
			}

			// Now get the settings from the individual lessons / topics / etc

			foreach ( $progress as $step => $completed ) {

				$step     = explode( ':', $step );
				$step_id  = $step[1];
				$settings = get_post_meta( $step_id, 'wpf-settings', true );

				if ( ! empty( $settings ) && ! empty( $settings['apply_tags_ld'] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_ld'] );
				}
			}
		}

		if ( ! empty( $apply_tags ) ) {

			wp_fusion()->user->apply_tags( $apply_tags, $user_id );

		}

	}

	/**
	 * Apply tags for a single user's group enrollments.
	 *
	 * @since 3.37.12
	 *
	 * @param int $user_id The user identifier.
	 */
	public function batch_step_groups( $user_id ) {

		$users_group_ids = learndash_get_users_group_ids( $user_id );

		foreach ( $users_group_ids as $group_id ) {

			wpf_log( 'info', $user_id, 'Processing LearnDash group enrollment status for <a href="' . admin_url( 'post.php?post=' . $group_id . '&action=edit' ) . '">' . get_the_title( $group_id ) . '</a>' );

			$this->added_group_access( $user_id, $group_id );

		}
	}

}

new WPF_LearnDash();
