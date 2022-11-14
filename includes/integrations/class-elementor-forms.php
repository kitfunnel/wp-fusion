<?php

use Elementor\Controls_Manager;
use Elementor\Settings;
use Elementor\Plugin;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Controls\Fields_Map;
use ElementorPro\Modules\Forms\Classes\Integration_Base;
use ElementorPro\Classes\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function wpf_add_form_actions() {
	\ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' )->add_form_action( 'wpfusion', new WPF_Elementor_Forms() );
}

add_action( 'elementor_pro/init', 'wpf_add_form_actions' );

class WPF_Elementor_Forms extends ElementorPro\Modules\Forms\Classes\Integration_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'elementor-forms';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Elementor Forms';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/lead-generation/elementor-forms/';


	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function __construct() {

		wp_fusion()->integrations->{'elementor-forms'} = $this;

		add_filter( 'get_post_metadata', array( $this, 'update_saved_forms' ), 10, 4 );

	}

	/**
	 * Get action ID
	 *
	 * @access  public
	 * @return  string ID
	 */

	public function get_name() {
		return 'wpfusion';
	}

	/**
	 * Get action label
	 *
	 * @access  public
	 * @return  string Label
	 */

	public function get_label() {
		return 'WP Fusion';
	}

	/**
	 * Get CRM fields
	 *
	 * @access  public
	 * @return  array fields
	 */

	public function get_fields() {

		$fields = array();

		$fields_merged    = array();
		$available_fields = wp_fusion()->settings->get_crm_fields_flat();

		foreach ( $available_fields as $field_id => $field_label ) {

			$remote_required = false;

			if ( $field_label == 'Email' ) {
				$remote_required = true;
			}

			$fields[] = array(
				'remote_label'    => $field_label,
				'remote_type'     => 'text',
				'remote_id'       => $field_id,
				'remote_required' => $remote_required,
			);

		}

		// Add as tag
		if ( in_array( 'add_tags', wp_fusion()->crm->supports ) ) {

			$fields[] = array(
				'remote_label'    => '+ Create tag(s) from',
				'remote_type'     => 'text',
				'remote_id'       => 'add_tag_e',
				'remote_required' => false,
			);

		}

		return $fields;

	}

	/**
	 * Get available tags for select
	 *
	 * @access  public
	 * @return  array Tags
	 */

	public function get_tags() {

		$available_tags = wpf_get_option( 'available_tags', array() );

		$data = array();

		foreach ( $available_tags as $id => $label ) {

			if ( is_array( $label ) ) {
				$label = $label['label'];
			}

			$data[ $id ] = $label;

		}

		return $data;

	}

	/**
	 * Registers settings
	 *
	 * @access  public
	 * @return  void
	 */

	public function register_settings_section( $widget ) {

		$widget->start_controls_section(
			'section_wpfusion',
			array(
				'label'     => 'WP Fusion',
				'condition' => array(
					'submit_actions' => $this->get_name(),
				),
			)
		);

		$widget->add_control(
			'wpf_apply_tags',
			array(
				'label'       => __( 'Apply Tags', 'wp-fusion' ),
				'description' => sprintf( __( 'The selected tags will be applied in %s when the form is submitted.', 'wp-fusion' ), wp_fusion()->crm->name ),
				'type'        => Controls_Manager::SELECT2,
				'options'     => $this->get_tags(),
				'multiple'    => true,
				'label_block' => true,
				'show_label'  => true,
			)
		);

		$widget->add_control(
			'wpf_add_only',
			array(
				'label'       => __( 'Add Only', 'wp-fusion' ),
				'description' => __( 'Only add new contacts, don\'t update existing ones.', 'wp-fusion' ),
				'type'        => Controls_Manager::SWITCHER,
				'label_block' => false,
				'show_label'  => true,
			)
		);

		if ( version_compare( ELEMENTOR_PRO_VERSION, '3.2.0', '>=' ) ) {

			$this->register_fields_map_control( $widget );

		} else {

			$widget->add_control(
				'wpf_fields_map',
				array(
					'label'     => sprintf( __( '%s Field Mapping', 'wp-fusion' ), wp_fusion()->crm->name ),
					'type'      => Fields_Map::CONTROL_TYPE,
					'separator' => 'before',
					'fields'    => array(
						array(
							'name' => 'remote_id',
							'type' => Controls_Manager::HIDDEN,
						),
						array(
							'name' => 'local_id',
							'type' => Controls_Manager::SELECT,
						),
					),
					'default'   => $this->get_fields(),
				)
			);

		}

		$widget->end_controls_section();

	}

	/**
	 * Update saved form data when it's loaded from the DB to detect new form fields (because Elementor support has been useless at helping with this, see https://github.com/elementor/elementor/issues/8938)
	 *
	 * @access  public
	 * @return  null / array Value
	 */

	public function update_saved_forms( $value, $object_id, $meta_key, $single ) {

		if ( is_admin() && '_elementor_data' == $meta_key && Plugin::$instance->editor->is_edit_mode() ) {

			// Prevent looping
			remove_filter( 'get_post_metadata', array( $this, 'update_saved_forms' ), 10, 4 );

			$settings = get_post_meta( $object_id, '_elementor_data', true );

			// Quit if the desired setting isn't found or if settings are already an array (no idea why it does that).
			if ( is_array( $settings ) || false === strpos( $settings, 'wpfusion_fields_map' ) ) {
				return $value;
			}

			$original_string = $settings;

			$settings = json_decode( $settings, true );

			$settings = $this->parse_elements_for_form( $settings );

			$value = wp_json_encode( $settings );

			if ( $value !== $original_string && class_exists( 'SitePress' ) ) {

				// Save it back to the database as well.

				// @todo This makes me very nervous, since it could corrupt the saved data. Since we've only had it reported as a conflict with WPML,
				// at the moment we'll only save the data back to postmeta if WPML (SitePress) is active.

				$save_value = wp_slash( $value );

				update_metadata( 'post', $object_id, '_elementor_data', $save_value );

			}
		}

		return $value;

	}

	/**
	 * Loop through saved elements, updating values as necessary
	 *
	 * @access  public
	 * @return  array Elements
	 */

	private function parse_elements_for_form( $elements ) {

		foreach ( $elements as $i => $element ) {

			if ( isset( $element['settings'] ) && isset( $element['settings']['wpfusion_fields_map'] ) ) {

				$new_settings = $this->get_fields();

				foreach ( $new_settings as $n => $setting ) {

					foreach ( $element['settings']['wpfusion_fields_map'] as $saved_value ) {

						if ( $saved_value['remote_id'] == $setting['remote_id'] ) {

							$new_settings[ $n ] = array_merge( $setting, $saved_value );

						}
					}
				}

				$elements[ $i ]['settings']['wpfusion_fields_map'] = $new_settings;

			}

			if ( ! empty( $element['elements'] ) ) {

				$elements[ $i ]['elements'] = $this->parse_elements_for_form( $element['elements'] );

			}
		}

		return $elements;

	}

	/**
	 * Unsets WPF settings on export
	 *
	 * @access  public
	 * @return  object Element
	 */

	public function on_export( $element ) {

		unset(
			$element['settings']['wpfusion_fields_map'],
			$element['settings']['wpf_apply_tags']
		);

		return $element;
	}

	/**
	 * Process form submission
	 *
	 * @access  public
	 * @return  void
	 */

	public function run( $record, $ajax_handler ) {

		$sent_data     = $record->get( 'sent_data' );
		$form_settings = $record->get( 'form_settings' );

		$update_data   = array();
		$email_address = false;

		// Check to see if it's a pre 3.2 form mapping.

		$found = false;

		if ( ! empty( $form_settings['wpfusion_fields_map'] ) ) {

			$map = $form_settings['wpfusion_fields_map'];

			foreach ( $map as $field ) {

				if ( ! empty( $field['local_id'] ) ) {
					$found = true;
					break;
				}
			}
		}

		if ( ! $found && ! empty( $form_settings['wpf_fields_map'] ) ) {

			// Maybe use old settings
			$map = $form_settings['wpf_fields_map'];

		}

		foreach ( $map as $field ) {

			if ( ! empty( $field['local_id'] ) && ! empty( $sent_data[ $field['local_id'] ] ) ) {

				$value = $sent_data[ $field['local_id'] ];

				if ( false !== strpos( $field['remote_id'], 'add_tag_' ) ) {

					// Don't run the filter on dynamic tagging inputs.
					$update_data[ $field['remote_id'] ] = $value;
					continue;

				}

				if ( is_array( $value ) ) {
					$type = 'checkboxes';
				} elseif ( '1' === $value || 'true' === $value ) {
					$type = 'checkbox'; // boolean true
				} elseif ( '0' === $value || 'false' === $value ) {
					$type = 'checkbox'; // boolean false
				} elseif ( ! is_numeric( $value ) && ! empty( strtotime( $value ) ) && preg_match( '/\\d/', $value ) > 0 ) {
					$type = 'date';
				} else {
					$type = 'text';
				}

				$update_data[ $field['remote_id'] ] = apply_filters( 'wpf_format_field_value', $value, $type, $field['remote_id'] );

				// For determining the email address, we'll try to find a field
				// mapped to the main lookup field in the CRM, but if not we'll take
				// the first email address on the form.

				if ( is_string( $value ) && is_email( $value ) && wpf_get_lookup_field() === $field['remote_id'] ) {
					$email_address = $value;
				} elseif ( false === $email_address && is_string( $value ) && is_email( $value ) ) {
					$email_address = $value;
				}
			}
		}

		if ( isset( $form_settings['wpf_add_only'] ) && 'yes' == $form_settings['wpf_add_only'] ) {
			$add_only = true;
		} else {
			$add_only = false;
		}

		if ( empty( $form_settings['wpf_apply_tags'] ) ) {
			$form_settings['wpf_apply_tags'] = array();
		}

		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => $form_settings['wpf_apply_tags'],
			'add_only'         => $add_only,
			'integration_slug' => 'elementor_forms',
			'integration_name' => 'Elementor Forms',
			'form_id'          => null,
			'form_title'       => null,
			'form_edit_link'   => null,
		);

		$contact_id = WPF_Forms_Helper::process_form_data( $args );

		// Return after login + auto login.

		if ( isset( $_COOKIE['wpf_return_to'] ) && doing_wpf_auto_login() ) {

			$post_id = absint( $_COOKIE['wpf_return_to'] );
			$url     = get_permalink( $post_id );

			setcookie( 'wpf_return_to', '', time() - ( 15 * 60 ) );

			if ( ! empty( $url ) && wpf_user_can_access( $post_id ) ) {

				$ajax_handler->add_response_data( 'redirect_url', $url );

			}
		}

	}

	/**
	 * @param array $data
	 *
	 * @return void
	 */

	public function handle_panel_request( array $data ) { }

	/**
	 * Get field map control options.
	 *
	 * @since  3.37.13
	 *
	 * @return array The fields map control options.
	 */
	protected function get_fields_map_control_options() {
		return array(
			'default'   => $this->get_fields(),
			'condition' => array(),
		);
	}


}
