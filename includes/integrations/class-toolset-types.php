<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Toolset_Types extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'toolset-types';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Toolset Types';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/toolset-types/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.34.7
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpcf_meta_box_order_defaults', array( $this, 'add_meta_box' ), 10, 2 );

		add_filter( 'wpf_settings_for_meta_box', array( $this, 'settings_for_meta_box' ), 10, 2 );

		add_action( 'admin_init', array( $this, 'save' ) );

	}

	/**
	 * Register the meta box
	 *
	 * @access public
	 * @return array Boxes
	 */

	public function add_meta_box( $boxes, $post_type ) {

		$boxes['wpf-meta'] = array(
			'callback' => array( wp_fusion()->admin_interfaces, 'meta_box_callback' ),
			'title'    => __( 'WP Fusion', 'wp-fusion' ),
			'default'  => 'side',
			'priority' => 'low',
		);

		remove_action( 'wpf_meta_box_content', array( wp_fusion()->admin_interfaces, 'restrict_content_checkbox' ), 10, 2 );
		remove_action( 'wpf_meta_box_content', array( wp_fusion()->admin_interfaces, 'apply_tags_select' ), 30, 2 );

		add_action( 'wpf_meta_box_content', array( $this, 'restrict_content_checkbox' ), 10, 2 );

		return $boxes;

	}

	/**
	 * Shows restrict content checkbox
	 *
	 * @access public
	 * @return void
	 */

	public function restrict_content_checkbox( $post, $settings ) {

		echo '<input class="checkbox wpf-restrict-access-checkbox" type="checkbox" data-unlock="wpf-settings-allow_tags wpf-settings-allow_tags_all" id="wpf-lock-content" name="wpf-settings[lock_content]" value="1" ' . checked( $settings['lock_content'], 1, false ) . ' /> <label for="wpf-lock-content" class="wpf-restrict-access">';
		_e( 'Users must be logged in to view any posts of this post type', 'wp-fusion' );
		echo '</label>';

	}

	/**
	 * Get settings for the post type
	 *
	 * @access public
	 * @return array Boxes
	 */

	public function settings_for_meta_box( $settings, $post ) {

		if ( empty( $post ) && isset( $_GET['wpcf-post-type'] ) ) {

			$post_type = $_GET['wpcf-post-type'];

			$defaults = array(
				'lock_content'   => 0,
				'allow_tags'     => array(),
				'allow_tags_all' => array(),
				'allow_tags_not' => array(),
				'redirect'       => '',
				'redirect_url'   => '',
			);

			$settings = wpf_get_option( 'post_type_rules', array() );

			if ( isset( $settings[ $post_type ] ) ) {
				$settings = wp_parse_args( $settings[ $post_type ], $defaults );
			} else {
				$settings = $defaults;
			}
		}

		return $settings;

	}

	/**
	 * Save the meta box data
	 *
	 * @access public
	 * @return void
	 */

	public function save() {

		if ( isset( $_GET['page'] ) && 'wpcf-edit-type' == $_GET['page'] && ! empty( $_POST['wpf-settings'] ) ) {

			$settings = wpf_get_option( 'post_type_rules', array() );

			if ( ! empty( $_POST['ct']['wpcf-post-type'] ) ) {

				// Edit existing
				$post_type = sanitize_text_field( $_POST['ct']['wpcf-post-type'] );

			} else {

				// New ones
				$post_type = sanitize_text_field( $_POST['ct']['slug'] );

			}

			$setting = array_filter( apply_filters( 'wpf_sanitize_meta_box', $_POST['wpf-settings'] ) );

			if ( ! empty( $setting ) ) {
				$settings[ $post_type ] = $setting;
			} elseif ( isset( $settings[ $post_type ] ) ) {
				unset( $settings[ $post_type ] ); // if nothing set, remove
			}

			wp_fusion()->settings->set( 'post_type_rules', $settings );

		}

	}

}

new WPF_Toolset_Types();
