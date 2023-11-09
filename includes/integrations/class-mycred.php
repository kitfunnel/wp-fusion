<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_myCRED extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'mycred';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Mycred';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/gamification/mycred/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// Badge actions
		add_action( 'mycred_badge_level_reached', array( $this, 'badge_level_reached' ), 10, 3 );

		// Rank actions
		add_action( 'updated_user_meta', array( $this, 'rank_updated' ), 10, 4 );
		add_action( 'added_user_meta', array( $this, 'rank_updated' ), 10, 4 );

		// Assign / remove linked badges and ranks
		add_action( 'wpf_tags_modified', array( $this, 'update_linked_badges' ), 10, 2 );
		add_action( 'wpf_tags_modified', array( $this, 'update_linked_ranks' ), 10, 2 );

		// Remove WPF side meta box on MyCRED post types
		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );

		// Meta boxes
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20, 2 );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ), 20, 2 );

	}

	/**
	 * Apply tags when badge assigned
	 *
	 * @access public
	 * @return void
	 */

	public function badge_level_reached( $user_id, $badge_id, $level_reached ) {

		$wpf_settings = get_post_meta( $badge_id, 'wpf-settings-mycred', true );

		if ( ! empty( $wpf_settings['tag_link'] ) ) {

			// Prevent looping
			remove_action( 'wpf_tags_modified', array( $this, 'update_linked_badges' ), 10, 2 );

			wp_fusion()->user->apply_tags( $wpf_settings['tag_link'], $user_id );

			add_action( 'wpf_tags_modified', array( $this, 'update_linked_badges' ), 10, 2 );

		}

	}

	/**
	 * Apply tags when rank earned
	 *
	 * @access public
	 * @return void
	 */

	public function rank_updated( $meta_id, $user_id, $meta_key, $rank_id ) {

		if ( ! defined( 'MYCRED_RANK_KEY' ) || MYCRED_RANK_KEY !== $meta_key ) {
			return;
		}

		$settings = get_post_meta( $rank_id, 'wpf-settings-mycred', true );

		if ( ! empty( $settings ) ) {

			$apply_tags = array();

			if ( ! empty( $settings['apply_tags'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags'] );
			}

			if ( ! empty( $settings['tag_link'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['tag_link'] );
			}

			if ( ! empty( $apply_tags ) ) {

				// Prevent looping
				remove_action( 'wpf_tags_modified', array( $this, 'update_linked_ranks' ), 10, 2 );

				wp_fusion()->user->apply_tags( $apply_tags, $user_id );

				add_action( 'wpf_tags_modified', array( $this, 'update_linked_ranks' ), 10, 2 );

			}
		}

	}

	/**
	 * Update's user badges when tags are modified
	 *
	 * @access public
	 * @return void
	 */

	public function update_linked_badges( $user_id, $user_tags ) {

		// If badges aren't enabled

		if ( ! function_exists( 'mycred_badge_level_reached' ) ) {
			return;
		}

		$linked_badges = get_posts(
			array(
				'post_type'  => 'mycred_badge',
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf-settings-mycred',
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
		remove_action( 'mycred_badge_level_reached', array( $this, 'badge_level_reached' ), 10, 3 );

		$user_badges = mycred_get_users_badges( $user_id, true );

		if ( empty( $user_badges ) ) {
			$user_badges = array();
		}

		// Assign linked badges
		foreach ( $linked_badges as $badge_id ) {

			$settings = get_post_meta( $badge_id, 'wpf-settings-mycred', true );

			if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
				continue;
			}

			$tag_id = $settings['tag_link'][0];

			if ( in_array( $tag_id, $user_tags ) && ! isset( $user_badges[ $badge_id ] ) ) {

				// Logger
				wpf_log( 'info', $user_id, 'User granted myCred badge <a href="' . get_edit_post_link( $badge_id ) . '" target="_blank">' . get_the_title( $badge_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'mycred' ) );

				mycred_assign_badge_to_user( $user_id, $badge_id );

				// Clear the cache
				mycred_get_users_badges( $user_id, true );

			} elseif ( ! in_array( $tag_id, $user_tags ) && isset( $user_badges[ $badge_id ] ) ) {

				// Remove badge
				wpf_log( 'info', $user_id, 'myCred badge <a href="' . get_edit_post_link( $badge_id ) . '" target="_blank">' . get_the_title( $badge_id ) . '</a> removed by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'mycred' ) );

				$badge = mycred_get_badge( $badge_id );

				$badge->divest( $user_id );

				// Clear the cache
				mycred_get_users_badges( $user_id, true );

			}
		}

	}

	/**
	 * Update's user badges when tags are modified
	 *
	 * @access public
	 * @return void
	 */

	public function update_linked_ranks( $user_id, $user_tags ) {

		// Quit if not enabled

		if ( ! defined( 'MYCRED_RANK_KEY' ) ) {
			return;
		}

		$linked_ranks = get_posts(
			array(
				'post_type'  => 'mycred_rank',
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf-settings-mycred',
						'compare' => 'EXISTS',
					),
				),
				'fields'     => 'ids',
			)
		);

		if ( empty( $linked_ranks ) ) {
			return;
		}

		// Prevent looping when the badges assigned / removed
		remove_action( 'updated_user_meta', array( $this, 'rank_updated' ), 10, 4 );
		remove_action( 'added_user_meta', array( $this, 'rank_updated' ), 10, 4 );

		// Assign linked ranks
		foreach ( $linked_ranks as $rank_id ) {

			$settings = get_post_meta( $rank_id, 'wpf-settings-mycred', true );

			if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
				continue;
			}

			$user_rank = get_user_meta( $user_id, MYCRED_RANK_KEY, true );

			$tag_id = $settings['tag_link'][0];

			if ( in_array( $tag_id, $user_tags ) && $user_rank != $rank_id ) {

				// Logger
				wpf_log( 'info', $user_id, 'User granted myCred rank <a href="' . get_edit_post_link( $rank_id ) . '" target="_blank">' . get_the_title( $rank_id ) . '</a> by tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>', array( 'source' => 'mycred' ) );

				update_user_meta( $user_id, MYCRED_RANK_KEY, $rank_id );

			}
		}

	}

	/**
	 * Hide WPF meta box on MyCRED post types
	 *
	 * @access public
	 * @return array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['mycred_badge'] );
		unset( $post_types['mycred_rank'] );

		return $post_types;

	}


	/**
	 * Adds meta box
	 *
	 * @access public
	 * @return void
	 */

	public function add_meta_box( $post_type, $post ) {

		add_meta_box( 'wpf-mycred-meta', 'WP Fusion - Badge Settings', array( $this, 'meta_box_callback' ), 'mycred_badge' );
		add_meta_box( 'wpf-mycred-meta', 'WP Fusion - Rank Settings', array( $this, 'meta_box_callback_rank' ), 'mycred_rank' );

	}

	/**
	 * Meta box output
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback( $post ) {

		wp_nonce_field( 'wpf_meta_box_mycred', 'wpf_meta_box_mycred_nonce' );

		$settings = array(
			'tag_link' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings-mycred', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings-mycred', true ) );
		}

		echo '<table class="form-table"><tbody>';

			echo '<tr>';

			echo '<th scope="row"><label for="tag_link">' . __( 'Link with Tag', 'wp-fusion' ) . ':</label></th>';
			echo '<td>';

			$args = array(
				'setting'     => $settings['tag_link'],
				'meta_name'   => 'wpf-settings-mycred',
				'field_id'    => 'tag_link',
				'placeholder' => 'Select Tag',
				'limit'       => 1,
			);

			wpf_render_tag_multiselect( $args );

			echo '<span class="description">' . sprintf( __( 'When the badge is awarded this tag will be applied in %1$s.<br />Likewise, if the tag is applied in %2$s, the user will automatically be granted the badge. If the tag is removed, the badge will be removed.', 'wp-fusion' ), wp_fusion()->crm->name, wp_fusion()->crm->name ) . '</span>';
			echo '</td>';

			echo '</tr>';

			echo '</tbody></table>';

	}

	/**
	 * Meta box output
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback_rank( $post ) {

		wp_nonce_field( 'wpf_meta_box_mycred', 'wpf_meta_box_mycred_nonce' );

		$settings = array(
			'apply_tags' => array(),
			'tag_link'   => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings-mycred', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings-mycred', true ) );
		}

		echo '<table class="form-table"><tbody>';

			echo '<tr>';

			echo '<th scope="row"><label for="apply_tags">' . __( 'Apply tags', 'wp-fusion' ) . ':</label></th>';
			echo '<td>';

			$args = array(
				'setting'   => $settings['apply_tags'],
				'meta_name' => 'wpf-settings-mycred',
				'field_id'  => 'apply_tags',
			);

			wpf_render_tag_multiselect( $args );

			echo '<span class="description">' . sprintf( __( 'Select tags to be applied in %s when this rank is earned.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
			echo '</td>';

			echo '</tr>';

			echo '<th scope="row"><label for="tag_link">' . __( 'Link with Tag', 'wp-fusion' ) . ':</label></th>';
			echo '<td>';

			$args = array(
				'setting'   => $settings['tag_link'],
				'meta_name' => 'wpf-settings-mycred',
				'field_id'  => 'tag_link',
			);

			wpf_render_tag_multiselect( $args );

			echo '<span class="description">' . sprintf( __( 'When the rank is awarded this tag will be applied in %1$s.<br />Likewise, if the tag is applied in %2$s, the user will automatically be granted the rank.', 'wp-fusion' ), wp_fusion()->crm->name, wp_fusion()->crm->name ) . '</span>';
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
		if ( ! isset( $_POST['wpf_meta_box_mycred_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_mycred_nonce'], 'wpf_meta_box_mycred' ) ) {
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

		if ( isset( $_POST['wpf-settings-mycred'] ) ) {
			update_post_meta( $post_id, 'wpf-settings-mycred', $_POST['wpf-settings-mycred'] );
		} else {
			delete_post_meta( $post_id, 'wpf-settings-mycred' );
		}

	}

}

new WPF_myCRED();
