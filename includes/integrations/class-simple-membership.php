<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}



class WPF_Simple_Membership extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'simple-membership';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Simple membership';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/simple-membership/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );
		add_action( 'wpf_user_created', array( $this, 'apply_membership_tags' ), 10, 3 );

		// Admin interfaces
		add_filter( 'swpm_admin_edit_membership_level_ui', array( $this, 'edit_membership_level_output' ), 10, 2 );
		add_filter( 'swpm_admin_edit_membership_level', array( $this, 'save_membership_level_settings' ), 10, 2 );

	}

	/**
	 * Triggered when new member is added
	 *
	 * @access  public
	 * @return  array Post data
	 */

	public function user_register( $post_data, $user_id ) {

		$field_map = array(
			'user_name' => 'user_login',
			'email'     => 'user_email',
			'password'  => 'user_pass',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		return $post_data;

	}

	/**
	 * Triggered when new member is added
	 *
	 * @access  public
	 * @return  void
	 */

	public function apply_membership_tags( $user_id, $contact_id, $post_data ) {

		// Quit early if now SWPM registration
		if ( ! isset( $post_data['swpm_level_hash'] ) && ! isset( $post_data['swpm_level_hash'] ) ) {
			return;
		}

		// Apply membership tags
		$level_id = $post_data['membership_level'];

		$settings = get_option( 'wpf_simple_membership_settings', array() );

		if ( isset( $settings[ $level_id ] ) && ! empty( $settings[ $level_id ]['apply_tags'] ) ) {
			wp_fusion()->user->apply_tags( $settings[ $level_id ]['apply_tags'], $user_id );
		}

	}

	/**
	 * Outputs apply tag settings for membership level
	 *
	 * @access  public
	 * @return  mixed HTML Content
	 */

	public function edit_membership_level_output( $output, $level_id ) {

		$settings = get_option( 'wpf_simple_membership_settings', array() );

		if ( ! isset( $settings[ $level_id ] ) ) {
			$settings[ $level_id ] = array( 'apply_tags' => array() );
		}

		ob_start(); ?>

		<tr>
			<th scope="row">
				<label for="wpf-apply-tags">Apply Tags</label><br />
			</th>
			<td>
				<?php
					$args = array(
						'setting'   => $settings[ $level_id ]['apply_tags'],
						'meta_name' => 'wpf-settings',
						'field_id'  => 'apply_tags',
					);

					wpf_render_tag_multiselect( $args );
					?>
				<span class="description">The selected tags will be applied in <?php echo wp_fusion()->crm->name; ?> at registration</span>

			</td>
		</tr>

		<?php

		$output .= ob_get_clean();
		return $output;

	}

	/**
	 * Saves custom membership level settings
	 *
	 * @access  public
	 * @return  array Custom Settings (unused by WPF)
	 */

	public function save_membership_level_settings( $custom_settings, $level_id ) {

		$post_data = $_POST;

		if ( isset( $post_data['wpf-settings'] ) ) {

			$settings              = get_option( 'wpf_simple_membership_settings', array() );
			$settings[ $level_id ] = $post_data['wpf-settings'];
			update_option( 'wpf_simple_membership_settings', $settings );

		}

		return $custom_settings;

	}

}

new WPF_Simple_Membership();
