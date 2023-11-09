<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_ProfilePress extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'profilepress';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Profilepress';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/profilepress/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'admin_menu', array( $this, 'page_menu' ) );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );
		add_filter( 'wpf_user_register', array( $this, 'user_register_filter' ), 10, 2 );

		// User Meta hooks
		add_action( 'pp_after_profile_update', array( $this, 'user_update' ), 10, 2 );
		add_filter( 'pp_after_registration', array( $this, 'user_register' ), 10, 3 );

	}

	/**
	 * Creates WPPP submenu item
	 *
	 * @access public
	 * @return void
	 */

	function page_menu() {

		$id = add_submenu_page(
			'pp-config',
			'WP Fusion - ProfilePress',
			'WP Fusion',
			'manage_options',
			'pp-wpf',
			array( $this, 'wpf_settings_page' )
		);

		add_action( 'load-' . $id, array( $this, 'enqueue_scripts' ) );

	}

	/**
	 * Renders WPPP Styles
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_scripts() {

		wp_enqueue_style( 'options-css', WPF_DIR_URL . 'includes/admin/options/css/options.css' );
		wp_enqueue_style( 'wpf-options', WPF_DIR_URL . 'assets/css/wpf-options.css' );

	}


	/**
	 * Renders PP submenu item
	 *
	 * @access public
	 * @return mixed
	 */

	public function wpf_settings_page() {

		if ( isset( $_POST['PROFILEPRESS_sql::sql_wp_list_table_registration_builder();'] ) && wp_verify_nonce( $_POST['PROFILEPRESS_sql::sql_wp_list_table_registration_builder();'], 'wpf_pp_settings' ) && ! empty( $_POST['wpf-settings'] ) ) {

			update_option( 'wpf_pp_settings', $_POST['wpf-settings'] );
			echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
		}

		$settings = get_option( 'wpf_pp_settings', array() );

		?>
		<div id="wrap">
		
			<form id="wpf-pp-settings" action="" method="post">
				<?php wp_nonce_field( 'wpf_pp_settings', 'PROFILEPRESS_sql::sql_wp_list_table_registration_builder();' ); ?>	        	
				<input type="hidden" name="action" value="update">				
					<h4>Registration Forms</h4>
				
					<p class="description">For each Registration Form below, specify tags to be applied in <?php echo wp_fusion()->crm->name; ?> when user is registered.</p>
				
					<br/>
				
					<table class="table table-hover" id="wpf-coursewre-levels-table">
						<thread>
				
							<tr>
							
								<th style="text-align:left;">Registration Forms</th>
					
								<th style="text-align:left;">Apply Tags</th>
					
							</tr> 
						</thread>
						<tbody>
							<?php $registration_builder = PROFILEPRESS_sql::sql_wp_list_table_registration_builder(); ?>
					
							<?php foreach ( $registration_builder as $data ) : ?>
					
								<?php
									$title = $data['title'];
									$id    = $data['id'];
								?>

								<?php

								if ( ! isset( $settings[ $id ] ) ) {
									$settings[ $id ] = array( 'apply_tags' => array() );
								}
								?>
					
								<tr style="border-bottom: 2px solid #ddd !important;">
					
									<td style="font-weight: bold;text-transform: uppercase;"><?php echo $title; ?></td>
					
									<td>
					
										<?php
											$args = array(
												'setting' => $settings[ $id ]['apply_tags'],
												'meta_name' => "wpf-settings[{$id}][apply_tags]",
											);
											wpf_render_tag_multiselect( $args );
											?>
									</td>
								
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table> 
				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes"/>
					</p>
			</form>
		</div>
		<?php
	}


	/**
	 * Adds User Meta field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['profilepress'] ) ) {
			$field_groups['profilepress'] = array(
				'title'  => 'ProfilePress',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Adds User Meta meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$profilepress_fields = PROFILEPRESS_sql::sql_wp_list_table_profile_fields();

		foreach ( $profilepress_fields as $field ) {

			$meta_fields[ $field['field_key'] ] = array(
				'label' => $field['label_name'],
				'type'  => $field['type'],
				'group' => 'profilepress',
			);

		}

		return $meta_fields;

	}


	/**
	 * Push changes to user meta on profile update and registration
	 *
	 * @access  public
	 * @return  void
	 */


	public function user_update( $user_data, $form_id ) {

		wp_fusion()->user->push_user_meta( $user_data['ID'], $user_data );

	}

	/**
	 * Triggered when new user registered through BBP
	 *
	 * @access  public
	 * @return  void
	 */

	public function user_register( $form_id, $user_data, $user_id ) {

		$settings = get_option( 'wpf_pp_settings', array() );

		if ( ! empty( $settings[ $form_id ] ) && ! empty( $settings[ $form_id ]['apply_tags'] ) ) {
			wp_fusion()->user->apply_tags( $settings[ $form_id ]['apply_tags'], $user_id );
		}

		wp_fusion()->user->push_user_meta( $user_data['ID'], $user_data );

	}

	/**
	 * Triggered when new member is added
	 *
	 * @access  public
	 * @return  array Post data
	 */

	public function user_register_filter( $post_data, $user_id ) {

		if ( ! isset( $post_data['pp_current_url'] ) ) {
			return $post_data;
		}

		$field_map = array(
			'reg_password' => 'user_pass',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		return $post_data;

	}

}

new WPF_ProfilePress();
