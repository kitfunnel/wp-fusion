<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
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

		if ( wpf_get_option( 'restrict_content', true ) ) {

			// Hide if they don't have access.
			add_filter( 'wpforo_permissions_forum_can', array( $this, 'permissions_forum_can' ), 10, 4 );

			// Redirect if they don't have access.
			add_action( 'template_redirect', array( $this, 'template_redirect' ), 15 );

		}

		// Admin settings.
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 40 );

		// Auto enrollments.
		add_action( 'wpf_tags_modified', array( $this, 'update_usergroup_access' ), 10, 2 );

		add_action( 'wpforo_update_profile_after', array( $this, 'profile_update' ) );

		// WPF stuff.
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

	public function permissions_forum_can( $can, $do, $forumid, $groupid ) {

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
	 * Sync profile updates.
	 *
	 * @since 3.19
	 * @since 3.40.29 Moved to wpforo_update_profile_after hook.
	 *
	 * @param array $user The user data.
	 */
	public function profile_update( $user ) {

		$user['avatar'] = WPF()->member->get_avatar_url( $user['userid'] );

		wp_fusion()->user->push_user_meta( $user['userid'], $user );

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

	public function prepare_meta_fields( $meta_fields = array() ) {

		$fields = wpforo_account_fields();
		$fields = apply_filters( 'wpforo_form_fields', $fields );

		$custom_fields = array();

		foreach ( $fields as $field_group ) {

			foreach ( $field_group as $field_sub_group ) {

				$custom_fields += $field_sub_group;
			}
		}

		if ( ! empty( get_option( 'wpfucf_custom_fields' ) ) ) {
			$custom_fields += get_option( 'wpfucf_custom_fields' );
		}

		$standard_fields = array( 'user_login', 'display_name', 'user_nicename', 'user_email', 'user_pass', 'user_url', 'first_name', 'last_name' );

		foreach ( $custom_fields as $key => $field ) {

			if ( in_array( $key, $standard_fields, true ) ) {
				continue; // Skip standard fields.
			}

			if ( ! isset( $field['label'] ) || 'html' === $field['type'] || false !== strpos( $key, 'groupid' ) ) {
				continue;
			}

			if ( 'checkbox' === $field['type'] ) {
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

		$wpf_fields = $this->prepare_meta_fields();

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

		// Prevent looping.
		remove_action( 'wpforo_update_profile_after', array( $this, 'profile_update' ) );

		// Update it in the wpforo_profiles table.

		$data['userid'] = $user_id;

		WPF()->member->update_user_fields( $user_id, $data, $check_permissions = false );

		WPF()->member->update_profile_fields( $user_id, $data, $check_permissions = false );

		WPF()->member->update_custom_fields( $user_id, $data, $check_permissions = false );

		WPF()->member->get_member( $user_id ); // forces a rebuild of the cache.

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

		$default_group = WPF()->usergroup->get_default_groupid( 3 ); // the default, Guest, is usually 3.

		$member = WPF()->member->get_member( array( 'userid' => $user_id ) );

		// First try setting a new group.

		foreach ( $settings as $group_id => $setting ) {

			if ( empty( $setting['enrollment_tag'] ) ) {
				continue;
			}

			$tag_id = $setting['enrollment_tag'][0];

			if ( in_array( $tag_id, $user_tags ) && intval( $member['groupid'] ) !== intval( $group_id ) ) {

				// If they have the tag.

				$group = WPF()->usergroup->get_usergroup( $group_id );

				wpf_log( 'info', $user_id, 'User auto-assigned wpForo usergroup <strong>' . $group['name'] . '</strong> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

				WPF()->usergroup->set_users_groupid( array( $group_id => array( $user_id ) ) );

				delete_user_meta( $user_id, '_wpf_member_obj' );
				WPF()->member->get_member( $user_id ); // this forces a rebuild of the member cache.

				if ( isset( $_POST['wpforo_usergroup'] ) ) {
					unset( $_POST['wpforo_usergroup'] ); // this prevents the usergroup from being reset when saving the admin profile.
				}

				return;

			}
		}

		// If no new group was set, maybe remove them from an existing group if the linked tag was removed

		foreach ( $settings as $group_id => $setting ) {

			if ( empty( $setting['enrollment_tag'] ) ) {
				continue;
			}

			$tag_id = $setting['enrollment_tag'][0];

			if ( ! in_array( $tag_id, $user_tags ) && intval( $member['groupid'] ) === intval( $group_id ) ) {

				// If they don't have the tag, set them back to the default group.

				$group = WPF()->usergroup->get_usergroup( $group_id );

				wpf_log( 'info', $user_id, 'User removed from group ID <strong>' . $group['name'] . '</strong> and auto-assigned wpForo default group #' . $default_group . ' because linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong> was removed.' );

				WPF()->usergroup->set_users_groupid( array( $default_group => array( $user_id ) ) );

				delete_user_meta( $user_id, '_wpf_member_obj' );
				WPF()->member->get_member( $user_id ); // this forces a rebuild of the member cache.

				if ( isset( $_POST['wpforo_usergroup'] ) ) {
					unset( $_POST['wpforo_usergroup'] ); // this prevents the usergroup from being reset when saving the admin profile.
				}

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
			'wpforo-overview',
			sprintf( __( '%s Integration', 'wp-fusion' ), wp_fusion()->crm->name ),
			__( 'WP Fusion', 'wp-fusion' ),
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

			// Get settings.
			$settings = get_option( 'wpf_wpforo_settings', array() );

			if ( ! $settings ) {
				$settings = array();
			}

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

				<?php if ( wpf_get_option( 'restrict_content', true ) ) : ?>

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

							if ( empty( $settings[ $forum_id ] ) ) {
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

				<?php else : ?>

					<p class="description"><em><?php _e( 'Content restriction is disabled in WP Fusion settings.', 'wp-fusion' ); ?></em></p>

				<?php endif; ?>

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
