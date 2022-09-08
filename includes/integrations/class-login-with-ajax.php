<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Login With AJAX plugin compatibility.
 *
 * @since 3.38.5
 */
class WPF_Login_With_Ajax extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'login-with-ajax';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Login-with-ajax';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started.
	 *
	 * @since 3.38.5
	 */
	public function init() {

		add_filter( 'lwa_ajax_login', array( $this, 'lwa_login' ) );

	}

	/**
	 * Sets the LWA login redirect to use the Return After Login redirect, if
	 * the cookie is set.
	 *
	 * @since  3.38.5
	 *
	 * @param  array $return The login response data.
	 * @return array The login response data.
	 */
	public function lwa_login( $return ) {

		if ( $return['result'] && ! empty( $_REQUEST['log'] ) ) {

			if ( isset( $_COOKIE['wpf_return_to'] ) ) {

				$login = sanitize_text_field( wp_unslash( $_REQUEST['log'] ) );
				$user  = get_user_by( 'login', $login );

				if ( empty( $user ) ) {
					$user = get_user_by( 'email', $login );
				}

				$post_id = absint( $_COOKIE['wpf_return_to'] );
				$url     = get_permalink( $post_id );

				setcookie( 'wpf_return_to', '', time() - ( 15 * 60 ) );

				if ( ! empty( $url ) && wpf_user_can_access( $post_id, $user->ID ) ) {
					$return['redirect'] = $url;
				}
			}
		}

		return $return;
	}
}

new WPF_Login_With_Ajax();

