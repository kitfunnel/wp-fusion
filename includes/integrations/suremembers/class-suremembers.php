<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


class WPF_SureMembers extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.41.23
	 * @var string $slug
	 */

	public $slug = 'suremembers';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.41.23
	 * @var string $name
	 */
	public $name = 'SureMembers';

	/**
	 * Init.
	 * Gets things started.
	 *
	 * @since 3.41.23
	 */
	public function init() {
		add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );
		add_action( 'suremembers_after_access_grant', array( $this, 'add_user_tags'), 10, 2 );
		add_action( 'suremembers_after_access_revoke', array( $this, 'remove_user_tags'), 10, 2 );

		add_action( 'suremembers_after_submit_form', array( $this, 'save_meta_box_data' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue Assets.
	 * Enqueue the javascript assets.
	 *
	 * @since 3.41.23
	 * @since 3.41.44 Fixed PHP errors, PHPCS errors, and array to string conversion.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( isset( $_GET['page'] ) && 'suremembers_rules' === $_GET['page'] ) {
			if ( isset( $_GET['post_id'] ) ) {
				$post_id = $_GET['post_id'];
			} else {
				return;
			}

			wp_enqueue_script(
				'wpf-suremembers-integration',
				plugins_url( '/build/index.js', __FILE__ ),
				array( 'wp-blocks', 'wp-element', 'wp-editor', 'wpf-admin' ),
				filemtime( plugin_dir_path( __FILE__ ) . '/build/index.js' )
			);

			$settings = array(
				'apply_tags' => array(),
				'tag_link'   => array(),
			);

			$settings = wp_parse_args( get_post_meta( $post_id, 'suremembers_plan_rules', true ), $settings );

			$args = array(
				'nonce' => wp_create_nonce( 'wpf_meta_box_suremembers' ),
			);

			$apply_tags = array();
			$tag_link  = array();

			if ( $settings['apply_tags'] ) {

				$string_tags = '';

				foreach ( $settings['apply_tags'] as $tag ){

					$string_tags = $string_tags . $tag . ',';

					$apply_tags[] = array(
						'label' => wpf_get_tag_label( $tag ),
						'value' => $tag,
					);
				}

				$args['raw_apply_tags'] = $string_tags;
			}

			if ( $settings['tag_link'] ) {

				$string_tags = '';

				foreach ( $settings['tag_link'] as $tag ){

					$string_tags = $string_tags . $tag . ',';

					$tag_link[] = array(
						'label' => wpf_get_tag_label( $tag ),
						'value' => $tag,
					);
				}

				$args['raw_tag_link'] = $string_tags;
			}

			$args['apply_tags'] = $apply_tags;
			$args['tag_link']  = $tag_link;

			$args['apply_tags_string'] = sprintf( __( 'Apply the selected tags in %s when a user is added to this access group.', 'wp-fusion' ), wp_fusion()->crm->name );
			$args['tag_link_string']   = sprintf( __( 'Select a tag to link with this access group. When the tag is applied in %s, the user will be enrolled. When the tag is removed, the user will be unenrolled.', 'wp-fusion' ), wp_fusion()->crm->name );

			wp_localize_script( 'wpf-suremembers-integration', 'wpf_suremembers', $args );
		}
	}

	/**
	 * Add user tags.
	 * Adds tags to the user when they are added to a group.
	 *
	 * @since 3.41.23
	 * @since 3.41.44 Fixed issue where tags were not being added to the user.
	 *
	 * @param int   $user_id The user ID.
	 * @param array $access_group_ids The ID of the access group(s) that is being granted.
	 */
	public function add_user_tags( $user_id, array $access_group_ids = array() ) {

		remove_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );

		$user_tags = wpf_get_tags( $user_id );

		foreach ( $access_group_ids as $group_id ) {

			$settings = get_post_meta( $group_id, 'suremembers_plan_rules', true );

			if ( empty( $settings ) ) {
				continue;
			}

			// We only need to check the first tag in the array since
			// the user should have all of the tags.
			$tag_id   = $settings['apply_tags'][0];
			$tag_link = $settings['tag_link'];

			$user_can_access = get_user_meta( $user_id, 'suremembers_user_access_group_' . $group_id, true );

			// If the group we are granting access to has a link tag, we need to add it here.
			if ( ! empty( $settings['tag_link'] ) ) {
				if ( ! array_intersect( $tag_link, $user_tags ) && 'active' === $user_can_access['status'] && ! user_can( $user_id, 'manage_options' ) ) {
					wpf_log( 'info', $user_id, 'User added to access group <a href="' . admin_url( 'edit.php?post_type=wsm_access_group&page=suremembers_rules&post_id=' . $group_id ) . '">' . get_the_title( $group_id ) . '</a> Applying link tag <strong>' . wpf_get_tag_label( $tag_link[0] ) . '</strong>.' );
					wp_fusion()->user->apply_tags( $tag_link, $user_id );
				}
			}

			// If the user does not have the tag but is in the group and is not an admin,
			// then apply the tags.
			if ( ! in_array( $tag_id, $user_tags ) && 'active' === $user_can_access['status'] && ! user_can( $user_id, 'manage_options' ) ) {
				wpf_log( 'info', $user_id, 'User added to access group <a href="' . admin_url( 'edit.php?post_type=wsm_access_group&page=suremembers_rules&post_id=' . $group_id ) . '">' . get_the_title( $group_id ) . '</a>. Applying tags.' );
				wp_fusion()->user->apply_tags( $settings['apply_tags'], $user_id );
			}
		}

		add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );
	}

	/**
	 * Remove user tags.
	 * Removes the link tag when a user is removed from a group.
	 *
	 * @since 3.41.23
	 * @since 3.41.44 Cleaned up code, removed unnecessary checks & fixed tags not being applied.
	 *
	 * @param int   $user_id The user ID.
	 * @param array $access_group_ids The ID of the access group(s) that is being revoked.
	 */
	public function remove_user_tags( $user_id, array $access_group_ids = array() ) {

		remove_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );

		$user_tags = wpf_get_tags( $user_id );

		foreach ( $access_group_ids as $group_id ) {
			$settings = get_post_meta( $group_id, 'suremembers_plan_rules', true );

			if ( empty( $settings ) ) {
				continue;
			}

			$tag_link = $settings['tag_link'];

			$user_can_access = get_user_meta( $user_id, 'suremembers_user_access_group_' . $group_id, true );

			// If the group we are revoking access to has a link tag, we need to remove it here.
			if ( ! empty( $settings['tag_link'] ) ) {
				if ( array_intersect( $tag_link, $user_tags ) && 'revoked' === $user_can_access['status'] && ! user_can( $user_id, 'manage_options' ) ) {
					wpf_log( 'info', $user_id, 'User removed from access group <a href="' . admin_url( 'edit.php?post_type=wsm_access_group&page=suremembers_rules&post_id=' . $group_id ) . '">' . get_the_title( $group_id ) . '</a>. Removing link tag <strong>' . wpf_get_tag_label(  $tag_link[0] ) . '</strong>.' );
					wp_fusion()->user->remove_tags( $tag_link, $user_id );
				}
			}
		}
		add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );
	}

	/**
	 * Tags modified.
	 * Updates user's access groups if a tag linked to a SureMembers access group is changed.
	 *
	 * @since 3.41.23
	 *
	 * @param int   $user_id   The user ID.
	 * @param array $user_tags The user tags.
	 */
	public function tags_modified( $user_id, array $user_tags = array() ) {

		$access_groups      = SureMembers\Inc\Access_Groups::get_active();
		$user_access_groups = (array) get_user_meta( $user_id, 'suremembers_user_access_group', true );

		// We are checking all the active access groups to see if the user has a linked tag.
		foreach ( $access_groups as $group_id => $group ) {
			$settings = get_post_meta( $group_id, 'suremembers_plan_rules', true );

			if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
				continue;
			}

			// We are only checking the first tag in the array since
			// linked tags should only have one tag.
			$tag_id = $settings['tag_link'][0];

			$user_can_access = get_user_meta( $user_id, 'suremembers_user_access_group_' . $group_id, true );

			// If the user has the tag but is not in the group and is not an admin, then add them to the group.
			// We also have to check for if the user is not in any groups,
			// or if they are in a group but have their access revoked.
			if ( in_array( $tag_id, $user_tags ) && empty( $user_access_groups )
				|| in_array( $tag_id, $user_tags ) && ! in_array( $group_id, $user_access_groups )
				|| in_array( $tag_id, $user_tags ) && 'revoked' === $user_can_access['status'] ) {

				wpf_log( 'info', $user_id, 'Linked tag <strong>' . wpf_get_tag_label( $tag_id ) . '</strong> applied to user. Adding user to access group <a href="' . admin_url( 'edit.php?post_type=wsm_access_group&page=suremembers_rules&post_id=' . $group_id ) . '">' . $group . '</a>.' );
				SureMembers\Inc\Access::grant( $user_id, $group_id, 'wp-fusion' );

				// If the user does not have the tag but is in the group and is not an admin.
				// Then remove them from the group.
			} elseif ( ! in_array( $tag_id, $user_tags ) && in_array( $group_id, $user_access_groups ) && 'active' === $user_can_access['status'] ) {

				wpf_log( 'info', $user_id, 'Linked tag <strong>' . wpf_get_tag_label( $tag_id ) . '</strong> removed from user. Removing user from access group <a href="' . admin_url( 'edit.php?post_type=wsm_access_group&page=suremembers_rules&post_id=' . $group_id ) . '">' . $group . '</a>.' );
				SureMembers\Inc\Access::revoke( $user_id, $group_id );
			}
		}

	}

	/**
	 * Save meta box data.
	 * Saves SureMembers meta box data.
	 *
	 * @since 3.41.23
	 * @since 3.41.44 Cleaned up code.
	 *
	 * @param int $access_group The access group ID.
	 */
	public function save_meta_box_data( $access_group ) {
		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_suremembers_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_suremembers_nonce'], 'wpf_meta_box_suremembers' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if there is no submitted data for any reason.
		if ( empty( $_POST['suremembers_post'] ) ) {
			return;
		}

		$settings = get_post_meta( $access_group, 'suremembers_plan_rules', true );

		if ( ! empty( $settings['apply_tags'] ) ) {
			$apply_tags = $settings['apply_tags'];
		}

		if ( ! empty( $settings['tag_link'] ) ) {
			$tag_link = $settings['tag_link'];
		}

		if ( ! empty( $_POST['wp_fusion']['apply_tags'] ) ) {
			$apply_tags = explode( ',', $_POST['wp_fusion']['apply_tags'] );

			$_POST['suremembers_post']['apply_tags'] = $apply_tags;
		}

		if ( ! empty( $_POST['wp_fusion']['tag_link'] ) ) {
			$tag_link = explode( ',', $_POST['wp_fusion']['tag_link'] );

			$_POST['suremembers_post']['tag_link'] = $tag_link;
		}

		$data = SureMembers\Inc\Utils::sanitize_recursively( 'sanitize_text_field', $_POST['suremembers_post'] );
		$data = SureMembers\Inc\Utils::remove_blank_array( $data );

		update_post_meta( $access_group, 'suremembers_plan_rules', $data );
	}
}

new WPF_SureMembers();
