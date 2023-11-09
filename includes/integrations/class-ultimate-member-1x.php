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
	public $name = 'Ultimate member';

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

		// Registration
		add_action( 'um_before_new_user_register', array( $this, 'before_user_registration' ) );
		add_action( 'um_post_registration_global_hook', array( $this, 'registration_global_hook' ), 20, 2 );
		add_action( 'um_after_user_is_approved', array( $this, 'after_user_is_approved' ) );

		// Profile
		add_action( 'um_after_user_updated', array( $this, 'profile_updated' ), 10 );
		add_action( 'um_profile_before_header', array( $this, 'pull_profile_changes' ) );

		// Password Reset
		add_action( 'send_password_change_email', array( $this, 'password_reset' ), 5 );

		// Callbacks and filters
		add_filter( 'wpf_pulled_user_meta', array( $this, 'format_user_meta' ), 10, 2 );
		add_filter( 'wpf_user_register', array( $this, 'filter_form_fields' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'filter_form_fields' ), 10, 2 );
		add_action( 'wpf_user_updated', array( $this, 'user_updated' ) );
		add_action( 'wpf_tags_modified', array( $this, 'update_role' ), 10, 2 );

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
	 * @return array Settings
	 */

	public function set_contact_field_names( $meta_fields ) {

		$um_fields = get_option( 'um_fields' );

		foreach ( (array) $um_fields as $key => $field ) {

			if ( ! isset( $field['label'] ) ) {
				$field['label'] = '';
			}

			if ( ! isset( $field['type'] ) ) {
				$field['type'] = '';
			}

			if ( $field['type'] == 'checkbox' ) {
				$field['type'] = 'checkboxes';
			}

			$meta_fields[ $key ] = array(
				'label' => $field['label'],
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
	 * Filters registration data before sending to the CRM
	 *
	 * @access public
	 * @return array Registration data
	 */

	public function filter_form_fields( $post_data, $user_id ) {

		// Quit early if it's not a UM form
		if ( ! isset( $post_data['form_id'] ) && ! isset( $post_data['_um_account'] ) ) {
			return $post_data;
		}

		if ( isset( $post_data['um_role'] ) ) {
			$post_data['role'] = $post_data['um_role'];
		}

		$unique_id = '-' . $post_data['form_id'];

		foreach ( $post_data as $key => $value ) {

			if ( is_array( $value ) ) {
				$post_data[ $key ] = implode( ', ', $value );
			}

			if ( substr( $key, - strlen( $unique_id ) ) == $unique_id ) {

				// Trim the unique ID from the end of the string
				$key = substr( $key, 0, - strlen( $unique_id ) );

				$post_data[ $key ] = $value;

			}
		}

		// Adapt Ultimate Member fields to WordPress values
		if ( isset( $post_data['user_password'] ) ) {
			$post_data['user_pass'] = $post_data['user_password'];
			unset( $post_data['user_password'] );
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
	 * @return null
	 */

	public function registration_global_hook( $user_id, $args ) {

		$settings = get_post_meta( $args['form_id'], 'wpf-settings-um', true );

		if ( ! empty( $settings['apply_tags'] ) ) {
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

		// If we're deferring registration until activation
		if ( wpf_get_option( 'um_defer' ) == true ) {
			wp_fusion()->user->user_register( $user_id );
		}

		$tags = get_user_meta( $user_id, 'wpf-settings-um', true );

		if ( ! empty( $tags ) ) {

			wp_fusion()->user->apply_tags( $tags, $user_id );

			// Clean up
			delete_user_meta( $user_id, 'wpf-settings-um' );

		}

	}

	/**
	 * Triggered after new user meta data is pulled down from the CRM, tells UM to clear caches for user
	 *
	 * @access public
	 * @return null
	 */

	public function user_updated( $user_id ) {

		// Prevent loops
		remove_action( 'um_after_user_updated', array( $this, 'profile_updated' ) );

		// Let UM do its thing
		do_action( 'um_after_user_updated', $user_id );

	}


	/**
	 * Triggered on UM profile update
	 *
	 * @access public
	 * @return void
	 */

	public function profile_updated( $user_id ) {

		wp_fusion()->user->push_user_meta( $user_id );

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
	 * Updates password field when reset
	 *
	 * @access public
	 * @return void
	 */

	public function password_reset( $args ) {

		wp_fusion()->user->push_user_meta( $args['id'], array( 'user_pass' => $args['user_password'] ) );

	}

	/**
	 * Formats loaded user meta fields to match UM syntax
	 *
	 * @access public
	 * @return array User meta
	 */

	public function format_user_meta( $user_meta, $user_id ) {

		$um_fields = get_option( 'um_fields' );

		foreach ( (array) $um_fields as $key => $field ) {

			// Reformat dates to match the configured UM format
			if ( isset( $user_meta[ $key ] ) && $field['type'] == 'date' ) {
				$user_meta[ $key ] = date( $field['format'], strtotime( $user_meta[ $key ] ) );
			}
		}

		return $user_meta;

	}

	/**
	 * Updates user's role if tag is linked to a UM role
	 *
	 * @access public
	 * @return void
	 */

	public function update_role( $user_id, $user_tags ) {

		if ( empty( $user_tags ) ) {
			return;
		}

		$linked_roles = get_posts(
			array(
				'post_type'  => 'um_role',
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf-settings-um',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		// Update role based on user tags
		foreach ( $linked_roles as $role ) {

			$settings = get_post_meta( $role->ID, 'wpf-settings-um', true );

			if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
				continue;
			}

			global $ultimatemember;
			um_fetch_user( $user_id );

			$tag_id    = $settings['tag_link'][0];
			$user_role = $ultimatemember->user->get_role();

			if ( in_array( $tag_id, $user_tags ) && $user_role != $role->post_name ) {

				// Logger
				wpf_log( 'info', $user_id, 'Setting Ultimate Member role <strong>' . $role->post_name . '</strong> linked to tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'ultimate-member' ) );

				$ultimatemember->user->set_role( $role->post_name );
			}
		}

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

		wp_enqueue_script(
			'wpf-admin',
			WPF_DIR_URL . 'assets/js/wpf-admin.js',
			array(
				'jquery',
				'select4',
			),
			WP_FUSION_VERSION,
			true
		);
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
			add_meta_box(
				'wpf-um-meta',
				'WP Fusion',
				array(
					$this,
					'meta_box_callback',
				),
				'um_form',
				'side',
				'default'
			);
		} elseif ( $post->post_type == 'um_role' ) {
			add_meta_box(
				'wpf-um-meta',
				'WP Fusion',
				array(
					$this,
					'meta_box_callback_role',
				),
				'um_role',
				'side',
				'default'
			);
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
			'tag_link' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings-um', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings-um', true ) );
		}

		/*
		// Apply tags
		*/

		echo '<p><label for="wpf-um-apply-tags">Link with ' . wp_fusion()->crm->name . ' tag:</label><br />';

		$args = array(
			'setting'     => $settings['tag_link'],
			'meta_name'   => 'wpf-settings-um',
			'field_id'    => 'tag_link',
			'placeholder' => 'Select Tag',
			'limit'       => 1,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">When the selected tag is applied, users will automatically be given the ' . $post->post_title . ' role.</span>';
		echo '</p>';

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
