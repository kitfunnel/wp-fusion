<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_wpForo extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wpforo';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'wpForo';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/forums/wpforo/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		if ( wpf_get_option( 'restrict_content', true )  ){

			// Hide if they don't have access
			add_filter( 'wpforo_permissions_forum_can', array( $this, 'permissions_forum_can' ), 10, 5 );

			// Redirect if they don't have access
			add_action( 'template_redirect', array( $this, 'template_redirect' ), 15 );

			// Admin settings
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 40 );

			// Auto enrollments
			add_action( 'wpf_tags_modified', array( $this, 'update_usergroup_access' ), 10, 2 );

		}

		add_action( 'wpforo_action_wpforo_profile_update', array( $this, 'profile_update' ), 5 );

		// WPF stuff
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );
		add_filter( 'wpf_set_user_meta', array( $this, 'set_user_meta' ), 10, 2 );



	}


	/**
	 * Handles redirects for locked content
	 *
	 * @access public
	 * @return bool
	 */

	public function template_redirect() {

		$current_object = WPF()->current_object;

		if ( ! isset( $current_object['forumid'] ) ) {
			return;
		}

		$settings = get_option( 'wpf_wpforo_settings', array() );

		if ( empty( $settings ) || ! isset( $settings[ $current_object['forumid'] ] ) ) {
			return;
		}

		if ( empty( $settings[ $current_object['forumid'] ]['required_tags'] ) || empty( $settings[ $current_object['forumid'] ]['redirect'] ) ) {
			return;
		}

		// If admins are excluded from restrictions
		if ( wpf_admin_override() ) {
			return;
		}

		$redirect = get_permalink( $settings[ $current_object['forumid'] ]['redirect'] );

		$has_access = true;

		if ( ! wpf_is_user_logged_in() ) {

			$has_access = false;

		} else {

			$user_tags = wp_fusion()->user->get_tags();

			if ( empty( $user_tags ) ) {

				$has_access = false;

			} else {

				$result = array_intersect( $user_tags, $settings[ $current_object['forumid'] ]['required_tags'] );

				if ( empty( $result ) ) {

					$has_access = false;

				}
			}
		}

		if ( ! $has_access ) {

			wp_redirect( $redirect );
			exit();

		}

	}

	/**
	 * Hide restricted forums
	 *
	 * @access public
	 * @return bool
	 */

	public function permissions_forum_can( $can, $do, $forumid, $groupid, $second_usergroupids ) {

		$settings = get_option( 'wpf_wpforo_settings', array() );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		if ( empty( $forumid ) || is_array( $forumid ) || ! isset( $settings[ $forumid ] ) ) {
			return $can;
		}

		if ( empty( $settings[ $forumid ]['required_tags'] ) || empty( $settings[ $forumid ]['hide'] ) ) {
			return $can;
		}

		// If admins are excluded from restrictions
		if ( wpf_admin_override() ) {
			return $can;
		}

		if ( ! wpf_is_user_logged_in() ) {

			$can = false;

		} else {

			$user_tags = wp_fusion()->user->get_tags();

			if ( empty( $user_tags ) ) {

				$can = false;

			} else {

				$result = array_intersect( $user_tags, $settings[ $forumid ]['required_tags'] );

				if ( empty( $result ) ) {

					$can = false;

				}
			}
		}

		return $can;

	}

	/**
	 * Sync profile updates
	 *
	 * @access  public
	 * @return  void
	 */

	public function profile_update() {

		if ( ! isset( $_POST['member'] ) ) {
			return;
		}

		$update_data = $_POST['member'];

		if ( ! empty( $_POST['data'] ) ) {
			$update_data = array_merge( $update_data, $_POST['data'] );
		}

		wp_fusion()->user->push_user_meta( $update_data['userid'], $update_data );

	}

	/**
	 * Adds field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['wpforo'] = array(
			'title'  => 'wpForo',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Adds meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$custom_fields = get_option( 'wpfucf_custom_fields' );

		if ( empty( $custom_fields ) ) {
			return $meta_fields;
		}

		foreach ( $custom_fields as $key => $field ) {

			if ( ! isset( $field['label'] ) ) {
				continue;
			}

			if ( 'checkbox' == $field['type'] ) {
				$field['type'] = 'multiselect';
			}

			$meta_fields[ $key ] = array(
				'label' => $field['label'],
				'type'  => $field['type'],
				'group' => 'wpforo',
			);

		}

		return $meta_fields;

	}

	/**
	 * Save custom meta to the member object
	 *
	 * @access  public
	 * @return  array User Meta
	 */

	public function set_user_meta( $user_meta, $user_id ) {

		$wpf_fields = $this->prepare_meta_fields( array() );

		$data = array();

		foreach ( $wpf_fields as $key => $field_data ) {

			if ( isset( $user_meta[ $key ] ) ) {

				// Custom fields

				if ( ! isset( $data['data'] ) ) {
					$data['data'] = array();
				}

				$data['data'][ $key ] = $user_meta[ $key ];

				// And also include it in the standard fields

				$data[ $key ] = $user_meta[ $key ];

			}
		}

		// If no wpForo fields, quit

		if ( empty( $data ) ) {
			return $user_meta;
		}

		// Update it in the wpforo_profiles table

		$data['userid'] = $user_id;

		WPF()->member->update_user_fields( $user_id, $data, $check_permissions = false );

		// they separated the calls
		WPF()->member->update_profile_fields( $user_id, $data, $check_permissions = false );

		WPF()->member->update_custom_fields( $user_id, $data, $check_permissions = false );

		// Update it in the wp_usermeta table

		$member_data = get_user_meta( $user_id, '_wpf_member_obj', true );

		if ( empty( $member_data ) ) {
			$member_data = array();
		}

		if ( ! empty( $member_data['fields'] ) && ! empty( $data['data'] ) ) {

			$member_data['fields'] = json_decode( $member_data['fields'], true );

			if ( ! empty( $member_data['fields'] ) ) {

				$member_data['fields'] = array_merge( $member_data['fields'], $data['data'] );

			}

			$member_data['fields'] = wp_json_encode( $member_data['fields'] );

			unset( $data['data'] );

		}

		$member_data = array_merge( $member_data, $data );

		update_user_meta( $user_id, '_wpf_member_obj', $member_data );

		return $user_meta;

	}

	/**
	 * Update usergroup enrollment when tags are modified
	 *
	 * @access public
	 * @return void
	 */

	public function update_usergroup_access( $user_id, $user_tags ) {

		if ( user_can( $user_id, 'manage_options' ) ) {
			return; // Don't do anything for administrators
		}

		$settings = get_option( 'wpf_wpforo_settings_usergroups', array() );

		if ( empty( $settings ) ) {
			return;
		}

		$user_groupid = WPF()->member->get_usergroup( $user_id );

		$default_group = get_option( 'wpforo_default_groupid', 3 ); // the default is 3 if it hasn't been changed

		// First try setting a new group

		foreach ( $settings as $group_id => $setting ) {

			if ( empty( $setting['enrollment_tag'] ) ) {
				continue;
			}

			$tag_id = $setting['enrollment_tag'][0];

			if ( in_array( $tag_id, $user_tags ) && $user_groupid != $group_id ) {

				// If they have the tag

				wpf_log( 'info', $user_id, 'User auto-assigned wpForo usergroup #' . $group_id . ' by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

				WPF()->member->set_usergroup( $user_id, $group_id );

				return;

			}
		}

		// If no new group was set, maybe remove them from an existing group if the linked tag was removed

		foreach ( $settings as $group_id => $setting ) {

			if ( empty( $setting['enrollment_tag'] ) ) {
				continue;
			}

			$tag_id = $setting['enrollment_tag'][0];

			if ( ! in_array( $tag_id, $user_tags ) && $user_groupid == $group_id ) {

				// If they don't have the tag, set them back to the default group

				wpf_log( 'info', $user_id, 'User removed from group ID #' . $group_id . ' and auto-assigned wpForo default group #' . $default_group . ' because linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong> was removed.' );

				WPF()->member->set_usergroup( $user_id, $default_group );

				return;

			}
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
			'wpforo-community',
			wp_fusion()->crm->name . ' Integration',
			'WP Fusion',
			'manage_options',
			'wpforo-wpf-settings',
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

		wp_enqueue_style( 'bootstrap', WPF_DIR_URL . 'includes/admin/options/css/bootstrap.min.css' );
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
			if ( isset( $_POST['wpf_wpforo_settings_nonce'] ) && wp_verify_nonce( $_POST['wpf_wpforo_settings_nonce'], 'wpf_wpforo_settings' ) ) {

				update_option( 'wpf_wpforo_settings', $_POST['wpf_settings'], false );
				update_option( 'wpf_wpforo_settings_usergroups', $_POST['wpf_settings_usergroups'], false );

				echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
			}

			// Get settings
			$settings = get_option( 'wpf_wpforo_settings', array() );

			// Get registered forums / categories
			$forums = WPF()->db->get_col( 'SELECT * FROM ' . WPF()->tables->forums . ' ORDER BY `forumid` ASC' );

			// Get pages for dropdown
			$post_types      = get_post_types( array( 'public' => true ) );
			$available_posts = array();

			unset( $post_types['attachment'] );
			$post_types = apply_filters( 'wpf_redirect_post_types', $post_types );

			foreach ( $post_types as $post_type ) {

				$posts = get_posts(
					array(
						'post_type'      => $post_type,
						'posts_per_page' => 200,
						'orderby'        => 'post_title',
						'order'          => 'ASC',
					)
				);

				foreach ( $posts as $post ) {
					$available_posts[ $post_type ][ $post->ID ] = $post->post_title;
				}
			}

			?>

			<form id="wpf-wpforo-settings" action="" method="post" style="width: 100%; max-width: 1200px;">

				<?php wp_nonce_field( 'wpf_wpforo_settings', 'wpf_wpforo_settings_nonce' ); ?>

				<h4>Categories and Forums</h4>
				<p class="description">You can restrict access to categories and forums by a logged in user's tags. If they don't have the required tags, they'll be redirected to the page you choose in the dropdown.</p>
				<br/>

				<input type="hidden" name="action" value="update">

					<table class="table table-hover wpf-settings-table">
						<thead>
							<tr>

								<th scope="row"><?php _e( 'Forum / Category', 'wp-fusion' ); ?></th>

								<th scope="row"><?php _e( 'Required tags (any)', 'wp-fusion' ); ?></th>

								<th scope="row"><?php _e( 'Hide if access is denied', 'wp-fusion' ); ?></th>

								<th scope="row"><?php _e( 'Redirect if access is denied', 'wp-fusion' ); ?></th>

							</tr>
						</thead>
						<tbody>

						<?php
						foreach ( $forums as $forum_id ) :

							$defaults = array(
								'required_tags' => array(),
								'hide'          => false,
								'redirect'      => false,
							);

							if ( ! isset( $settings[ $forum_id ] ) ) {
								$settings[ $forum_id ] = array();
							}

							$settings[ $forum_id ] = array_merge( $defaults, $settings[ $forum_id ] );

							$name = WPF()->db->get_var( 'SELECT `title` FROM `' . WPF()->tables->forums . '` WHERE `forumid` = ' . $forum_id );

							?>

							<tr>
								<td><?php echo $name; ?></td>
								<td>
								<?php

									$args = array(
										'setting'   => $settings[ $forum_id ]['required_tags'],
										'meta_name' => "wpf_settings[{$forum_id}][required_tags]",
										'read_only' => true,
									);

									wpf_render_tag_multiselect( $args );

									?>
								</td>

								<td><input type="checkbox" name="wpf_settings[<?php echo $forum_id; ?>][hide]" value="1" <?php checked( $settings[ $forum_id ]['hide'], true ); ?> /></td>

								<td>

									<select id="wpf-redirect-<?php echo $forum_id; ?>" class="select4-search" style="width: 100%;" data-placeholder="None" name="wpf_settings[<?php echo $forum_id; ?>][redirect]">

										<option></option>

										<?php foreach ( $available_posts as $post_type => $data ) : ?>

											<optgroup label="<?php echo $post_type; ?>">

											<?php foreach ( $available_posts[ $post_type ] as $id => $post_name ) : ?>
												<option value="<?php echo $id; ?>" <?php selected( $id, $settings[ $forum_id ]['redirect'] ); ?> ><?php echo $post_name; ?></option>
											<?php endforeach; ?>

											</optgroup>

										<?php endforeach; ?>

									</select>

								</td>

							</tr>

						<?php endforeach; ?>

					</tbody>
				</table>

				<h4>Usergroups</h4>
				<p class="description">You can automate enrollment into WPForo usergroups with tags. For each group set a tag to be used as an enrollment trigger. When the tag is applied the user will be added to the usergroup. When the tag is removed the user will be removed and added to the default usergroup.</p>
				<br/>

				<table class="table table-hover wpf-settings-table">
					<thead>
						<tr>

							<th scope="row"><?php _e( 'Usergroup', 'wp-fusion' ); ?></th>
							<th scope="row"><?php _e( 'Enrollment Tag', 'wp-fusion' ); ?></th>

						</tr>
					</thead>
					<tbody>

						<?php
						$groups_settings = get_option( 'wpf_wpforo_settings_usergroups', array() );

						if ( empty( $groups_settings ) ) {
							$groups_settings = array();
						}

						?>

						<?php $groups = WPF()->usergroup->usergroup_list_data(); ?>
						<?php foreach ( $groups as $group ) : ?>

							<tr>
								<td><?php echo esc_html( $group['name'] ); ?></td>
								<td>

									<?php

									if ( ! isset( $groups_settings[ $group['groupid'] ] ) ) {
										$groups_settings[ $group['groupid'] ] = array( 'enrollment_tag' => array() );
									}

									if ( ! isset( $groups_settings[ $group['groupid'] ]['enrollment_tag'] ) ) {
										$groups_settings[ $group['groupid'] ]['enrollment_tag'] = array();
									}

									$args = array(
										'setting'   => $groups_settings[ $group['groupid'] ]['enrollment_tag'],
										'meta_name' => "wpf_settings_usergroups[{$group['groupid']}][enrollment_tag]",
										'limit'     => 1,
									);

									wpf_render_tag_multiselect( $args );

									?>

								</td>

							</tr>

						<?php endforeach; ?>


					</tbody>

				</table>

				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes"/></p>

			</form>
		</div>
		<?php
	}

}

new WPF_wpForo();
