<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_bbPress extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'bbpress';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'bbPress';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/forums/bbpress/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// Settings.
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		add_action( 'wpf_meta_box_content', array( $this, 'meta_box_content' ), 40, 2 );

		// Syncing data.
		add_filter( 'wpf_user_update', array( $this, 'profile_update' ), 10, 2 );
		add_filter( 'wp_pre_insert_user_data', array( $this, 'sync_email_address_changes' ), 10, 3 );

		// New topics / replies:
		add_action( 'bbp_new_topic', array( $this, 'topic_created' ), 10, 4 );
		add_action( 'bbp_new_reply', array( $this, 'reply_created' ), 10, 5 );

		if ( wpf_get_option( 'restrict_content', true ) ) {

			// Access control and redirects.
			add_action( 'wpf_begin_redirect', array( $this, 'begin_redirect' ), 10, 2 );
			add_filter( 'wpf_redirect_post_id', array( $this, 'redirect_post_id' ) );
			add_filter( 'wpf_user_can_access_post_id', array( $this, 'user_can_access_post_id' ) );
			add_filter( 'wpf_post_access_meta', array( $this, 'inherit_permissions_from_forum' ), 10, 2 );

			add_action( 'wpf_filtering_page_content', array( $this, 'prepare_content_filter' ) );
			add_filter( 'bbp_get_forum_class', array( $this, 'get_forum_class' ), 10, 2 );

			// Query filtering.
			add_filter( 'bbp_exclude_forum_ids', array( $this, 'query_filter_exclude_forum_ids' ), 10, 3 );
			add_filter( 'wpf_should_filter_query', array( $this, 'should_filter_query' ), 10, 2 );

		}

	}

	/**
	 * Registers bbPress settings
	 *
	 * @access  public
	 * @return  array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['bbp_header'] = array(
			'title'   => __( 'bbPress Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['bbp_lock'] = array(
			'title'   => __( 'Restrict Archives', 'wp-fusion' ),
			'desc'    => sprintf( __( 'Restrict access to forums archive (%s/forums/)', 'wp-fusion' ), home_url() ),
			'type'    => 'checkbox',
			'section' => 'integrations',
			'unlock'  => array( 'bbp_lock_all', 'bbp_allow_tags', 'bbp_redirect' ),
		);

		$settings['bbp_lock_all'] = array(
			'title'   => __( 'Restrict Forums', 'wp-fusion' ),
			'desc'    => __( 'Restrict access to all forums in addition to the archive', 'wp-fusion' ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		$settings['bbp_allow_tags'] = array(
			'title'     => __( 'Required tags (any)', 'wp-fusion' ),
			'desc'      => __( 'If the user doesn\'t have any of the tags specified, they will be redirected to the URL below. You must specify a redirect for forum restriction to work.', 'wp-fusion' ),
			'type'      => 'assign_tags',
			'section'   => 'integrations',
			'read_only' => true,
		);

		$settings['bbp_redirect'] = array(
			'title'   => __( 'Redirect URL', 'wp-fusion' ),
			'type'    => 'text',
			'std'     => home_url(),
			'section' => 'integrations',
		);

		return $settings;

	}

	/**
	 * Adds-forum and topic specific options to the meta boxes.
	 *
	 * @since 3.40.57
	 *
	 * @param  WP_Post $post     The post object.
	 * @param  array   $settings The settings array.
	 * @return mixed The settings output.
	 */
	public function meta_box_content( $post, $settings ) {

		$defaults = array(
			'apply_tags_forum_post'  => array(),
			'apply_tags_topic_reply' => array(),
		);

		$settings = array_merge( $defaults, $settings );

		if ( 'forum' === $post->post_type ) {

			echo '<hr />';

			echo '<p><label for="wpf-settings-apply_tags_forum_post"><small>';
			printf( esc_html__( 'Apply tags - new %s topic:', 'wp-fusion' ), esc_html( $post->post_type_singular_name ) );
			echo '</small>';
			echo '<span class="dashicons dashicons-editor-help wpf-tip wpf-tip-bottom" data-tip="' . sprintf( esc_attr__( 'These tags will be applied to the user when they post a topic in this %s.', 'wp-fusion' ), esc_html( $post->post_type_singular_name ) ) . '"></span>';
			echo '</label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags_forum_post'],
					'meta_name' => 'wpf-settings',
					'field_id'  => 'apply_tags_forum_post',
				)
			);

			echo '</p>';

		} elseif ( 'topic' === $post->post_type ) {

			echo '<hr />';

			echo '<p><label for="wpf-settings-apply_tags_topic_reply"><small>';
			printf( esc_html__( 'Apply tags - new %s reply:', 'wp-fusion' ), esc_html( $post->post_type_singular_name ) );
			echo '</small>';
			echo '<span class="dashicons dashicons-editor-help wpf-tip wpf-tip-bottom" data-tip="' . sprintf( esc_attr__( 'These tags will be applied to the user when they post a reply to this %s.', 'wp-fusion' ), esc_html( $post->post_type_singular_name ) ) . '"></span>';
			echo '</label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags_topic_reply'],
					'meta_name' => 'wpf-settings',
					'field_id'  => 'apply_tags_topic_reply',
				)
			);

			echo '</p>';

		}

	}

	/**
	 * Sync bbPress frontend profile updates.
	 *
	 * @since  3.36.8
	 *
	 * @param  array $user_meta The user meta.
	 * @param  int   $user_id   The user identifier.
	 * @return array  $data The profile data.
	 */
	public function profile_update( $user_meta, $user_id ) {

		if ( isset( $_REQUEST['bbp_user_edit_submit'] ) ) {

			$field_map = array(
				'email'         => 'user_email',
				'url'           => 'user_url',
				'pass1-text'    => 'user_pass',
				'user_password' => 'user_pass',
				'pass1'         => 'user_pass',
			);

			$user_meta = $this->map_meta_fields( $user_meta, $field_map );

		}

		return $user_meta;

	}

	/**
	 * Sync frontend email address changes after they've been confirmed
	 *
	 * @since  3.36.8
	 *
	 * @param  array $user_meta The user meta.
	 * @param  bool  $update    Updating vs. new user.
	 * @param  int   $user_id   The user ID being updated.
	 * @return array $data The profile data.
	 */
	public function sync_email_address_changes( $user_meta, $update, $user_id ) {

		if ( isset( $_REQUEST['action'] ) && 'bbp-update-user-email' == $_REQUEST['action'] ) {
			wp_fusion()->user->push_user_meta( $user_id, array( 'user_email' => $user_meta['user_email'] ) );
		}

		return $user_meta;

	}

	/**
	 * Triggered when a topic is created in frontend.
	 *
	 * @since  3.40.57
	 * @param int   $topic_id       The topic id.
	 * @param int   $forum_id       The forum id.
	 * @param array $anonymous_data Annonymous data.
	 * @param int   $topic_author   The topic author.
	 */
	public function topic_created( $topic_id, $forum_id, $anonymous_data, $topic_author ) {

		$settings = get_post_meta( $forum_id, 'wpf-settings', true );

		if ( $settings && ! empty( $settings['apply_tags_forum_post'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_forum_post'], $topic_author );

		}

	}

	/**
	 * Triggered when a topic is created in frontend.
	 *
	 * @since  3.40.57
	 * @param int   $reply_id the reply id.
	 * @param int   $topic_id the topic id.
	 * @param int   $forum_id the forum id.
	 * @param array $anonymous_data annonymous data.
	 * @param int   $reply_author topic author.
	 */
	public function reply_created( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {

		$settings = get_post_meta( $topic_id, 'wpf-settings', true );

		if ( $settings && ! empty( $settings['apply_tags_topic_reply'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_topic_reply'], $reply_author );

		}

	}


	/**
	 * Sets topics to inherit permissions from their forums
	 *
	 * @access  public
	 * @return  int Post ID
	 */

	public function redirect_post_id( $post_id ) {

		if ( 'topic' == get_post_type( $post_id ) ) {

			$settings = get_post_meta( $post_id, 'wpf-settings', true );

			if ( empty( $settings ) || empty( $settings['lock_content'] ) ) {

				// If the discussion is open then inherit permissions from the parent forum

				$forum_id = get_post_meta( $post_id, '_bbp_forum_id', true );

				if ( ! empty( $forum_id ) ) {
					$post_id = $forum_id;
				}
			}
		}

		// In BuddyBoss a forum can be displayed as a tab on a group, which bypasses the normal template_redirect

		if ( empty( $post_id ) && function_exists( 'bp_is_current_action' ) && bp_is_current_action( 'forum' ) ) {

			$forum_ids = bbp_get_group_forum_ids( bp_get_current_group_id() );

			if ( ! empty( $forum_ids ) ) {
				$post_id = array_shift( $forum_ids );
			}
		}

		return $post_id;

	}

	/**
	 * Inherit protections for replies from the topic
	 *
	 * @access  public
	 * @return  int Post ID
	 */

	public function user_can_access_post_id( $post_id ) {

		if ( 'reply' == get_post_type( $post_id ) ) {

			$post_id = get_post_meta( $post_id, '_bbp_topic_id', true );

		}

		return $post_id;

	}

	/**
	 * Inherit protections for replies from the topic
	 *
	 * @access  public
	 * @return  array Access Meta
	 */

	public function inherit_permissions_from_forum( $access_meta, $post_id ) {

		if ( empty( $access_meta ) || empty( $access_meta['lock_content'] ) ) {

			$post_type = get_post_type( $post_id );

			if ( 'topic' == $post_type ) {

				$forum_id    = get_post_meta( $post_id, '_bbp_forum_id', true );
				$access_meta = get_post_meta( $forum_id, 'wpf-settings', true );

			}

			if ( wpf_get_option( 'bbp_lock_all' ) ) {

				// If all forum content is locked

				if ( 'topic' == $post_type || 'forum' == $post_type ) {

					if ( empty( $access_meta ) || empty( $access_meta['lock_content'] ) ) {

						// Inherit from site
						$access_meta = array(
							'lock_content' => true,
							'allow_tags'   => wpf_get_option( 'bbp_allow_tags', array() ),
							'redirect_url' => wpf_get_option( 'bbp_redirect', home_url() ),
						);

					}
				}
			}
		}

		return $access_meta;

	}


	/**
	 * If query filtering is enabled, exclude restricted forum IDs from the
	 * bbPress topic query.
	 *
	 * @since  3.37.6
	 * @since  3.37.11 Made it only run on Advanced mode.
	 *
	 * @param  array|string $retval    The return value.
	 * @param  array        $forum_ids The forum IDs to exclude.
	 * @param  string       $type      The type of return value.
	 * @return array|string The return value.
	 */
	public function query_filter_exclude_forum_ids( $retval, $forum_ids, $type ) {

		if ( is_admin() ) {
			return $retval;
		}

		if ( 'advanced' == wpf_get_option( 'hide_archives' ) && wp_fusion()->access->is_post_type_eligible_for_query_filtering( 'topic' ) ) {

			// Prevent looping

			remove_filter( 'bbp_exclude_forum_ids', array( $this, 'query_filter_exclude_forum_ids' ), 10, 3 );

			$not_in = wp_fusion()->access->get_restricted_posts( 'forum' );

			add_filter( 'bbp_exclude_forum_ids', array( $this, 'query_filter_exclude_forum_ids' ), 10, 3 );

			if ( ! empty( $not_in ) ) {

				$forum_ids = array_merge( $forum_ids, $not_in );

				switch ( $type ) {

					// Separate forum ID's into a comma separated string
					case 'string':
						$retval = implode( ',', $forum_ids );
						break;

					// Use forum_ids array
					case 'array':
						$retval = $forum_ids;
						break;

					// Build a meta_query
					case 'meta_query':
						$retval = array(
							'key'     => '_bbp_forum_id',
							'value'   => implode( ',', $forum_ids ),
							'type'    => 'numeric',
							'compare' => ( 1 < count( $forum_ids ) ) ? 'NOT IN' : '!=',
						);
						break;
				}
			}
		}

		return $retval;

	}

	/**
	 * Bypass query filtering on topics (they inherit permission from forums).
	 *
	 * @since  3.36.17
	 * @since  3.37.6 Inverted the logic when the filter name changed to wpf_should_filter_query.
	 *
	 * @param  bool     $filter Whether or not to filter the query.
	 * @param  WP_Query $query  The query.
	 * @return bool     Whether or not to filter the query.
	 */
	public function should_filter_query( $filter, $query ) {

		$post_type = (array) $query->get( 'post_type' );

		if ( in_array( 'topic', $post_type ) && 'advanced' == wpf_get_option( 'hide_archives' ) ) {
			return false;
		}

		return $filter;

	}

	/**
	 * Re-add the content filter after bbPress has removed it for theme compatibility
	 *
	 * @access public
	 * @return void
	 */

	public function prepare_content_filter( $post_id ) {

		add_action( 'bbp_head', array( $this, 'add_content_filter' ) );

	}


	/**
	 * Re-add the content filter after bbPress has removed it for theme compatibility
	 *
	 * @access public
	 * @return void
	 */

	public function add_content_filter( $post_id ) {

		add_filter( 'the_content', array( wp_fusion()->access, 'restricted_content_filter' ) );

	}

	/**
	 * Enables redirects for bbP forum archives
	 *
	 * @access public
	 * @return void
	 */

	public function begin_redirect( $bypass, $user_id ) {

		if ( wpf_get_option( 'bbp_lock' ) ) {

			global $post;

			if (
				bbp_is_forum_archive() ||
				bbp_is_search() ||
				( is_object( $post ) && bbp_is_forum( $post->ID ) && wpf_get_option( 'bbp_lock_all' ) ) ||
				( function_exists( 'bp_is_current_action' ) && bp_is_current_action( urlencode( get_option( '_bbp_forum_slug', 'forum' ) ) ) )
			) {

				$redirect = apply_filters( 'wpf_redirect_url', wpf_get_option( 'bbp_redirect' ), $post_id = false );

				if ( empty( $redirect ) ) {
					return $bypass;
				}

				// If admins are excluded from restrictions.
				if ( wpf_admin_override() ) {
					return $bypass;
				}

				if ( ! wpf_is_user_logged_in() || ! wpf_has_tag( wpf_get_option( 'bbp_allow_tags', array() ) ) ) {
					wp_redirect( $redirect, 302, 'WP Fusion; Restricted forum.' );
					exit();
				}
			} else {
				return false; // Restrict Archives is enabled but this isn't a bbPress request.
			}
		} elseif ( bbp_is_search() ) {

			return true; // never restrict the search page.

		}

		return false;

	}


	/**
	 * Applies a class to bbPress forums if they're locked
	 *
	 * @access  public
	 * @return  array Classes
	 */

	public function get_forum_class( $classes, $forum_id ) {

		if ( ! wp_fusion()->access->user_can_access( $forum_id ) ) {
			$classes[] = 'wpf-locked';
		}

		return $classes;

	}


}

new WPF_bbPress();
