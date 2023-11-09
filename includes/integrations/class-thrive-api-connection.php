<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Autoresponder API.
 *
 * @since 3.40.24
 */
class WPF_Thrive_Autoresponder_API {

	/**
	 * Get crm user by email.
	 *
	 * @param integer $list_id
	 * @param string  $email
	 *
	 * @since 3.40.24
	 * @return mixed
	 */
	public function get_subscriber_by_email( $list_id, $email ) {
		$contact    = '';
		$contact_id = wp_fusion()->crm->get_contact_id( $email );
		if ( $contact_id ) {
			$contact = wp_fusion()->crm->load_contact( $contact_id );
		}
		return $contact;
	}

	/**
	 * Get crm lists.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public function get_lists() {
		$formatted_lists = array();
		$lists           = wp_fusion()->settings->get( 'available_lists', array() );

		if ( ! empty( $lists ) ) {
			foreach ( $lists as $key => $val ) {
				$formatted_lists[] = array(
					'id'   => $key,
					'name' => $val,
				);
			}
		}

		return $formatted_lists;
	}

}


class WPF_Thrive_API_Connection extends WPF_Integrations_Base {

	/**
	 * Get registered autoresponders.
	 *
	 * @since 3.40.24
	 * @var array
	 */
	public static $registered_autoresponders = array();

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.24
	 * @var string $slug
	 */

	public $slug = 'thrive-leads';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.24
	 * @var string $name
	 */
	public $name = 'Thrive Leads';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.24
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/lead-generation/thrive-leads/';


	/**
	 * Init class.
	 *
	 * @since 3.40.24
	 */
	public function init() {

		if ( version_compare( TVE_DASH_VERSION, '3.30.0', '<' ) ) {
			return;
		}

		require_once WPF_DIR_PATH . 'includes/integrations/class-thrive-autoresponder-main.php';

		static::$registered_autoresponders['wpfusion'] = new WPF_Thrive_Autoresponder_Main();

		add_filter( 'tvd_api_available_connections', array( $this, 'add_api_to_connection_list' ), 10, 3 );
		add_filter( 'tvd_third_party_autoresponders', array( $this, 'add_api_to_thrive_dashboard_list' ) );

		add_action( 'tcb_editor_enqueue_scripts', array( __CLASS__, 'enqueue_architect_scripts' ) );
		add_filter( 'tcb_lead_generation_apis_with_tag_support', array( __CLASS__, 'tcb_apis_with_tags' ) );

	}


	/**
	 * Hook that adds the autoresponder to the list of available APIs that gets retrieved by Thrive Architect and Thrive Automator.
	 *
	 * @since 3.40.24
	 *
	 * @param $autoresponders The autoresponders registered.
	 * @param $only_connected Check if it's connected.
	 * @param $include_all - a flag that is set to true when all the connections ( including third party APIs ) must be shown.
	 * @return array The autoresponders.
	 */
	public static function add_api_to_connection_list( $autoresponders, $only_connected, $api_filter ) {
		$include_3rd_party_apis = ! empty( $api_filter['include_3rd_party_apis'] );

		if ( ( $include_3rd_party_apis || $only_connected ) && static::should_include_autoresponders( $api_filter ) ) {
			foreach ( static::$registered_autoresponders as $autoresponder_key => $autoresponder_instance ) {
				/* @var Autoresponder $autoresponder_data */
				if ( $include_3rd_party_apis || $autoresponder_instance->is_connected() ) {
					$autoresponders[ $autoresponder_key ] = $autoresponder_instance;
				}
			}
		}

		return $autoresponders;
	}

	/**
	 * Hook that adds the card of this API to the Thrive Dashboard API Connection page.
	 *
	 * Note that at the moment this outputs an ugly blank box, but without it the initial
	 * Add Connection in Leads will fail since Thrive_Dash_List_Manager::connection_instance()
	 * fails in class TCB_Editor_Ajax.
	 *
	 * @since 3.40.24
	 *
	 * @param array $autoresponders The autoresponders registered.
	 * @return array The autoresponders.
	 */
	public static function add_api_to_thrive_dashboard_list( $autoresponders ) {
		foreach ( static::$registered_autoresponders as $key => $autoresponder_instance ) {
			$autoresponders[ $key ] = $autoresponder_instance;
		}

		return $autoresponders;
	}

	/**
	 * Check if it should include autoresponders.
	 *
	 * @param array $api_filter
	 *
	 * @since 3.40.24
	 * @return bool
	 */
	public static function should_include_autoresponders( $api_filter ) {

		$type = 'autoresponder';

		if ( empty( $api_filter['include_types'] ) ) {
			$should_include_api = ! in_array( $type, $api_filter['exclude_types'], true );
		} else {
			$should_include_api = in_array( $type, $api_filter['include_types'], true );
		}

		return $should_include_api;
	}

	/**
	 * Enqueue an additional script inside Thrive Architect in order to add some custom hooks which integrate WP Fusion with the Lead Generation element API Connections.
	 *
	 * @since 3.40.24
	 */
	public static function enqueue_architect_scripts() {

		wp_enqueue_script( 'wpf-thrive-api-connection', WPF_DIR_URL . 'assets/js/wpf-thrive-api-connection.js', array( 'tve_editor' ) );

		$localized_data = array(
			'api_logo' => WPF_DIR_URL . '/assets/img/logo-sm-trans.png',
			'api_key'  => 'wpfusion',
		);

		wp_localize_script( 'wpf-thrive-api-connection', 'wpf_thrive_api', $localized_data );

	}

	/**
	 * Add WP Fusion to the list of supported APIs with tags. Required inside TCB.
	 *
	 * @param $apis
	 *
	 * @since 3.40.24
	 * @return mixed
	 */
	public static function tcb_apis_with_tags( $apis ) {
		$apis[] = 'wpfusion';

		return $apis;
	}


}

new WPF_Thrive_API_Connection();
