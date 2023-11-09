<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Elementor extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'elementor';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Elementor';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/page-builders/elementor/';


	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		if ( ! wpf_get_option( 'restrict_content', true ) ) {
			return;
		}

		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );

		add_filter( 'wpf_user_can_access', array( $this, 'admin_override_in_preview_mode' ) );

		// Controls. From /elementor/includes/base/controls-stack.php:1594.
		add_action( 'elementor/element/common/_section_style/after_section_end', array( $this, 'register_section' ) ); // Widgets.
		add_action( 'elementor/element/section/section_advanced/after_section_end', array( $this, 'register_section' ) ); // Sections.
		add_action( 'elementor/element/column/section_advanced/after_section_end', array( $this, 'register_section' ) ); // Columns.
		add_action( 'elementor/element/container/section_layout/after_section_end', array( $this, 'register_section' ) ); // Containers.

		add_action( 'elementor/element/common/wpf_tags_section/before_section_end', array( $this, 'register_controls' ), 10, 2 );
		add_action( 'elementor/element/section/wpf_tags_section/before_section_end', array( $this, 'register_controls' ), 10, 2 );
		add_action( 'elementor/element/column/wpf_tags_section/before_section_end', array( $this, 'register_controls' ), 10, 2 );
		add_action( 'elementor/element/container/wpf_tags_section/before_section_end', array( $this, 'register_controls' ), 10, 2 );

		// Display.
		add_filter( 'elementor/frontend/widget/should_render', array( $this, 'should_render' ), 10, 2 );
		add_filter( 'elementor/frontend/section/should_render', array( $this, 'should_render' ), 10, 2 );
		add_filter( 'elementor/frontend/column/should_render', array( $this, 'should_render' ), 10, 2 );
		add_filter( 'elementor/frontend/container/should_render', array( $this, 'should_render' ), 10, 2 );

		// Filter queries.
		add_action( 'elementor/element/before_section_end', array( $this, 'add_filter_queries_control' ), 10, 3 );
		add_filter( 'elementor/query/query_args', array( $this, 'query_args' ), 10, 3 );

		// Add Scripts.
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

	}

	/**
	 * Removes standard WPF meta boxes from Elementor template library items
	 *
	 * @access  public
	 * @return  array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['elementor_library'] );

		return $post_types;

	}

	/**
	 * Force bypass WPF access rules in the Elementor editor.
	 *
	 * @since 3.40.22
	 *
	 * @param bool $override Override WPF access rules
	 * @return bool Override access rules.
	 */
	public function admin_override_in_preview_mode( $override ) {

		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor ) {

			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				return true;
			}
		}

		return $override;

	}


	/**
	 * Register controls section
	 *
	 * @access public
	 * @return void
	 */

	public function register_section( $element ) {

		$element->start_controls_section(
			'wpf_tags_section',
			array(
				'label' => __( 'WP Fusion', 'wp-fusion' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);

		$element->end_controls_section();

	}

	/**
	 * Register controls
	 *
	 * @access public
	 * @return void
	 */

	public function register_controls( $element, $args ) {

		if ( is_a( $element, 'Elementor\Core\DocumentTypes\Post' ) ) {
			return;
		}

		$available_tags = wpf_get_option( 'available_tags', array() );

		$data = array();

		foreach ( $available_tags as $id => $label ) {

			if ( is_array( $label ) ) {
				$label = $label['label'];
			}

			$data[ $id ] = $label;

		}

		$element->add_control(
			'wpf_visibility',
			array(
				'label'       => __( 'Visibility', 'wp-fusion' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'everyone',
				'options'     => array(
					'everyone'  => __( 'Everyone', 'wp-fusion' ),
					'loggedin'  => __( 'Logged In Users', 'wp-fusion' ),
					'loggedout' => __( 'Logged Out Users', 'wp-fusion' ),
				),
				'multiple'    => false,
				'label_block' => true,
			)
		);

		$element->add_control(
			'wpf_tags',
			array(
				'label'       => sprintf( __( 'Required %s Tags (Any)', 'wp-fusion' ), wp_fusion()->crm->name ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $data,
				'multiple'    => true,
				'label_block' => true,
				'condition'   => array(
					'wpf_visibility' => array( 'loggedin', 'everyone' ),
				),
				// 'description' => __( 'The user must be logged in and have at least one of the tags specified to access the content.', 'wp-fusion' ),
			)
		);

		$element->add_control(
			'wpf_tags_all',
			array(
				'label'       => sprintf( __( 'Required %s Tags (All)', 'wp-fusion' ), wp_fusion()->crm->name ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $data,
				'multiple'    => true,
				'label_block' => true,
				'condition'   => array(
					'wpf_visibility' => array( 'loggedin', 'everyone' ),
				),
				// 'description' => __( 'The user must be logged in and have <em>all</em> of the tags specified to access the content.', 'wp-fusion' ),
			)
		);

		$element->add_control(
			'wpf_tags_not',
			array(
				'label'       => sprintf( __( 'Required %s Tags (Not)', 'wp-fusion' ), wp_fusion()->crm->name ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $data,
				'multiple'    => true,
				'label_block' => true,
				'condition'   => array(
					'wpf_visibility' => array( 'everyone', 'loggedin' ),
				),
				'description' => __( 'If the user is logged in and has any of these tags, the content will be hidden.', 'wp-fusion' ),
			)
		);

		do_action( 'wpf_elementor_controls_section', $element );

	}

	/**
	 * Determines if a user has access to an element
	 *
	 * @access public
	 * @return bool Access
	 */

	private function can_access( $element ) {

		if ( is_admin() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return true;
		}

		$visibility      = $element->get_settings( 'wpf_visibility' );
		$widget_tags     = $element->get_settings( 'wpf_tags' );
		$widget_tags_all = $element->get_settings( 'wpf_tags_all' );
		$widget_tags_not = $element->get_settings( 'wpf_tags_not' );

		if ( ( empty( $visibility ) || 'everyone' === $visibility ) && empty( $widget_tags ) && empty( $widget_tags_all ) && empty( $widget_tags_not ) ) {

			// No settings, allow access

			$can_access = apply_filters( 'wpf_elementor_can_access', true, $element );

			return apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), false );

		}

		if ( wpf_admin_override() ) {
			return true;
		}

		// Since the Elementor tag input doesn't support adding new tags, we'll remove
		// any invalid tags here (that might have been left over from another CRM).

		if ( ! empty( $widget_tags ) ) {
			$widget_tags = array_filter( array_map( 'wpf_get_tag_id', $widget_tags ) );
		}

		if ( ! empty( $widget_tags_all ) ) {
			$widget_tags_all = array_filter( array_map( 'wpf_get_tag_id', $widget_tags_all ) );
		}

		if ( ! empty( $widget_tags_not ) ) {
			$widget_tags_not = array_filter( array_map( 'wpf_get_tag_id', $widget_tags_not ) );
		}

		// Maybe migrate the settings from the pre 3.35.7 format.

		if ( empty( $visibility ) ) {

			// At least some tags are specified but there's nothing for visibility, so we'll default to "loggedin"

			$visibility = 'loggedin';

			if ( ! empty( $widget_tags_not ) && 'display' == $element->get_settings( 'wpf_loggedout' ) ) {
				$visibility = 'everyone';
			}
		}

		$can_access = true;

		if ( wpf_is_user_logged_in() ) {

			$user_tags = wp_fusion()->user->get_tags();

			if ( 'everyone' == $visibility || 'loggedin' == $visibility ) {

				// See if user has required tags.

				if ( ! empty( $widget_tags ) ) {

					// Required tags (any).

					$result = array_intersect( $widget_tags, $user_tags );

					if ( empty( $result ) ) {
						$can_access = false;
					}
				}

				if ( true == $can_access && ! empty( $widget_tags_all ) ) {

					// Required tags (all)

					$result = array_intersect( $widget_tags_all, $user_tags );

					if ( count( $result ) != count( $widget_tags_all ) ) {
						$can_access = false;
					}
				}

				if ( true == $can_access && ! empty( $widget_tags_not ) ) {

					// Required tags (not)

					$result = array_intersect( $widget_tags_not, $user_tags );

					if ( ! empty( $result ) ) {
						$can_access = false;
					}
				}
			} elseif ( 'loggedout' == $visibility ) {

				// The user is logged in but the widget is set to logged-out only
				$can_access = false;

			}
		} else {

			// Not logged in

			if ( 'loggedin' == $visibility ) {

				// The user is not logged in but the widget is set to logged-in only
				$can_access = false;

			} elseif ( 'everyone' == $visibility ) {

				// Also deny access if tags are specified

				if ( ! empty( $widget_tags ) || ! empty( $widget_tags_all ) ) {
					$can_access = false;
				}
			}
		}

		$can_access = apply_filters( 'wpf_elementor_can_access', $can_access, $element );

		$can_access = apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), false );

		if ( $can_access ) {
			return true;
		} else {
			return false;
		}

	}


	/**
	 * Conditionally show / hide widget, columns, sections, and containers based
	 * on tags.
	 *
	 * @since  3.15.0
	 *
	 * @param  bool         $should_render Whether or not to render the element.
	 * @param  Element_Base $widget        The element.
	 * @return bool         Whether or not to render the element.
	 */
	public function should_render( $should_render, $widget ) {

		if ( ! $this->can_access( $widget ) ) {
			$should_render = false;
		}

		return $should_render;

	}

	/**
	 * Render widget controls
	 *
	 * @access public
	 * @return void
	 */

	public function add_filter_queries_control( $element, $section_id, $args ) {
		if ( $section_id !== 'section_query' ) {
			return;
		}

		$element->add_control(
			'wpf_filter_queries',
			array(
				'label'       => __( 'Filter Queries', 'wp-fusion' ),
				'description' => __( 'Filter results based on WP Fusion access rules', 'wp-fusion' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'label_block' => false,
				'show_label'  => true,
				'separator'   => 'before',
			)
		);

	}

	/**
	 * Filter queries if enabled.
	 *
	 * @access public
	 * @return array Query Args
	 */

	public function query_args( $query_args, $widget ) {

		if ( is_object( $widget ) && method_exists( $widget, 'get_settings_for_display' ) ) {
			$settings = $widget->get_settings_for_display();
		} elseif ( is_array( $widget ) ) {
			$settings = $widget;
		}

		if ( ! isset( $settings['wpf_filter_queries'] ) || 'yes' !== $settings['wpf_filter_queries'] ) {
			return $query_args;
		}

		// No need to do this again if WPF is already doing it globally.

		if ( 'advanced' === wpf_get_option( 'hide_archives' ) ) {
			return $query_args;
		}

		if ( wpf_admin_override() ) {
			return $query_args;
		}

		// This lets everyone know WP Fusion is messing with the query, and also
		// enables the posts_where filter in WPF_Access_Control.

		$query_args['wpf_filtering_query'] = true;

		// Exclude any restricted post IDs.

		$post_ids = wp_fusion()->access->get_restricted_posts( $query_args['post_type'] );

		if ( ! empty( $post_ids ) ) {
			$query_args['post__not_in'] = $post_ids;
		}

		return $query_args;

	}

	/**
	 * Admin scripts.
	 *
	 * @since 3.41.13
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'wpf-elementor-script', WPF_DIR_URL . 'assets/js/wpf-elementor.js', array( 'jquery' ), WP_FUSION_VERSION, true );
	}

}

new WPF_Elementor();
