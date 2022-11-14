<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WPBakery builder integration.
 *
 * @since 3.40.12
 *
 * @link https://wpfusion.com/documentation/page-builders/wpbakery/
 */
class WPF_WPBakery extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.12
	 * @var string $slug
	 */
	public $slug = 'wpbakery';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.12
	 * @var string $name
	 */
	public $name = 'WP Bakery';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.12
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started.
	 *
	 * @since 3.40.12
	 */
	public function init() {

		add_action( 'vc_before_init', array( $this, 'add_controls' ) );

		$params = array(
			'wpf_tags',
			'wpf_tags_all',
			'wpf_tags_not',
		);
		foreach ( $this->get_shortcodes() as $name ) {
			foreach ( $params as $param ) {
				add_filter(
					'vc_autocomplete_' . $name . '_' . $param . '_callback',
					array(
						$this,
						'get_tags',
					),
					10,
					1
				);

			}
		}

		add_action( 'wp_head', array( $this, 'hide_element' ) );
		add_action( 'wp_footer', array( $this, 'remove_element' ) );

		add_filter( 'vc_shortcodes_css_class', array( $this, 'filter_css_class' ), 10, 3 );

	}

	/**
	 * Remove element by Javascript.
	 * 
	 * @since 3.40.12
	 */
	public function remove_element() {
		echo '<script>
            jQuery(".wpf-cannot-access").remove();
        </script>';
	}

	/**
	 * Hide element through CSS.
	 *
	 * @since 3.40.12
	 */
	public function hide_element() {
		echo '<style>
            .wpf-cannot-access{
                display:none;
            }
        </style>';
	}

	/**
	 * Apply filter to the div CSS class.
	 * 
	 * @since 3.40.12
	 *
	 * @param string $class_to_filter The class to filter.
	 * @param string $tag             The tag.
	 * @param array  $atts            The atts.
	 * @return string The classes.
	 */
	public function filter_css_class( $class_to_filter, $tag, $atts = array() ) {

		if ( empty( $atts ) ) {
			return;
		}

		$can_access = $this->can_access( $atts );

		if ( ! $can_access ) {
			return $class_to_filter . ' wpf-cannot-access';
		}

		return $class_to_filter;
	}


	/**
	 * Get WPF CRM Tags.
	 * 
	 * @since 3.40.12
	 *
	 * @param string $query
	 * @return array
	 */
	public function get_tags( $query ) {

		$tags   = wp_fusion()->settings->get_available_tags_flat();
		$array  = array_keys( $tags );
		$search = preg_grep( "/^$query.*/", $array );
		$data   = array();

		if ( ! empty( $search ) ) {
			foreach ( $search as $key ) {
				$data[] = array(
					'value' => $key,
					'label' => $tags[ $key ],
				);
			}
		} else {
			foreach ( $tags as $key => $value ) {
				$data[] = array(
					'value' => $key,
					'label' => $value,
				);
			}
		}

		return $data;
	}

	/**
	 * Add controls to elements.
	 *
	 * @since 3.40.12
	 */
	public function add_controls() {
		$attributes = array(
			array(
				'type'       => 'dropdown',
				'param_name' => 'wpf_visibility',
				'heading'    => __( 'Visibility', 'wp-fusion' ),
				'value'      => array(
					__( 'Everyone', 'wp-fusion' )         => 'everyone',
					__( 'Logged In Users', 'wp-fusion' )  => 'loggedin',
					__( 'Logged Out Users', 'wp-fusion' ) => 'loggedout',
				),
			),

			array(
				'param_name'  => 'wpf_tags',
				'heading'     => sprintf( __( 'Required %s Tags (Any)', 'wp-fusion' ), wp_fusion()->crm->name ),
				'type'        => 'autocomplete',
				'settings'    => array(
					'multiple'   => true,
					'min_length' => 1,
					'groups'     => true,
				),

				'dependency'  => array(
					'element' => 'wpf_visibility',
					'value'   => array( 'loggedin', 'everyone' ),
				),

				'description' => __( 'The user must be logged in and have at least one of the tags specified to access the content.', 'wp-fusion' ),
			),

			array(
				'param_name'  => 'wpf_tags_all',
				'heading'     => sprintf( __( 'Required %s Tags (All)', 'wp-fusion' ), wp_fusion()->crm->name ),
				'type'        => 'autocomplete',
				'settings'    => array(
					'multiple'   => true,
					'min_length' => 1,
					'groups'     => true,
				),

				'dependency'  => array(
					'element' => 'wpf_visibility',
					'value'   => array( 'loggedin', 'everyone' ),
				),

				'description' => __( 'The user must be logged in and have <em>all</em> of the tags specified to access the content.', 'wp-fusion' ),
			),

			array(
				'param_name'  => 'wpf_tags_not',
				'heading'     => sprintf( __( 'Required %s Tags (Not)', 'wp-fusion' ), wp_fusion()->crm->name ),
				'type'        => 'autocomplete',
				'settings'    => array(
					'multiple'   => true,
					'min_length' => 1,
					'groups'     => true,
				),
				'dependency'  => array(
					'element' => 'wpf_visibility',
					'value'   => array( 'loggedin', 'everyone' ),
				),
				'description' => __( 'If the user is logged in and has any of these tags, the content will be hidden.', 'wp-fusion' ),
			),

		);

		foreach ( $this->get_shortcodes() as $name ) {
			vc_add_params( $name, $attributes );
		}
	}

	/**
	 * Get shortcodes base names.
	 * 
	 * WPBakery doesn't currently have a way to list all shortcodes so we'll do it using the filesystem.
	 * 
	 * @since 3.40.12
	 *
	 * @return array The shortcode slugs.
	 */
	private function get_shortcodes() {

		$file_names = array();

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$files = list_files( WPB_PLUGIN_DIR . '/config/', 20, array( 'lean-map.php', 'templates.php', 'deprecated' ) );

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				$filesize     = size_format( filesize( $file ) );
				$file_names[] = str_replace( array( '.php', '-', 'shortcode_' ), array( '', '_', '' ), basename( $file ) );
			}
		}

		return $file_names;

	}


	/**
	 * Determines if a user has access to an element.
	 *
	 * @since  3.40.12
	 *
	 * @param  $atts.
	 * @return bool   Whether or not the user can access the element.
	 */
	private function can_access( $atts ) {

		if ( is_admin() ) {
			return true;
		}

		if ( ! isset( $atts['wpf_visibility'] ) ) {
			return true;
		}

		$wpf_tags        = ( ! empty( $atts['wpf_tags'] ) ? array_map( 'trim', explode( ',', $atts['wpf_tags'] ) ) : '' );
		$wpf_tags_all    = ( ! empty( $atts['wpf_tags_all'] ) ? array_map( 'trim', explode( ',', $atts['wpf_tags_all'] ) ) : '' );
		$widget_tags_not = ( ! empty( $atts['widget_tags_not'] ) ? array_map( 'trim', explode( ',', $atts['widget_tags_not'] ) ) : '' );

		$visibility      = isset( $atts['wpf_visibility'] ) ? strtolower( $atts['wpf_visibility'] ) : 'everyone';
		$widget_tags     = isset( $wpf_tags ) ? $wpf_tags : array();
		$widget_tags_all = isset( $wpf_tags_all ) ? $wpf_tags_all : array();
		$widget_tags_not = isset( $widget_tags_not ) ? $widget_tags_not : array();

		if ( 'everyone' === $visibility && empty( $widget_tags ) && empty( $widget_tags_all ) && empty( $widget_tags_not ) ) {

			// No settings, allow access.

			$can_access = apply_filters( 'wpf_wpbakery_can_access', true, $atts );

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

		$can_access = apply_filters( 'wpf_wpbakery_can_access', $can_access, $atts );

		$can_access = apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), false );

		if ( $can_access ) {
			return true;
		} else {
			return false;
		}
	}

}

new WPF_WPBakery();
