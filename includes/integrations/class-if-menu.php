<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * If-Menu
 *
 * @since 3.38.28
 *
 * @link https://wpfusion.com/documentation/other/if-menu/
 */
class WPF_If_Menu extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.28
	 * @var string $slug
	 */

	public $slug = 'If_Menu';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.28
	 * @var string $name
	 */
	public $name = 'If Menu';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.28
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started.
	 *
	 * @since 3.38.28
	 */
	public function init() {

		if ( ! wpf_get_option( 'restrict_content', true ) ) {
			return;
		}

		add_filter( 'wpf_get_setting_enable_menu_items', '__return_false' ); // Disable built in menu editor settings.

		add_filter( 'if_menu_conditions', array( $this, 'add_wpf_items' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_init' ) );

	}

	/**
	 * Enable premium feature to use sub menu option.
	 *
	 * @since 3.38.28
	 */
	public function admin_init() {

		global $pagenow;

		if ( 'nav-menus.php' === $pagenow ) {
			$script = "
			IfMenu.plan = {};
			IfMenu.plan.plan = 'premium';
			";
			wp_add_inline_script( 'if-menu', $script );
		}

	}

	/**
	 * Adds WPF conditions.
	 *
	 * @since  3.38.28
	 *
	 * @param  array $conditions The conditions.
	 * @return array The conditions.
	 */
	public function add_wpf_items( $conditions ) {

		$available_tags = wp_fusion()->settings->get_available_tags_flat();

		$conditions['wpf_req_tags_any'] = array(
			'id'        => 'wpf_req_tags_any',
			'type'      => 'multiple',
			'name'      => __( 'Required tags (any)', 'wp-fusion' ),
			'options'   => array_map( 'wp_strip_all_tags', $available_tags ),
			'group'     => __( 'WP Fusion', 'wp-fusion' ),
			'condition' => function( $item, $selected_options = array() ) {
				return wpf_has_tag( $selected_options, get_current_user_id() );
			},
		);

		$conditions['wpf_req_tags_all'] = array(
			'id'        => 'wpf_req_tags_all',
			'type'      => 'multiple',
			'name'      => __( 'Required tags (all)', 'wp-fusion' ),
			'options'   => array_map( 'wp_strip_all_tags', $available_tags ),
			'group'     => __( 'WP Fusion', 'wp-fusion' ),
			'condition' => function( $item, $selected_options = array() ) {
				$can_access = true;
				foreach ( $selected_options as $option ) {
					if ( ! wpf_has_tag( $option, get_current_user_id() ) ) {
						$can_access = false;
					}
				}
				return $can_access;
			},
		);

		$conditions['wpf_req_tags_not'] = array(
			'id'        => 'wpf_req_tags_not',
			'type'      => 'multiple',
			'name'      => __( 'Required tags (not)', 'wp-fusion' ),
			'options'   => array_map( 'wp_strip_all_tags', $available_tags ),
			'group'     => __( 'WP Fusion', 'wp-fusion' ),
			'condition' => function( $item, $selected_options = array() ) {
				return ! wpf_has_tag( $selected_options, get_current_user_id() );
			},
		);
		return $conditions;
	}


}

new WPF_If_Menu();
