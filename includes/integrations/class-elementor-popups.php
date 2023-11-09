<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Elementor_Popups extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'elementor-popups';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Elementor Popups';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/page-builders/elementor/#elementor-popups';


	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		// Control styles
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'enqueue_styles' ) );

		// Controls
		add_action( 'elementor/element/popup_timing/timing/before_section_end', array( $this, 'render_controls' ), 10, 2 );

		// Filter template loading
		add_filter( 'elementor/theme/get_location_templates/template_id', array( $this, 'get_template' ) );

	}

	/**
	 * Enqueue editor styles
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_styles() {

		wp_enqueue_style( 'wpf-admin', WPF_DIR_URL . 'assets/css/wpf-admin.css', array(), WP_FUSION_VERSION );

	}


	/**
	 * Render widget controls
	 *
	 * @access public
	 * @return void
	 */

	public function render_controls( $element, $args ) {

		$available_tags = wpf_get_option( 'available_tags', array() );

		$data = array();

		foreach ( $available_tags as $id => $label ) {

			if ( is_array( $label ) ) {
				$label = $label['label'];
			}

			$data[ $id ] = $label;

		}

		// Heading

		$args = array(
			'type'  => \Elementor\Controls_Manager::HEADING,
			'label' => sprintf( __( 'When user has any of the %s tags', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		$element->add_control( 'wp_fusion_heading', $args );

		// Condition

		$args = array(
			'type'               => \Elementor\Controls_Manager::SELECT,
			'options'            => array(
				'show' => __( 'Show', 'elementor-pro' ),
				'hide' => __( 'Hide', 'elementor-pro' ),
			),
			'default'            => 'show',
			'frontend_available' => true,
			'condition'          => array(
				'wp_fusion' => 'yes',
			),
		);

		$element->add_control( 'wp_fusion_condition', $args );

		// Tags select

		$args = array(
			'type'               => \Elementor\Controls_Manager::SELECT2,
			'multiple'           => true,
			'options'            => $data,
			'frontend_available' => true,
			'condition'          => array(
				'wp_fusion' => 'yes',
			),
		);

		$element->add_control( 'wp_fusion_popup_tags', $args );

		// Switcher

		$element->add_control(
			'wp_fusion',
			array(
				'type'               => \Elementor\Controls_Manager::SWITCHER,
				'classes'            => 'elementor-popup__display-settings__group-toggle',
				'frontend_available' => true,
			)
		);

	}


	/**
	 * Hide popup if conditions not met
	 *
	 * @access public
	 * @return bool / int Post ID
	 */

	public function get_template( $post_id ) {

		$popup_settings = get_post_meta( $post_id, '_elementor_popup_display_settings', true );

		if ( empty( $popup_settings ) || ! isset( $popup_settings['timing'] ) || ! isset( $popup_settings['timing']['wp_fusion'] ) || $popup_settings['timing']['wp_fusion'] != 'yes' ) {
			return $post_id;
		}

		// If no tags set

		if ( empty( $popup_settings['timing']['wp_fusion_popup_tags'] ) ) {
			return $post_id;
		}

		$widget_tags = $popup_settings['timing']['wp_fusion_popup_tags'];

		$can_access = true;

		if ( isset( $popup_settings['timing']['wp_fusion_condition'] ) && $popup_settings['timing']['wp_fusion_condition'] == 'hide' ) {

			if ( wpf_is_user_logged_in() ) {

				$user_tags = wp_fusion()->user->get_tags();

				$result = array_intersect( $widget_tags, $user_tags );

				if ( ! empty( $result ) ) {
					$can_access = false;
				}
			}
		} elseif ( ! isset( $popup_settings['timing']['wp_fusion_condition'] ) || $popup_settings['timing']['wp_fusion_condition'] == 'show' ) {

			$can_access = false;

			if ( wpf_is_user_logged_in() ) {

				$user_tags = wp_fusion()->user->get_tags();

				$result = array_intersect( $widget_tags, $user_tags );

				if ( ! empty( $result ) ) {
					$can_access = true;
				}
			}
		}

		//
		// Don't check for exclude_admins here otherwise the popup will show on every page
		//

		$can_access = apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), $post_id );

		if ( $can_access ) {

			return $post_id;

		} else {

			return false;

		}

	}


}

new WPF_Elementor_Popups();
