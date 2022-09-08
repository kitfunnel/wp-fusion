<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Woo_Memberships_Teams extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'woo-memberships-teams';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Teams for WooCommerce Memberships';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/teams-for-woocommerce-memberships/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		add_action( 'wc_memberships_for_teams_add_team_member', array( $this, 'add_team_member' ), 10, 3 );
		add_action( 'wc_memberships_for_teams_after_remove_team_member', array( $this, 'after_remove_team_member' ), 10, 3 );
		add_action( 'wc_memberships_user_membership_cancelled', array( $this, 'owner_cancel_membership' ) );

		add_action( 'updated_user_meta', array( $this, 'sync_teams_role' ), 10, 4 );
		add_action( 'added_user_meta', array( $this, 'sync_teams_role' ), 10, 4 );

		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ) );

		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

		// Batch operations
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_filter( 'wpf_batch_woo_memberships_teams_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_woo_memberships_teams', array( $this, 'batch_step' ) );
	}

	/**
	 * Runs when a team member accepts an invite and registers an account
	 *
	 * @access public
	 * @return void
	 */

	public function add_team_member( $member, $team, $user_membership ) {

		if ( empty( $member ) ) {
			return;
		}

		// Sync name.

		wp_fusion()->user->push_user_meta( $member->get_id(), array( 'wc_memberships_for_teams_team_name' => $team->get_name() ) );

		$product = $team->get_product();

		// Maybe apply tags

		if ( ! empty( $product ) ) {

			$product_id = $product->get_id();

			$parent_id = $product->get_parent_id();

			if ( ! empty( $parent_id ) ) {
				$product_id = $parent_id;
			}

			$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_members'] ) ) {

				wp_fusion()->user->apply_tags( $settings['apply_tags_members'], $member->get_id() );

			}
		}

	}

	/**
	 * Runs when a team member accepts an invite and registers an account
	 *
	 * @access public
	 * @return void
	 */

	public function after_remove_team_member( $user_id, $team ) {

		$product = $team->get_product();

		if ( empty( $product ) ) {
			return;
		}

		$product_id = $product->get_id();

		$parent_id = $product->get_parent_id();

		if ( ! empty( $parent_id ) ) {
			$product_id = $parent_id;
		}

		$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_members'] ) && ! empty( $settings['remove_tags_members'] ) ) {

			wp_fusion()->user->remove_tags( $settings['apply_tags_members'], $user_id );

		}

	}


	/**
	 * Triggered when the owner of a team cancels his membership.
	 *
	 * @since 3.38.36
	 *
	 * @param WC_Memberships_User_Membership $membership The membership.
	 */
	public function owner_cancel_membership( $membership ) {

		$user_id = $membership->user_id;
		$teams   = wc_memberships_for_teams_get_teams( $user_id );
		if ( empty( $teams ) ) {
			return;
		}

		foreach ( $teams as $team ) {
			// Check if he is the owner.
			if ( intval( $user_id ) !== intval( $team->get_owner_id() ) ) {
				continue;
			}

			// Check if team has members.
			$members_ids = $team->get_member_ids();
			if ( empty( $members_ids ) ) {
				continue;
			}

			// Check if team has a product.
			$product = $team->get_product();
			if ( empty( $product ) ) {
				continue;
			}

			$product_id = $product->get_id();
			$parent_id  = $product->get_parent_id();

			if ( ! empty( $parent_id ) ) {
				$product_id = $parent_id;
			}

			$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_members'] ) && ! empty( $settings['remove_tags_members_cancelled'] ) ) {
				foreach ( $members_ids as $member_user_id ) {
					wp_fusion()->user->remove_tags( $settings['apply_tags_members'], $member_user_id );
				}
			}
		}

	}


	/**
	 * Sync changes to teams roles
	 *
	 * @access public
	 * @return void
	 */

	public function sync_teams_role( $meta_id, $user_id, $meta_key, $value ) {

		if ( strpos( $meta_key, '_wc_memberships_for_teams_team_' ) !== false && strpos( $meta_key, '_role' ) !== false ) {

			wp_fusion()->user->push_user_meta( $user_id, array( 'wc_memberships_for_teams_team_role' => $value ) );

		}

	}


	/**
	 * Writes subscriptions options to WPF/Woo panel
	 *
	 * @access public
	 * @return mixed
	 */

	public function panel_content( $post_id ) {

		$settings = array(
			'apply_tags_members'            => array(),
			'remove_tags_members'           => false,
			'remove_tags_members_cancelled' => false,
		);

		if ( get_post_meta( $post_id, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group wpf-product js-wc-memberships-for-teams-show-if-has-team-membership hidden">';

		echo '<p class="form-field"><label><strong>Team Membership</strong></label></p>';

		echo '<p class="form-field"><label>' . __( 'Apply tags to team members', 'wp-fusion' );
		echo ' <span class="dashicons dashicons-editor-help wpf-tip wpf-tip-bottom" data-tip="' . __( 'These tags will be applied to users when they are added as members to the team, and accept the invite.', 'wp-fusion' ) . '"></span>';
		echo '</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_members'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_members',
			)
		);

		echo '</p>';

		echo '<p class="form-field"><label for="wpf-remove-tags-members">' . __( 'Remove tags', 'wp-fusion' ) . '</label>';
		echo '<input class="checkbox" type="checkbox" id="wpf-remove-tags-members" name="wpf-settings-woo[remove_tags_members]" value="1" ' . checked( $settings['remove_tags_members'], 1, false ) . ' />';
		echo '<span class="description">' . __( 'Remove team member tags (above) when members are removed from the team.', 'wp-fusion' ) . '</span>';
		echo '</p>';

		echo '<p class="form-field"><label for="wpf-remove-tags-members-cancelled">' . __( 'Remove tags - Cancelled', 'wp-fusion' ) . '</label>';
		echo '<input class="checkbox" type="checkbox" id="wpf-remove-tags-members-cancelled" name="wpf-settings-woo[remove_tags_members_cancelled]" value="1" ' . checked( $settings['remove_tags_members_cancelled'], 1, false ) . ' />';
		echo '<span class="description">' . __( 'Remove team member tags when the team owner’s membership is cancelled.', 'wp-fusion' ) . '</span>';
		echo '</p>';

		echo '</div>';

	}

	/**
	 * Sets field labels and types for WooCommerce custom fields
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['wc_memberships_for_teams_team_role'] = array(
			'label'  => 'Memberships for Teams Role',
			'type'   => 'text',
			'group'  => 'woocommerce_memberships',
			'pseudo' => true,
		);

		$meta_fields['wc_memberships_for_teams_team_name'] = array(
			'label'  => 'Memberships for Teams Team Name',
			'type'   => 'text',
			'group'  => 'woocommerce_memberships',
			'pseudo' => true
		);

		return $meta_fields;

	}


	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds Woo Memberships for Teams batch operation.
	 *
	 * @since  3.37.26
	 *
	 * @param  array $options The export options.
	 * @return array The export options.
	 */
	public function export_options( $options ) {

		$options['woo_memberships_teams'] = array(
			'label'   => __( 'WooCommerce Memberships for Teams team meta', 'wp-fusion' ),
			'title'   => __( 'Members', 'wp-fusion' ),
			'tooltip' => sprintf( __( 'For each member who is part of a team, syncs the team name and that member\'s role in the team to the corresponding custom fields in %s.', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		return $options;

	}

	/**
	 * Get the all the members to export.
	 *
	 * @since  3.37.26
	 *
	 * @return array User membership IDs.
	 */
	public function batch_init() {
		$args  = array(
			'post_type'        => 'wc_user_membership',
			'posts_per_page'   => '-1',
			'fields'           => 'ids',
			'nopaging'         => true,
			'suppress_filters' => 1,
			'meta_query'       => array(
				array(
					'key'     => '_team_id',
					'compare' => 'EXISTS',
				),
			),
		);
		$query = new \WP_Query( $args );
		return $query->posts;
	}


	/**
	 * Process team members one by one.
	 *
	 * @since 3.37.26
	 *
	 * @param int $member_id The team member ID.
	 */
	public function batch_step( $member_id ) {

		$user_membership = wc_memberships_get_user_membership( $member_id );
		$user_id         = $user_membership->get_user_id();
		$team_id         = absint( get_post_meta( $member_id, '_team_id', true ) );
		$team_member     = wc_memberships_for_teams_get_team_member( $team_id, $user_id );

		if ( false === $team_member ) { // if user was deleted or removed from team.
			return;
		}

		$update_data = array(
			'wc_memberships_for_teams_team_role' => $team_member->get_role(),
			'wc_memberships_for_teams_team_name' => $team_member->get_team()->get_name(),
		);

		wp_fusion()->user->push_user_meta( $user_id, $update_data );
	}


}

new WPF_Woo_Memberships_Teams();
