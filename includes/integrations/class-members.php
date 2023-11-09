<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Members_Plugin extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'members';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Members';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/members/';

	/**
	 * Gets things started.
	 *
	 * @since 3.37.3
	 */

	public function init() {

		// Update roles / tags based on settings
		add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );

		add_action( 'add_user_role', array( $this, 'add_user_role' ), 10, 2 );
		add_action( 'remove_user_role', array( $this, 'remove_user_role' ), 10, 2 );

		add_action( 'wpf_user_created', array( $this, 'user_created' ) );

		// Show Metabox
		add_action( 'members_load_role_edit', array( $this, 'load' ) );
		add_action( 'members_load_role_new', array( $this, 'load' ) );

		// Save role position.
		add_action( 'members_role_updated', array( $this, 'save_meta_box_data' ) );
		add_action( 'members_role_added', array( $this, 'save_meta_box_data' ) );

		// Remove role meta
		add_action( 'admin_init', array( $this, 'remove_meta_role' ) );

	}



	/**
	 * Updates user's role if tag linked to a Members role is changed.
	 *
	 * @since 3.37.3
	 *
	 * @param int   $user_id   The user ID.
	 * @param array $user_tags The user tags.
	 */
	public function tags_modified( $user_id, $user_tags ) {

		global $wp_roles;

		$user = get_userdata( $user_id );

		// Prevent looping
		remove_action( 'add_user_role', array( $this, 'add_user_role' ), 10, 2 );
		remove_action( 'remove_user_role', array( $this, 'remove_user_role' ), 10, 2 );

		foreach ( $wp_roles->role_names as $slug => $label ) {

			$settings = get_option( 'members_role_' . $slug . '_meta' );

			if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
				continue;
			}

			$tag_id = $settings['tag_link'][0];

			if ( in_array( $tag_id, $user_tags ) && ! in_array( $slug, $user->roles ) && ! user_can( $user_id, 'manage_options' ) ) {

				// Logger
				wpf_log( 'info', $user_id, 'Setting Members role <a href="' . admin_url( 'admin.php?page=roles&action=edit&role=' . $slug ) . '">' . $label . '</a> from linked tag <strong>' . wpf_get_tag_label( $tag_id ) . '</strong>' );

				$user->add_role( $slug );

			} elseif ( ! in_array( $tag_id, $user_tags ) && in_array( $slug, $user->roles ) && ! user_can( $user_id, 'manage_options' ) ) {

				// Logger
				wpf_log( 'info', $user_id, 'Removing Members role <a href="' . admin_url( 'admin.php?page=roles&action=edit&role=' . $slug ) . '">' . $label . '</a> from linked tag <strong>' . wpf_get_tag_label( $tag_id ) . '</strong>' );

				$user->remove_role( $slug );

				if ( empty( $user->roles ) ) {

					// We don't want to leave someone with no role so we'll assign the default role
					$default_role = get_option( 'default_role' );

					wpf_log( 'info', $user_id, 'User was left with no role, so assigning default role <strong>' . $default_role . '</strong>.' );

					$user->add_role( $default_role );
				}
			}
		}

		add_action( 'add_user_role', array( $this, 'add_user_role' ), 10, 2 );
		add_action( 'remove_user_role', array( $this, 'remove_user_role' ), 10, 2 );

	}

	/**
	 * Apply the tags when the role is added.
	 *
	 * Note that if BuddyPress is active, the
	 * bp_assign_default_member_type_to_activate_user_on_admin function runs
	 * during registration and removes the Members roles / sets the default site
	 * role.
	 *
	 * @since 3.37.3
	 * @since 3.37.21 No longer running during user_register.
	 *
	 * @param int   $user_id The user ID.
	 * @param array $role    The role.
	 */
	public function add_user_role( $user_id, $role ) {

		if ( doing_action( 'user_register' ) ) {
			return;
		}

		$settings = get_option( 'members_role_' . $role . '_meta', array() );

		if ( ! empty( $settings['tag_link'] ) ) {

			wpf_log( 'info', $user_id, 'Members role <a href="' . admin_url( 'admin.php?page=roles&action=edit&role=' . $role ) . '">' . members_get_role( $role )->get( 'label' ) . '</a> added, applying linked tag.' );

			// Prevent looping
			remove_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );

			wp_fusion()->user->apply_tags( $settings['tag_link'], $user_id );

			add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );
		}

	}

	/**
	 * Remove the tags when the role is removed.
	 *
	 * @since 3.37.3
	 * @since 3.37.21 No longer running during user_register.
	 *
	 * @param int   $user_id The user ID
	 * @param array $role    The role.
	 */
	public function remove_user_role( $user_id, $role ) {

		if ( doing_action( 'user_register' ) ) {
			return;
		}

		$settings = get_option( 'members_role_' . $role . '_meta', array() );

		if ( ! empty( $settings['tag_link'] ) ) {

			wpf_log( 'info', $user_id, 'Members role <a href="' . admin_url( 'admin.php?page=roles&action=edit&role=' . $role ) . '">' . members_get_role( $role )->get( 'label' ) . '</a> removed, removing linked tag.' );

			remove_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );

			wp_fusion()->user->remove_tags( $settings['tag_link'], $user_id );

			add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );
		}

	}

	/**
	 * Remove role meta when the role is removed.
	 *
	 * @since 3.37.3
	 */
	public function remove_meta_role() {

		// Get the current action if sent as request.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : false;

		// Get the current action if posted.
		if ( ( isset( $_POST['action'] ) && 'delete' == $_POST['action'] ) || ( isset( $_POST['action2'] ) && 'delete' == $_POST['action2'] ) ) {
			$action = 'bulk-delete';
		}

		// Bulk delete role handler.
		if ( 'bulk-delete' === $action && ! empty( $_POST['roles'] ) ) {

			// If roles were selected, let's delete some roles.
			if ( current_user_can( 'delete_roles' ) ) {

				// Verify the nonce. Nonce created via `WP_List_Table::display_tablenav()`.
				check_admin_referer( 'bulk-roles' );

				// Loop through each of the selected roles.
				foreach ( $_POST['roles'] as $role ) {

					$role = members_sanitize_role( $role );

					if ( members_role_exists( $role ) ) {
						delete_option( "members_role_{$role}_meta" );
					}
				}
			}

			// Delete single role handler.
		} elseif ( 'delete' === $action && isset( $_GET['role'] ) && isset( $_GET['members_delete_role_nonce'] ) ) {

			// Make sure the current user can delete roles.
			if ( current_user_can( 'delete_roles' ) ) {

				// Verify the referer.
				check_admin_referer( 'delete_role', 'members_delete_role_nonce' );

				// Get the role we want to delete.
				$role = members_sanitize_role( $_GET['role'] );

				// Check that we have a role before attempting to delete it.
				if ( members_role_exists( $role ) ) {
					// Delete the role meta.
					delete_option( "members_role_{$role}_meta" );
				}
			}
		}
	}

	/**
	 * Runs after WP Fusion has finished processing a new user registration and
	 * applies any linked tags from the user's roles.
	 *
	 * @since 3.37.21
	 *
	 * @param int $user_id The user ID.
	 */
	public function user_created( $user_id ) {

		$user = new WP_User( $user_id );

		foreach ( $user->roles as $role ) {

			$settings = get_option( 'members_role_' . $role . '_meta', array() );

			if ( ! empty( $settings['tag_link'] ) ) {

				wpf_log( 'info', $user_id, 'Members role <a href="' . admin_url( 'admin.php?page=roles&action=edit&role=' . $role ) . '">' . members_get_role( $role )->get( 'label' ) . '</a> added, applying linked tag.' );

				// Prevent looping
				remove_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );

				wp_fusion()->user->apply_tags( $settings['tag_link'], $user_id );

				add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );
			}
		}

	}


	/**
	 * Runs on the page load hook to hook in the meta boxes.
	 *
	 * @since 3.37.3
	 */

	public function load() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}


	/**
	 * Adds meta box, only for Members role page.
	 *
	 * @since 3.37.3
	 *
	 * @param object $screen_id
	 * @param string $role
	 */
	public function add_meta_box( $screen_id, $role = '' ) {
		// If role isn't editable, bail.
		if ( $role && ! members_is_role_editable( $role ) ) {
			return;
		}

		add_meta_box( 'wpf-members-meta', 'WP Fusion', array( $this, 'meta_box_callback' ), $screen_id, 'side', 'core' );
	}

	/**
	 * Displays meta box content.
	 *
	 * @since 3.37.3
	 *
	 * @param string $role
	 */
	public function meta_box_callback( $role ) {
		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wpf_meta_box_members', 'wpf_meta_box_members_nonce' );

		$settings = array(
			'tag_link' => array(),
		);
		if ( isset( $_GET['role'] ) ) {
			$settings = wp_parse_args( get_option( "members_role_{$_GET['role']}_meta" ), $settings );
		}

		/*
		// Apply tags
		*/

		echo '<p><label><strong>' . sprintf( __( 'Link with %s tag', 'wp-fusion' ), wp_fusion()->crm->name ) . ':</strong></label></p>';

		$args = array(
			'setting'   => $settings['tag_link'],
			'meta_name' => 'members_tag',
			'field_id'  => 'tag_link',
			'limit'     => 1,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . sprintf( __( 'When the selected tag is applied, users will automatically be given the %s role.', 'wp-fusion' ), isset( $_GET['role'] ) ? '<strong>' . $_GET['role'] . '</strong>' : '' );
		echo '<br /><br />' . __( 'When the tag is removed the role will be removed.', 'wp-fusion' );

		echo '<br /><br />' . __( 'Likewise, when a user is added to this role the tag will be applied, and when a user is removed from this role the tag will be removed.', 'wp-fusion' ) . '</span>';

	}


	/**
	 * Saves Members meta box data
	 *
	 * @since 3.37.3
	 * @access public
	 * @return null
	 */

	public function save_meta_box_data() {
		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_members_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_members_nonce'], 'wpf_meta_box_members' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( isset( $_GET['role'] ) ) {
			$role_name = sanitize_text_field( $_GET['role'] );
		} else {
			$role_name = sanitize_text_field( $_POST['role_name'] );
		}

		if ( isset( $_POST['members_tag'] ) && isset( $_POST['members_tag']['tag_link'] ) ) {
			update_option( "members_role_{$role_name}_meta", $_POST['members_tag'], false );
		} else {
			delete_option( "members_role_{$role_name}_meta" );
		}
	}



}

new WPF_Members_Plugin();
