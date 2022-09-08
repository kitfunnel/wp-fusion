<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_GamiPress extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'gamipress';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Gamipress';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/gamification/gamipress/';


	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		// Add meta field group
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

		// Achievement tagging
		add_action( 'gamipress_award_achievement', array( $this, 'user_complete_achievement' ), 10, 5 );
		add_action( 'gamipress_revoke_achievement_to_user', array( $this, 'user_revoke_achievement' ), 10, 3 );

		// Ranks
		add_action( 'gamipress_update_user_rank', array( $this, 'update_user_rank' ), 10, 5 );
		add_filter( 'wpf_get_user_meta', array( $this, 'get_user_meta' ), 10, 2 );
		add_filter( 'wpf_pulled_user_meta', array( $this, 'pulled_user_meta' ), 10, 2 );

		// Points
		add_action( 'gamipress_update_user_points', array( $this, 'update_user_points' ), 10, 8 );

		// Activity triggers
		add_filter( 'gamipress_activity_triggers', array( $this, 'activity_triggers' ) );
		add_action( 'wpf_tags_applied', array( $this, 'tags_applied' ), 10, 2 );
		add_action( 'wpf_tags_removed', array( $this, 'tags_removed' ), 10, 2 );

		// Our custom requirement
		add_filter( 'gamipress_requirement_object', array( $this, 'requirement_object' ), 10, 2 );
		add_action( 'gamipress_requirement_ui_html_after_achievement_post', array( $this, 'requirement_ui_fields' ), 10, 2 );
		add_action( 'gamipress_ajax_update_requirement', array( $this, 'update_requirement' ), 10, 2 );
		add_filter( 'user_has_access_to_achievement', array( $this, 'user_has_access_to_achievement' ), 10, 6 );
		add_filter( 'gamipress_trigger_get_user_id', array( $this, 'trigger_get_user_id' ), 10, 3 );

		add_action( 'save_post', array( $this, 'save_multiselect_data' ), 20, 2 );

		// Settings
		add_filter( 'gamipress_achievement_data_fields', array( $this, 'achievement_fields' ) );
		add_filter( 'gamipress_rank_data_fields', array( $this, 'rank_fields' ) );
		add_action( 'cmb2_render_multiselect', array( $this, 'cmb2_render_multiselect' ), 10, 5 );

		// Assign / remove linked achievements
		add_action( 'wpf_tags_modified', array( $this, 'update_linked_achievements' ), 10, 2 );

	}


	/**
	 * Adds field group for BadgeOS to contact fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['gamipress'] = array(
			'title'  => 'Gamipress',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Sets field labels and types for EDD custom fields
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['_gamipress_points'] = array(
			'label' => 'Default Points',
			'type'  => 'int',
			'group' => 'gamipress',
		);

		$points_types = gamipress_get_points_types();

		foreach ( $points_types as $slug => $type ) {
			$meta_fields[ '_gamipress_' . $slug . '_points' ] = array(
				'label' => $type['plural_name'],
				'type'  => 'int',
				'group' => 'gamipress',
			);
		}

		$rank_types = gamipress_get_rank_types();

		if ( ! empty( $rank_types ) ) {

			foreach ( $rank_types as $slug => $type ) {

				$meta_fields[ '_gamipress_' . $slug . '_rank' ] = array(
					'label' => $type['plural_name'],
					'type'  => 'text',
					'group' => 'gamipress',
				);
			}
		}

		return $meta_fields;

	}

	/**
	 * Applies tags when a GamiPress achievement is attained
	 *
	 * @access public
	 * @return void
	 */

	public function user_complete_achievement( $user_id, $achievement_id, $trigger, $site_id, $args ) {

		$settings = get_post_meta( $achievement_id, 'wpf_settings_gamipress', true );

		if ( empty( $settings ) ) {
			return;
		}

		remove_action( 'wpf_tags_modified', array( $this, 'update_linked_achievements' ), 10, 2 );

		if ( ! empty( $settings['wpf_apply_tags'] ) ) {
			wp_fusion()->user->apply_tags( $settings['wpf_apply_tags'], $user_id );
		}

		if ( ! empty( $settings['wpf_tag_link'] ) ) {
			wp_fusion()->user->apply_tags( $settings['wpf_tag_link'], $user_id );
		}

		add_action( 'wpf_tags_modified', array( $this, 'update_linked_achievements' ), 10, 2 );

	}

	/**
	 * Remove tags when a GamiPress achievement is revoked
	 *
	 * @access public
	 * @return void
	 */

	public function user_revoke_achievement( $user_id, $achievement_id, $earning_id ) {

		$settings = get_post_meta( $achievement_id, 'wpf_settings_gamipress', true );

		if ( ! empty( $settings ) && ! empty( $settings['wpf_tag_link'] ) ) {
			wp_fusion()->user->remove_tags( $settings['wpf_tag_link'], $user_id );
		}

	}

	/**
	 * Applies tags and sync meta when a GamiPress rank is attained
	 *
	 * @access public
	 * @return void
	 */

	public function update_user_rank( $user_id, $new_rank, $old_rank, $admin_id, $achievement_id ) {

		$settings = get_post_meta( $new_rank->ID, 'wpf_settings_gamipress', true );

		if ( ! empty( $settings ) && ! empty( $settings['wpf_apply_tags'] ) ) {
			wp_fusion()->user->apply_tags( $settings['wpf_apply_tags'], $user_id );
		}

		$update_data = array(
			"_gamipress_{$new_rank->post_type}_rank" => $new_rank->post_title,
		);

		wp_fusion()->user->push_user_meta( $user_id, $update_data );

	}

	/**
	 * Include each user's rank when syncing user meta.
	 *
	 * This is normally stored in the wp_usermeta table anyway once a rank has
	 * been earned, but this function ensures that users who still have the
	 * default rank are properly updated in the CRM.
	 *
	 * @since  3.38.47
	 *
	 * @param  array $user_meta The user meta.
	 * @param  int   $user_id   The user ID
	 * @return array The user meta.
	 */
	public function get_user_meta( $user_meta, $user_id ) {

		foreach ( gamipress_get_rank_types() as $slug => $type ) {

			$id = gamipress_get_user_rank_id( $user_id, $slug );

			$user_meta[ "_gamipress_{$slug}_rank" ] = get_the_title( $id );

		}

		return $user_meta;

	}

	/**
	 * When loading a rank from the CRM, convert it to a post ID
	 *
	 * @access public
	 * @return array User Meta
	 */

	public function pulled_user_meta( $user_meta, $user_id ) {

		$rank_type_slugs = gamipress_get_rank_types_slugs();

		foreach ( $rank_type_slugs as $slug ) {

			if ( isset( $user_meta[ "_gamipress_{$slug}_rank" ] ) ) {

				$post = get_page_by_title( $user_meta[ "_gamipress_{$slug}_rank" ], OBJECT, $slug );

				if ( $post ) {
					$user_meta[ "_gamipress_{$slug}_rank" ] = $post->ID;
				}
			}
		}

		return $user_meta;

	}

	/**
	 * Update points when points updated
	 *
	 * @access public
	 * @return void
	 */

	public function update_user_points( $user_id, $new_points, $total_points, $admin_id, $achievement_id, $points_type, $reason, $log_type ) {

		if ( empty( $points_type ) ) {
			$key = '_gamipress_points';
		} else {
			$key = '_gamipress_' . $points_type . '_points';
		}

		wp_fusion()->user->push_user_meta( $user_id, array( $key => $total_points ) );

	}

	/**
	 * Register WP Fusion activity triggers
	 *
	 * @access public
	 * @return array Triggers
	 */

	public function activity_triggers( $triggers ) {

		$triggers[ __( 'WP Fusion', 'wp-fusion' ) ] = array(
			'wp_fusion_specific_tag_applied' => sprintf( __( '%s tag applied', 'wp-fusion' ), wp_fusion()->crm->name ),
			'wp_fusion_specific_tag_removed' => sprintf( __( '%s tag removed', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		return $triggers;

	}

	/**
	 * Register the new setting on the requirement object
	 *
	 * @access public
	 * @return void
	 */

	public function requirement_object( $requirement, $requirement_id ) {

		if ( isset( $requirement['trigger_type'] ) && ( 'wp_fusion_specific_tag_applied' === $requirement['trigger_type'] || 'wp_fusion_specific_tag_removed' === $requirement['trigger_type'] ) ) {
			// Field form
			$requirement['wp_fusion_tag'] = get_post_meta( $requirement_id, '_gamipress_wp_fusion_tag', true );
		}

		return $requirement;

	}

	/**
	 * Add select box to Requirements UI
	 *
	 * @access public
	 * @return void
	 */

	public function requirement_ui_fields( $requirement_id, $post_id ) {

		$tags     = wp_fusion()->settings->get_available_tags_flat();
		$selected = get_post_meta( $requirement_id, '_gamipress_wp_fusion_tag', true );

		asort( $tags ); ?>

		<select class="select-wp-fusion-tag">
			<option><?php _e( 'Select a Tag', 'wp-fusion' ); ?></option>
			<?php foreach ( $tags as $id => $tag ) : ?>
				<option value="<?php echo $id; ?>" <?php selected( $selected, $id ); ?>><?php echo $tag; ?></option>
			<?php endforeach; ?>
		</select>

		<?php
	}

	/**
	 * CRMs that don't use tag IDs don't currently save properly with the built in Gamipress logic
	 *
	 * @access public
	 * @return void
	 */

	public function update_requirement( $requirement_id, $requirement ) {

		if ( 'wp_fusion_specific_tag_applied' == $requirement['trigger_type'] || 'wp_fusion_specific_tag_removed' == $requirement['trigger_type'] ) {

			update_post_meta( $requirement_id, '_gamipress_wp_fusion_tag', $requirement['wp_fusion_tag'] );

		}

	}

	/**
	 * Checks if an user is allowed to work on a given requirement related to a
	 * specific form
	 *
	 * @since  3.35.8
	 *
	 * @param  bool   $return         The default return value
	 * @param  int    $user_id        The given user's ID
	 * @param  int    $requirement_id The given requirement's post ID
	 * @param  string $trigger        The trigger triggered
	 * @param  int    $site_id        The site id
	 * @param  array  $args           Arguments of this trigger
	 * @return bool   True if user has access to the requirement, false otherwise
	 */
	public function user_has_access_to_achievement( $return = false, $user_id = 0, $requirement_id = 0, $trigger = '', $site_id = 0, $args = array() ) {

		// If we're not working with a requirement, bail here
		if ( ! in_array( get_post_type( $requirement_id ), gamipress_get_requirement_types_slugs() ) ) {
			return $return;
		}

		// Check if user has access to the achievement ($return will be false if user has exceed the limit or achievement is not published yet)
		if ( ! $return ) {
			return $return;
		}

		// If is specific form trigger, rules engine needs the attached form
		if ( 'wp_fusion_specific_tag_applied' === $trigger || 'wp_fusion_specific_tag_removed' === $trigger ) {

			$tag          = $args[0];
			$required_tag = get_post_meta( $requirement_id, '_gamipress_wp_fusion_tag', true );

			// True if there is a specific form, an attached form and both are equal
			$return = (bool) (
				$tag
				&& $required_tag
				&& $tag == $required_tag
			);

			if ( true == $return ) {

				if ( 'wp_fusion_specific_tag_applied' === $trigger ) {
					$str = 'applied';
				} elseif ( 'wp_fusion_specific_tag_removed' === $trigger ) {
					$str = 'removed';
				}

				wpf_log( 'info', $user_id, 'User triggered Gamipress requirement <strong>' . get_the_title( $requirement_id ) . '</strong> by ' . $str . ' tag <strong>' . wp_fusion()->user->get_tag_label( $tag ) . '</strong>' );
			}
		}

		// Send back our eligibility
		return $return;
	}

	/**
	 * Get the user_id from the args for WP Fusion triggers
	 *
	 * @since  3.36.8
	 *
	 * @param  int    $user_id The user ID.
	 * @param  string $trigger The trigger.
	 * @param  array  $args    The arguments.
	 * @return int    The user ID.
	 */
	public function trigger_get_user_id( $user_id, $trigger, $args ) {

		if ( 'wp_fusion_specific_tag_applied' === $trigger || 'wp_fusion_specific_tag_removed' === $trigger ) {
			$user_id = $args[1];
		}

		return $user_id;

	}

	/**
	 * Trigger any activity triggers when tags applied
	 *
	 * @access public
	 * @return void
	 */

	public function tags_applied( $user_id, $tags ) {

		foreach ( $tags as $id ) {

			do_action( 'wp_fusion_specific_tag_applied', $id, $user_id );

		}

	}

	/**
	 * Trigger any activity triggers when tags removed
	 *
	 * @access public
	 * @return void
	 */

	public function tags_removed( $user_id, $tags ) {

		foreach ( $tags as $id ) {

			do_action( 'wp_fusion_specific_tag_removed', $id, $user_id );

		}

	}


	/**
	 * Update's user achievements when tags are modified
	 *
	 * @access public
	 * @return void
	 */

	public function update_linked_achievements( $user_id, $user_tags ) {

		$linked_achievements = get_posts(
			array(
				'post_type'  => gamipress_get_achievement_types_slugs(),
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf_settings_gamipress',
						'compare' => 'EXISTS',
					),
				),
				'fields'     => 'ids',
			)
		);

		if ( empty( $linked_achievements ) ) {
			return;
		}

		// Prevent looping when the achievements assigned / removed
		remove_action( 'gamipress_award_achievement', array( $this, 'user_complete_achievement' ), 10, 5 );
		remove_action( 'gamipress_revoke_achievement_to_user', array( $this, 'user_revoke_achievement' ), 10, 3 );

		// Assign / revoke linked achievements
		foreach ( $linked_achievements as $achievement_id ) {

			$settings = get_post_meta( $achievement_id, 'wpf_settings_gamipress', true );

			if ( empty( $settings ) || empty( $settings['wpf_tag_link'] ) ) {
				continue;
			}

			$tag_id = $settings['wpf_tag_link'][0];

			$earned = gamipress_get_user_achievements(
				array(
					'user_id'        => absint( $user_id ),
					'achievement_id' => absint( $achievement_id ),
				)
			);

			if ( in_array( $tag_id, $user_tags ) && empty( $earned ) ) {

				// Logger
				wpf_log( 'info', $user_id, 'User granted Gamipress achivement <a href="' . get_edit_post_link( $achievement_id, '' ) . '" target="_blank">' . get_the_title( $achievement_id ) . '</a> by tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

				gamipress_award_achievement_to_user( $achievement_id, $user_id );

			} elseif ( ! in_array( $tag_id, $user_tags ) && ! empty( $earned ) ) {

				// Logger
				wpf_log( 'info', $user_id, 'Gamipress achievement <a href="' . get_edit_post_link( $achievement_id, '' ) . '" target="_blank">' . get_the_title( $achievement_id ) . '</a> revoked by tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

				gamipress_revoke_achievement_to_user( $achievement_id, $user_id );

			}
		}

		add_action( 'gamipress_award_achievement', array( $this, 'user_complete_achievement' ), 10, 5 );
		add_action( 'gamipress_revoke_achievement_to_user', array( $this, 'user_revoke_achievement' ), 10, 3 );

	}


	/**
	 * Renders multiselector
	 *
	 * @access public
	 * @return void
	 */

	public function cmb2_render_multiselect( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {

		wp_nonce_field( 'wpf_multiselect_gamipress', 'wpf_multiselect_gamipress_nonce' );

		$settings = array(
			'wpf_apply_tags' => array(),
			'wpf_tag_link'   => array(),
		);

		if ( get_post_meta( $object_id, 'wpf_settings_gamipress', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $object_id, 'wpf_settings_gamipress', true ) );
		}

		$args = array(
			'setting'   => $settings[ $field->args['id'] ],
			'meta_name' => 'wpf_settings_gamipress',
			'field_id'  => $field->args['id'],
		);

		if ( $field->args['id'] == 'wpf_tag_link' ) {
			$args['limit']       = 1;
			$args['placeholder'] = 'Select a tag';
		}

		wpf_render_tag_multiselect( $args );

		echo '<p class="cmb2-metabox-description">' . $field->args['desc'] . '</p>';

	}

	/**
	 * Add custom achievement fields
	 *
	 * @access public
	 * @return array Fields
	 */

	public function achievement_fields( $fields ) {

		$fields['wpf_apply_tags'] = array(
			'name' => __( 'Apply tags', 'gamipress' ),
			'desc' => sprintf( __( 'These tags will be applied in %s when the achievement is earned.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'type' => 'multiselect',
		);

		$fields['wpf_tag_link'] = array(
			'name' => __( 'Link with Tag', 'gamipress' ),
			'desc' => sprintf( __( 'This tag will be applied when the achievement is earned. Likewise, if this tag is applied in %s the achievement will be automatically granted. If this tag is removed, the achievement will be revoked.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'desc' => '',
			'type' => 'multiselect',
		);

		return $fields;

	}

	/**
	 * Add custom rank fields
	 *
	 * @access public
	 * @return array Fields
	 */

	public function rank_fields( $fields ) {

		$fields['wpf_apply_tags'] = array(
			'name' => __( 'Apply tags', 'wp-fusion' ),
			'desc' => sprintf( __( 'These tags will be applied in %s when the rank is earned.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'type' => 'multiselect',
		);

		return $fields;

	}

	/**
	 * Runs when WPF multiselector is saved
	 *
	 * @access public
	 * @return void
	 */

	public function save_multiselect_data( $post_id ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_multiselect_gamipress_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_multiselect_gamipress_nonce'], 'wpf_multiselect_gamipress' ) ) {
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

		if ( isset( $_POST['wpf_settings_gamipress'] ) ) {
			$data = $_POST['wpf_settings_gamipress'];
		} else {
			$data = array();
		}

		// Update the meta field in the database.
		update_post_meta( $post_id, 'wpf_settings_gamipress', $data );

	}


}

new WPF_GamiPress();
