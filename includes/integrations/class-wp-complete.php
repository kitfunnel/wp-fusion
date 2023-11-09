<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_WPComplete extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wp-complete';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WPComplete';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/wpcomplete/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.18.4
	 * @return  void
	 */

	public function init() {

		// Apply tags on course completion
		add_action( 'wpcomplete_mark_completed', array( $this, 'button_complete' ) );
		add_action( 'wpcomplete_course_completed', array( $this, 'course_complete' ) );

		// Settings
		add_action( 'wpf_meta_box_content', array( $this, 'meta_box_content' ), 40, 2 );

		// Create sub-menu for course settings
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

	}

	/**
	 * Triggered when course / lesson / button marked complete
	 *
	 * @access public
	 * @return void
	 */

	public function button_complete( $button_info ) {

		$settings = get_post_meta( $button_info['post_id'], 'wpf-settings', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_wpc_complete'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_wpc_complete'], $button_info['user_id'] );
		}

	}

	/**
	 * Triggered when an entire course has been marked complete
	 *
	 * @access public
	 * @return void
	 */

	public function course_complete( $course_info ) {

		$settings = get_option( 'wpf_wpc_settings', array() );

		if ( empty( $course_info['course'] ) ) {
			$course_info['course'] = get_bloginfo( 'name' );
		}

		if ( ! empty( $settings[ $course_info['course'] ] ) && ! empty( $settings[ $course_info['course'] ]['apply_tags'] ) ) {
			wp_fusion()->user->apply_tags( $settings[ $course_info['course'] ]['apply_tags'], $course_info['user_id'] );
		}

	}


	/**
	 * Adds wp-complete fields to WPF meta box
	 *
	 * @access public
	 * @return void
	 */

	public function meta_box_content( $post, $settings ) {

		echo '<hr />';
		echo '<p><strong>WPComplete:</strong></p>';

		echo '<p><label for="wpf-apply-tags-wpc-complete"><small>' . __( 'Apply these tags when marked complete', 'wp-fusion' ) . ':</small></label>';

		if ( ! isset( $settings['apply_tags_wpc_complete'] ) ) {
			$settings['apply_tags_wpc_complete'] = array();
		}

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_wpc_complete'],
				'meta_name' => 'wpf-settings',
				'field_id'  => 'apply_tags_wpc_complete',
			)
		);

		echo '</p>';

	}

	/**
	 * Creates WLM submenu item
	 *
	 * @access public
	 * @return void
	 */

	public function admin_menu() {

		$crm = wp_fusion()->crm->name;

		$id = add_submenu_page(
			'wpcomplete-courses',
			__( 'WP Fusion', 'wp-fusion' ),
			__( 'WP Fusion', 'wp-fusion' ),
			'manage_options',
			'wpcomplete-wp-fusion',
			array( $this, 'render_admin_page' )
		);

		add_action( 'load-' . $id, array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueues WPF scripts and styles on the options page
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_scripts() {

		wp_enqueue_style( 'options-css', WPF_DIR_URL . 'includes/admin/options/css/options.css' );
		wp_enqueue_style( 'wpf-options', WPF_DIR_URL . 'assets/css/wpf-options.css' );

	}


	/**
	 * Renders WLM submenu item
	 *
	 * @access public
	 * @return mixed
	 */

	public function render_admin_page() {

		// Save settings
		if ( isset( $_POST['wpf_wpc_settings_nonce'] ) && wp_verify_nonce( $_POST['wpf_wpc_settings_nonce'], 'wpf_wpc_settings' ) ) {
			update_option( 'wpf_wpc_settings', $_POST['wpf_settings'], false );
			echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
		}

		$settings     = get_option( 'wpf_wpc_settings', array() );
		$course_names = array();

		$wpc   = new WPComplete_Common( 'wpcomplete', false );
		$posts = $wpc->get_completable_posts();

		foreach ( $posts as $post ) {

			if ( ! isset( $post['course'] ) ) {
				$post['course'] = get_bloginfo( 'name' ); // the default course is the blog name
			}

			if ( ! in_array( $post['course'], $course_names ) ) {
				$course_names[] = $post['course'];
			}
		}

		?>

		<div class="wrap">
		<h2><?php printf( __( '%s Integration', 'wp-fusion' ), wp_fusion()->crm->name ); ?></h2>

		<br />
		<p class="description"><?php printf( __( 'For each course below, specify tags to be applied in %s when all items have been marked complete.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></p>
		<br/>

			<form id="wpf-wpc-settings" action="" method="post">

				<?php wp_nonce_field( 'wpf_wpc_settings', 'wpf_wpc_settings_nonce' ); ?>

				<?php if ( empty( $course_names ) ) : ?>

					No courses found.

				<?php else : ?>

					<table class="table table-hover" id="wpf-settings-table">
						<thead>
						<tr>
							<th style="width: 25%"><?php _e( 'Course Name', 'wp-fusion' ); ?></th>
							<th><?php _e( 'Apply Tags', 'wp-fusion' ); ?></th>
						</tr>
						</thead>
						<tbody>

							<?php foreach ( $course_names as $course_name ) : ?>

								<?php

								if ( ! isset( $settings[ $course_name ] ) ) {
									$settings[ $course_name ] = array(
										'apply_tags' => array(),
									);
								}

								?>

								<tr>
									<td><?php echo $course_name; ?></td>
									<td>
										<?php

										$args = array(
											'setting'   => $settings[ $course_name ]['apply_tags'],
											'meta_name' => "wpf_settings[{$course_name}][apply_tags]",
										);

										wpf_render_tag_multiselect( $args );

										?>

									</td>

								</tr>

							<?php endforeach; ?>

						</tbody>
					</table>

				<?php endif; ?>


				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes"/>
				</p>

			</form>

		</div>

		<?php

	}

}

new WPF_WPComplete();
