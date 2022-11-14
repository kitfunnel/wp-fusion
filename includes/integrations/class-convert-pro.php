<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Convert_Pro extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'convert-pro';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Convert pro';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/lead-generation/convert-pro/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'cp_after_options', array( $this, 'add_options' ) );

		add_filter( 'cp_pro_target_page_settings', array( $this, 'target_page_settings' ), 10, 2 );

	}


	/**
	 * Adds options to CP editor
	 *
	 * @access public
	 * @return array Options
	 */

	public function add_options( $options ) {

		$available_tags = wpf_get_option( 'available_tags', array() );

		$tags = array();

		foreach ( $available_tags as $id => $label ) {

			if ( is_array( $label ) ) {
				$label = $label['label'];
			}

			$tags[ $id ] = $label;

		}

		asort( $tags );

		$options['options'][] = array(
			'type'         => 'switch',
			'class'        => '',
			'name'         => 'enable_wpf',
			'opts'         => array(
				'title'       => '',
				'value'       => '',
				'on'          => __( 'ON', 'convertpro' ),
				'off'         => __( 'OFF', 'convertpro' ),
				'description' => __( 'Do you wish to display this only to registered users who have a specific tag?', 'convertpro' ),
			),
			'panel'        => 'Target',
			'section'      => 'Configure',
			'section_icon' => 'cp-icon-embed',
			'category'     => sprintf( __( 'When User Has %s Tag', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		$options['options'][] = array(
			'type'         => 'dropdown',
			'class'        => 'select4-wpf-tags',
			'name'         => 'tags_trigger',
			'id'           => 'wpf-apply-tags',
			'opts'         => array(
				'title'   => __( 'Select Tag', 'wp-fusion' ),
				'options' => $tags,
				'class'   => 'select4-wpf-tags',
			),
			'panel'        => 'Target',
			'section'      => 'Configure',
			'section_icon' => 'cp-icon-embed',
			'category'     => sprintf( __( 'When User Has %s Tag', 'wp-fusion' ), wp_fusion()->crm->name ),
			'dependency'   => array(
				'name'     => 'enable_wpf',
				'operator' => '==',
				'value'    => 'true',
			),
		);

		$options['options'][] = array(
			'type'         => 'dropdown',
			'class'        => 'select4-wpf-tags',
			'name'         => 'tags_logic',
			'id'           => 'wpf-apply-tags',
			'opts'         => array(
				'title'   => __( 'Logic', 'wp-fusion' ),
				'options' => array(
					'show' => 'Show only to users who have the tag',
					'hide' => 'Hide from users who have the tag',
				),
				'class'   => 'select4-wpf-tags',
			),
			'panel'        => 'Target',
			'section'      => 'Configure',
			'section_icon' => 'cp-icon-embed',
			'category'     => sprintf( __( 'When User Has %s Tag', 'wp-fusion' ), wp_fusion()->crm->name ),
			'dependency'   => array(
				'name'     => 'enable_wpf',
				'operator' => '==',
				'value'    => 'true',
			),
		);

		return $options;

	}


	/**
	 * Control display based on tags
	 *
	 * @access public
	 * @return bool Display
	 */

	public function target_page_settings( $display, $style_id ) {

		$settings = get_post_meta( $style_id, 'configure', true );

		if ( isset( $settings['enable_wpf'] ) && $settings['enable_wpf'] == true ) {

			if ( ! isset( $settings['tags_logic'] ) || $settings['tags_logic'] == 'show' ) {

				$display = false;

				if ( wpf_is_user_logged_in() ) {

					$user_tags = wp_fusion()->user->get_tags();

					if ( in_array( $settings['tags_trigger'], $user_tags ) ) {
						$display = true;
					}
				}

				if ( wpf_admin_override() ) {
					$display = true;
				}
			} elseif ( $settings['tags_logic'] == 'hide' ) {

				$display = true;

				if ( wpf_is_user_logged_in() ) {

					$user_tags = wp_fusion()->user->get_tags();

					if ( in_array( $settings['tags_trigger'], $user_tags ) ) {
						$display = false;
					}
				}

				if ( wpf_admin_override() ) {
					$display = true;
				}
			}

			$display = apply_filters( 'wpf_user_can_access', $display, wpf_get_current_user_id(), $style_id );

		}

		return $display;

	}


}

new WPF_Convert_Pro();
