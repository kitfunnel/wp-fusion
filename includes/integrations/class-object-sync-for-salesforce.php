<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Object Sync for Salesforce integration.
 *
 * @since 3.40.58
 */
class WPF_Object_Sync_Salesforce extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.58
	 * @var string $slug
	 */

	public $slug = 'object-sync-for-salesforce';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.58
	 * @var string $name
	 */
	public $name = 'Object Sync for Salesforce';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.58
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/objet-sync-for-salesforce/';


	/**
	 * Gets things started.
	 *
	 * @since 3.40.58
	 */
	public function init() {

		if ( wpf_get_option( 'enable_object_sync', true ) ) {

			add_filter( object_sync_for_salesforce()->option_prefix . 'http_request', array( $this, 'http_request' ), 10, 6 );

			if ( is_admin() ) {
				$this->set_initial_options();
			}

			$this->set_options();

		}

		// Settings.
		add_filter( 'wpf_configure_settings', array( $this, 'configure_settings' ) );

	}

	/**
	 * Overrides Object Sync's HTTP requests to use WP Fusion.
	 *
	 * @since 3.40.58
	 *
	 * @param null|array $response Whether to short-circuit the HTTP request. Default null.
	 * @param string     $url      Path to make request from.
	 * @param array      $data     The request body.
	 * @param array      $headers  Request headers to send as name => value.
	 * @param string     $method   Method to initiate the call, such as GET or POST. Defaults to GET.
	 * @param array      $options  This is the options array from the api_http_request method.
	 */
	public function http_request( $response, $url, $data, $headers, $method, $options ) {

		if ( wp_fusion()->crm->auth_url === $url ) {

			// Auth requests, skip the API call.

			$response = array(
				'code' => 200,
				'data' => array(
					'access_token' => wpf_get_option( 'sf_access_token' ),
					'instance_url' => wpf_get_option( 'sf_instance_url' ),
					'id'           => wpf_get_option( 'sf_id' ),
				),
			);

			return $response;

		}

		// Regular requests.

		if ( false === strpos( $url, 'https://' ) ) {
			$request_url = wpf_get_option( 'sf_instance_url' ) . '/services/data/v42.0/' . $url;
		} else {
			$request_url = $url;
		}

		$params           = wp_fusion()->crm->get_params();
		$params['method'] = $method;

		if ( ( 'POST' === $method || 'PATCH' === $method ) && ! empty( $data ) ) {
			$params['body'] = $data; // it's already JSON encoded.
		}

		$response = wp_remote_request( $request_url, $params );

		if ( is_wp_error( $response ) ) {

			// Error handling.
			wpf_log( 'error', wpf_get_current_user_id(), 'Error performing method <code>' . $method . '</code> to <code>' . $url . '</code>: ' . $response->get_error_message() );
			return array( 'code' => 200 ); // Object sync throws unhandled exceptions so we'll avoid that by returning false.

		}

		$response = array(
			'code' => wp_remote_retrieve_response_code( $response ),
			'data' => json_decode( wp_remote_retrieve_body( $response ), true ),
		);

		return $response;

	}

	/**
	 * Gets the options for Object Sync.
	 *
	 * @since 3.40.58
	 *
	 * @return array Options.
	 */
	public function get_options() {

		// Get the base URL for the login.

		preg_match( '/^https?:\/\/[^\/]+\.com/', wp_fusion()->crm->auth_url, $matches );

		$base_url = $matches[0];

		$options = array(
			'consumer_key'       => wp_fusion()->crm->client_id,
			'consumer_secret'    => wp_fusion()->crm->client_secret,
			'callback_url'       => wp_fusion()->crm->get_oauth_url(),
			'login_base_url'     => $base_url,
			'authorize_url_path' => '/services/oauth2/authorize',
			'token_url_path'     => '/services/oauth2/token',
			'refresh_token'      => wpf_get_option( 'sf_refresh_token' ),
			'access_token'       => wpf_get_option( 'sf_access_token' ),
			'instance_url'       => wpf_get_option( 'sf_instance_url' ),
		);

		return $options;

	}

	/**
	 * Object Sync initializes on plugins_loaded -10, so we can't filter the options.
	 * We'll set some defaults here so the intial connection can proceed.
	 *
	 * @since 3.40.58
	 */
	public function set_initial_options() {

		$option_prefix = object_sync_for_salesforce()->option_prefix;

		if ( get_option( $option_prefix . 'refresh_token' ) ) {
			return; // the plugin was already set up, we won't mess with it.
		}

		foreach ( $this->get_options() as $key => $value ) {

			update_option( $option_prefix . $key, $value );

		}

	}

	/**
	 * Overrides OSSF's settings to use WPF's credentials
	 *
	 * @since 3.40.58
	 */
	public function set_options() {

		$option_prefix = object_sync_for_salesforce()->option_prefix;

		if ( get_option( $option_prefix . 'refresh_token' ) ) {
			return; // the plugin was already set up, we won't mess with it.
		}

		foreach ( $this->get_options() as $key => $value ) {

			add_filter(
				'option_' . $option_prefix . $key,
				function() use ( $value ) {
					return $value;
				}
			);

		}

		add_filter(
			'object_sync_for_salesforce_modify_salesforce_api_version',
			function() {
				return '42.0';
			}
		);

	}

	/**
	 * Global settings.
	 *
	 * @since 3.40.58
	 *
	 * @param array $settings Settings.
	 * @return array Settings.
	 */
	public function configure_settings( $settings ) {

		if ( 'salesforce' !== wpf_get_option( 'crm' ) ) {
			return $settings;
		}

		$settings['object_sync_header'] = array(
			'title'   => __( 'Object Sync for Salesforce Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		if ( empty( get_option( object_sync_for_salesforce()->option_prefix . 'refresh_token' ) ) ) {
			$std = true;
		} else {
			$std = false;
		}

		$settings['enable_object_sync'] = array(
			'title'   => __( 'Enable', 'wp-fusion' ),
			'desc'    => __( 'Use WP Fusion\'s credentials to authorize Object Sync for Salesforce.', 'wp-fusion' ),
			'std'     => $std,
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;

	}

}

new WPF_Object_Sync_Salesforce();
