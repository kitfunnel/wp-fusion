<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_PeepSo extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'peepso';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Peepso';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/peepso/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		// Auto login fixes
		add_action( 'wpf_started_auto_login', array( $this, 'started_auto_login' ), 10, 2 );

		// User Meta hooks
		add_action( 'peepso_action_profile_field_save', array( $this, 'user_update' ), 10 );
		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'user_register' ), 10, 2 );
		add_action( 'peepso_action_user_role_change', array( $this, 'role_changed' ), 10, 2 );
		add_action( 'peepso_save_profile_form', array( $this, 'save_profile' ) );
		add_action( 'wpf_user_updated', array( $this, 'maybe_set_role' ), 10, 2 );

		// Groups stuff
		add_action( 'peepso_action_group_user_join', array( $this, 'join_group' ), 10, 2 );
		add_action( 'peepso_action_group_user_delete', array( $this, 'leave_group' ), 10, 2 );
		add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		// Groups admin
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

	}

	/**
	 * Adds User Meta field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['peepso'] ) ) {
			$field_groups['peepso'] = array(
				'title'  => 'PeepSo',
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

		$peepsouser     = PeepSoUser::get_instance( 0 );
		$profile_fields = new PeepSoProfileFields( $peepsouser );
		$fields         = $profile_fields->load_fields();

		foreach ( $fields as $field ) {

			// No need to track these separately
			if ( $field->key == 'peepso_user_field_first_name' || $field->key == 'peepso_user_field_last_name' ) {
				continue;
			}

			$meta_fields[ $field->key ] = array(
				'label' => $field->title,
				'type'  => 'text',
				'group' => 'peepso',
			);

		}

		$args = array(
			'post_type' => 'peepso_user_field',
			'nopaging'  => true,
		);

		$custom_fields = get_posts( $args );

		foreach ( $custom_fields as $post ) {

			// No need to track these separately
			if ( $post->post_name == 'first_name' || $post->post_name == 'last_name' ) {
				continue;
			}

			$meta_fields[ 'peepso_user_field_' . $post->post_name ] = array(
				'label' => $post->post_title,
				'type'  => 'text',
				'group' => 'peepso',
			);
		}

		$meta_fields['peepso_role'] = array(
			'label' => 'Membership Role',
			'type'  => 'text',
			'group' => 'peepso',
		);

		return $meta_fields;

	}


	/**
	 * Compatibility tweaks so PeepSo doesn't crash during auto login sessions
	 *
	 * @access  public
	 * @return  void
	 */

	public function started_auto_login( $user_id, $contact_id ) {

		if ( class_exists( 'peepsolimitusers' ) ) {

			remove_action( 'peepso_init', array( peepsolimitusers::get_instance(), 'init' ) );

		}

	}


	/**
	 * Push changes to user meta on change in member role.
	 *
	 * @access  public
	 * @return  void
	 */

	public function role_changed( $user_id, $new_role ) {

		wp_fusion()->user->push_user_meta( $user_id, array( 'peepso_role' => $new_role ) );

	}

	/**
	 * Filter PeepSo account tab fields
	 *
	 * @access  public
	 * @return  void
	 */

	public function save_profile( $user_id ) {

		$post_data = $_POST;

		$field_map = array(
			'change_password' => 'user_pass',
			'user_nicename'   => 'user_login',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		wp_fusion()->user->push_user_meta( $user_id, $post_data );

	}

	/**
	 * Update the user's PeepSo role if it's been modified
	 *
	 * @access  public
	 * @return  void
	 */

	public function maybe_set_role( $user_id, $user_meta ) {

		if ( ! empty( $user_meta['peepso_role'] ) ) {

			$user = PeepSoUser::get_instance( $user_id );
			$role = $user->get_user_role();

			$valid_roles = array( 'member', 'admin', 'ban', 'register', 'verified' );

			if ( $user_meta['peepso_role'] != $role && in_array( $user_meta['peepso_role'], $valid_roles ) ) {

				remove_action( 'peepso_action_user_role_change', array( $this, 'role_changed' ), 10, 2 );

				$user->set_user_role( $user_meta['peepso_role'] );

				add_action( 'peepso_action_user_role_change', array( $this, 'role_changed' ), 10, 2 );

			}
		}

	}

	/**
	 * Gets field labels from internal keys for multi-checkbox fields
	 *
	 * @access  public
	 * @return  array Field Value
	 */

	public function get_multiselect_labels( $field_key, $field_value ) {

		// Multi-selects
		$peepsouser     = PeepSoUser::get_instance( 0 );
		$profile_fields = new PeepSoProfileFields( $peepsouser );
		$fields         = $profile_fields->load_fields();

		foreach ( $fields as $field ) {

			if ( $field->key == $field_key ) {

				foreach ( $field->meta->select_options as $key => $value ) {

					foreach ( $field_value as $field_value_key => $field_value_value ) {

						if ( $field_value_value == $key ) {

							$field_value[ $field_value_key ] = $value;

						}
					}
				}
			}
		}

		return $field_value;

	}


	/**
	 * Push changes to user meta on profile update and registration
	 *
	 * @access  public
	 * @return  void
	 */

	public function user_update( $field ) {

		if ( ! isset( $_POST['user_id'] ) ) {
			return;
		}

		$field_value = $field->value;
		$field_key   = $field->key;

		wp_fusion()->user->push_user_meta( intval( $_POST['user_id'] ), array( $field_key => $field_value ) );

	}

	/**
	 * Triggered when new user registered or profile updated
	 *
	 * @access  public
	 * @return  array Post Data
	 */

	public function user_register( $post_data, $user_id ) {

		$PeepSoUser     = PeepSoUser::get_instance( 0 );
		$profile_fields = new PeepSoProfileFields( $PeepSoUser );
		$fields         = $profile_fields->load_fields();

		foreach ( $fields as $field_key => $field ) {

			if ( isset( $post_data[ $field->input_args['name'] ] ) ) {

				$post_data[ $field_key ] = $post_data[ $field->input_args['name'] ];

			}

			if ( ! empty( $post_data[ $field_key ] ) ) {

				// Formatting stuff

				if ( is_a( $field, 'PeepSoFieldSelectMulti' ) ) {

					// Multi-select

					foreach ( $field->meta->select_options as $key => $value ) {

						foreach ( $post_data[ $field_key ] as $field_value_key => $field_value_value ) {

							if ( $field_value_value == $key ) {

								$post_data[ $field_key ][ $field_value_key ] = $value;

							}
						}
					}
				} elseif ( is_a( $field, 'PeepSoFieldSelectSingle' ) ) {

					// Single select

					foreach ( $field->meta->select_options as $key => $value ) {

						if ( $post_data[ $field_key ] == $key ) {
							$post_data[ $field_key ] = $value;
						}
					}
				}
			}
		}

		$field_map = array(
			'password'                     => 'user_pass',
			'username'                     => 'user_login',
			'peepso_user_field_first_name' => 'first_name',
			'peepso_user_field_last_name'  => 'last_name',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		return $post_data;

	}

	/**
	 * Apply linked tag when user joins group
	 *
	 * @access public
	 * @return void
	 */

	public function join_group( $group_id, $user_id ) {

		$settings = get_option( 'wpf_peepso_settings', array() );

		if ( ! empty( $settings[ $group_id ] ) && ! empty( $settings[ $group_id ]['tag_link'] ) ) {

			remove_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

			wp_fusion()->user->apply_tags( $settings[ $group_id ]['tag_link'], $user_id );

			add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		}

	}

	/**
	 * Remove linked tag when user leaves group
	 *
	 * @access public
	 * @return void
	 */

	public function leave_group( $group_id, $user_id ) {

		$settings = get_option( 'wpf_peepso_settings', array() );

		if ( ! empty( $settings[ $group_id ] ) && ! empty( $settings[ $group_id ]['tag_link'] ) ) {

			remove_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

			wp_fusion()->user->remove_tags( $settings[ $group_id ]['tag_link'], $user_id );

			add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		}

	}


	/**
	 * Updates user's groups based on linked tags when tags are modified
	 *
	 * @access public
	 * @return void
	 */

	public function update_group_access( $user_id, $user_tags ) {

		$settings = get_option( 'wpf_peepso_settings', array() );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		if ( class_exists( 'PeepSoVIPPlugin' ) ) {

			$current_icons = get_the_author_meta( 'peepso_vip_user_icon', $user_id );

			if ( ! is_array( $current_icons ) ) {
				$current_icons = array();
			}
		}

		foreach ( $settings as $group_id => $setting ) {

			if ( empty( $setting ) || empty( $setting['tag_link'] ) ) {
				continue;
			}

			// Groups
			if ( class_exists( 'PeepSoGroups' ) ) {

				$user     = new PeepSoGroupUser( $group_id, $user_id );
				$follower = new PeepSoGroupFollower( $group_id, $user_id );

				if ( $user->is_member == false && in_array( $setting['tag_link'][0], $user_tags ) ) {

					// Enroll
					wpf_log( 'info', $user_id, 'User auto-enrolled in PeepSo group <strong>' . get_the_title( $user->group_id ) . '</strong> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $setting['tag_link'][0] ) . '</strong>', array( 'source' => 'peepso' ) );
					$user->member_join();

				} elseif ( $user->is_member == true && ! in_array( $setting['tag_link'][0], $user_tags ) ) {

					// Un-enroll
					wpf_log( 'info', $user_id, 'User removed from PeepSo group <strong>' . get_the_title( $user->group_id ) . '</strong> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $setting['tag_link'][0] ) . '</strong>', array( 'source' => 'peepso' ) );
					$user->member_leave();
					$follower->delete();

				}
			}

			// VIP Icons
			if ( class_exists( 'PeepSoVIPPlugin' ) && strpos( $group_id, 'vip_' ) !== false ) {

				$icon_id = str_replace( 'vip_', '', $group_id );

				if ( in_array( $setting['tag_link'][0], $user_tags ) && ! in_array( $icon_id, $current_icons ) ) {

					$current_icons[] = $icon_id;
					update_user_meta( $user_id, 'peepso_vip_user_icon', $current_icons );

				} elseif ( ! in_array( $setting['tag_link'][0], $user_tags ) && ( $key = array_search( $icon_id, $current_icons ) ) !== false ) {

					unset( $current_icons[ $key ] );
					update_user_meta( $user_id, 'peepso_vip_user_icon', $current_icons );

				}
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

		$crm = wp_fusion()->crm->name;

		if ( class_exists( 'PeepSoGroups' ) || class_exists( 'PeepSoVIPPlugin' ) ) {

			$id = add_submenu_page(
				'peepso',
				$crm . ' Integration',
				'WP Fusion',
				'manage_options',
				'peepso-wpf-settings',
				array( $this, 'render_admin_menu' )
			);

			add_action( 'load-' . $id, array( $this, 'enqueue_scripts' ) );

		}

	}

	/**
	 * Enqueues WPF scripts and styles on MM options page
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
	 * Renders WPF submenu item
	 *
	 * @access public
	 * @return mixed
	 */

	public function render_admin_menu() {

		// Save settings
		if ( isset( $_POST['wpf_peepso_settings_nonce'] ) && wp_verify_nonce( $_POST['wpf_peepso_settings_nonce'], 'wpf_peepso_settings' ) ) {
			update_option( 'wpf_peepso_settings', $_POST['wpf-settings'] );
			echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
		}

		$settings = get_option( 'wpf_peepso_settings', array() );

		?>

		<div class="wrap">
			<h2><?php echo wp_fusion()->crm->name; ?> Integration</h2>

			<form id="wpf-peepso-settings" action="" method="post" style="margin-bottom: 100px;">
				<?php wp_nonce_field( 'wpf_peepso_settings', 'wpf_peepso_settings_nonce' ); ?>
				<input type="hidden" name="action" value="update">

				<?php if ( class_exists( 'PeepSoGroups' ) ) : ?>

					<h4>Group Tags</h4>
					<p class="description">For each PeepSo group, you can specify a linked tag in <?php echo wp_fusion()->crm->name; ?>. When this tag is applied, the user will be added to the group. When this tag is removed, the user will be removed from the group.</p>
					<br/>

					<?php
					
					$settings = get_option( 'wpf_peepso_settings', array() );

					if ( empty( $settings ) ) {
						$settings = array();
					}

					?>

					<?php $groups = PeepSoGroups::admin_get_groups( 0, null ); ?>

					<style> .select4-container { min-width: 300px; } </style>

					<table class="table table-hover" id="wpf-peepso-groups-table">
						<thead>
						<tr>
							<th>Group</th>
							<th>Tag Link</th>
						</tr>
						</thead>
						<tbody>

							<?php foreach ( $groups as $group ) : ?>

								<tr>
									<td><?php echo $group->name; ?></td>
									<td>
										<?php

										if ( ! isset( $settings[ $group->id ] ) ) {
											$settings[ $group->id ] = array(
												'tag_link' => array(),
											);
										}

										$args = array(
											'setting'   => $settings[ $group->id ]['tag_link'],
											'meta_name' => "wpf-settings[{$group->id}][tag_link]",
											'limit'     => 1,
										);

										wpf_render_tag_multiselect( $args );

										?>

									</td>
								</tr>

							<?php endforeach; ?>

						</tbody>

					</table>

				<?php endif; ?>

				<?php if ( class_exists( 'PeepSoVIPPlugin' ) ) : ?>

					<h4>VIP Icon Tags</h4>
					<p class="description">For each VIP icon, you can specify a linked tag in <?php echo wp_fusion()->crm->name; ?>. When this tag is applied, the user will given the icon. When this tag is removed, the icon will be removed.</p>
					<br/>

					<?php $PeepSoVipIconsModel = new PeepSoVipIconsModel(); ?>

					<style> .select4-container { min-width: 300px; } </style>

					<table class="table table-hover" id="wpf-peepso-groups-table">
						<thead>
						<tr>
							<th>Icon</th>
							<th>Tag Link</th>
						</tr>
						</thead>
						<tbody>

							<?php foreach ( $PeepSoVipIconsModel->vipicons as $key => $value ) : ?>

								<tr>
									<td><?php echo $value->title; ?></td>
									<td>
										<?php

										$args = array(
											'setting'   => $settings[ 'vip_' . $key ]['tag_link'],
											'meta_name' => "wpf-settings[vip_{$key}][tag_link]",
											'limit'     => 1,
										);

										wpf_render_tag_multiselect( $args );

										?>

									</td>
								</tr>

							<?php endforeach; ?>

						</tbody>

					</table>

				<?php endif; ?>

				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes"/></p>

			</form>

		</div>

		<?php

	}

}

new WPF_PeepSo();
