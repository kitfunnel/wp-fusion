<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Courseware extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'courseware';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WP Courseware';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/wp-courseware/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'admin_menu', array( $this, 'admin_menu' ), 100 );
		add_action( 'wpf_meta_box_content', array( $this, 'meta_box_content' ), 40, 2 );

		add_filter( 'wpcw_unit_custom_columns', array( $this, 'bulk_edit_columns' ) );
		add_filter( 'wpcw_course_custom_columns', array( $this, 'bulk_edit_columns' ) );

		add_action( 'wpcw_user_completed_course', array( $this, 'completed_course' ), 10, 3 );
		add_action( 'wpcw_user_completed_module', array( $this, 'completed_module' ), 10, 3 );
		add_action( 'wpcw_user_completed_unit', array( $this, 'completed_unit' ), 10, 3 );

		add_action( 'wpcw_enroll_user', array( $this, 'enroll_user' ), 10, 2 );
		add_action( 'wpcw_unenroll_user', array( $this, 'unenroll_user' ), 10, 2 );
		add_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

	}

	/**
	 * Tags users upon course start.
	 *
	 * @access public
	 * @return void
	 */

	public function enroll_user( $user_id, $courses_enrolled ) {

		if ( empty( $courses_enrolled ) ) {
			return;
		}

		$wpf_settings = get_option( 'wpf_wpcw_settings', array() );

		foreach ( $courses_enrolled as $course_id ) {

			if ( ! empty( $wpf_settings[ $course_id ]['apply_tags_started'] ) ) {

				wp_fusion()->user->apply_tags( $wpf_settings[ $course_id ]['apply_tags_started'], $user_id );

			}

			if ( ! empty( $wpf_settings[ $course_id ]['tag_link'] ) ) {

				remove_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

				wp_fusion()->user->apply_tags( $wpf_settings[ $course_id ]['tag_link'], $user_id );

				add_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

			}
		}
	}

	/**
	 * Unenrolls Users.
	 *
	 * @access public
	 * @return void
	 */

	public function unenroll_user( $user_id, $courseIDsToRemove ) {

		if ( empty( $courseIDsToRemove ) ) {
			return;
		}

		$wpf_settings = get_option( 'wpf_wpcw_settings', array() );

		foreach ( $courseIDsToRemove as $course_id ) {

			if ( ! empty( $wpf_settings[ $course_id ]['tag_link'] ) ) {

				remove_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

				wp_fusion()->user->remove_tags( $wpf_settings[ $course_id ]['tag_link'], $user_id );

				add_action( 'wpf_tags_modified', array( $this, 'update_course_access' ), 10, 2 );

			}
		}
	}

	/**
	 * If a user has a tag it checks to
	 * see if user is enrolled, if not
	 * enrolled, then enroll them.
	 *
	 * @access public
	 * @return void
	 */

	public function update_course_access( $user_id, $user_tags ) {

		$wpf_settings = get_option( 'wpf_wpcw_settings', array() );

		foreach ( $wpf_settings as $course_id => $settings ) {

			if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
				continue;
			}

			$enrollment_date = WPCW_users_getCourseEnrolmentDate( $user_id, $course_id );

			if ( in_array( $settings['tag_link'][0], $user_tags ) && $enrollment_date == false ) {

				WPCW_courses_syncUserAccess( $user_id, $course_id );

			} elseif ( ! in_array( $settings['tag_link'][0], $user_tags ) && $enrollment_date != false ) {

				$course_list = WPCW_users_getUserCourseList( $user_id );
				$course_ids  = array();

				foreach ( $course_list as $course ) {
					$course_ids[] = $course->course_id;
				}

				if ( ( $key = array_search( $course_id, $course_ids ) ) !== false ) {
					unset( $course_ids[ $key ] );
				}

				WPCW_courses_syncUserAccess( $user_id, $course_ids, $syncMode = 'sync' );

			}
		}
	}


	/**
	 * Tags users upon course completion.
	 *
	 * @access public
	 * @return void
	 */

	public function completed_module( $user_id, $unit_id, $unit_parent_data ) {

		$wpf_settings = get_option( 'wpf_wpcw_settings', array() );

		if ( isset( $wpf_settings[ 'm-' . $unit_parent_data->parent_module_id ]['apply_tags_completed'] ) ) {
			wp_fusion()->user->apply_tags( $wpf_settings[ 'm-' . $unit_parent_data->parent_module_id ]['apply_tags_completed'], $user_id );

		}

	}


	/**
	 * Tags users upon unit completion
	 *
	 * @access public
	 * @return void
	 */

	public function completed_unit( $user_id, $unit_id, $unit_parent_data ) {

		$settings = get_post_meta( $unit_id, 'wpf-settings', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_wpcw'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_wpcw'], $user_id );
		}

	}


	/**
	 * Tags users upon course completion.
	 *
	 * @access public
	 * @return void
	 */

	public function completed_course( $user_id, $unit_id, $unit_parent_data ) {

		$wpf_settings = get_option( 'wpf_wpcw_settings', array() );

		if ( isset( $wpf_settings[ $unit_parent_data->parent_course_id ]['apply_tags_completed'] ) ) {
			wp_fusion()->user->apply_tags( $wpf_settings[ $unit_parent_data->parent_course_id ]['apply_tags_completed'], $user_id );

		}

	}


	/**
	 * Creates WPCW submenu item
	 *
	 * @access public
	 * @return void
	 */


	public function admin_menu() {

		$id = add_submenu_page(
			'wpcw',
			__( 'WP Courseware - WP-Fusion', 'wp-courseware' ),
			__( 'WP Fusion', 'wp-courseware' ),
			'manage_options',
			'wpcw-wp-fusion',
			array( $this, 'render_admin_menu' )
		);

		add_action( 'load-' . $id, array( $this, 'enqueue_scripts' ) );
	}




	/**
	 * Enqueues WPF scripts and styles on CW options page
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_scripts() {

		wp_enqueue_style( 'options-css', WPF_DIR_URL . 'includes/admin/options/css/options.css' );
		wp_enqueue_style( 'wpf-options', WPF_DIR_URL . 'assets/css/wpf-options.css' );

	}

	/**
	 * Renders CW submenu item
	 *
	 * @access public
	 * @return mixed
	 */

	public function render_admin_menu() {

		// Save settings

		if ( isset( $_POST['wpf_wpcw_settings_nonce'] ) && wp_verify_nonce( $_POST['wpf_wpcw_settings_nonce'], 'wpf_wpcw_settings' ) && ! empty( $_POST['wpf-settings'] ) ) {

			update_option( 'wpf_wpcw_settings', $_POST['wpf-settings'] );
			echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
		}

		?>

		<div class="wrap">
			<h2><?php echo wp_fusion()->crm->name; ?> Integration</h2>

			<form id="wpf-mm-settings" action="" method="post">

				<?php

				$course_list = WPCW_courses_getCourseList();

				if ( ! empty( $course_list ) ) :
					?>

					<?php wp_nonce_field( 'wpf_wpcw_settings', 'wpf_wpcw_settings_nonce' ); ?>
					<input type="hidden" name="action" value="update">

					<h4>Course Tags</h4>

					<p class="description">For each Course Level below, specify tags to be applied in <?php echo wp_fusion()->crm->name; ?> when a user is enrolled, and when the course is completed.</p>
						
					<p class="description">Note that if you have WP Courseware set to auto-enroll users on registration the enrollment tags will not be applied.</p>

					<p class="description">Tags and access rules for units can be configured by <a href="<?php echo admin_url( 'edit.php?post_type=course_unit' ); ?>">editing the individual units</a>.</p>

					<br/>

					<?php $settings = get_option( 'wpf_wpcw_settings', array() ); ?>

					<table class="table table-hover" id="wpf-coursewre-levels-table">
						<thead>
						<tr>
							<th>Courses / Modules</th>
							<th>Linked Tag (for auto-enrollment)</th>
							<th>Apply Tags - Enrolled</th>
							<th>Apply Tags - Completed</th>
						</tr>
						</thead>
						<tbody>

							<?php
							if ( empty( $course_list ) ) {
								return;}
							?>

							<?php foreach ( $course_list as $course_id => $course_title ) : ?>

								<?php

								if ( ! isset( $settings[ $course_id ] ) ) {
									$settings[ $course_id ] = array();
								}

								$defaults = array(
									'tag_link'             => array(),
									'apply_tags_started'   => array(),
									'apply_tags_completed' => array(),
									'apply_tags_viewed'    => array(),
								);

								$settings[ $course_id ] = array_merge( $defaults, $settings[ $course_id ] );

								?>


								<tr style="border-bottom: 2px solid #ddd !important;">

									<td style="font-weight: bold;"><?php echo $course_title; ?></td>

									<td>
										<?php

										$args = array(
											'setting'   => $settings[ $course_id ]['tag_link'],
											'meta_name' => "wpf-settings[{$course_id}][tag_link]",
											'limit'     => 1,
										);

										wpf_render_tag_multiselect( $args );

										?>
										
									</td>
									<td>
										<?php

										$args = array(
											'setting'   => $settings[ $course_id ]['apply_tags_started'],
											'meta_name' => "wpf-settings[{$course_id}][apply_tags_started]",
										);

										wpf_render_tag_multiselect( $args );

										?>
										
									</td>

									<td>
										<?php

										$args = array(
											'setting'   => $settings[ $course_id ]['apply_tags_completed'],
											'meta_name' => "wpf-settings[{$course_id}][apply_tags_completed]",
										);

										wpf_render_tag_multiselect( $args );

										?>
										
									</td>
								</tr>

								<?php $module_list = WPCW_courses_getModuleDetailsList( $course_id ); ?>

								<?php
								if ( empty( $module_list ) ) {
									continue;}
								?>


								<?php foreach ( $module_list as $module_id => $module ) : ?>
									
									<?php

									if ( ! isset( $settings[ 'm-' . $module_id ] ) ) {
										$settings[ 'm-' . $module_id ] = array();
									}

									$defaults = array(
										'apply_tags_completed' => array(),
										'apply_tags_viewed'    => array(),
									);

									$settings[ 'm-' . $module_id ] = array_merge( $defaults, $settings[ 'm-' . $module_id ] );

									?>

									<tr>
										<td style="padding-left:20px;"><?php echo $module->module_title; ?></td>
										<td></td>
										<td></td>

										<td>
											<?php

											$args = array(
												'setting' => $settings[ 'm-' . $module_id ]['apply_tags_completed'],
												'meta_name' => "wpf-settings[m-{$module_id}][apply_tags_completed]",
											);

											wpf_render_tag_multiselect( $args );

											?>
											
										</td>
									</tr>


								<?php endforeach; ?>


							<?php endforeach; ?>

						</tbody>

					</table>

				<?php elseif ( empty( $course_list ) ) : ?>

					<em>No courses found</em>

				<?php endif; ?>

				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes"/>
				</p>

			</form>

		</div>

		<?php

	}


	/**
	 * Adds WPCW fields to WPF meta box
	 *
	 * @access public
	 * @return void
	 */

	public function meta_box_content( $post, $settings ) {

		if ( $post->post_type != 'course_unit' ) {
			return;
		}

		if ( ! isset( $settings['apply_tags_wpcw'] ) ) {
			$settings['apply_tags_wpcw'] = array();
		}

		echo '<p><label for="wpf-apply-tags-ld"><small>Apply these tags when marked complete:</small></label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_wpcw'],
				'meta_name' => 'wpf-settings',
				'field_id'  => 'apply_tags_wpcw',
			)
		);

		echo '</p>';

	}

	/**
	 * Restores bulk edit in Units and Courses list
	 *
	 * @access public
	 * @return array Columns
	 */

	public function bulk_edit_columns( $columns ) {

		$columns['wpf_settings'] = false;

		return $columns;

	}


}

new WPF_Courseware();
