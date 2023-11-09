<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Oxygen extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'oxygen';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Oxygen';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/page-builders/oxygen/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.30.1
	 * @return  void
	 */

	public function init() {

		if ( ! wpf_get_option( 'restrict_content', true ) ) {
			return;
		}

		add_action( 'init', array( $this, 'register_condition' ) );

	}

	/**
	 * Register custom Oxygen condition
	 *
	 * @access public
	 * @return void
	 */

	public function register_condition() {

		$options = wp_fusion()->settings->get_available_tags_flat( true, false );

		$args = array(
			'options' => $options,
			'custom'  => true,
		);

		$operators = array( __( 'Has tag', 'wp-fusion' ), __( 'Does not have tag', 'wp-fusion' ) );

		oxygen_vsb_register_condition( sprintf( __( '%s Tags', 'wp-fusion' ), wp_fusion()->crm->name ), $args, $operators, 'wpf_oxygen_condition_callback', 'User' );

	}


}

/**
 * Check conditions to determine visibility of component (this should really be in the class but Oxygen doesn't support array syntax for callbacks)
 *
 * @access public
 * @return bool Can Access
 */

function wpf_oxygen_condition_callback( $value, $operator ) {

	$can_access = true;

	if ( 'Has tag' == $operator && ! wp_fusion()->user->has_tag( $value ) ) {

		$can_access = false;

	} elseif ( 'Does not have tag' == $operator && ! wpf_is_user_logged_in() ) {

		$can_access = true;

	} elseif ( 'Does not have tag' == $operator && wp_fusion()->user->has_tag( $value ) ) {

		$can_access = false;

	}

	if ( wpf_admin_override() ) {
		$can_access = true;
	}

	$can_access = apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), false );

	return $can_access;

}

new WPF_Oxygen();
