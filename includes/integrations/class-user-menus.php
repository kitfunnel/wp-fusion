<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * User Menus integration.
 *
 * @since 3.38.33
 *
 * @link https://wpfusion.com/documentation/tutorials/menu-item-visibility/
 */
class WPF_User_Menus extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.33
	 * @var string $slug
	 */

	public $slug = 'user-menus';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.33
	 * @var string $name
	 */
	public $name = 'User Menus';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.33
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/tutorials/menu-item-visibility/#advanced-usage';

	/**
	 * Gets things started.
	 *
	 * @since 3.38.33
	 */
	public function init() {
		
		if ( ! wpf_get_option( 'restrict_content', true ) ) {
			return;
		}

		add_filter( 'wpf_menu_item_settings', array( $this, 'filter_wpf_settings' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'hide_wpf_menus' ) );

	}

	/**
	 * Filter menu settings.
	 *
	 * @since  3.38.33
	 *
	 * @param  array $settings The menu item settings.
	 * @param  int   $item_id  The menu item ID.
	 * @return array The settings.
	 */
	public function filter_wpf_settings( $settings, $item_id ) {

		$user_menus_settings = get_post_meta( $item_id, '_jp_nav_item_options', true );

		if ( isset( $user_menus_settings['which_users'] ) && $user_menus_settings['which_users'] === 'logged_in' ) {
			$settings['lock_content'] = '1';
		} else {
			$settings['lock_content'] = '';
		}

		return $settings;
	}

	/**
	 * Hide the built in "Who can see this menu link" dropdown.
	 *
	 * @since 3.38.33
	 */
	public function hide_wpf_menus() {
		echo '<style>
			.wpf_nav_menu_field.description-wide{
				display:none!important;
			}
		</style>';
	}



}

new WPF_User_Menus();
