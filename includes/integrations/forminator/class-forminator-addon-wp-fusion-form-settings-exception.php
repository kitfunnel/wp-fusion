<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Forminator integration exception class.
 *
 * @since 3.42.0
 *
 * @link https://wpfusion.com/documentation/lead-generation/forminator/
 */

class Forminator_Addon_WP_Fusion_Form_Settings_Exception extends Exception {

	/**
	 * Holder of input exceptions
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @var array
	 */
	protected $input_exceptions = array();

	/**
	 * Forminator_Addon_WP_Fusion_Form_Settings_Exception constructor.
	 *
	 * Useful if input_id is needed for later.
	 * If no input_id needed, use @see Forminator_Addon_WP_Fusion_Exception
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param string $message
	 * @param string $input_id
	 */
	public function __construct( $message = '', $input_id = '' ) {
		parent::__construct( $message, 0 );
		if ( ! empty( $input_id ) ) {
			$this->add_input_exception( $message, $input_id );
		}
	}

	/**
	 * Set exception message for an input
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $message
	 * @param $input_id
	 */
	public function add_input_exception( $message, $input_id ) {
		$this->input_exceptions[ $input_id ] = $message;
	}

	/**
	 * Get all input exceptions
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return array
	 */
	public function get_input_exceptions() {
		return $this->input_exceptions;
	}

	/**
	 * Check if there is input_exceptions_is_available
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return bool
	 */
	public function input_exceptions_is_available() {
		return count( $this->input_exceptions ) > 0;
	}
}