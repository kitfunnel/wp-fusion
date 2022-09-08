<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}



class WPF_BadgeOS extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'badgeos';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Badgeos';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/gamification/badgeos/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// Add meta field group
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

		// Remove WPF side meta box on BadgeOS post types
		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );

		// Tag users when badges / points are added and removed
		add_action( 'badgeos_award_achievement', array( $this, 'award_achievement' ), 10, 5 );
		add_action( 'badgeos_revoke_achievement', array( $this, 'revoke_achievement' ), 10, 2 );
		add_action( 'badgeos_update_users_points', array( $this, 'update_points' ), 10, 5 );

		// Assign / remove linked badges
		add_action( 'wpf_tags_modified', array( $this, 'update_linked_achievements' ), 10, 2 );

		// Meta boxes
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20, 2 );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ), 20, 2 );

	}

	/**
	 * Adds field group for BadgeOS to contact fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['badgeos'] ) ) {
			$field_groups['badgeos'] = array(
				'title'  => 'BadgeOS',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Sets field labels and types for EDD custom fields
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['_badgeos_points'] = array(
			'label' => 'BadgeOS Points',
			'type'  => 'text',
			'group' => 'badgeos',
		);

		return $meta_fields;

	}

	/**
	 * Hide WPF meta box on BadgeOS post types
	 *
	 * @access public
	 * @return array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		foreach ( badgeos_get_achievement_types_slugs() as $slug ) {
			unset( $post_types[ $slug ] );
		}

		unset( $post_types['achievement-type'] );
		unset( $post_types['submission'] );
		unset( $post_types['nomination'] );
		unset( $post_types['badgeos-log-entry'] );

		return $post_types;

	}

	/**
	 * Applies tags when a badge is awarded
	 *
	 * @access public
	 * @return void
	 */

	public function award_achievement( $user_id, $achievement_id, $this_trigger, $site_id, $args ) {

		$wpf_settings = get_post_meta( $achievement_id, 'wpf-settings-badgeos', true );

		if ( ! empty( $wpf_settings['tag_link'] ) ) {

			wp_fusion()->user->apply_tags( $wpf_settings['tag_link'], $user_id );

		}

	}

	/**
	 * Removes linked tags when achievement revoked
	 *
	 * @access public
	 * @return void
	 */

	public function revoke_achievement( $user_id, $achievement_id ) {

		$wpf_settings = get_post_meta( $achievement_id, 'wpf-settings-badgeos', true );

		if ( empty( $wpf_settings ) ) {
			return;
		}

		if ( ! empty( $wpf_settings['tag_link'] ) ) {
			wp_fusion()->user->remove_tags( $wpf_settings['tag_link'], $user_id );
		}

	}

	/**
	 * Syncs points when they're updated
	 *
	 * @access public
	 * @return void
	 */

	public function update_points( $user_id, $new_points, $total_points, $admin_id, $achievement_id ) {

		wp_fusion()->user->push_user_meta( $user_id, array( '_badgeos_points' => $total_points ) );

	}


	/**
	 * Update's user achievements when tags are modified
	 *
	 * @access public
	 * @return void
	 */

	public function update_linked_achievements( $user_id, $user_tags ) {

		$linked_badges = get_posts(
			array(
				'post_type'  => badgeos_get_achievement_types_slugs(),
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf-settings-badgeos',
						'compare' => 'EXISTS',
					),
				),
				'fields'     => 'ids',
			)
		);

		if ( empty( $linked_badges ) ) {
			return;
		}

		// Prevent looping when the badges assigned / removed
		remove_action( 'badgeos_award_achievement', array( $this, 'award_achievement' ), 10, 5 );
		remove_action( 'badgeos_revoke_achievement', array( $this, 'revoke_achievement' ), 10, 2 );

		// Assign / revoke linked badges

		foreach ( $linked_badges as $badge_id ) {

			$settings = get_post_meta( $badge_id, 'wpf-settings-badgeos', true );

			if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
				continue;
			}

			$tag_id = $settings['tag_link'][0];

			// Check if user already has badge
			$args = array(
				'user_id'        => $user_id,
				'achievement_id' => $badge_id,
			);

			$has_badge = badgeos_get_user_achievements( $args );

			if ( in_array( $tag_id, $user_tags ) && empty( $has_badge ) ) {

				// Logger
				wpf_log( 'info', $user_id, 'User granted BadgeOS badge <a href="' . get_edit_post_link( $badge_id ) . '" target="_blank">' . get_the_title( $badge_id ) . '</a> by tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'badgeos' ) );

				badgeos_award_achievement_to_user( $badge_id, $user_id );

			} elseif ( ! in_array( $tag_id, $user_tags ) && ! empty( $has_badge ) ) {

				// Logger
				wpf_log( 'info', $user_id, 'BadgeOS badge <a href="' . get_edit_post_link( $badge_id ) . '" target="_blank">' . get_the_title( $badge_id ) . '</a> revoked by tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'badgeos' ) );

				badgeos_revoke_achievement_from_user( $badge_id, $user_id );

			}
		}

	}


	/**
	 * Adds meta box
	 *
	 * @access public
	 * @return void
	 */

	public function add_meta_box( $post_type, $post ) {

		add_meta_box( 'wpf-badgeos-meta', 'WP Fusion - Achievement Settings', array( $this, 'meta_box_callback' ), badgeos_get_achievement_types_slugs() );

	}

	/**
	 * Meta box output
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback( $post ) {

		wp_nonce_field( 'wpf_meta_box_badgeos', 'wpf_meta_box_badgeos_nonce' );

		$settings = array(
			'tag_link' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings-badgeos', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings-badgeos', true ) );
		}

		echo '<table class="form-table"><tbody>';

			echo '<tr>';

			echo '<th scope="row"><label for="tag_link">Link with Tag:</label></th>';
			echo '<td>';

			$args = array(
				'setting'     => $settings['tag_link'],
				'meta_name'   => 'wpf-settings-badgeos',
				'field_id'    => 'tag_link',
				'placeholder' => 'Select Tag',
				'limit'       => 1,
			);

			wpf_render_tag_multiselect( $args );

			echo '<span class="description">When the achievement is awarded, this tag will be applied in ' . wp_fusion()->crm->name . '.<br />Likewise, if the tag is applied in ' . wp_fusion()->crm->name . ', the user will automatically be granted the achievement.</span>';
			echo '</td>';

			echo '</tr>';

			echo '</tbody></table>';

	}

	/**
	 * Saves WPF configuration for badge
	 *
	 * @access public
	 * @return void
	 */

	public function save_meta_box_data( $post_id ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_badgeos_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_badgeos_nonce'], 'wpf_meta_box_badgeos' ) ) {
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

		if ( isset( $_POST['wpf-settings-badgeos'] ) ) {
			$data = $_POST['wpf-settings-badgeos'];
		} else {
			$data = array();
		}

		// Update the meta field in the database.
		update_post_meta( $post_id, 'wpf-settings-badgeos', $data );
	}


}

new WPF_BadgeOS();
