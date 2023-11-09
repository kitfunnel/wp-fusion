<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WS Form integration.
 *
 * @since 3.37.19
 */

class WPF_WS_Form extends WS_Form_Action {

	// Settings for WS_Form_Action.
	public $id           = 'wpfusion';
	public $pro_required = false;
	public $label;
	public $label_action;
	public $events;
	public $multiple   = false;
	public $priority   = 150;
	public $can_repost = true;
	public $form_add   = false;
	public $kb_slug    = 'wp-fusion';

	// Config.
	public $opt_in_field;
	public $add_only;
	public $field_mapping;
	public $custom_mapping;
	public $tags;

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.49
	 * @var string $slug
	 */
	public $slug = 'ws-form';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.49
	 * @var string $name
	 */
	public $name = 'WS Form';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.49
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/lead-generation/ws-form/';

	/**
	 * Constructor.
	 *
	 * @since 3.37.19
	 */
	public function __construct() {

		add_action( 'wp_fusion_init', array( $this, 'init' ) );

	}

	/**
	 * Initialize the action.
	 *
	 * @since 3.37.19
	 */
	public function init() {

		wp_fusion()->integrations->{'ws-form'} = $this;

		// Set label
		$this->label = __( 'WP Fusion', 'wp-fusion' );

		// Set label for actions pull down
		$this->label_action = __( 'WP Fusion', 'wp-fusion' );

		// Events
		$this->events = array( 'submit' );

		// Register action
		parent::register( $this );

		// Register config filters
		add_filter( 'wsf_config_meta_keys', array( $this, 'config_meta_keys' ), 10, 2 );

	}

	/**
	 * Process the form submission.
	 *
	 * @since  3.37.19
	 *
	 * @param  unknown $form   The form.
	 * @param  array   $submit The submitted data.
	 * @param  array   $config The configuration.
	 * @return bool    Success or fail.
	 */
	public function post( $form, &$submit, $config ) {

		// Load configuration
		self::load_config( $config );

		// Get opt in value (False if field not submitted)
		$opt_in_field_value = parent::get_submit_value( $submit, WS_FORM_FIELD_PREFIX . $this->opt_in_field, false );

		if ( ( false !== $this->opt_in_field ) && ( '' !== $this->opt_in_field ) && ( false !== $opt_in_field_value ) ) {

			// End user did not opt in, exit gracefully
			if ( empty( $opt_in_field_value ) ) {

				self::success( __( 'User did not opt in, no data pushed to action', 'wp-fusion' ) );
				return true;

			}
		}

		// Get form fields
		$fields = WS_Form_Common::get_fields_from_form( $form );

		// Build WPF data
		$email_address = false;
		$update_data   = array();
		$apply_tags    = array();

		// Process field mapping
		foreach ( $this->field_mapping as $field_map ) {

			// Get value
			$field_id = $field_map['ws_form_field'];
			if ( empty( $field_id ) ) {
				continue; }
			if ( ! isset( $fields[ $field_id ] ) ) {
				continue; }
			$wpf_value = parent::get_submit_value( $submit, WS_FORM_FIELD_PREFIX . $field_id, '', true );

			// Get field type
			$field_type = $fields[ $field_id ]->type;

			// Get CRM field
			$wpf_field = $field_map[ 'action_' . $this->id . '_wpf_field' ];
			if ( empty( $wpf_field ) ) {
				continue;
			}

			// Parse value
			self::parse_value( $wpf_value, $field_type, $email_address );

			// Don't run the filter on dynamic tagging inputs.
			if ( false !== strpos( $wpf_field, 'add_tag_' ) ) {
				$update_data[ $wpf_field ] = $wpf_value;
				continue;
			}

			// Save to update data
			$update_data[ $wpf_field ] = apply_filters( 'wpf_format_field_value', $wpf_value, $field_type, $wpf_field );
		}

		// Process custom mapping
		foreach ( $this->custom_mapping as $custom_map ) {

			// Get value
			$wpf_value = $custom_map[ 'action_' . $this->id . '_wpf_value' ];
			$wpf_value = WS_Form_Common::parse_variables_process( $wpf_value, $form, $submit, 'text/plain' );

			// Get field type
			$field_type = $fields[ $field_id ]->type;

			// Get CRM field
			$wpf_field = $custom_map[ 'action_' . $this->id . '_wpf_field' ];
			if ( empty( $wpf_field ) ) {
				continue; }

			// Parse value
			self::parse_value( $wpf_value, $field_type, $email_address );

			// Don't run the filter on dynamic tagging inputs.
			if ( false !== strpos( $wpf_field, 'add_tag_' ) ) {
				$update_data[ $wpf_field ] = $wpf_value;
				continue;
			}

			// Save to update data
			$update_data[ $wpf_field ] = apply_filters( 'wpf_format_field_value', $wpf_value, $field_type, $wpf_field );
		}

		// Process tags
		foreach ( $this->tags as $tag ) {

			// Get tag
			$wpf_tag = $tag[ 'action_' . $this->id . '_wpf_tag' ];
			if ( '' === $wpf_tag ) {
				continue;
			}

			// Add to apply_tags array
			$apply_tags[] = $wpf_tag;
		}

		// Process form data args for WP Fusion
		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => $apply_tags,
			'add_only'         => ! empty( $this->add_only ) ? true : false,
			'integration_slug' => 'ws-form',
			'integration_name' => 'WS Form',
			'form_id'          => $form->id,
			'form_title'       => $form->label,
			'form_edit_link'   => WS_Form_Common::get_admin_url( 'ws-form-edit', $form->id ),
		);

		// Get new contact ID
		$contact_id = WPF_Forms_Helper::process_form_data( $args );

		if ( is_wp_error( $contact_id ) ) {

			return self::error( $contact_id->get_error_message() );

		} else {

			self::success( sprintf( __( 'Successfully added contact via WP Fusion: %s', 'wp-fusion' ), $contact_id ) );
			return true;
		}
	}


	/**
	 * Parse value.
	 *
	 * @since 3.37.19
	 *
	 * @param mixed  $value         The value.
	 * @param string $type          The type.
	 * @param string $email_address The email address.
	 */
	public function parse_value( &$value, $type, &$email_address ) {

		// Process by field type
		switch ( $type ) {

			case 'file':
			case 'signature':
				if ( is_array( $value ) && isset( $value[0] ) && isset( $value[0]['url'] ) ) {

					$value = $value[0]['url'];

				} else {

					$value = '';
				}

				break;
		}

		// Array to delimited
		if ( is_array( $value ) ) {
			$value = implode( ',', $wpf_value );
		}

		// Check for email address
		if ( ( false === $email_address ) && ( 'email' === $type ) ) {
			$email_address = $value;
		}

	}

	/**
	 * Meta keys for this action.
	 *
	 * @since  3.37.19
	 *
	 * @param  array $meta_keys The meta keys.
	 * @param  int   $form_id   The form ID
	 * @return array The meta keys.
	 */
	public function config_meta_keys( $meta_keys = array(), $form_id = 0 ) {

		// Get WP Fusion CRM fields
		$wpf_field_options = array();
		$wpf_fields        = wpf_get_option( 'crm_fields' );

		if ( in_array( 'add_tags', wp_fusion()->crm->supports ) ) {

			$wpf_fields['Tagging'] = array(
				'add_tag_' . $form_id => __( 'Create tag(s) from value', 'wp-fusion' ),
			);

		}

		if ( ! empty( $wpf_fields ) ) {

			foreach ( $wpf_fields as $group_header => $fields ) {

				// For CRMs with separate custom and built in fields
				if ( is_array( $fields ) ) {

					foreach ( $wpf_fields[ $group_header ] as $field => $label ) {

						if ( is_array( $label ) ) {
							$label = $label['label'];
						}

						$wpf_field_options[] = array(
							'value'    => $field,
							'text'     => $label,
							'optgroup' => $group_header,
						);
					}
				} else {

					$field = $group_header;
					$label = $fields;

					$wpf_field_options[] = array(
						'value' => $field,
						'text'  => $label,
					);
				}
			}
		}

		$available_tags = array();

		foreach ( wp_fusion()->settings->get_available_tags_flat() as $id => $tag ) {

			$available_tags[] = array(
				'value' => $id,
				'text'  => $tag,
			);

		}

		// Build config_meta_keys
		$config_meta_keys = array(

			// Opt-In field
			'action_' . $this->id . '_opt_in_field'   => array(

				'label'              => __( 'Opt-In Field (Optional)', 'wp-fusion' ),
				'type'               => 'select',
				'options'            => 'fields',
				'options_blank'      => __( 'Select...', 'wp-fusion' ),
				'fields_filter_type' => array( 'select', 'checkbox', 'radio' ),
				'help'               => __( 'Checkbox recommended', 'wp-fusion' ),
			),

			// Add only
			'action_' . $this->id . '_add_only'       => array(

				'label'   => __( 'Add Only', 'wp-fusion' ),
				'type'    => 'checkbox',
				'help'    => __( 'Only add new contacts, don\'t update existing ones.', 'wp-fusion' ),
				'default' => '',
			),

			// Field mapping
			'action_' . $this->id . '_field_mapping'  => array(

				'label'     => __( 'Field Mapping', 'wp-fusion' ),
				'type'      => 'repeater',
				'help'      => sprintf( __( 'Map WS Form fields to %s fields.', 'wp-fusion' ), wp_fusion()->crm->name ),
				'meta_keys' => array(
					'ws_form_field',
					'action_' . $this->id . '_wpf_field',
				),
			),

			// Field mapping - Custom
			'action_' . $this->id . '_custom_mapping' => array(

				'label'     => __( 'Custom Field Mapping', 'wp-fusion' ),
				'type'      => 'repeater',
				'help'      => sprintf( __( 'Map custom field values to %s fields.', 'wp-fusion' ), wp_fusion()->crm->name ),
				'meta_keys' => array(
					'action_' . $this->id . '_wpf_field',
					'action_' . $this->id . '_wpf_value',
				),
			),

			// Tag mapping - Custom
			'action_' . $this->id . '_tags'           => array(

				'label'     => __( 'Apply Tags', 'wp-fusion' ),
				'type'      => 'repeater',
				'help'      => sprintf( __( 'Select tags to be applied in %s when this form is submitted.', 'wp-fusion' ), wp_fusion()->crm->name ),
				'meta_keys' => array(

					'action_' . $this->id . '_wpf_tag',
				),
			),

			// WP Fusion - Field
			'action_' . $this->id . '_wpf_field'      => array(

				'label'         => __( 'Key', 'wp-fusion' ),
				'type'          => 'select',
				'options'       => $wpf_field_options,
				'options_blank' => __( 'Select...', 'wp-fusion' ),
			),

			// WP Fusion - Value
			'action_' . $this->id . '_wpf_value'      => array(

				'label' => __( 'Value', 'wp-fusion' ),
				'type'  => 'text',
			),

			// WP Fusion - Tag

			'action_' . $this->id . '_wpf_tag'        => array(

				'label'         => __( 'Tag', 'wp-fusion' ),
				'type'          => 'select',
				'options'       => $available_tags,
				'options_blank' => __( 'Select a tag', 'wp-fusion' ),
			),

		);

		// Merge
		$meta_keys = array_merge( $meta_keys, $config_meta_keys );

		return $meta_keys;

	}


	/**
	 * Load config for this action.
	 *
	 * @since 3.37.19
	 *
	 * @param array $config The configuration.
	 */
	public function load_config( $config = array() ) {

		// Opt-in field
		$this->opt_in_field = parent::get_config( $config, 'action_' . $this->id . '_opt_in_field' );

		// Add new
		$this->add_only = parent::get_config( $config, 'action_' . $this->id . '_add_only' );

		// Field mapping
		$this->field_mapping = parent::get_config( $config, 'action_' . $this->id . '_field_mapping', array() );
		if ( ! is_array( $this->field_mapping ) ) {
			$this->field_mapping = array();
		}

		// Custom mapping
		$this->custom_mapping = parent::get_config( $config, 'action_' . $this->id . '_custom_mapping', array() );
		if ( ! is_array( $this->custom_mapping ) ) {
			$this->custom_mapping = array();
		}

		// Custom tag mapping
		$this->tags = parent::get_config( $config, 'action_' . $this->id . '_tags', array() );
		if ( ! is_array( $this->tags ) ) {
			$this->tags = array();
		}

	}


	/**
	 * Gets the action settings.
	 *
	 * @since  3.37.19
	 *
	 * @return array The action settings.
	 */
	public function get_action_settings() {

		$settings = array(
			'meta_keys' => array(
				'action_' . $this->id . '_opt_in_field',
				'action_' . $this->id . '_add_only',
				'action_' . $this->id . '_field_mapping',
				'action_' . $this->id . '_custom_mapping',
				'action_' . $this->id . '_tags',
			),
		);

		// Wrap settings so they will work with sidebar_html function in admin.js
		$settings = parent::get_settings_wrapper( $settings );

		// Add labels
		$settings->label        = $this->label;
		$settings->label_action = $this->label_action;

		// Add multiple
		$settings->multiple = $this->multiple;

		// Add events
		$settings->events = $this->events;

		// Add can_repost
		$settings->can_repost = $this->can_repost;

		// Apply filter
		$settings = apply_filters( 'wsf_action_' . $this->id . '_settings', $settings );

		return $settings;
	}

}

wp_fusion()->integrations->{'ws-form'} = new WPF_WS_Form();
