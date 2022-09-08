<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Private_Messages extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'private-messages';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Private messages';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'pm_new_message', array( $this, 'new_message' ), 10, 2 );

		// Admin settings
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 40 );

	}


	/**
	 * Apply tags when a message is sent
	 *
	 * @access public
	 * @return void
	 */

	public function new_message( $message, $thread ) {

		$settings = get_option( 'wpf_private_messages_settings', array() );

		if ( ! empty( $settings['apply_tags_sent'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_sent'], $thread->author );

		}

		if ( ! empty( $settings['apply_tags_received'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_received'], $thread->recipient );

		}

	}


	/**
	 * Creates WPF submenu item
	 *
	 * @access public
	 * @return void
	 */

	public function admin_menu() {

		$id = add_submenu_page(
			'edit.php?post_type=private-messages',
			wp_fusion()->crm->name . ' Integration',
			'WP Fusion',
			'manage_options',
			'private-messages-wpf-settings',
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

		?>

		<div class="wrap">

			<h1><?php echo wp_fusion()->crm->name; ?> Integration</h1>

			<?php

			// Save settings

			if ( isset( $_POST['wpf_private_messages_settings_nonce'] ) && wp_verify_nonce( $_POST['wpf_private_messages_settings_nonce'], 'wpf_private_messages_settings' ) ) {
				update_option( 'wpf_private_messages_settings', $_POST['wpf-settings'] );
				echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
			}

			// Get settings
			$settings = get_option( 'wpf_private_messages_settings', array() );

			?>
		
			<form id="wpf-private-messages-settings" action="" method="post" style="width: 100%; max-width: 800px;">

				<?php wp_nonce_field( 'wpf_private_messages_settings', 'wpf_private_messages_settings_nonce' ); ?>

				<table class="form-table" id="wpf-settings-table">

					<tbody>
						
						<tr>
							<th scope="row">
								<label>Apply tags when a user sends a message</label>
							</th>
							<td>
								<?php

								$args = array(
									'setting'   => $settings['apply_tags_sent'],
									'meta_name' => 'wpf-settings',
									'field_id'  => 'apply_tags_sent',
								);

								wpf_render_tag_multiselect( $args );

								?>

							</td>
						</tr>

						<tr>
							<th scope="row">
								<label>Apply tags when a user receives a message</label>
							</th>
							<td>
								
								<?php

								$args = array(
									'setting'   => $settings['apply_tags_received'],
									'meta_name' => 'wpf-settings',
									'field_id'  => 'apply_tags_received',
								);

								wpf_render_tag_multiselect( $args );

								?>

							</td>
						</tr>
					</tbody>

				</table>

				<input type="hidden" name="action" value="update">	

				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes"/></p>

			</form>
		</div>
		<?php
	}


}

new WPF_Private_Messages();
