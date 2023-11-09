<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_BuddyPress extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'buddypress';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name;

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/buddypress/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */
	public function init() {

		if ( function_exists( 'bp_rest_namespace' ) && 'buddyboss' === bp_rest_namespace() ) {
			$this->name = 'BuddyBoss';
		} else {
			$this->name = 'BuddyPress';
		}

		add_filter( 'wpf_redirect_post_id', array( $this, 'get_bb_page_id' ) );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 30 );

		// Loading XProfile data
		add_filter( 'wpf_set_user_meta', array( $this, 'set_user_meta' ), 10, 2 );

		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );

		// 8 so it runs before WPF_ACF::user_update() merges the acf() array into the post data.
		add_filter( 'wpf_user_register', array( $this, 'user_update' ), 8, 2 );
		add_filter( 'wpf_user_update', array( $this, 'user_update' ), 8, 2 );
		add_filter( 'wpf_get_user_meta', array( $this, 'get_user_meta' ), 10, 2 );

		// Auto login
		add_action( 'wpf_started_auto_login', array( $this, 'started_auto_login' ), 10, 2 );

		// Settings
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

		// Defer until activation
		add_action( 'bp_signup_usermeta', array( $this, 'before_user_registration' ) );
		add_action( 'bp_core_activated_user', array( $this, 'after_user_activation' ), 20, 3 ); // 20 so all the normal BP stuff has run

		// Profile updates
		add_action( 'xprofile_updated_profile', array( $this, 'updated_profile' ), 10, 5 );
		add_action( 'profile_update', array( $this, 'sync_email_address_changes' ), 10, 2 );
		add_action( 'xprofile_avatar_uploaded', array( $this, 'sync_avatars' ), 10, 3 );

		// Profile completion
		add_filter( 'xprofile_pc_user_progress_formatted', array( $this, 'apply_profile_complete_tags' ) );

		if ( wpf_get_option( 'restrict_content', true ) ){
			// Profile tabs (via BuddyPress User Profile Tabs Creator Pro)
			add_action( 'add_meta_boxes_bpptc_profile_tab', array( $this, 'add_meta_box_profile_tabs' ) );
			add_filter( 'bp_core_create_nav_link', array( $this, 'filter_profile_tabs' ) );
		}

		// Group tag linking
		add_action( 'bp_groups_admin_meta_boxes', array( $this, 'add_meta_box_groups' ) );
		add_action( 'bp_group_admin_edit_after', array( $this, 'save_groups_data' ), 20 );
		add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 9, 2 ); // This is 9 so that the user is in the correct groups by the time LearnDash (maybe) runs to update their courses

		// Group joins and leaves
		add_action( 'groups_join_group', array( $this, 'join_group' ), 10, 2 );
		add_action( 'groups_accept_invite', array( $this, 'groups_accept_invite' ), 10, 2 );
		add_action( 'groups_membership_accepted', array( $this, 'groups_accept_invite' ), 10, 2 );
		add_action( 'groups_leave_group', array( $this, 'leave_group' ), 10, 2 );
		add_action( 'groups_remove_member', array( $this, 'leave_group' ), 10, 2 );

		// Organizer tags
		add_action( 'groups_promote_member', array( $this, 'add_tags_to_organizer' ), 10, 3 );
		add_action( 'groups_demote_member', array( $this, 'remove_tags_from_organizer' ), 10, 2 );

		// Group visibility
		if ( wpf_get_option( 'restrict_content', true ) ){
			add_filter( 'wpf_restrict_content_post_type_object_label', array( $this, 'groups_meta_box_object_label' ), 10, 2 );
			add_action( 'wpf_meta_box_content', array( $this, 'groups_meta_box_notice' ), 5, 2 );
			add_filter( 'wpf_settings_for_meta_box', array( $this, 'groups_meta_box_settings' ), 10, 2 );
			add_filter( 'wpf_post_access_meta', array( $this, 'groups_access_meta' ) );
			add_action( 'wpf_filtering_page_content', array( $this, 'filter_group_content' ) );
		}

		// Group types

		if ( function_exists( 'bp_groups_get_group_type_post_type' ) ) {

			add_action( 'add_meta_boxes_' . bp_groups_get_group_type_post_type(), array( $this, 'add_meta_box_group_types' ) );
			add_action( 'save_post', array( $this, 'save_group_type_meta_box_data' ) );

		}

		if ( wpf_get_option( 'restrict_content', true ) ){
			// BuddyBoss access control
			add_filter( 'bb_get_access_control_lists', array( $this, 'get_access_control_lists' ) );
			add_filter( 'groups_get_group_potential_invites_requests_args', array( $this, 'invites_requests_args' ) );
		}

		// BuddyBoss app
		add_action( 'init', array( $this, 'load_app_integration' ) );
		add_action( 'bp_rest_xprofile_update_items', array( $this, 'rest_xprofile_updated' ), 10, 3 );

		// Username changer
		add_action( 'bp_username_changed', array( $this, 'username_changed' ), 10, 3 );

		// Profile type tag linking
		add_action( 'add_meta_boxes', array( $this, 'add_profile_type_meta_box' ), 10, 2 );
		add_action( 'save_post_bp-member-type', array( $this, 'save_profile_type_meta_box_data' ), 10 );
		add_action( 'wpf_tags_modified', array( $this, 'update_profile_types' ), 10, 2 );
		add_action( 'set_object_terms', array( $this, 'set_member_type' ), 10, 6 );
		add_action( 'bp_remove_member_type', array( $this, 'remove_member_type' ), 10, 2 );

		// Filter activity stream for restricted items
		if ( wpf_get_option( 'restrict_content', true ) ){
			add_filter( 'bp_activity_get', array( $this, 'filter_activity_stream' ), 10, 2 );
		}

		// LearnDash group sync compatibility
		add_action( 'groups_member_after_save', array( $this, 'groups_member_after_save' ) );
		add_action( 'ld_removed_group_access', array( $this, 'removing_group_access' ), 5, 2 ); // 5 so it runs before removed_group_access in WPF_LearnDash

		// Meta fields
		add_filter( 'wpf_user_meta_shortcode_value', array( $this, 'user_meta_shortcode_value' ), 10, 2 );

		// Export functions
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_buddypress_groups_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_buddypress_groups', array( $this, 'batch_step_groups' ) );

		add_action( 'wpf_batch_buddyboss_profile_types_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_buddyboss_profile_types', array( $this, 'batch_step_profile_types' ) );

	}

	/**
	 * Remove tags from an organizer if he is demoted.
	 *
	 * @since 3.38.9
	 *
	 * @param int $group_id The group ID.
	 * @param int $user_id  The user ID.
	 */
	public function remove_tags_from_organizer( $group_id, $user_id ) {
		// If organizer tags empty then skip.
		$settings = groups_get_groupmeta( $group_id, 'wpf-settings-buddypress' );

		if ( empty( $settings ) || empty( $settings['organizer_tag'] ) ) {
			return;
		}

		// Prevent looping.

		remove_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		wp_fusion()->user->remove_tags( $settings['organizer_tag'], $user_id );

		add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

	}


	/**
	 * Add tags to a group member if he is promoted to an organizer.
	 *
	 * @since 3.38.9
	 *
	 * @param int    $group_id The group ID.
	 * @param int    $user_id  The user ID.
	 * @param string $status   The status.
	 */
	public function add_tags_to_organizer( $group_id, $user_id, $status ) {

		if ( 'admin' !== $status ) {
			return;
		}

		// If organizer tags empty then skip.
		$settings = groups_get_groupmeta( $group_id, 'wpf-settings-buddypress' );

		if ( empty( $settings ) || empty( $settings['organizer_tag'] ) ) {
			return;
		}

		// Prevent looping.

		remove_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		wp_fusion()->user->apply_tags( $settings['organizer_tag'], $user_id );

		add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );
	}

	/**
	 * Gets page ID for BBP core pages
	 *
	 * @access  public
	 * @return  int Post ID
	 */

	public function get_bb_page_id( $post_id ) {

		if ( $post_id != 0 ) {
			return $post_id;
		}

		global $bp;
		$post = get_page_by_path( $bp->unfiltered_uri[0] );

		if ( ! empty( $post ) ) {
			return $post->ID;
		}

		return $post_id;

	}

	/**
	 * Adds Buddypress field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['buddypress'] ) ) {
			$field_groups['buddypress'] = array(
				'title'  => 'BuddyPress',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Loads XProfile fields for inclusion in Contact Fields table
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['bbp_profile_type'] = array(
			'label' => 'Profile Type',
			'type'  => 'text',
			'group' => 'buddypress',
		);

		if ( ! class_exists( 'BP_XProfile_Data_Template' ) ) {
			return $meta_fields;
		}

		$meta_fields['bbp_avatar'] = array(
			'label' => 'Avatar URL',
			'type'  => 'text',
			'group' => 'buddypress',
		);

		$groups = bp_xprofile_get_groups(
			array(
				'fetch_fields' => true,
			)
		);

		foreach ( $groups as $group ) {

			if ( ! empty( $group->fields ) ) {

				foreach ( $group->fields as $field ) {

					if ( $field->type == 'checkbox' ) {
						$type = 'multiselect';
					} elseif ( $field->type == 'multiselect_custom_taxonomy' ) {
						$type = 'multiselect';
					} elseif ( $field->type == 'multiselectbox' ) {
						$type = 'multiselect';
					} elseif ( $field->type == 'datebox' ) {
						$type = 'date';
					} else {
						$type = 'text';
					}

					$meta_fields[ 'bbp_field_' . $field->id ] = array(
						'label' => $field->name,
						'type'  => $type,
						'group' => 'buddypress',
					);

				}
			}
		}

		return $meta_fields;

	}

	/**
	 * Triggered before registration, allows removing WPF create_user hook when
	 * Defer Until Activation is enabled.
	 *
	 * This used to be on bp_signup_pre_validate but was moved to the
	 * bp_signup_usermeta filter in v3.38.1 because bp_signup_pre_validate
	 * doesn't run for users added over the REST API (i.e. the BuddyBoss app).
	 *
	 * @since  3.38.1  Moved to bp_signup_usermeta hook.
	 * @since  3.38.41 Added additional remove_action()s for user role changes.
	 *
	 * @param  array $user_meta The user meta.
	 * @return array The user meta.
	 */
	public function before_user_registration( $user_meta ) {

		if ( wpf_get_option( 'bp_defer' ) ) {
			remove_action( 'user_register', array( wp_fusion()->user, 'user_register' ), 20 );

			// Stop role changes after registrattion triggering user_register() in WPF_User::add_remove_user_role().
			remove_action( 'set_user_role', array( wp_fusion()->user, 'add_remove_user_role' ), 10, 2 );
			remove_action( 'add_user_role', array( wp_fusion()->user, 'add_remove_user_role' ), 10, 2 );
		}

		return $user_meta;

	}

	/**
	 * Triggered after activation, syncs the new user to the CRM
	 *
	 * @access public
	 * @return void
	 */

	public function after_user_activation( $user_id, $key, $user ) {

		if ( wpf_get_option( 'bp_defer' ) ) {

			if ( is_array( $user ) ) {
				$user_meta = $user['meta'];
			} else {
				$user_meta = false;
			}
			wp_fusion()->user->user_register( $user_id, $user_meta );

		}

	}


	/**
	 * Filters updates to profile form
	 *
	 * @access  public
	 * @return  array Post Data
	 */

	public function user_update( $post_data, $user_id ) {

		$field_map = array(
			'pass1'           => 'user_pass',
			'email'           => 'user_email',
			'first-name'      => 'first_name',
			'last-name'       => 'last_name',
			'signup_username' => 'user_login',
			'signup_password' => 'user_pass',
			'signup_email'    => 'user_email',
		);

		// BuddyBoss only.
		if ( function_exists( 'bp_xprofile_lastname_field_id' ) ) {
			$field_map[ 'field_' . bp_xprofile_lastname_field_id() ]      = 'last_name';
			$field_map[ 'field_' . bp_xprofile_firstname_field_id() ]     = 'first_name';
			$field_map[ 'field_' . bp_xprofile_nickname_field_id() ]      = 'nickname';
			$field_map[ 'bbp_field_' . bp_xprofile_lastname_field_id() ]  = 'last_name';
			$field_map[ 'bbp_field_' . bp_xprofile_firstname_field_id() ] = 'first_name';
			$field_map[ 'bbp_field_' . bp_xprofile_nickname_field_id() ]  = 'nickname';
		}

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		// Clean up XProfile fields.

		foreach ( $post_data as $field_id => $value ) {

			if ( strpos( $field_id, 'field_' ) === 0 ) {

				if ( is_array( $value ) ) {
					$post_data[ 'bbp_' . $field_id ] = array_map( 'wp_strip_all_tags', $value );
				} else {
					$post_data[ 'bbp_' . $field_id ] = wp_strip_all_tags( $value, true );
				}
			}
		}

		// buddypress()->profile->table_name_groups — If this function is run too early the table name isn't defined and an SQL warning is thrown

		if ( ! class_exists( 'BP_XProfile_Data_Template' ) || ! isset( buddypress()->profile->table_name_groups ) ) {
			return $post_data;
		}

		$r = array(
			'user_id'      => $user_id,
			'member_type'  => 'any',
			'fetch_fields' => true,
		);

		$profile_template = new BP_XProfile_Data_Template( $r );

		foreach ( $profile_template->groups as $group ) {

			if ( ! empty( $group->fields ) ) {

				foreach ( $group->fields as $field ) {

					if ( 'multiselect_custom_taxonomy' === $field->type && isset( $post_data[ 'bbp_field_' . $field->id ] ) ) {

						if ( is_array( $post_data[ 'bbp_field_' . $field->id ] ) ) {

							foreach ( $post_data[ 'bbp_field_' . $field->id ] as $i => $term_id ) {

								if ( ! is_numeric( $term_id ) ) {
									continue;
								}

								$term = get_term( $term_id );

								if ( $term == null ) {
									continue;
								}

								$post_data[ 'bbp_field_' . $field->id ][] = $term->name;

								unset( $post_data[ 'bbp_field_' . $field->id ][ $i ] );

							}

							$post_data[ 'bbp_field_' . $field->id ] = array_values( $post_data[ 'bbp_field_' . $field->id ] );

						} elseif ( is_numeric( $post_data[ 'bbp_field_' . $field->id ] ) ) {

							$term = get_term( $term_id );

							if ( $term != null ) {
								$post_data[ 'bbp_field_' . $field->id ] = $term->name;
							}
						}
					} elseif ( 'gender' === $field->type && ! empty( $post_data[ "bbp_field_{$field->id}" ] ) ) {

						$parts = explode( '_', $post_data[ "bbp_field_{$field->id}" ] );

						// Remove the her_ / his_ prefix.

						if ( isset( $parts[1] ) ) {
							$post_data[ "bbp_field_{$field->id}" ] = $parts[1];
						}
					} elseif ( 'membertypes' === $field->type && ! empty( $post_data[ "bbp_field_{$field->id}" ] ) ) {

						if ( function_exists( 'bp_get_member_type_key' ) ) {

							$member_type = bp_get_member_type_key( $post_data[ "bbp_field_{$field->id}" ] );
							$member_type = bp_get_member_type_object( $member_type );

							if ( is_object( $member_type ) ) {
								$post_data[ "bbp_field_{$field->id}" ] = $member_type->labels['singular_name'];
							}
						}
					}
				}
			}
		}

		return $post_data;

	}

	/**
	 * Gets XProfile fields from the database when exporting metadata
	 *
	 * @access  public
	 * @return  array User Meta
	 */

	public function get_user_meta( $user_meta, $user_id ) {

		global $wpdb;
		$bp = buddypress();

		$user_data = $wpdb->get_results( $wpdb->prepare( "SELECT field_id, value FROM {$bp->profile->table_name_data} WHERE user_id = %d", $user_id ), 'ARRAY_A' );

		foreach ( $user_data as $field ) {

			$data = maybe_unserialize( $field['value'] );

			// Clean up special characters.

			if ( is_array( $data ) ) {
				$data = array_map( 'htmlspecialchars_decode', $data );
			} else {
				$data = htmlspecialchars_decode( $data );
			}

			$user_meta[ 'bbp_field_' . $field['field_id'] ] = $data;
		}

		// Get the avatar.

		$avatar_url = bp_core_fetch_avatar(
			array(
				'object'  => 'user',
				'item_id' => $user_id,
				'html'    => false,
				'type'    => 'full',
			)
		);

		if ( $avatar_url ) {
			$user_meta['bbp_avatar'] = $avatar_url;
		}

		// Member type.

		if ( function_exists( 'bp_get_member_type' ) ) {

			$member_type = bp_get_member_type( $user_id );
			$member_type = bp_get_member_type_object( $member_type );

			if ( is_object( $member_type ) ) {
				$user_meta['bbp_profile_type'] = $member_type->labels['singular_name'];
			}
		}

		return $user_meta;

	}

	/**
	 * Set some BP cache stuff so that auto-login doesn't throw errors
	 *
	 * @access  public
	 * @return  void
	 */

	public function started_auto_login( $user_id, $contact_id ) {

		wp_cache_set( $user_id, array(), 'bp_member_member_type' );

	}

	/**
	 * Triggered when an XProfile is updated. Syncs the data to the CRM.
	 *
	 * @since 3.38.14
	 *
	 * @param int   $user_id    The user ID.
	 * @param array $field_ids  The field IDs.
	 * @param bool  $errors     The errors.
	 * @param array $old_values The old values.
	 * @param array $new_values The new values.
	 */
	public function updated_profile( $user_id, $field_ids = array(), $errors = false, $old_values = array(), $new_values = array() ) {

		$user_meta = array();

		foreach ( $new_values as $field_id => $value ) {
			$user_meta[ "field_{$field_id}" ] = $value['value'];
		}

		wp_fusion()->user->push_user_meta( $user_id, $user_meta );

		// With BuddyBoss only, profile_update is also triggered, so we'll remove that here.
		remove_action( 'profile_update', array( wp_fusion()->user, 'profile_update' ), 10, 2 );

	}


	/**
	 * Triggered when email changed
	 *
	 * @access  public
	 * @return  void
	 */

	public function sync_email_address_changes( $user_id, $old_user_data ) {

		$user = get_user_by( 'id', $user_id );

		if ( $user->user_email !== $old_user_data->user_email ) {
			wp_fusion()->user->push_user_meta( $user_id, array( 'user_email' => $user->user_email ) );
		}

	}

	/**
	 * Sync avatars when they've been uploaded
	 *
	 * @access  public
	 * @since   3.35.7
	 * @return  void
	 */

	public function sync_avatars( $user_id, $avatar_type, $avatar_data = false ) {

		if ( empty( $avatar_data ) ) {
			return;
		}

		$avatar_url = bp_core_fetch_avatar(
			array(
				'object'  => $avatar_data['object'],
				'item_id' => $avatar_data['item_id'],
				'html'    => false,
				'type'    => 'full',
			)
		);

		wp_fusion()->user->push_user_meta( $user_id, array( 'bbp_avatar' => $avatar_url ) );

	}

	/**
	 * Apply profile complete tags
	 *
	 * @access  public
	 * @return  array Progress Details
	 */

	public function apply_profile_complete_tags( $progress_details ) {

		if ( ! wpf_is_user_logged_in() ) {
			return $progress_details;
		}

		$user_id  = wpf_get_current_user_id();
		$complete = get_user_meta( $user_id, 'wpf_bp_profile_complete', true );

		if ( 100 === (int) $progress_details['completion_percentage'] && empty( $complete ) ) {

			wp_fusion()->user->apply_tags( wpf_get_option( 'bp_apply_tags_profile_complete' ) );

			update_user_meta( $user_id, 'wpf_bp_profile_complete', current_time( 'Y-m-d H:i:s' ) );

		} elseif ( 100 > (int) $progress_details['completion_percentage'] && ! empty( $complete ) ) {

			// Complete flag is set but profile is not complete.

			delete_user_meta( $user_id, 'wpf_bp_profile_complete' );

		}

		return $progress_details;

	}

	/**
	 * Triggered when user meta is loaded from the CRM or when a user is imported
	 *
	 * @access  public
	 * @return  array User Mtea
	 */

	public function set_user_meta( $user_meta, $user_id ) {

		if ( ! class_exists( 'BP_XProfile_ProfileData' ) ) {
			return $user_meta;
		}

		foreach ( $user_meta as $key => $value ) {

			if ( strpos( $key, 'bbp_field_' ) !== false ) {

				$field_id = str_replace( 'bbp_field_', '', $key );

				$field        = new BP_XProfile_ProfileData( $field_id, $user_id );
				$field->value = $value;
				$field->save();

				unset( $user_meta[ $key ] );

			}
		}

		return $user_meta;

	}

	/**
	 * Removes standard WPF meta boxes from BP related post types
	 *
	 * @access  public
	 * @return  array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['bp-member-type'] );
		unset( $post_types['bp-group-type'] );

		return $post_types;

	}

	/**
	 * Adds meta boxes to the profile tab editor
	 *
	 * @since  3.36.7
	 */

	public function add_meta_box_profile_tabs() {

		add_meta_box( 'wpf-meta', __( 'WP Fusion - Profile Tab', 'wp-fusion' ), array( $this, 'meta_box_callback_profile_tabs' ), 'bpptc_profile_tab', 'side', 'core' );

	}

	/**
	 * If profile tab is disabled, hide it
	 *
	 * @since  3.36.7
	 *
	 * @param  array $nav_item The navigation item.
	 * @return array|false The navigation item, or false if access is denied.
	 */

	public function filter_profile_tabs( $nav_item ) {

		if ( ! function_exists( 'bpptc_get_post_type' ) ) {
			return $nav_item;
		}

		$post = get_page_by_path( $nav_item['slug'], OBJECT, bpptc_get_post_type() );

		if ( is_object( $post ) && ! wp_fusion()->access->user_can_access( $post->ID ) ) {

			// If the user can't access, remove the item
			$nav_item = false;

		}

		return $nav_item;

	}

	/**
	 * Adds meta boxes to the profile tab editor.
	 *
	 * @since 3.36.7
	 *
	 * @param WP_Post $post   The post.
	 * @return mixed The HTML settings output.
	 */

	public function meta_box_callback_profile_tabs( $post ) {

		$defaults = array(
			'lock_content' => false,
			'allow_tags'   => array(),
		);

		$settings = wp_parse_args( get_post_meta( $post->ID, 'wpf-settings', true ), $defaults );

		wp_nonce_field( 'wpf_meta_box', 'wpf_meta_box_nonce' );

		echo '<input class="checkbox wpf-restrict-access-checkbox" type="checkbox" data-unlock="wpf-settings-allow_tags wpf-settings-allow_tags_all" id="wpf-lock-content" name="wpf-settings[lock_content]" value="1" ' . checked( $settings['lock_content'], 1, false ) . ' /> <label for="wpf-lock-content" class="wpf-restrict-access">';
		esc_html_e( 'Restrict access to this tab', 'wp-fusion' );
		echo '</label>';

		echo '<p class="wpf-required-tags-select"><label for="wpf-settings-buddypress-allow_tags">' . __( 'Required tags (any)', 'wp-fusion' ) . ':';

		echo '<span class="dashicons dashicons-editor-help wpf-tip wpf-tip-bottom" data-tip="' . __( 'If the user does not have any of these tags, the profile tab will be hidden.', 'wp-fusion' ) . '"></span></label>';

		$args = array(
			'setting'   => $settings['allow_tags'],
			'meta_name' => 'wpf-settings',
			'field_id'  => 'allow_tags',
			'read_only' => true,
		);

		wpf_render_tag_multiselect( $args );

		echo '</p>';

	}



	/**
	 * Adds meta boxes to groups
	 *
	 * @access public
	 * @return mixed
	 */

	public function add_meta_box_groups() {

		add_meta_box( 'wpf-buddypress-meta', 'WP Fusion - Group Settings', array( $this, 'meta_box_callback_groups' ), get_current_screen()->id );

		add_meta_box( 'wpf-meta', __( 'WP Fusion', 'wp-fusion' ), array( wp_fusion()->admin_interfaces, 'meta_box_callback' ), get_current_screen()->id, 'side', 'core' );

	}


	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback_groups() {

		$gid = absint( $_GET['gid'] );

		$settings = array(
			'apply_tags'    => array(),
			'tag_link'      => array(),
			'organizer_tag' => array(),
		);

		if ( groups_get_groupmeta( $gid, 'wpf-settings-buddypress' ) ) {
			$settings = array_merge( $settings, groups_get_groupmeta( $gid, 'wpf-settings-buddypress' ) );
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags">' . __( 'Apply Tags', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags'],
			'meta_name' => 'wpf-settings-buddypress',
			'field_id'  => 'apply_tags',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'Select tags to apply when a user joins this group.', 'wp-fusion' ) . '</span>';

		echo '</td>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">' . __( 'Link with Tag', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['tag_link'],
			'meta_name' => 'wpf-settings-buddypress',
			'field_id'  => 'tag_link',
			'limit'     => 1,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'Select a tag to link with this group. When the tag is applied, the user will automatically be enrolled. When the tag is removed the user will be unenrolled.', 'wp-fusion' ) . '</span>';

		echo '<br />';
		echo '<p class="wpf-notice notice notice-warning">' . __( '<strong>Warning:</strong> Users can choose to leave a social group. If a user leaves this group, the linked tag will be removed. For this reason it\'s recommended <em>not to link this tag to anything else</em> (for example a course).', 'wp-fusion' ) . '</p>';

		echo '</td>';

		echo '</tr>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">' . __( 'Link with Tag - Group Organizer', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['organizer_tag'],
			'meta_name' => 'wpf-settings-buddypress',
			'field_id'  => 'organizer_tag',
			'limit'     => 1,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'When the linked tag is applied, the user will automatically be added to the group and promoted to organizer. When the tag is removed, the user will be demoted from organizer to regular group member.', 'wp-fusion' ) . '</span>';

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

	public function save_groups_data( $post_id ) {

		if ( isset( $_POST['bp-groups-slug'] ) ) {

			// Groups auto-enrollment

			if ( ! empty( $_POST['wpf-settings-buddypress'] ) ) {
				groups_update_groupmeta( $post_id, 'wpf-settings-buddypress', $_POST['wpf-settings-buddypress'] );
			} else {
				groups_delete_groupmeta( $post_id, 'wpf-settings-buddypress' );
			}

			// Groups visibility

			if ( ! empty( $_POST['wpf-settings'] ) ) {
				groups_update_groupmeta( $post_id, 'wpf-settings', $_POST['wpf-settings'] );
			} else {
				groups_delete_groupmeta( $post_id, 'wpf-settings' );
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

		// Don't bother if groups component is disabled.
		if ( ! function_exists( 'groups_get_groups' ) ) {
			return;
		}

		// Allow searching for hidden groups even if not logged in.

		add_filter(
			'bp_groups_get_paged_groups_sql',
			function( $sql ) {
				return str_replace( "g.status != 'hidden'", "g.status LIKE '%'", $sql );
			}
		);

		$linked_groups = groups_get_groups(
			array(
				'nopaging'    => true,
				'show_hidden' => true,
				'per_page'    => null, // show all groups.
				'meta_query'  => array(
					array(
						'key'     => 'wpf-settings-buddypress',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		// Update course access based on user tags

		$user_tags = wp_fusion()->user->get_tags( $user_id ); // Get them here for cases where the tags might have changed since wpf_tags_modified was triggered.

		// Prevent looping

		remove_action( 'groups_join_group', array( $this, 'join_group' ), 10, 2 );
		remove_action( 'groups_leave_group', array( $this, 'leave_group' ), 10, 2 );

		foreach ( $linked_groups['groups'] as $group ) {

			$settings = groups_get_groupmeta( $group->id, 'wpf-settings-buddypress' );

			if ( empty( $settings ) ) {
				continue;
			}

			// Organizer tag
			if ( ! empty( $settings['organizer_tag'] ) ) {
				$tag_id = $settings['organizer_tag'][0];

				if ( in_array( $tag_id, $user_tags ) && ! groups_is_user_member( $user_id, $group->id ) ) {

					wpf_log( 'info', $user_id, 'Adding user to BuddyPress group <a href="' . admin_url( 'admin.php?page=bp-groups&gid=' . $group->id . '&action=edit' ) . '">' . $group->name . '</a> and making them an organizer, from linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

					groups_join_group( $group->id, $user_id );
					groups_promote_member( $user_id, $group->id, 'admin' );

				} elseif ( in_array( $tag_id, $user_tags ) && groups_is_user_member( $user_id, $group->id ) && ! groups_is_user_admin( $user_id, $group->id ) ) {

					wpf_log( 'info', $user_id, 'Promoting user to an organizer in Budypress group <a href="' . admin_url( 'admin.php?page=bp-groups&gid=' . $group->id . '&action=edit' ) . '">' . $group->name . '</a>, from linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );
					groups_promote_member( $user_id, $group->id, 'admin' );

				} elseif ( ! in_array( $tag_id, $user_tags ) && groups_is_user_member( $user_id, $group->id ) && groups_is_user_admin( $user_id, $group->id ) ) {

					wpf_log( 'info', $user_id, 'Removing organizer role from member in group <a href="' . admin_url( 'admin.php?page=bp-groups&gid=' . $group->id . '&action=edit' ) . '">' . $group->name . '</a>, from linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );
					groups_demote_member( $user_id, $group->id );

				}
			}

			// Tag link.
			if ( ! empty( $settings['tag_link'] ) ) {

				$tag_id = $settings['tag_link'][0];

				$user_groups    = bp_get_user_groups( $user_id );
				$user_group_ids = wp_list_pluck( $user_groups, 'group_id' );

				if ( in_array( $tag_id, $user_tags ) && ! in_array( $group->id, $user_group_ids ) ) {

					wpf_log( 'info', $user_id, 'Adding user to BuddyPress group <a href="' . admin_url( 'admin.php?page=bp-groups&gid=' . $group->id . '&action=edit' ) . '">' . $group->name . '</a>, from linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

					groups_join_group( $group->id, $user_id );

				} elseif ( ! in_array( $tag_id, $user_tags ) && in_array( $group->id, $user_group_ids ) ) {

					wpf_log( 'info', $user_id, 'Removing user from BuddyPress group <a href="' . admin_url( 'admin.php?page=bp-groups&gid=' . $group->id . '&action=edit' ) . '">' . $group->name . '</a>, from linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

					groups_leave_group( $group->id, $user_id );

				}
			}
		}

		add_action( 'groups_join_group', array( $this, 'join_group' ), 10, 2 );
		add_action( 'groups_leave_group', array( $this, 'leave_group' ), 10, 2 );

	}

	/**
	 * Runs when user has joined group and applies any linked tags
	 *
	 * @access public
	 * @return void
	 */

	public function join_group( $group_id, $user_id ) {

		$settings = groups_get_groupmeta( $group_id, 'wpf-settings-buddypress' );

		$apply_tags = array();

		if ( ! empty( $settings ) && ! empty( $settings['tag_link'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['tag_link'] );
		}

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags'] );
		}

		// Group types (only available in BuddyBoss)

		if ( function_exists( 'bp_groups_get_group_type' ) && function_exists( 'bp_group_get_group_type_id' ) ) {

			$type = bp_groups_get_group_type( $group_id );

			if ( ! empty( $type ) ) {

				$type_id = bp_group_get_group_type_id( $type );

				$settings = get_post_meta( $type_id, 'wpf_settings_buddypress', true );

				if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags'] );
				}
			}
		}

		if ( ! empty( $apply_tags ) ) {

			remove_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

			wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		}

	}

	/**
	 * Runs when user has accepted an invite to join a group
	 *
	 * @access public
	 * @return void
	 */

	public function groups_accept_invite( $user_id, $group_id ) {

		$this->join_group( $group_id, $user_id );

	}

	/**
	 * Runs when user leaves group and removes any linked tags
	 *
	 * @access public
	 * @return void
	 */

	public function leave_group( $group_id, $user_id ) {

		$settings = groups_get_groupmeta( $group_id, 'wpf-settings-buddypress' );

		if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
			return;
		}

		remove_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

		wpf_log( 'info', $user_id, 'User left BuddyPress group <a href="' . admin_url( 'admin.php?page=bp-groups&gid=' . $group_id . '&action=edit' ) . '">#' . $group_id . '</a>. Removing linked tag.' );

		wp_fusion()->user->remove_tags( $settings['tag_link'], $user_id );

		add_action( 'wpf_tags_modified', array( $this, 'update_group_access' ), 10, 2 );

	}


	/**
	 * Set the post type label for the groups visibility meta box.
	 *
	 * @since  3.36.7
	 *
	 * @param  string  $post_object_label The post object label.
	 * @param  WP_Post $post              The post object.
	 * @return string  The label.
	 */
	public function groups_meta_box_object_label( $post_object_label, $post ) {

		if ( is_a( $post, 'BP_Groups_Group' ) ) {
			$post_object_label = 'group';
		}

		return $post_object_label;

	}



	/**
	 * Meta box notice.
	 *
	 * Output a notice when the group is inheriting access rules from the Groups page.
	 *
	 * @since 3.37.0
	 *
	 * @param WP_Post $post     The post.
	 * @param array   $settings The WP Fusion settings from the post.
	 */
	public function groups_meta_box_notice( $post, $settings ) {

		if ( false === strpos( get_current_screen()->id, 'bp-groups' ) ) {
			return;
		}

		$groups_page_id = bp_core_get_directory_page_id( 'groups' );

		$wpf_settings = get_post_meta( $groups_page_id, 'wpf-settings', true );

		if ( ! empty( $wpf_settings ) && ! empty( $wpf_settings['lock_content'] ) ) {

			echo '<div class="wpf-metabox-notice">';

			printf( __( '<strong>Note:</strong> This group is inheriting access rules from the %1$smain Groups directory page%2$s.', 'wp-fusion' ), '<a href="' . get_edit_post_link( $groups_page_id ) . '">', '</a>' );

			if ( ! empty( $wpf_settings['allow_tags'] ) ) {

				$required_tags = array_map( array( wp_fusion()->user, 'get_tag_label' ), $wpf_settings['allow_tags'] );

				echo '<span class="notice-required-tags">' . sprintf( __( 'Required tags: %s', 'wp-fusion' ), implode( ', ', $required_tags ) ) . '</span>';
			}

			echo '</div>';

		}

	}


	/**
	 * Load the group access settings from the wp_bp_groups_groupmeta table.
	 *
	 * @since  3.36.7
	 *
	 * @param  array   $settings The access settings.
	 * @param  WP_Post $post     The post object.
	 * @return array   The settings.
	 */
	public function groups_meta_box_settings( $settings, $post ) {

		if ( is_a( $post, 'BP_Groups_Group' ) ) {
			$settings = wp_parse_args( groups_get_groupmeta( $post->id, 'wpf-settings' ), WPF_Admin_Interfaces::$meta_box_defaults );
		}

		return $settings;

	}

	/**
	 * Load the group access settings from the wp_bp_groups_groupmeta table
	 * during the check to user_can_access()
	 *
	 * @since  3.36.7
	 * @since  3.36.12 Check for empty lock_content to avoid this setting taking
	 *                 priority over menu or widget restrictions when viewing the
	 *                 group.
	 *
	 * @param  array $settings The access settings.
	 * @return array The settings.
	 */
	public function groups_access_meta( $settings ) {

		if ( bp_is_group() && empty( $settings['lock_content'] ) ) {

			$group          = groups_get_current_group();
			$group_settings = groups_get_groupmeta( $group->id, 'wpf-settings' );

			if ( ! empty( $group_settings ) ) {
				return $group_settings;
			}
		}

		return $settings;

	}

	/**
	 * If access to a group is denied and no redirect is set, replace the group
	 * content area with the restricted content message.
	 *
	 * @since 3.36.7
	 * @since 3.37.0 Content will now be replaced with the restricted content
	 *                message, and it now runs on the groups directory page as well.
	 *
	 * @param int $post_id The ID of the post to filter the content for.
	 */
	public function filter_group_content( $post_id ) {

		if ( bp_is_groups_directory() || bp_is_group() ) {

			add_filter( 'bp_replace_the_content', array( wp_fusion()->access, 'get_restricted_content_message' ) );
		}

	}


	/**
	 * Adds meta box to group types
	 *
	 * @access public
	 * @return mixed
	 */

	public function add_meta_box_group_types() {

		add_meta_box( 'wpf-buddypress-meta', 'WP Fusion - Group Type Settings', array( $this, 'meta_box_callback_group_types' ) );

	}

	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback_group_types() {

		global $post;

		$settings = array(
			'apply_tags' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf_settings_buddypress', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf_settings_buddypress', true ) );
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags">' . __( 'Apply tags', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags'],
			'meta_name' => 'wpf_settings_buddypress',
			'field_id'  => 'apply_tags',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'Apply these tags when a user joins a group of this type.', 'wp-fusion' ) . '</span>';
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

	public function save_group_type_meta_box_data( $post_id ) {

		if ( isset( $_POST['post_type'] ) && $_POST['post_type'] == 'bp-group-type' ) {

			if ( isset( $_POST['wpf_settings_buddypress'] ) ) {
				update_post_meta( $post_id, 'wpf_settings_buddypress', $_POST['wpf_settings_buddypress'] );
			} else {
				delete_post_meta( $post_id, 'wpf_settings_buddypress' );
			}
		}

	}


	/**
	 * Registers WP Fusion's access control module in BuddyBoss Platform Pro.
	 *
	 * @since  3.36.10
	 *
	 * @param  array $access_controls The access controls.
	 * @return array The access controls.
	 */
	public function get_access_control_lists( $access_controls ) {

		$access_controls['wp_fusion'] = array(
			'label'      => sprintf( __( '%s Tag', 'wp-fusion' ), wp_fusion()->crm->name ) . ' (' . __( 'WP Fusion', 'wp-fusion' ) . ')',
			'is_enabled' => true,
			'class'      => WPF_BuddyBoss_Access_Control::class,
		);

		return $access_controls;

	}

	/**
	 * Get the elligible user IDs for group invites.
	 *
	 * @since  3.37.13
	 *
	 * @param  array $requests The requests.
	 * @return array The requests.
	 */
	public function invites_requests_args( $requests ) {

		if ( ! function_exists( 'bb_access_control_join_group_settings' ) ) {
			return $requests; // BuddyBoss Platorm Pro only
		}

		$join_group_settings = bb_access_control_join_group_settings();

		if ( empty( $join_group_settings ) || ( isset( $join_group_settings['access-control-type'] ) && empty( $join_group_settings['access-control-type'] ) ) ) {
			return $requests;
		} elseif ( is_array( $join_group_settings ) && isset( $join_group_settings['access-control-type'] ) && 'wp_fusion' == $join_group_settings['access-control-type'] ) {

			$user_query = array(
				'fields'     => 'ID',
				'meta_query' => array(
					'relation' => 'OR',
				),
			);

			foreach ( $join_group_settings['access-control-options'] as $tag ) {

				$user_query['meta_query'][] = array(
					'key'     => WPF_TAGS_META_KEY,
					'value'   => '"' . $tag . '"',
					'compare' => 'LIKE',
				);

			}

			$user_ids = get_users( $user_query );

			if ( ! empty( $user_ids ) ) {
				$user_ids = array_unique( bb_access_control_array_flatten( $user_ids ) );
				$user_ids = implode( ',', $user_ids );
			} else {
				$user_ids = true;
			}

			return wp_parse_args(
				$requests,
				array(
					'include' => $user_ids,
				)
			);
		}

		return $requests;

	}

	/**
	 * Load the BuddyBoss app integration module
	 *
	 * @since  3.37.0
	 */
	public function load_app_integration() {

		if ( class_exists( 'WPF_BuddyBoss_IAP' ) ) {

			WPF_BuddyBoss_IAP::instance()->set_up( 'wp-fusion', 'WP Fusion' );

			bbapp_iap()->integrations['wp_fusion'] = array(
				'type'    => 'wp-fusion',
				'label'   => sprintf( __( '%s Tag', 'wp-fusion' ), wp_fusion()->crm->name ) . ' (' . __( 'WP Fusion', 'wp-fusion' ) . ')',
				'enabled' => true,
				'class'   => WPF_BuddyBoss_IAP::class,
			);

			wp_fusion()->integrations->{'buddyboss-app'} = WPF_BuddyBoss_IAP::class;

		}

	}

	/**
	 * REST XProfile Updated.
	 *
	 * Runs when a profile is updated in the BuddyBoss app.
	 *
	 * @since 3.37.26
	 *
	 * @param BP_XProfile_Field $field    Created field object.
	 * @param WP_REST_Response  $response The response data.
	 * @param WP_REST_Request   $request  The request sent to the API.
	 */
	public function rest_xprofile_updated( $field_groups, $response, $request ) {

		$fields = $request->get_param( 'fields' );

		$update_data = array();

		foreach ( $fields as $k => $field_post ) {

			$field_id = ( isset( $field_post['field_id'] ) && ! empty( $field_post['field_id'] ) ) ? $field_post['field_id'] : '';
			$value    = ( isset( $field_post['value'] ) && ! empty( $field_post['value'] ) ) ? $field_post['value'] : '';

			if ( empty( $field_id ) ) {
				continue;
			}

			$field = xprofile_get_field( $field_id );

			if ( 'checkbox' === $field->type || 'multiselectbox' === $field->type ) {
				if ( is_serialized( $value ) ) {
					$value = maybe_unserialize( $value );
				}

				$value = json_decode( wp_json_encode( $value ), true );

				if ( ! is_array( $value ) ) {
					$value = (array) $value;
				}
			}

			// Format social network value.
			if ( 'socialnetworks' === $field->type ) {
				if ( is_serialized( $value ) ) {
					$value = maybe_unserialize( $value );
				}
			}

			$update_data[ "bbp_field_{$field_id}" ] = $value;
		}

		wp_fusion()->user->push_user_meta( bp_loggedin_user_id(), $update_data );

	}

	/**
	 * Support for the BP Username Changer addon
	 *
	 * @access public
	 * @return void
	 */

	public function username_changed( $new_username, $userdata, $user ) {

		wp_fusion()->user->push_user_meta( $user->ID, array( 'user_login' => $new_username ) );

	}

	/**
	 * Adds profile type meta box
	 *
	 * @access public
	 * @return mixed
	 */

	public function add_profile_type_meta_box( $post_id, $data ) {

		add_meta_box( 'wpf-buddypress-profile-type-meta', 'WP Fusion - Profile Type Settings', array( $this, 'meta_box_callback_profile_type' ), 'bp-member-type' );

	}

	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback_profile_type( $post ) {

		$defaults = array(
			'tag_link' => array(),
		);

		$settings = get_post_meta( $post->ID, 'wpf_settings_buddypress', true );

		if ( ! empty( $settings ) ) {
			$settings = array_merge( $defaults, $settings );
		} else {
			$settings = $defaults;
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">' . __( 'Link with Tag', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['tag_link'],
			'meta_name' => 'wpf_settings_buddypress',
			'field_id'  => 'tag_link',
			'limit'     => 1,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'Select a tag to link with this profile type. When the tag is applied the user will automatically be given the profile type. When the tag is removed the profile type will be removed.', 'wp-fusion' ) . '</span>';
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

	public function save_profile_type_meta_box_data( $post_id ) {

		// Profile types

		if ( isset( $_POST['wpf_settings_buddypress'] ) ) {
			$data = WPF_Admin_Interfaces::sanitize_tags_settings( $_POST['wpf_settings_buddypress'] );
			update_post_meta( $post_id, 'wpf_settings_buddypress', $data );
		} else {
			delete_post_meta( $post_id, 'wpf_settings_buddypress' );
		}

	}

	/**
	 * Update user profile type assignments when tags are modified
	 *
	 * @access public
	 * @return void
	 */

	public function update_profile_types( $user_id, $user_tags ) {

		if ( ! function_exists( 'bp_get_member_type' ) || ! function_exists( 'bp_member_type_enable_disable' ) ) {
			return;
		}

		if ( false === bp_member_type_enable_disable() ) {
			return;
		}

		$linked_types = get_posts(
			array(
				'post_type'  => 'bp-member-type',
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf_settings_buddypress',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		// Update profile types based on user tags

		if ( ! empty( $linked_types ) ) {

			$user_tags = wp_fusion()->user->get_tags( $user_id ); // Get them here for cases where the tags might have changed since wpf_tags_modified was triggered.

			$member_types = bp_get_member_type( $user_id, false );

			if ( empty( $member_types ) ) {
				$member_types = array();
			}

			//
			// Add types
			//

			foreach ( $linked_types as $type ) {

				// Since adding a type automatically removes the other types, we'll start with that

				$settings = get_post_meta( $type->ID, 'wpf_settings_buddypress', true );

				if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
					continue;
				}

				$linked_tag_id = $settings['tag_link'][0];
				$type_key      = bp_get_member_type_key( $type->ID );

				if ( in_array( $linked_tag_id, $user_tags ) && ! in_array( $type_key, $member_types ) ) {

					wpf_log( 'info', $user_id, 'Adding user to BuddyPress profile type <a href="' . admin_url( 'post.php?post=' . $type->ID . '&action=edit' ) . '">' . $type->post_title . '</a> by tag <strong>' . wp_fusion()->user->get_tag_label( $linked_tag_id ) . '</strong>' );

					// Prevent looping.

					remove_action( 'set_object_terms', array( $this, 'set_member_type' ), 10, 2 );

					$result = bp_set_member_type( $user_id, $type_key );

					if ( false === $result ) {
						wpf_log( 'error', $user_id, 'Unable to add user to profile type ' . $type->post_name . ', because it is an invalid profile type.' );
					} elseif ( is_wp_error( $result ) ) {
						wpf_log( 'error', $user_id, 'Error adding user to profile type ' . $result->get_error_message() );
					}

					// Sync the name.

					$update_data = array(
						'bbp_profile_type' => $type->post_title,
					);

					wp_fusion()->user->push_user_meta( $user_id, $update_data );

					add_action( 'set_object_terms', array( $this, 'set_member_type' ), 10, 3 );

					return; // Someone can only be in one type at a time, so let's quit here.

				} elseif ( in_array( $linked_tag_id, $user_tags ) && in_array( $type->post_name, $member_types ) ) {

					// If the user has the tag and is in the type, we can also quit, since there's nothing more to be done.

					return;

				}
			}

			//
			// Remove types
			//

			if ( doing_action( 'user_register' ) || doing_action( 'bp_core_activated_user' ) ) {
				return; // Don't remove any types as part of the signup or activation process.
			}

			foreach ( $linked_types as $type ) {

				$type_key = bp_get_member_type_key( $type->ID );

				// Now handle cases where a tag might have been removed and we need to remove the type.

				$settings = get_post_meta( $type->ID, 'wpf_settings_buddypress', true );

				if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
					continue;
				}

				if ( ! in_array( $linked_tag_id, $user_tags ) && in_array( $type_key, $member_types ) ) {

					$default = bp_member_type_default_on_registration();

					$msg = 'Removing user from BuddyPress profile type <a href="' . admin_url( 'post.php?post=' . $type->ID . '&action=edit' ) . '">' . $type->post_title . '</a> by tag <strong>' . wp_fusion()->user->get_tag_label( $linked_tag_id ) . '</strong>';

					if ( ! empty( $default ) ) {
						$msg .= ', setting default profile type.';
					}

					wpf_log( 'info', $user_id, $msg );

					// Prevent looping.

					remove_action( 'set_object_terms', array( $this, 'set_member_type' ), 10, 2 );

					if ( ! empty( $default ) ) {

						// Set them to the site default.
						bp_set_member_type( $user_id, $default );

						$update_data = array(
							'bbp_profile_type' => $default,
						);

					} else {

						// Remove the type (no default available so they'll be left type-less).

						bp_set_member_type( $user_id, false );

						$update_data = array(
							'bbp_profile_type' => null,
						);

					}

					wp_fusion()->user->push_user_meta( $user_id, $update_data ); // sync the type.

					add_action( 'set_object_terms', array( $this, 'set_member_type' ), 10, 2 );

					return; // Someone can only be in one type at a time, so let's quit here.

				}
			}
		}

	}

	/**
	 * Apply linked tags when a profile type is set
	 *
	 * @access public
	 * @return void
	 */

	public function set_member_type( $user_id, $terms, $tt_ids = array(), $taxonomy = false, $append = false, $old_tt_ids = array() ) {

		if ( ! function_exists( 'bp_member_type_post_by_type' ) ) {
			return;
		}

		if ( false === $taxonomy || bp_get_member_type_tax_name() !== $taxonomy ) {
			return;
		}

		if ( empty( $terms ) ) {
			return; // nothing was added.
		}

		$new_type_id = bp_member_type_post_by_type( $terms[0] );

		if ( empty( $new_type_id ) ) {
			return; // couldn't find the new profile type.
		}

		// Sometimes the member type is set before WPF has synced the new user
		// to the CRM, so we'll wait until that's finished.

		if ( ( doing_action( 'user_register' ) || doing_action( 'bp_core_activated_user' ) ) && ! did_action( 'wpf_user_created' ) ) {

			add_action(
				'wpf_user_created',
				function( $user_id ) use ( $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {

					$this->set_member_type( $user_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids );

				}
			);

			return;

		}

		// Sync the name.

		$new_member_type = bp_get_member_type_object( $terms[0] );

		$update_data = array(
			'bbp_profile_type' => $new_member_type->labels['singular_name'],
		);

		wp_fusion()->user->push_user_meta( $user_id, $update_data );

		// Update the tags. First, prevent looping.

		remove_action( 'wpf_tags_modified', array( $this, 'update_profile_types' ), 10, 2 );

		// Maybe remove tags from the previous type.

		if ( false === $append && ! empty( $old_tt_ids ) ) {

			$old_member_type = bp_get_term_by( 'term_taxonomy_id', $old_tt_ids[0] );
			$old_type_id     = bp_member_type_post_by_type( $old_member_type->name );
			$old_member_type = bp_get_member_type_object( $old_member_type->name );

			// Maybe remove linked tags from the old type.

			$settings = get_post_meta( $old_type_id, 'wpf_settings_buddypress', true );

			if ( ! empty( $settings ) && ! empty( $settings['tag_link'] ) ) {
				wpf_log( 'info', $user_id, 'User removed from BuddyPress profile type <a href="' . admin_url( 'post.php?post=' . $old_type_id . '&action=edit' ) . '">' . $old_member_type->labels['singular_name'] . '</a>. Removing tags.' );
				wp_fusion()->user->remove_tags( $settings['tag_link'], $user_id );
			}
		}

		// Now maybe apply the tags for this type.

		$settings = get_post_meta( $new_type_id, 'wpf_settings_buddypress', true );

		if ( ! empty( $settings ) && ! empty( $settings['tag_link'] ) ) {
			wpf_log( 'info', $user_id, 'User added to BuddyPress profile type <a href="' . admin_url( 'post.php?post=' . $new_type_id . '&action=edit' ) . '">' . $new_member_type->labels['singular_name'] . '</a>. Applying tags.' );
			wp_fusion()->user->apply_tags( $settings['tag_link'], $user_id );
		}

		add_action( 'wpf_tags_modified', array( $this, 'update_profile_types' ), 10, 2 );

	}

	/**
	 * Remove linked tags when a profile type is removed
	 *
	 * @access public
	 * @return void
	 */

	public function remove_member_type( $user_id, $member_type ) {

		if ( ! function_exists( 'bp_member_type_post_by_type' ) ) {
			return;
		}

		$type_id = bp_member_type_post_by_type( $member_type );

		if ( empty( $type_id ) ) {
			return;
		}

		$settings = get_post_meta( $type_id, 'wpf_settings_buddypress', true );

		if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
			return;
		}

		// Prevent looping

		remove_action( 'wpf_tags_modified', array( $this, 'update_profile_types' ), 10, 2 );

		wpf_log( 'info', $user_id, 'User removed from BuddyPress profile type <a href="' . admin_url( 'post.php?post=' . $type_id . '&action=edit' ) . '">' . $member_type->labels['singular_name'] . '</a>. Removing linked tag.' );

		wp_fusion()->user->remove_tags( $settings['tag_link'], $user_id );

		add_action( 'wpf_tags_modified', array( $this, 'update_profile_types' ), 10, 2 );

	}

	/**
	 * Removes posts from restricted forums from BuddyBoss activity stream
	 *
	 * @access public
	 * @return array Activity
	 */

	public function filter_activity_stream( $activity, $args ) {

		if ( wpf_get_option( 'hide_archives' ) ) {

			foreach ( $activity['activities'] as $i => $item ) {

				// Generic filtering on secondary item ID

				if ( ! empty( $item->secondary_item_id ) && ! wp_fusion()->access->user_can_access( $item->secondary_item_id ) ) {
					unset( $activity['activities'][ $i ] );
				}
			}

			// Clean up the array after unsetting stuff
			$activity['activities'] = array_values( $activity['activities'] );

		}

		return $activity;

	}

	/**
	 * If we're syncing a LearnDash group enrollment to BuddyPress, make sure that the linked tag from the group is applied
	 *
	 * @access public
	 * @return void
	 */

	public function groups_member_after_save( $group_member ) {

		global $bp_ld_sync__syncing_to_buddypress;

		if ( true == $bp_ld_sync__syncing_to_buddypress ) {

			$this->join_group( $group_member->group_id, $group_member->user_id );

		}

	}

	/**
	 * Prevent linked tags from getting removed if a BB group member is promoted to organizer or mod in a BB group that's linked to an LD group that has an auto-enrollment tag
	 *
	 * @access public
	 * @return void
	 */

	public function removing_group_access( $user_id, $group_id ) {

		global $bp_ld_sync__syncing_to_learndash;

		if ( true == $bp_ld_sync__syncing_to_learndash ) {

			remove_action( 'ld_removed_group_access', array( wp_fusion()->integrations->learndash, 'removed_group_access' ), 10, 2 );

		}

	}

	/**
	 * Allow displaying BuddyPress data using the [user_meta] shortcode
	 *
	 * @access public
	 * @return string Value
	 */

	public function user_meta_shortcode_value( $value, $field ) {

		if ( ! class_exists( 'BP_XProfile_ProfileData' ) ) {
			return $value;
		}

		$id = str_replace( 'bbp_field_', '', $field );

		$user_data = BP_XProfile_ProfileData::get_all_for_user( bp_loggedin_user_id() );

		if ( ! empty( $user_data ) ) {

			foreach ( $user_data as $name => $field_data ) {

				if ( is_array( $field_data ) && ( $field_data['field_id'] == $id || $name == $field ) ) {
					$value = $field_data['field_data'];
				}
			}
		}

		return $value;

	}



	/**
	 * BuddyPress-specific settings.
	 *
	 * @since  3.29.5
	 *
	 * @param  array $settings The settings.
	 * @param  array $options  The options.
	 * @return array The settings.
	 */
	public function register_settings( $settings, $options ) {

		// BuddyPress uses the username on the frontend of the site so we'll set the default for newly imported users to be FirstnameLastname

		$settings['username_format']['std'] = 'flname';

		$settings['bp_header'] = array(
			'title'   => sprintf( __( '%s Integration', 'wp-fusion' ), $this->name ),
			'url'     => 'https://wpfusion.com/documentation/membership/buddypress/',
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['bp_defer'] = array(
			'title'   => __( 'Defer Until Activation', 'wp-fusion' ),
			'desc'    => sprintf( __( 'Don\'t send any data to %s until the account has been activated, either by an administrator or via email activation.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		$settings['bp_apply_tags_profile_complete'] = array(
			'title'   => __( 'Apply Tags - Profile Complete', 'wp-fusion' ),
			'desc'    => __( 'The selected tags will be applied when a user reaches 100% profile completeness (using the Profile Completion Widget).', 'wp-fusion' ),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		return $settings;

	}


	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds WooCommerce checkbox to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		if ( function_exists( 'groups_get_groups' ) ) {

			$options['buddypress_groups'] = array(
				'label'   => __( 'BuddyPress groups statuses', 'wp-fusion' ),
				'title'   => __( 'Users', 'wp-fusion' ),
				'tooltip' => __( 'Applies tags to all group members based on their current group and group type enrollments, using the WP Fusion settings for each group. <br /><br />Does not trigger any automated group enrollments.', 'wp-fusion' ),
			);

		}

		if ( function_exists( 'bp_get_member_type' ) && function_exists( 'bp_member_type_enable_disable' ) ) {

			$options['buddyboss_profile_types'] = array(
				'label'   => __( 'BuddyBoss profile type statuses', 'wp-fusion' ),
				'title'   => __( 'Users', 'wp-fusion' ),
				'tooltip' => __( 'Applies tags to all members based on their current profile type assignments, using the WP Fusion settings on each profile type. <br /><br />Does not assign or remove any profile types.', 'wp-fusion' ),
			);

		}

		return $options;

	}

	/**
	 * Counts total number of users to be processed
	 *
	 * @access public
	 * @return int Count
	 */

	public function batch_init() {

		$args = array(
			'fields' => 'ID',
		);

		$users = get_users( $args );

		return $users;

	}

	/**
	 * Checks groups for each user and applies tags
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step_groups( $user_id ) {

		$groups = bp_get_user_groups( $user_id );

		if ( ! empty( $groups ) ) {

			foreach ( $groups as $group ) {

				$this->join_group( $group->group_id, $user_id );

			}
		}

	}


	/**
	 * Checks profile types for each user and applies tags
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step_profile_types( $user_id ) {

		$member_types = bp_get_member_type( $user_id, false );

		if ( ! empty( $member_types ) ) {

			foreach ( $member_types as $type ) {

				$this->set_member_type( $user_id, $type );

			}
		}

	}


}

new WPF_BuddyPress();
