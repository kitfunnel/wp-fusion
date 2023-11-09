<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Bricks builder integration.
 *
 * @since 3.40.11
 *
 * @link https://wpfusion.com/documentation/page-builders/bricks/
 */
class WPF_Bricks extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.11
	 * @var string $slug
	 */
	public $slug = 'bricks';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.11
	 * @var string $name
	 */
	public $name = 'Bricks';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.11
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/page-builders/bricks/';

	/**
	 * Gets things started.
	 *
	 * @since 3.40.11
	 */
	public function init() {

		if ( ! wpf_get_option( 'restrict_content', true ) ) {
			return;
		}

		add_filter( 'bricks/element/render', array( $this, 'render' ), 10, 2 );

		foreach ( $this->get_elements() as $element ) {
			add_filter( 'bricks/elements/' . strtolower( $element ) . '/control_groups', array( $this, 'add_common_control_group' ) );
			add_filter( 'bricks/elements/' . strtolower( $element ) . '/controls', array( $this, 'add_controls' ) );
		}

		// Query controls.
		add_filter( 'bricks/elements/posts/controls', array( $this, 'add_query_control' ) );
		add_filter( 'bricks/posts/query_vars', array( $this, 'query_args' ), 10, 2 );

	}


	/**
	 * Determines if a user has access to an element.
	 *
	 * @since  3.40.11
	 *
	 * @param  Bricks\Element $element The element.
	 * @return bool   Whether or not the user can access the element.
	 */
	private function can_access( $element ) {

		if ( is_admin() ) {
			return true;
		}

		$visibility      = isset( $element->settings['wpf_visibility'] ) ? $element->settings['wpf_visibility'] : 'everyone';
		$widget_tags     = isset( $element->settings['wpf_tags'] ) ? $element->settings['wpf_tags'] : array();
		$widget_tags_all = isset( $element->settings['wpf_tags_all'] ) ? $element->settings['wpf_tags_all'] : array();
		$widget_tags_not = isset( $element->settings['wpf_tags_not'] ) ? $element->settings['wpf_tags_not'] : array();

		if ( 'everyone' === $visibility && empty( $widget_tags ) && empty( $widget_tags_all ) && empty( $widget_tags_not ) ) {

			// No settings, allow access.

			$can_access = apply_filters( 'wpf_bricks_can_access', true, $element );

			return apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), false );

		}

		if ( wpf_admin_override() ) {
			return true;
		}

		$can_access = true;

		if ( wpf_is_user_logged_in() ) {

			$user_tags = wp_fusion()->user->get_tags();

			if ( 'everyone' === $visibility || 'loggedin' === $visibility ) {

				// See if user has required tags.

				if ( ! empty( $widget_tags ) ) {

					// Required tags (any).

					$result = array_intersect( $widget_tags, $user_tags );

					if ( empty( $result ) ) {
						$can_access = false;
					}
				}

				if ( true === $can_access && ! empty( $widget_tags_all ) ) {

					// Required tags (all).

					$result = array_intersect( $widget_tags_all, $user_tags );

					if ( count( $result ) !== count( $widget_tags_all ) ) {
						$can_access = false;
					}
				}

				if ( true === $can_access && ! empty( $widget_tags_not ) ) {

					// Required tags (not).

					$result = array_intersect( $widget_tags_not, $user_tags );

					if ( ! empty( $result ) ) {
						$can_access = false;
					}
				}
			} elseif ( 'loggedout' === $visibility ) {

				// The user is logged in but the widget is set to logged-out only.
				$can_access = false;

			}
		} else {

			// Not logged in.

			if ( 'loggedin' === $visibility ) {

				// The user is not logged in but the widget is set to logged-in only.
				$can_access = false;

			} elseif ( 'everyone' === $visibility ) {

				// Also deny access if tags are specified.

				if ( ! empty( $widget_tags ) || ! empty( $widget_tags_all ) ) {
					$can_access = false;
				}
			}
		}

		$can_access = apply_filters( 'wpf_bricks_can_access', $can_access, $element );

		$can_access = apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), false );

		if ( $can_access ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Don't render the element if the user doesn't have the right permissions.
	 *
	 * @since  3.40.11
	 *
	 * @param  bool           $render  To render or not.
	 * @param  Bricks\Element $element The element.
	 * @return bool           To render or not.
	 */
	public function render( $render, $element ) {
		if ( $element->is_frontend && ! $this->can_access( $element ) ) {
			return false;
		}
		return $render;
	}

	/**
	 * Add common control group.
	 *
	 * @since  3.40.11
	 *
	 * @param  array $control_groups The control groups.
	 * @return array The control groups.
	 */
	public function add_common_control_group( $control_groups ) {

		$control_groups['wpfusion'] = array(
			'title' => esc_html__( 'WP Fusion', 'wp-fusion' ),
			'tab'   => 'content',
		);
		return $control_groups;
	}

	/**
	 * Add WPF controls to all elements.
	 *
	 * @since  3.40.11
	 *
	 * @param  array $controls The controls.
	 * @return array The controls.
	 */
	public function add_controls( $controls ) {
		$data = wp_fusion()->settings->get_available_tags_flat();

		$controls['wpf_visibility'] = array(
			'tab'     => 'content',
			'group'   => 'wpfusion',
			'label'   => __( 'Visibility', 'wp-fusion' ),
			'type'    => 'select',
			'options' => array(
				'everyone'  => __( 'Everyone', 'wp-fusion' ),
				'loggedin'  => __( 'Logged In Users', 'wp-fusion' ),
				'loggedout' => __( 'Logged Out Users', 'wp-fusion' ),
			),
			'inline'  => false,
		);

		$controls['wpf_tags'] = array(
			'tab'         => 'content',
			'group'       => 'wpfusion',
			'label'       => sprintf( __( 'Required %s Tags (Any)', 'wp-fusion' ), wp_fusion()->crm->name ),
			'type'        => 'select',
			'options'     => $data,
			'multiple'    => true,
			'inline'      => false,
			'required'    => array( 'wpf_visibility', '=', array( 'loggedin', 'everyone' ) ),
			'description' => __( 'The user must be logged in and have at least one of the tags specified to access the content.', 'wp-fusion' ),
		);

		$controls['wpf_tags_all'] = array(
			'tab'         => 'content',
			'group'       => 'wpfusion',
			'label'       => sprintf( __( 'Required %s Tags (All)', 'wp-fusion' ), wp_fusion()->crm->name ),
			'type'        => 'select',
			'options'     => $data,
			'multiple'    => true,
			'inline'      => false,
			'required'    => array( 'wpf_visibility', '=', array( 'loggedin', 'everyone' ) ),
			'description' => __( 'The user must be logged in and have <em>all</em> of the tags specified to access the content.', 'wp-fusion' ),
		);

		$controls['wpf_tags_not'] = array(
			'tab'         => 'content',
			'group'       => 'wpfusion',
			'label'       => sprintf( __( 'Required %s Tags (Not)', 'wp-fusion' ), wp_fusion()->crm->name ),
			'type'        => 'select',
			'options'     => $data,
			'multiple'    => true,
			'inline'      => false,
			'required'    => array( 'wpf_visibility', '=', array( 'loggedin', 'everyone' ) ),
			'description' => __( 'If the user is logged in and has any of these tags, the content will be hidden.', 'wp-fusion' ),
		);

		return $controls;
	}

	/**
	 * Get Bricks elements.
	 *
	 * @since  3.40.11
	 *
	 * @return array The elements.
	 */
	public function get_elements() {

		$elements_names = array();

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$files = list_files( BRICKS_PATH . 'includes/elements/' );

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				$filesize         = size_format( filesize( $file ) );
				$elements_names[] = str_replace( '.php', '', basename( $file ) );
			}
		}

		return $elements_names;
	}


	/**
	 * Add query control.
	 *
	 * @param array $controls
	 * @return array
	 */
	public function add_query_control( $controls ) {
		$controls['wpf_filter_queries'] = array(
			'tab'         => 'content',
			'group'       => 'wpfusion',
			'label'       => __( 'Filter Queries', 'wp-fusion' ),
			'type'        => 'checkbox',
			'description' => __( 'Filter query results based on WP Fusion access rules.', 'wp-fusion' ),
		);

		return $controls;
	}

	/**
	 * Filter queries if enabled
	 *
	 * @param array $query_vars The query vars.
	 * @param array $settings   The settings.
	 * @return array The query vars.
	 */
	public function query_args( $query_vars, $settings ) {

		if ( ! isset( $settings['wpf_filter_queries'] ) || true !== $settings['wpf_filter_queries'] ) {
			return $query_vars;
		}

		// No need to do this again if WPF is already doing it globally.
		if ( 'advanced' === wpf_get_option( 'hide_archives' ) ) {
			return $query_vars;
		}

		if ( wpf_admin_override() ) {
			return $query_vars;
		}

		// This lets everyone know WP Fusion is messing with the query, and also
		// enables the posts_where filter in WPF_Access_Control.

		$query_vars['wpf_filtering_query'] = true;

		// Exclude any restricted post IDs.

		$post_ids = wp_fusion()->access->get_restricted_posts( $query_vars['post_type'] );

		if ( ! empty( $post_ids ) ) {
			$query_vars['post__not_in'] = $post_ids;
		}

		return $query_vars;
	}

}

new WPF_Bricks();
