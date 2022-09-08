<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_UM extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'ultimate-member';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Ultimate Member';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/ultimate-member/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'set_contact_field_names' ), 30 );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'metabox_scripts' ) );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ) );
		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );
		add_filter( 'um_admin_role_metaboxes', array( $this, 'admin_role_metaboxes' ) );

		// Registration
		add_action( 'um_submit_form_register', array( $this, 'before_user_registration' ), 5 );
		add_action( 'um_registration_complete', array( $this, 'registration_global_hook' ), 20, 2 );
		add_action( 'um_after_user_is_approved', array( $this, 'after_user_is_approved' ) );
		add_action( 'um_user_register', array( $this, 'register_sync_password' ), 0, 2 );

		// Role changes
		add_action( 'set_user_role', array( $this, 'after_user_role_is_updated' ), 10, 3 );

		// Profile
		add_action( 'um_profile_before_header', array( $this, 'pull_profile_changes' ) );

		// Profile completeness
		add_action( 'um_profile_completeness_get_progress_result', array( $this, 'completeness_get_progress_result' ), 10, 2 );

		// Account
		add_action( 'um_after_user_account_updated', array( $this, 'user_account_updated' ), 5, 2 );
		add_action( 'um_after_changing_user_password', array( $this, 'password_reset' ) );

		// Callbacks and filters
		add_filter( 'wpf_bypass_profile_update', array( $this, 'bypass_profile_update' ), 10, 2 );
		add_filter( 'wpf_pulled_user_meta', array( $this, 'format_user_meta' ), 10, 2 );
		add_filter( 'wpf_user_register', array( $this, 'filter_form_fields' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'filter_form_fields' ), 10, 2 );

		add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );
		add_action( 'wpf_user_imported', array( $this, 'user_imported' ), 10, 2 );

	}

	/**
	 * Add fields to settings page
	 *
	 * @access public
	 * @return array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['um_header'] = array(
			'title'   => __( 'Ultimate Member Integration', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['um_pull'] = array(
			'title'   => __( 'Pull', 'wp-fusion' ),
			'desc'    => __( 'Update the local profile data for a given user from ' . wp_fusion()->crm->name . ' before displaying. May slow down profile load times.', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		$settings['um_defer'] = array(
			'title'   => __( 'Defer Until Activation', 'wp-fusion' ),
			'desc'    => __( 'Don\'t send any data to ' . wp_fusion()->crm->name . ' until the account has been activated, either by an administrator or via email activation.', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		$settings['deactivation_tag'] = array(
			'title'       => __( 'Deactivation Tag', 'wp-fusion' ),
			'desc'        => __( 'You can specify a tag here to be used as an account deactivation trigger.<br />When the tag is applied the account will be set to deactivated. When the tag is removed the account will be reactivated.', 'wp-fusion' ),
			'std'         => array(),
			'type'        => 'assign_tags',
			'section'     => 'integrations',
			'limit'       => 1,
			'placeholder' => __( 'Select tag', 'wp-fusion' ),
		);

		return $settings;

	}

	/**
	 * Adds UM field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['um'] ) ) {
			$field_groups['um'] = array(
				'title'  => 'Ultimate Member',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Set field labels from UM field labels
	 *
	 * @access public
	 * @return array Meta Fields
	 */

	public function set_contact_field_names( $meta_fields ) {

		$fields = UM()->classes['builtin']->{'all_user_fields'};

		foreach ( (array) $fields as $key => $field ) {

			if ( ! isset( $field['title'] ) ) {
				$field['title'] = '';
			}

			if ( 'checkbox' == $field['type'] ) {
				$field['type'] = 'multiselect';
			}

			$meta_fields[ $key ] = array(
				'label' => $field['title'],
				'type'  => $field['type'],
				'group' => 'um',
			);

		}

		return $meta_fields;

	}


	/**
	 * Removes standard WPF meta boxes from UM related post types
	 *
	 * @access  public
	 * @return  array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['um_form'] );
		unset( $post_types['um_role'] );
		unset( $post_types['um_directory'] );

		return $post_types;

	}

	/**
	 * Adds metabox to admin edit user roles on UM V-2
	 *
	 * @access  public
	 * @return  array Roles Metaboxes
	 */

	public function admin_role_metaboxes( $roles_metaboxes ) {

			$roles_metaboxes[] = array(
				'id'       => 'um-admin-form-admin',
				'title'    => __( 'WP Fusion', 'ultimate-member' ),
				'callback' => array( $this, 'meta_box_callback_role' ),
				'screen'   => 'um_role_meta',
				'context'  => 'side',
				'priority' => 'default',
			);

			return $roles_metaboxes;

	}

	/**
	 * Filters registration data before sending to the CRM
	 *
	 * @access public
	 * @return array Registration data
	 */

	public function filter_form_fields( $post_data, $user_id ) {

		// Quit early if it's not a UM form
		if ( ! isset( $post_data['form_id'] ) ) {
			return $post_data;
		}

		if ( isset( $post_data['um_role'] ) ) {
			$post_data['role'] = $post_data['um_role'];
		}

		if ( isset( $post_data['role'] ) ) {
			$post_data['role_select'] = $post_data['role']; // Built in role fields
			$post_data['role_radio']  = $post_data['role'];
		}

		$unique_id = '-' . $post_data['form_id'];

		foreach ( $post_data as $key => $value ) {

			if ( substr( $key, - strlen( $unique_id ) ) == $unique_id ) {

				// Trim the unique ID from the end of the string
				$key = substr( $key, 0, - strlen( $unique_id ) );

				$post_data[ $key ] = $value;

			}
		}

		if ( empty( $post_data['first_name'] ) && isset( $post_data['name'] ) ) {
			$post_data['first_name'] = $post_data['name'];
		}

		if ( ! empty( $post_data['user_password'] ) ) {
			$post_data['user_pass'] = $post_data['user_password'];
		}

		return $post_data;

	}

	/**
	 * Triggered before registration, allows removing WPF create_user hook
	 *
	 * @access public
	 * @return null
	 */

	public function before_user_registration( $args ) {

		if ( wpf_get_option( 'um_defer' ) == true ) {
			remove_action( 'user_register', array( wp_fusion()->user, 'user_register' ), 20 );
		}

	}

	/**
	 * Triggered after registration to save custom tags as meta to the user's profile
	 *
	 * @access public
	 * @return void
	 */

	public function registration_global_hook( $user_id, $args ) {

		if ( ! isset( $args['form_id'] ) ) {
			return;
		}

		$settings = get_post_meta( $args['form_id'], 'wpf-settings-um', true );

		if ( empty( $settings ) || empty( $settings['apply_tags'] ) ) {
			return;
		}

		if ( wpf_get_option( 'um_defer' ) != true ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags'], $user_id );

		} elseif ( wpf_get_option( 'um_defer' ) == true ) {

			update_user_meta( $user_id, 'wpf-settings-um', $settings['apply_tags'] );

		}

	}

	/**
	 * Triggered after user is approved. Applies tags stored in registration_global_hook
	 *
	 * @access public
	 * @return null
	 */

	public function after_user_is_approved( $user_id ) {

		if ( wpf_get_option( 'um_defer' ) != true ) {
			return;
		}

		// If we're deferring registration until activation
		wp_fusion()->user->user_register( $user_id );

		$tags = get_user_meta( $user_id, 'wpf-settings-um', true );

		if ( ! empty( $tags ) ) {

			wp_fusion()->user->apply_tags( $tags, $user_id );

		}

		// Clean up
		delete_user_meta( $user_id, 'wpf-settings-um' );

	}


	/**
	 * Sync passwords generated at registration
	 *
	 * @access public
	 * @return null
	 */

	public function register_sync_password( $user_id, $args ) {

		if ( isset( $args['submitted'] ) && isset( $args['submitted']['user_password'] ) ) {

			wp_fusion()->user->push_user_meta( $user_id, array( 'user_pass' => $args['submitted']['user_password'] ) );

		}

	}


	/**
	 * Loads new values from CRM before displaying UM profile
	 *
	 * @access public
	 * @return void
	 */

	public function pull_profile_changes( $args ) {

		if ( wpf_get_option( 'um_pull' ) == true ) {

			$user_id = um_profile_id();
			wp_fusion()->user->pull_user_meta( $user_id );

		}

	}

	/**
	 * Apply tags when a profile is marked complete
	 *
	 * @access public
	 * @return array Result
	 */

	public function completeness_get_progress_result( $result, $role_data ) {

		if ( $result['req_progress'] == $result['progress'] && ! empty( $role_data['wpf_apply_tags_profile_complete'] ) ) {

			if ( ! wpf_has_tag( $role_data['wpf_apply_tags_profile_complete'] ) ) {
				wp_fusion()->user->apply_tags( $role_data['wpf_apply_tags_profile_complete'] );
			}
		}

		return $result;

	}

	/**
	 * Updates password field when reset
	 *
	 * @access public
	 * @return void
	 */

	public function user_account_updated( $user_id, $changes ) {

		if ( ! empty( $_POST['user_password'] ) ) {
			wp_fusion()->user->push_user_meta( $user_id, array( 'user_pass' => $_POST['user_password'] ) );
		}

	}

	/**
	 * Updates password field when reset
	 *
	 * @access public
	 * @return void
	 */

	public function password_reset( $user_id ) {

		wp_fusion()->user->push_user_meta( $user_id, array( 'user_pass' => $_POST['user_password'] ) );

	}

	/**
	 * Don't sync passwords when someone initially requests a reset
	 *
	 * @access public
	 * @return bool Bypass
	 */

	public function bypass_profile_update( $bypass, $post_data ) {

		if ( isset( $post_data['_um_password_reset'] ) ) {
			$bypass = true;
		}

		return $bypass;

	}

	/**
	 * Formats loaded user meta fields to match UM syntax
	 *
	 * @access public
	 * @return array User meta
	 */

	public function format_user_meta( $user_meta, $user_id ) {

		$fields = UM()->classes['builtin']->{'all_user_fields'};

		foreach ( (array) $fields as $key => $field ) {

			if ( isset( $user_meta[ $key ] ) && $field['type'] == 'date' ) {

				// Reformat dates to match the configured UM format

				if ( ! is_numeric( $user_meta[ $key ] ) ) {
					$user_meta[ $key ] = strtotime( $user_meta[ $key ] );
				}

				$user_meta[ $key ] = date( 'Y/m/d', $user_meta[ $key ] );

			} elseif ( isset( $user_meta[ $key ] ) && ( $field['type'] == 'checkbox' || $field['type'] == 'multiselect' ) ) {

				// Checkboxes / multiselects may need to be converted into arrays
				if ( ! is_array( $user_meta[ $key ] ) ) {
					$user_meta[ $key ] = explode( ',', $user_meta[ $key ] );
				}
			}
		}

		// Clear the cache
		UM()->user()->remove_cache( $user_id );

		return $user_meta;

	}

	/**
	 * This triggers the UM signup flow after a user has been imported
	 *
	 * @access public
	 * @return void
	 */

	public function user_imported( $user_id, $user_meta ) {

		$user_meta['submitted'] = $user_meta;

		if ( doing_wpf_webhook() ) {

			define( 'WP_ADMIN', true ); // This stops UM from trying to do a redirect

			// If send_notification hasn't been explicitly set to false, allow UM to send emails

			if ( ! isset( $_GET['send_notification'] ) || 'false' != $_GET['send_notification'] ) {
				remove_filter( 'wp_mail', array( wp_fusion()->user, 'suppress_wp_mail' ), 100 );
			}
		}

		do_action( 'um_user_register', $user_id, $user_meta );

	}

	/**
	 * Updates user's role if tag is linked to a UM role, and activates / deactivates accounts
	 *
	 * @access public
	 * @return void
	 */

	public function tags_modified( $user_id, $user_tags ) {

		remove_action( 'set_user_role', array( $this, 'after_user_role_is_updated' ), 10, 3 );

		global $wp_roles;

		$user = get_userdata( $user_id );

		foreach ( $wp_roles->role_names as $slug => $label ) {

			$settings = get_option( 'um_role_' . $slug . '_meta' );

			if ( empty( $settings ) ) {
				$alt_slug = str_replace( 'um_', '', $slug );
				$settings = get_option( 'um_role_' . $alt_slug . '_meta' );
			}

			if ( empty( $settings ) || empty( $settings['wpf_tag_link'] ) ) {
				continue;
			}

			$tag_id = $settings['wpf_tag_link'][0];

			if ( in_array( $tag_id, $user_tags ) && ! in_array( $slug, $user->roles ) && ! user_can( $user_id, 'manage_options' ) ) {

				if ( strpos( $slug, 'um_' ) !== false ) {

					// Logger
					wpf_log( 'info', $user_id, 'Setting Ultimate Member role <strong>' . $label . '</strong> from linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'ultimate-member' ) );

					// Ultimate member roles
					um_fetch_user( $user_id );
					UM()->roles()->set_role( $user_id, $slug );

				} else {

					// Logger
					wpf_log( 'info', $user_id, 'Setting user role <strong>' . $label . '</strong> from linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'ultimate-member' ) );

					// WordPress roles
					$user->set_role( $slug );

				}
			} elseif ( ! in_array( $tag_id, $user_tags ) && in_array( $slug, $user->roles ) && ! user_can( $user_id, 'manage_options' ) ) {

				// Logger
				wpf_log( 'info', $user_id, 'Removing Ultimate Member role <strong>' . $label . '</strong> from linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'ultimate-member' ) );

				$user->remove_role( $slug );

				if ( empty( $user->roles ) ) {

					// We don't want to leave someone with no role so we'll assign the UM default role

					$default_role = UM()->options()->get( 'register_role' );

					if ( empty( $default_role ) ) {
						$default_role = get_option( 'default_role' );
					}

					wpf_log( 'info', $user_id, 'User was left with no role so assigning default role <strong>' . $default_role . '</strong>.', array( 'source' => 'ultimate-member' ) );

					um_fetch_user( $user_id );
					UM()->roles()->set_role( $user_id, $default_role );

				}
			}
		}

		add_action( 'set_user_role', array( $this, 'after_user_role_is_updated' ), 10, 3 );

		// Account deactivation / reactivation.
		$deactivation_tag = wpf_get_option( 'deactivation_tag', array() );

		if ( ! empty( $deactivation_tag ) && ! empty( $deactivation_tag[0] ) ) {

			if ( in_array( $deactivation_tag[0], $user_tags ) ) {

				wpf_log( 'notice', $user_id, 'User\'s account was deactivated by deactivation tag <strong>' . wpf_get_tag_label( $deactivation_tag[0] ) . '</strong>' );

				um_fetch_user( $user_id );
				UM()->user()->deactivate();

			} elseif ( ! in_array( $deactivation_tag[0], $user_tags ) ) {

				um_fetch_user( $user_id );
				UM()->user()->approve();

			}
		}

	}

	/**
	 * When user's role is changed the tag linked is changed for the UM role ('um_after_user_role_is_updated' found in class-roles-capabilities file of was used before but would not fire in new Beta version of UM)
	 *
	 * @access public
	 * @return void
	 */

	public function after_user_role_is_updated( $user_id, $role, $old_roles ) {

		remove_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );

		// Remove old role tags
		$tags_to_remove = array();

		foreach ( $old_roles as $old_role ) {

			$settings = get_option( 'um_role_' . $old_role . '_meta' );

			if ( empty( $settings ) ) {
				$alt_old_role = str_replace( 'um_', '', $old_role );
				$settings     = get_option( 'um_role_' . $alt_old_role . '_meta' );
			}

			if ( empty( $settings ) || empty( $settings['wpf_tag_link'] ) ) {
				continue;
			}

			$tags_to_remove = array_merge( $tags_to_remove, $settings['wpf_tag_link'] );

		}

		if ( ! empty( $tags_to_remove ) ) {
			wp_fusion()->user->remove_tags( $tags_to_remove, $user_id );
		}

		// Apply new role tags
		$settings = get_option( 'um_role_' . $role . '_meta' );

		if ( empty( $settings ) ) {
			$alt_role = str_replace( 'um_', '', $role );
			$settings = get_option( 'um_role_' . $alt_role . '_meta' );
		}

		if ( empty( $settings ) || empty( $settings['wpf_tag_link'] ) ) {

			add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );
			return;

		}

		$tag_to_add = $settings['wpf_tag_link'];

		if ( ! empty( $tag_to_add ) ) {
			wp_fusion()->user->apply_tags( $tag_to_add, $user_id );
		}

		add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );

	}

	/**
	 * Loads scripts for UM meta box
	 *
	 * @access public
	 * @return void
	 */

	public function metabox_scripts() {

		wp_enqueue_style( 'select4', WPF_DIR_URL . 'includes/admin/options/lib/select2/select4.min.css' );
		wp_enqueue_script( 'select4', WPF_DIR_URL . 'includes/admin/options/lib/select2/select4.min.js', array( 'jquery' ), '4.0.1', true );

		wp_enqueue_script( 'wpf-admin', WPF_DIR_URL . 'assets/js/wpf-admin.js', array( 'jquery', 'select4' ), WP_FUSION_VERSION, true );
		wp_enqueue_style( 'wpf-admin', WPF_DIR_URL . 'assets/css/wpf-admin.css', array(), WP_FUSION_VERSION );

	}

	/**
	 * Adds meta box, only for UM registration type forms
	 *
	 * @access public
	 * @return void
	 */

	public function add_meta_box() {

		global $post;

		if ( get_post_meta( $post->ID, '_um_mode', true ) == 'register' ) {
			add_meta_box( 'wpf-um-meta', 'WP Fusion', array( $this, 'meta_box_callback' ), 'um_form', 'side', 'default' );
		}
	}

	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback( $post ) {

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wpf_meta_box_um', 'wpf_meta_box_um_nonce' );

		$settings = array(
			'apply_tags' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings-um', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings-um', true ) );
		}

		/*
		// Apply tags
		*/

		echo '<p><label for="wpf-um-apply-tags">Apply these tags when a user registers:</label><br />';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags'],
				'meta_name' => 'wpf-settings-um',
				'field_id'  => 'apply_tags',
			)
		);
		echo '</p>';

	}

	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback_role( $post ) {

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wpf_meta_box_um', 'wpf_meta_box_um_nonce' );

		$settings = array(
			'wpf_tag_link'                    => array(),
			'wpf_apply_tags_profile_complete' => array(),
		);

		$settings = array_merge( $settings, get_option( "um_role_{$_GET['id']}_meta", array() ) );

		/*
		// Apply tags
		*/

		echo '<p><label><strong>' . sprintf( __( 'Link with %s tag', 'wp-fusion' ), wp_fusion()->crm->name ) . ':</strong></label></p>';

		$args = array(
			'setting'     => $settings['wpf_tag_link'],
			'meta_name'   => 'role',
			'field_id'    => 'wpf_tag_link',
			'placeholder' => 'Select Tag',
			'limit'       => 1,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">When the selected tag is applied, users will automatically be given the ' . $_GET['id'] . ' role. <br /><br />When the tag is removed the role will be removed.</span>';

		if ( class_exists( 'UM_Profile_Completeness_API' ) ) {

			echo '<p><label><strong>' . __( 'Apply Tags - Profile Complete', 'wp-fusion' ) . ':</strong></label></p>';

			$args = array(
				'setting'   => $settings['wpf_apply_tags_profile_complete'],
				'meta_name' => 'role',
				'field_id'  => 'wpf_apply_tags_profile_complete',
			);

			wpf_render_tag_multiselect( $args );

			echo '<span class="description">' . __( 'The selected tags will be applied when the profile is completed.', 'wp-fusion' ) . '</span>';

		}

	}

	/**
	 * Saves UM meta box data
	 *
	 * @access public
	 * @return null
	 */

	public function save_meta_box_data( $post_id ) {

		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_um_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_um_nonce'], 'wpf_meta_box_um' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Don't update on revisions
		if ( $_POST['post_type'] == 'revision' ) {
			return;
		}

		if ( isset( $_POST['wpf-settings-um'] ) ) {
			update_post_meta( $post_id, 'wpf-settings-um', $_POST['wpf-settings-um'] );
		} else {
			delete_post_meta( $post_id, 'wpf-settings-um' );
		}
	}


}

new WPF_UM();
