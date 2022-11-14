<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Ninja_Forms extends NF_Abstracts_Action {

	/**
	 * @var string
	 */
	public $slug = 'ninja-forms';

	/**
	 * @var string
	 */
	protected $_name = 'wpfusion';

	/**
	 * @var array
	 */
	protected $_tags = array();

	/**
	 * @var string
	 */
	protected $_timing = 'late';

	/**
	 * @var int
	 */
	protected $_priority = 10;


	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function __construct() {

		// Batch operations
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_filter( 'wpf_batch_ninja_forms_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_ninja_forms', array( $this, 'batch_step' ) );

		wp_fusion()->integrations->{'ninja-forms'} = $this;

		parent::__construct();

		$this->_nicename = 'WP Fusion';

		$settings = $this->get_settings();

		$this->_settings = array_merge( $this->_settings, $settings );

		add_action( 'ninja_forms_builder_templates', array( $this, 'row_template' ) );

	}

	/**
	 * Add admin action settings
	 *
	 * @access public
	 * @return array Settings
	 */

	public function row_template() {

		?>
		<script id="tmpl-nf-wpf-field-map-row" type="text/template">

		<div>
			<span class="dashicons dashicons-menu handle"></span>
		</div>
		<div>
			<label class="has-merge-tags">
				<input type="text" class="setting" value="{{{ data.form_field }}}" data-id="form_field">
				<span class="dashicons dashicons-list-view merge-tags"></span>
			</label>
			<span class="nf-option-error"></span>
		</div>
		<div>
			<label>
				<select data-id="field_map" list="field_map" class="setting">
				{{{ data.renderOptions( 'field_map', data.field_map )}}}
				</select>
			</label>
			<span class="nf-option-error"></span>
		</div>
		<div>
			<span class="dashicons dashicons-dismiss nf-delete"></span>
		</div>
		</script>


		<?php

	}


	/**
	 * Add admin action settings
	 *
	 * @access public
	 * @return array Settings
	 */

	public function get_settings() {

		$fields = array(
			array(
				'label' => __( 'Select a field', 'wp-fusion' ),
				'value' => false,
			),
		);

		foreach ( wp_fusion()->settings->get_crm_fields_flat() as $key => $label ) {

			$fields[] = array(
				'label' => $label,
				'value' => $key,
			);

		}

		$settings = array();

		$settings['apply_tags'] = array(
			'name'        => 'apply_tags',
			'type'        => 'textbox',
			'group'       => 'primary',
			'label'       => __( 'Apply Tags', 'wp-fusion' ),
			'width'       => 'full',
			'placeholder' => __( 'Comma-separated list of tag names or IDs', 'wp-fusion' ),
		);

		$settings['wpf_field_map'] = array(
			'name'     => 'wpf_field_map',
			'type'     => 'option-repeater',
			'label'    => sprintf( __( '%s Field Mapping', 'wp-fusion' ), wp_fusion()->crm->name ) . ' <a href="#" class="nf-add-new">' .
							__( 'Add New', 'wp-fusion' ) . '</a>',
			'width'    => 'full',
			'group'    => 'primary',
			'tmpl_row' => 'tmpl-nf-wpf-field-map-row',
			'value'    => array(),
			'columns'  => array(
				'form_field' => array(
					'header'  => __( 'Form Field', 'wp-fusion' ),
					'default' => '',
				),
				'field_map'  => array(
					'header'  => sprintf( __( '%s Field', 'wp-fusion' ), wp_fusion()->crm->name ),
					'options' => $fields,
					'default' => '',
					// 'field_types' => array(
					// 'textbox',
					// ),
				),
			),
		);

		if ( class_exists( 'NF_ConditionalLogic' ) ) {
			$settings = array_merge( $settings, NF_ConditionalLogic::config( 'ActionSettings' ) );
		}

		return $settings;

	}

	/**
	 * Save
	 *
	 * @access  public
	 * @return  void
	 */

	public function save( $action_settings ) {

	}

	/**
	 * Process form sumbission
	 *
	 * @access  public
	 * @return  void
	 */

	public function process( $action_settings, $form_id, $data, $sub_id = false ) {
		$email_address = false;
		$update_data   = array();

		foreach ( $data['fields'] as $field ) {

			if ( false == $email_address && 'email' == $field['type'] && is_email( $field['value'] ) ) {
				$email_address = $field['value'];
				break;
			}
		}

		// New fields map

		if ( isset( $action_settings['wpf_field_map'] ) ) {

			foreach ( $action_settings['wpf_field_map'] as $id => $setting ) {

				$value = $setting['form_field'];

				// Get the type.
				// The form_field and field_map are stored in the $action_settings, but the field types are only stored in $data.

				$type = 'text';

				foreach ( $data['fields'] as $field ) {
					// Convert checkboxes to bool

					if ( 'checkbox' == $field['type'] && $value == $field['checked_value'] ) {
						$value = true;
						$type  = 'checkbox';
						break;
					} elseif ( 'checkbox' == $field['type'] && $value == $field['unchecked_value'] ) {
						$value = null;
						$type  = 'checkbox';
						break;
					}

					if ( false !== strpos( $setting['field_map'], 'add_tag_' ) ) {

						// Don't run the filter on dynamic tagging inputs.
						$update_data[ $setting['field_map'] ] = $value;
						continue;
					}

					// Implode arrays so they match in the next step

					if ( is_array( $field['value'] ) ) {
						$field['value'] = implode( ',', $field['value'] );
					}

					if ( $field['value'] === $value ) {

						if ( 'listmultiselect' == $field['type'] || 'listcheckbox' == $field['type'] ) {
							$type = 'multiselect';
						} else {
							$type = $field['type'];
						}

						break;

					}
				}

				if ( ! empty( $value ) || null === $value ) {

					$update_data[ $setting['field_map'] ] = apply_filters( 'wpf_format_field_value', $value, $type, $setting['field_map'] );

				}
			}
		}

		$apply_tags = array();

		if ( ! empty( $action_settings['apply_tags'] ) ) {

			$tags_exploded = explode( ',', $action_settings['apply_tags'] );

			foreach ( $tags_exploded as $tag ) {

				$tag_id = wp_fusion()->user->get_tag_id( $tag );

				if ( false === $tag_id ) {

					wpf_log( 'notice', wpf_get_current_user_id(), 'Unable to determine tag ID from tag with name <strong>' . $tag . '</strong>. Tag will not be applied.' );
					continue;

				}

				$apply_tags[] = $tag_id;
			}
		}

		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => $apply_tags,
			'integration_slug' => 'ninja_forms',
			'integration_name' => 'Ninja Forms',
			'form_id'          => $form_id,
			'form_title'       => $data['settings']['title'],
			'form_edit_link'   => admin_url( 'admin.php?page=ninja-forms&form_id=' . $form_id ),
		);

		$contact_id = WPF_Forms_Helper::process_form_data( $args );

		if ( ! is_wp_error( $contact_id ) && isset( $data['actions']['save'] ) ) {

			$submission_id = absint( $data['actions']['save']['sub_id'] );
			update_post_meta( $submission_id, '_wpf_complete', current_time( 'Y-m-d H:i:s' ) );
			update_post_meta( $submission_id, '_wpf_' . WPF_CONTACT_ID_META_KEY, $contact_id );

		}

	}


	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/


	/**
	 * Adds Ninja forms checkbox to available export options.
	 *
	 * @since  3.37.21
	 *
	 * @param  array $options The options.
	 * @return array  Options.
	 */
	public function export_options( $options ) {

		$options['ninja_forms'] = array(
			'label'         => 'Ninja Forms entries',
			'process_again' => true,
			'title'         => 'Entries',
			'tooltip'       => 'Find Ninja Forms entries that have not been successfully processed by WP Fusion and syncs them to ' . wp_fusion()->crm->name . ' based on their configured feeds.',
		);

		return $options;

	}


	/**
	 * Gets total list of subs to be processed
	 *
	 * @since  3.37.21
	 *
	 * @return array Submission IDs.
	 */
	public function batch_init() {

		$all_subs = array();

		$forms = Ninja_Forms()->form()->get_forms();

		if ( empty( $forms ) ) {
			return $all_subs;
		}

		$form_ids = array();

		foreach ( $forms as $form ) {

			$form_id = $form->get_id();

			// Check if the form has wpfusion tags
			$actions = Ninja_Forms()->form( $form_id )->get_actions();

			foreach ( $actions as $action ) {
				$action_settings = $action->get_settings();
			}

			if ( ! isset( $action_settings['apply_tags'] ) || empty( $action_settings['apply_tags'] ) ) {
				continue;
			}

			$form_ids[] = $form_id;

		}

		if ( empty( $form_ids ) ) {
			return $all_subs;
		}

		$args = array(
			'post_type'              => 'nf_sub',
			'posts_per_page'         => 1000,
			'update_post_meta_cache' => false,
			'fields'                 => 'ids',
			'update_post_term_cache' => false,
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'     => '_form_id',
					'value'   => $form_ids,
					'compare' => 'IN',
				),
			),
		);

		if ( ! empty( $args['skip_processed'] ) ) {

			$args['meta_query'][] = array(
				'key'     => '_wpf_complete',
				'compare' => 'NOT EXISTS',
			);
		}

		$all_subs = get_posts( $args );

		return $all_subs;
	}


	/**
	 * Process submissions one at a time.
	 *
	 * @since 3.37.21
	 *
	 * @param int $sub_id The submission ID.
	 */
	public function batch_step( $sub_id ) {

		// Get form id
		$sub     = Ninja_Forms()->form()->get_sub( $sub_id );
		$form_id = $sub->get_form_id();

		// Check email address & Get data
		$field_values = $sub->get_field_values();
		$models       = Ninja_Forms()->form( $form_id )->get_fields();

		// Get action settings
		$actions = Ninja_Forms()->form( $form_id )->get_actions();

		foreach ( $actions as $action ) {

			$action_settings = $action->get_settings();

			if ( isset( $action_settings['wpf_field_map'] ) ) {
				break;
			}
		}

		$data = array(
			'settings' => Ninja_Forms()->form( $form_id )->get_settings(),
			'actions'  => array(
				'save' => array(
					'sub_id' => $sub_id,
				),
			),
		);

		foreach ( $models as $model ) {
			$settings = $model->get_settings();

			// Put values into the settings
			$key = $settings['key'];
			if ( isset( $field_values[ $key ] ) ) {
				$settings['value'] = $field_values[ $key ];
			}

			if ( isset( $action_settings['wpf_field_map'] ) ) {

				// Put values into the actions
				foreach ( $action_settings['wpf_field_map'] as $k => $action_field ) {
					if ( strpos( $action_field['form_field'], '{field:' . $key . '}' ) !== false ) {
						$action_settings['wpf_field_map'][ $k ]['form_field'] = $field_values[ $key ];
					}
				}
			}

			$data['fields'][] = $settings;
		}

		// Process the data
		$this->process( $action_settings, $form_id, $data, $sub_id );

	}


}

Ninja_Forms()->actions['wpfusion'] = new WPF_Ninja_Forms();
