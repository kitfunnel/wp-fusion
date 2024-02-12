<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Forminator integration form settings class.
 *
 * @since 3.42.0
 *
 * @link https://wpfusion.com/documentation/lead-generation/forminator/
 */

class Forminator_Addon_WP_Fusion_Form_Settings extends Forminator_Addon_Form_Settings_Abstract {

	/**
	 * @var Forminator_Addon_WP_Fusion
	 * @since 3.42.0 WP_Fusion Addon
	 */
	protected $addon;

	/**
	 * @var Forminator_Addon_WP_Fusion_CustomField
	 * @since 3.42.0 WP_Fusion Custom Fields
	 */
	protected $custom_fields;

	/**
	 * Forminator_Addon_WP_Fusion_Form_Settings constructor.
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param Forminator_Addon_Abstract $addon
	 * @param                           $form_id
	 *
	 * @throws Forminator_Addon_Exception
	 */
	public function __construct( Forminator_Addon_Abstract $addon, $form_id ) {
		parent::__construct( $addon, $form_id );

		$this->_update_form_settings_error_message = esc_html__(
			'The update to your settings for this form failed, check the form input and try again.',
			'wp-fusion'
		);
	}

	/**
	 * WP_Fusion Form Settings wizard
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return array
	 */
	public function form_settings_wizards() {
		// numerical array steps.
		$wizard = array(
			array(
				'callback'     => array( $this, 'pick_name' ),
				'is_completed' => array( $this, 'pick_name_is_completed' ),
			),
			array(
				'callback'     => array( $this, 'select_list' ),
				'is_completed' => array( $this, 'select_list_is_completed' ),
			),
			array(
				'callback'     => array( $this, 'map_fields' ),
				'is_completed' => array( $this, 'map_fields_is_completed' ),
			),
			array(
				'callback'     => array( $this, 'setup_options' ),
				'is_completed' => array( $this, 'setup_options_is_completed' ),
			),
		);

		if ( ! wpf_get_option( 'available_lists' ) ) {
			unset( $wizard[1] );
		}
		return $wizard;
	}

	/**
	 * Set up Connection Name
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $submitted_data
	 *
	 * @return array
	 */
	public function pick_name( $submitted_data ) {
		$multi_id = $this->generate_multi_id();
		if ( isset( $submitted_data['multi_id'] ) ) {
			$multi_id = $submitted_data['multi_id'];
		}
		$template_params = array(
			'name'       => $this->get_multi_id_form_settings_value( $multi_id, 'name', '' ),
			'name_error' => '',
			'multi_id'   => $multi_id,
		);

		unset( $submitted_data['multi_id'] );

		$is_submit  = ! empty( $submitted_data );
		$has_errors = false;
		if ( $is_submit ) {
			$name                    = isset( $submitted_data['name'] ) ? $submitted_data['name'] : '';
			$template_params['name'] = $name;

			try {
				if ( empty( $name ) ) {
					throw new Error( esc_html__( 'Please pick valid name', 'wp-fusion' ) );
				}

				$time_added = $this->get_multi_id_form_settings_value( $multi_id, 'time_added', time() );
				$this->save_multi_id_form_setting_values(
					$multi_id,
					array(
						'name'       => $name,
						'time_added' => $time_added,
					)
				);

			} catch ( Error $e ) {
				$template_params['name_error'] = $e->getMessage();
				$has_errors                    = true;
			}
		}

		$buttons = array();
		if ( $this->pick_name_is_completed( array( 'multi_id' => $multi_id ) ) ) {
			$buttons['disconnect']['markup'] = Forminator_Addon_Abstract::get_button_markup(
				esc_html__( 'Deactivate', 'wp-fusion' ),
				'sui-button-ghost sui-tooltip sui-tooltip-top-center forminator-addon-form-disconnect',
				esc_html__( 'Deactivate this WP Fusion Integration from this Form.', 'wp-fusion' )
			);
		}

		$buttons['next']['markup'] = '<div class="sui-actions-right">' .
									Forminator_Addon_Abstract::get_button_markup( esc_html__( 'Next', 'wp-fusion' ), 'forminator-addon-next' ) .
									'</div>';

		$template = WPF_Forminator::pick_name_template( $template_params );

		return array(
			'html'       => $template,
			'buttons'    => $buttons,
			'redirect'   => false,
			'has_errors' => $has_errors,
		);
	}

	/**
	 * Check if pick name step completed
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $submitted_data
	 *
	 * @return bool
	 */
	public function pick_name_is_completed( $submitted_data ) {
		$multi_id = '';
		if ( isset( $submitted_data['multi_id'] ) ) {
			$multi_id = $submitted_data['multi_id'];
		}

		if ( empty( $multi_id ) ) {
			return false;
		}

		$name = $this->get_multi_id_form_settings_value( $multi_id, 'name', '' );

		if ( empty( $name ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Set up Contact List
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $submitted_data
	 *
	 * @return array
	 */
	public function select_list( $submitted_data ) {
		if ( ! isset( $submitted_data['multi_id'] ) ) {
			return $this->get_force_closed_wizard( esc_html__( 'Please pick valid connection', 'wp-fusion' ) );
		}

		$multi_id = $submitted_data['multi_id'];
		unset( $submitted_data['multi_id'] );

		$template_params = array(
			'list_id'       => $this->get_multi_id_form_settings_value( $multi_id, 'list_id', '' ),
			'list_id_error' => '',
			'multi_id'      => $multi_id,
			'error_message' => '',
			'lists'         => array(),
		);

		$is_submit                = ! empty( $submitted_data );
		$has_errors               = false;
		$lists                    = wpf_get_option( 'available_lists' );
		$template_params['lists'] = $lists;

		if ( $is_submit ) {
			$list_id                    = isset( $submitted_data['list_id'] ) ? $submitted_data['list_id'] : '';
			$template_params['list_id'] = $list_id;

			try {
				if ( empty( $list_id ) ) {
					throw new Error( esc_html__( 'Please pick a valid list', 'wp-fusion' ) );
				}

				// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				if ( ! in_array( $list_id, array_keys( $lists ) ) ) {
					throw new Error( esc_html__( 'Please pick a valid list', 'wp-fusion' ) );
				}

				$list_name = $lists[ $list_id ];

				$this->save_multi_id_form_setting_values(
					$multi_id,
					array(
						'list_id'   => $list_id,
						'list_name' => $list_name,
					)
				);

			} catch ( Error $e ) {
				$template_params['list_id_error'] = $e->getMessage();
				$has_errors                       = true;
			}
		}

		$buttons = array();
		if ( $this->pick_name_is_completed( array( 'multi_id' => $multi_id ) ) ) {
			$buttons['disconnect']['markup'] = Forminator_Addon_Abstract::get_button_markup(
				esc_html__( 'Deactivate', 'wp-fusion' ),
				'sui-button-ghost sui-tooltip sui-tooltip-top-center forminator-addon-form-disconnect',
				esc_html__( 'Deactivate this WP Fusion Integration from this Form.', 'wp-fusion' )
			);
		}

		$buttons['next']['markup'] = '<div class="sui-actions-right">' .
									Forminator_Addon_Abstract::get_button_markup( esc_html__( 'Next', 'wp-fusion' ), 'forminator-addon-next' ) .
									'</div>';

		$template = WPF_Forminator::select_list_template( $template_params );
		return array(
			'html'       => $template,
			'buttons'    => $buttons,
			'redirect'   => false,
			'has_errors' => $has_errors,
			'has_back'   => true,
		);
	}

	/**
	 * Check if select contact list completed
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $submitted_data
	 *
	 * @return bool
	 */
	public function select_list_is_completed( $submitted_data ) {
		$multi_id = '';
		if ( isset( $submitted_data['multi_id'] ) ) {
			$multi_id = $submitted_data['multi_id'];
		}

		if ( empty( $multi_id ) ) {
			return false;
		}

		$list_id = $this->get_multi_id_form_settings_value( $multi_id, 'list_id', '' );

		if ( empty( $list_id ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Set up fields map
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $submitted_data
	 *
	 * @return array
	 */
	public function map_fields( $submitted_data ) {
		if ( ! isset( $submitted_data['multi_id'] ) ) {
			return $this->get_force_closed_wizard( esc_html__( 'Please pick valid connection', 'wp-fusion' ) );
		}

		$multi_id = $submitted_data['multi_id'];
		unset( $submitted_data['multi_id'] );

		// find type of email.
		$email_fields                 = array();
		$forminator_field_element_ids = array();
		foreach ( $this->form_fields as $form_field ) {
			// collect element ids.
			$forminator_field_element_ids[] = $form_field['element_id'];
			if ( 'email' === $form_field['type'] ) {
				$email_fields[] = $form_field;
			}
		}

		$template_params = array(
			'fields_map'    => $this->get_multi_id_form_settings_value( $multi_id, 'fields_map', array() ),
			'multi_id'      => $multi_id,
			'error_message' => '',
			'fields'        => array(),
			'form_fields'   => $this->form_fields,
			'email_fields'  => $email_fields,
		);

		$is_submit     = ! empty( $submitted_data );
		$has_errors    = false;
		$custom_fields = array();

		$list_id = $this->get_multi_id_form_settings_value( $multi_id, 'list_id', 0 );

		$crm_fields      = wpf_get_option( 'crm_fields' );
		$standard_fields = ( $crm_fields['Standard Fields'] ? $crm_fields['Standard Fields'] : array() );
		$custom_fields   = ( $crm_fields['Custom Fields'] ? $crm_fields['Custom Fields'] : array() );

		$template_params['fields'] = array_merge( $standard_fields, $custom_fields );

		if ( $is_submit ) {
			$fields_map                    = isset( $submitted_data['fields_map'] ) ? $submitted_data['fields_map'] : array();
			$template_params['fields_map'] = $fields_map;

			try {
				if ( empty( $fields_map ) ) {
					throw new Error( esc_html__( 'Please assign fields.', 'wp-fusion' ) );
				}

				$input_exceptions = new Forminator_Addon_WP_Fusion_Form_Settings_Exception();
				if ( ! isset( $fields_map['email'] ) || empty( $fields_map['email'] ) ) {
					$input_exceptions->add_input_exception( 'Please assign field for Email Address', 'email_error' );
				}

				$fields_map_to_save = array();
				foreach ( $fields as $key => $title ) {
					if ( isset( $fields_map[ $key ] ) && ! empty( $fields_map[ $key ] ) ) {
						$element_id = $fields_map[ $key ];
						if ( ! in_array( $element_id, $forminator_field_element_ids, true ) ) {
							$input_exceptions->add_input_exception(
								sprintf(
								/* translators: %s: Field title */
									esc_html__( 'Please assign valid field for %s', 'wp-fusion' ),
									esc_html( $title )
								),
								$key . '_error'
							);
							continue;
						}

						$fields_map_to_save[ $key ] = $fields_map[ $key ];
					}
				}

				if ( $input_exceptions->input_exceptions_is_available() ) {
					throw $input_exceptions;
				}

				$this->save_multi_id_form_setting_values( $multi_id, array( 'fields_map' => $fields_map ) );

			} catch ( Forminator_Addon_WP_Fusion_Form_Settings_Exception $e ) {
				$template_params = array_merge( $template_params, $e->get_input_exceptions() );
				$has_errors      = true;
			} catch ( Error $e ) {
				$template_params['error_message'] = $e->getMessage();
				$has_errors                       = true;
			}
		}

		$buttons = array();
		if ( $this->pick_name_is_completed( array( 'multi_id' => $multi_id ) ) ) {
			$buttons['disconnect']['markup'] = Forminator_Addon_Abstract::get_button_markup(
				esc_html__( 'Deactivate', 'wp-fusion' ),
				'sui-button-ghost sui-tooltip sui-tooltip-top-center forminator-addon-form-disconnect',
				esc_html__( 'Deactivate this WP Fusion Integration from this Form.', 'wp-fusion' )
			);
		}

		$buttons['next']['markup'] = '<div class="sui-actions-right">' .
									Forminator_Addon_Abstract::get_button_markup( esc_html__( 'Next', 'wp-fusion' ), 'forminator-addon-next' ) .
									'</div>';
		$template                  = WPF_Forminator::map_fields_template( $template_params );
		return array(
			'html'       => $template,
			'buttons'    => $buttons,
			'size'       => 'normal',
			'redirect'   => false,
			'has_errors' => $has_errors,
			'has_back'   => true,
		);
	}

	/**
	 * Check if fields mapped
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $submitted_data
	 *
	 * @return bool
	 */
	public function map_fields_is_completed( $submitted_data ) {
		$multi_id = '';
		if ( isset( $submitted_data['multi_id'] ) ) {
			$multi_id = $submitted_data['multi_id'];
		}

		if ( empty( $multi_id ) ) {
			return false;
		}

		$fields_map = $this->get_multi_id_form_settings_value( $multi_id, 'fields_map', array() );

		if ( empty( $fields_map ) || ! is_array( $fields_map ) || count( $fields_map ) < 1 ) {
			return false;
		}

		if ( ! isset( $fields_map['email'] ) || empty( $fields_map['email'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Set up options
	 *
	 * Contains :
	 * - Double opt-in form,
	 * - tags,
	 * - instant-responder,
	 * - send last broadcast
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $submitted_data
	 *
	 * @return array
	 */
	public function setup_options( $submitted_data ) {
		if ( ! isset( $submitted_data['multi_id'] ) ) {
			return $this->get_force_closed_wizard( esc_html__( 'Please pick valid connection', 'wp-fusion' ) );
		}

		$multi_id = $submitted_data['multi_id'];
		unset( $submitted_data['multi_id'] );

		$forminator_form_element_ids = array();
		foreach ( $this->form_fields as $field ) {
			$forminator_form_element_ids[ $field['element_id'] ] = $field;
		}

		$template_params = array(
			'multi_id'             => $multi_id,
			'error_message'        => '',
			'tags_fields'          => array(),
			'tags_selected_fields' => array(),
		);

		$saved_tags = $this->get_multi_id_form_settings_value( $multi_id, 'tags', array() );
		if ( isset( $submitted_data['tags'] ) && is_array( $submitted_data['tags'] ) ) {
			$saved_tags = $submitted_data['tags'];
		}

		$tags                 = wp_fusion()->settings->get_available_tags_flat();
		$tags_selected_fields = array();
		$tags_fields          = array();

		foreach ( $tags as $key => $value ) {
			if ( ! empty( $saved_tags ) && in_array( $key, $saved_tags ) ) {
				$tags_selected_fields[ $key ] = $value;
			} else {
				$tags_fields[ $key ] = $value;
			}
		}

		$is_submit    = ! empty( $submitted_data );
		$has_errors   = false;
		$notification = array();
		$is_close     = false;

		if ( $is_submit ) {

			try {
				$input_exceptions = new Forminator_Addon_WP_Fusion_Form_Settings_Exception();

				if ( $input_exceptions->input_exceptions_is_available() ) {
					throw $input_exceptions;
				}

				$this->save_multi_id_form_setting_values(
					$multi_id,
					array(
						'tags' => $saved_tags,
					)
				);

				$notification = array(
					'type' => 'success',
					'text' => '<strong>' . $this->addon->get_title() . '</strong> ' . esc_html__( 'Successfully connected to your form', 'wp-fusion' ),
				);
				$is_close     = true;

			} catch ( Forminator_Addon_WP_Fusion_Form_Settings_Exception $e ) {
				$template_params = array_merge( $template_params, $e->get_input_exceptions() );
				$has_errors      = true;
			} catch ( Error $e ) {
				$template_params['error_message'] = $e->getMessage();
				$has_errors                       = true;
			}
		}

		$template_params['tags_fields']          = $tags_fields;
		$template_params['tags_selected_fields'] = $tags_selected_fields;

		$buttons = array();
		if ( $this->pick_name_is_completed( array( 'multi_id' => $multi_id ) ) ) {
			$buttons['disconnect']['markup'] = Forminator_Addon_Abstract::get_button_markup(
				esc_html__( 'Deactivate', 'wp-fusion' ),
				'sui-button-ghost sui-tooltip sui-tooltip-top-center forminator-addon-form-disconnect',
				esc_html__( 'Deactivate this WP Fusion Integration from this Form.', 'wp-fusion' )
			);
		}

		$buttons['next']['markup'] = '<div class="sui-actions-right">' .
									Forminator_Addon_Abstract::get_button_markup( esc_html__( 'Save', 'wp-fusion' ), 'sui-button-primary forminator-addon-finish' ) .
									'</div>';

		$template = WPF_Forminator::setup_options_template( $template_params );
		return array(
			'html'         => $template,
			'buttons'      => $buttons,
			'size'         => 'normal',
			'redirect'     => false,
			'has_errors'   => $has_errors,
			'has_back'     => true,
			'notification' => $notification,
			'is_close'     => $is_close,
		);
	}

	/**
	 * Check if setup options completed
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $submitted_data
	 *
	 * @return bool
	 */
	public function setup_options_is_completed( $submitted_data ) {
		// all settings here are optional, so it can be marked as completed.
		return true;
	}

	/**
	 * Generate multi id for multiple connection
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return string
	 */
	public function generate_multi_id() {
		return uniqid( 'WP_Fusion_', true );
	}


	/**
	 * Override how multi connection displayed
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return array
	 */
	public function get_multi_ids() {
		$multi_ids            = array();
		$form_settings_values = $this->get_form_settings_values();
		foreach ( $form_settings_values as $key => $value ) {
			// apply some sorting if applicable.
			$multi_ids[] = array(
				'id'    => $key,
				// use name that was added by user on creating connection.
				'label' => isset( $value['name'] ) ? $value['name'] : $key,
			);
		}

		/**
		 * Filter labels of multi_id on integrations tab
		 *
		 * @since 3.42.0
		 *
		 * @param array $multi_ids
		 * @param array $form_settings_values
		 */
		$multi_ids = apply_filters( 'forminator_addon_wp_fusion_multi_id_labels', $multi_ids, $form_settings_values );

		return $multi_ids;
	}

	/**
	 * Disconnect a connection from current form
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param array $submitted_data
	 */
	public function disconnect_form( $submitted_data ) {
		// only execute if multi_id provided on submitted data.
		if ( isset( $submitted_data['multi_id'] ) && ! empty( $submitted_data['multi_id'] ) ) {
			$addon_form_settings = $this->get_form_settings_values();
			unset( $addon_form_settings[ $submitted_data['multi_id'] ] );
			$this->save_form_settings_values( $addon_form_settings );
		}
	}

	/**
	 * Check if multi_id form settings values completed
	 *
	 * @since 3.42.0 WP Fusion Added
	 *
	 * @param $multi_id
	 *
	 * @return bool
	 */
	public function is_multi_form_settings_complete( $multi_id ) {
		$data = array( 'multi_id' => $multi_id );

		if ( ! $this->pick_name_is_completed( $data ) ) {
			return false;
		}
		if ( wpf_get_option( 'available_lists' ) && ! $this->select_list_is_completed( $data ) ) {
			return false;
		}

		if ( ! $this->map_fields_is_completed( $data ) ) {
			return false;
		}

		if ( ! $this->setup_options_is_completed( $data ) ) {
			return false;
		}

		return true;
	}
}
