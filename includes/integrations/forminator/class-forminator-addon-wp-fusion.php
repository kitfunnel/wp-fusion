<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Forminator integration addon class.
 *
 * @since 3.42.0
 *
 * @link https://wpfusion.com/documentation/lead-generation/forminator/
 */

final class Forminator_Addon_WP_Fusion extends Forminator_Addon_Abstract {

	/**
	 * @var self|null
	 */
	private static $_instance = null;

	protected $_slug                   = 'wpfusion';
	protected $_version                = '1.0';
	protected $_min_forminator_version = '1.1';
	protected $_short_title            = 'WP Fusion';
	protected $_title                  = 'WP Fusion';
	protected $_url                    = 'https://wpfusion.com';
	protected $_full_path              = __FILE__;
	protected $_position               = 8;

	protected $_form_settings = 'Forminator_Addon_WP_Fusion_Form_Settings';
	protected $_form_hooks    = 'Forminator_Addon_WP_Fusion_Form_Hooks';

	/**
	 * @var Forminator_Addon_WP_Fusion_Wp_Api|null
	 */
	private static $api = null;

	public $connected_account = null;

	/**
	 * Forminator_Addon_WP_Fusion constructor.
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 */
	public function __construct() {
		// late init to allow translation.
		$this->_description                = esc_html__( 'WP Fusion connects your website to your CRM or marketing automation tool, with support for dozens of CRMs and 100+ WordPress plugins.', 'wp-fusion' );
		$this->_activation_error_message   = esc_html__( 'Sorry but we failed to activate WP Fusion Integration, don\'t hesitate to contact us', 'wp-fusion' );
		$this->_deactivation_error_message = esc_html__( 'Sorry but we failed to deactivate WP Fusion Integration, please try again', 'wp-fusion' );

		$this->_update_settings_error_message = esc_html__(
			'Sorry, we failed to update settings, please check your form and try again',
			'wp-fusion'
		);

		$this->_icon     = WPF_DIR_URL . 'assets/img/logo-sm-trans.png';
		$this->_icon_x2  = WPF_DIR_URL . 'assets/img/logo-sm-trans.png';
		$this->_image    = WPF_DIR_URL . 'assets/img/logo.png';
		$this->_image_x2 = WPF_DIR_URL . 'assets/img/logo.png';

		$this->is_multi_global = true;
	}

	/**
	 * Get Instance
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return self|null
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Override on is_connected
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @return bool
	 */
	public function is_connected() {
		return true;
	}

	/**
	 * Check if WP_Fusion is connected with current form
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $form_id
	 *
	 * @return bool
	 */
	public function is_form_connected( $form_id ) {
		try {
			$form_settings_instance = null;
			if ( ! $this->is_connected() ) {
				throw new Error( esc_html__( 'WP Fusion is not connected', 'wp-fusion' ) );
			}

			$form_settings_instance = $this->get_addon_settings( $form_id, 'form' );
			if ( ! $form_settings_instance instanceof Forminator_Addon_WP_Fusion_Form_Settings ) {
				throw new Error( esc_html__( 'Invalid Form Settings of WP Fusion', 'wp-fusion' ) );
			}

			// Mark as active when there is at least one active connection.
			if ( false === $form_settings_instance->find_one_active_connection() ) {
				throw new Error( esc_html__( 'No active WP Fusion connection found in this form', 'wp-fusion' ) );
			}

			$is_form_connected = true;

		} catch ( Error $e ) {
			$is_form_connected = false;
			forminator_addon_maybe_log( __METHOD__, $e->getMessage() );
		}

		/**
		 * Filter connected status of WP Fusion with the form
		 *
		 * @since 3.42.0
		 *
		 * @param bool                                               $is_form_connected
		 * @param int                                                $form_id                Current Form ID.
		 * @param Forminator_Addon_WP_Fusion_Form_Settings|null $form_settings_instance Instance of form settings, or null when unavailable.
		 */
		$is_form_connected = apply_filters( 'forminator_addon_wp_fusion_is_form_connected', $is_form_connected, $form_id, $form_settings_instance );

		return $is_form_connected;
	}

	/**
	 * Override settings available,
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return bool
	 */
	public function is_settings_available() {
		return true;
	}

	/**
	 * Flag show full log on entries
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return bool
	 */
	public static function is_show_full_log() {
		$show_full_log = false;
		if ( defined( 'FORMINATOR_ADDON_WP_FUSION_SHOW_FULL_LOG' ) && FORMINATOR_ADDON_WP_FUSION_SHOW_FULL_LOG ) {
			$show_full_log = true;
		}

		/**
		 * Filter Flag show full log on entries
		 *
		 * @since  1.2
		 *
		 * @params bool $show_full_log
		 */
		$show_full_log = apply_filters( 'forminator_addon_wp_fusion_show_full_log', $show_full_log );

		return $show_full_log;
	}

	/**
	 * Flag enable delete contact before delete entries
	 *
	 * Its disabled by default
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return bool
	 */
	public static function is_enable_delete_contact() {
		$enable_delete_contact = false;
		if ( defined( 'FORMINATOR_ADDON_WPFUSION_ENABLE_DELETE_CONTACT' ) && FORMINATOR_ADDON_WPFUSION_ENABLE_DELETE_CONTACT ) {
			$enable_delete_contact = true;
		}

		/**
		 * Filter Flag enable delete contact before delete entries
		 *
		 * @since  1.2
		 *
		 * @params bool $enable_delete_contact
		 */
		$enable_delete_contact = apply_filters( 'forminator_addon_wp_fusion_delete_contact', $enable_delete_contact );

		return $enable_delete_contact;
	}

	/**
	 * Allow multiple connection on one form
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return bool
	 */
	public function is_allow_multi_on_form() {
		return true;
	}

	/**
	 * Setting wizard of WP Fusion
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return array
	 */
	public function settings_wizards() {
		return array(
			array(
				'callback'     => array( $this, 'setup_api' ),
				'is_completed' => array( $this, 'is_api_completed' ),
			),
		);
	}


	/**
	 * Set up API Wizard
	 *
	 * @since 3.42.0 WP Fusion Addon
	 *
	 * @param     $submitted_data
	 *
	 * @param int            $form_id
	 *
	 * @return array
	 */
	public function setup_api( $submitted_data, $form_id = 0 ) {
		$html  = '<p>';
		$html .= sprintf( esc_html__( 'To edit WP Fusion Settings%1$s Please go to the %2$sWP Fusion settings page%3$s.', 'wp-fusion' ), '<br>', '<a href="' . esc_url( get_admin_url() . '/options-general.php?page=wpf-settings#setup' ) . '">', '</a>' );
		$html .= '</p>';
		return array(
			'html'       => $html,
			'buttons'    => false,
			'redirect'   => false,
			'has_errors' => false,
		);
	}

	public function is_api_completed() {
		return true;
	}

	/**
	 * Validate API URL
	 *
	 * @since 3.42.0 WP Fusion
	 *
	 * @param string $api_url
	 *
	 * @return string
	 * @throws Error
	 */
	public function validate_api_url( $api_url ) {
		return true;
	}

	/**
	 * Validate API Key
	 *
	 * @since 3.42.0 WP Fusion
	 *
	 * @param string $api_key
	 *
	 * @return string
	 * @throws Error
	 */
	public function validate_api_key( $api_key ) {
		return true;
	}

	/**
	 * Validate API
	 *
	 * @since 3.42.0 WP Fusion Addon
	 *
	 * @param $api_url
	 * @param $api_key
	 *
	 * @throws Forminator_Addon_WP_Fusion_Wp_Api_Exception
	 * @throws Error
	 */
	public function validate_api( $api_url, $api_key ) {
		return true;
	}

	/**
	 * Get API Instance
	 *
	 * @since 3.42.0 WP Fusion Addon
	 *
	 * @param null $api_url
	 * @param null $api_key
	 *
	 * @return Forminator_Addon_WP_Fusion_Wp_Api
	 * @throws Forminator_Addon_WP_Fusion_Wp_Api_Exception
	 */
	public function get_api( $api_url = null, $api_key = null ) {
		if ( is_null( $api_key ) || is_null( $api_url ) ) {
			$setting_values = $this->get_settings_values();
			$api_key        = '';
			$api_url        = '';
			if ( isset( $setting_values['api_url'] ) ) {
				$api_url = $setting_values['api_url'];
			}

			if ( isset( $setting_values['api_key'] ) ) {
				$api_key = $setting_values['api_key'];
			}
		}
		$api = new Forminator_Addon_WP_Fusion_Wp_Api( $api_url, $api_key );

		return $api;
	}

	public function before_save_settings_values( $values ) {
		if ( ! empty( $this->connected_account ) ) {
			$values['connected_account'] = $this->connected_account;
		}

		return $values;
	}

	/**
	 * Flag for check if and addon connected to a poll(poll settings such as list id completed)
	 *
	 * Please apply necessary WordPress hook on the inheritance class
	 *
	 * @since   1.6.1
	 *
	 * @param $poll_id
	 *
	 * @return boolean
	 */
	public function is_poll_connected( $poll_id ) {
		return false;
	}
}
