<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function wpf_register_formidable_actions( $actions ) {

	$actions['wpfusion'] = 'WPF_Formidable_Forms';
	return $actions;

}

add_filter( 'frm_registered_form_actions', 'wpf_register_formidable_actions' );


// Add this class to the list of integrations (not ideal but the only way we could get it to work with the FrmFormAction framework)
wp_fusion()->integrations->{'formidable-forms'} = (object) array(
	'slug' => 'formidable-forms',
	'name' => 'Formidable Forms',
);

class WPF_Formidable_Forms extends FrmFormAction {

	/**
	 * Get things started
	 *
	 * @access public
	 * @return void
	 */

	function __construct() {

		$action_ops = array(
			'classes'  => 'wpf_frm_icon frm-inverse',
			'limit'    => 99,
			'active'   => true,
			'priority' => 25,
			'event'    => array( 'create', 'update' ),
			'tooltip'  => sprintf( __( 'Add to %s', 'wp-fusion' ), wp_fusion()->crm->name ),
			'color'    => 'var(--primary-hover)',
		);

		$this->FrmFormAction( 'wpfusion', 'WP Fusion', $action_ops );

		// Settings
		add_filter( 'frm_add_form_settings_section', array( $this, 'add_settings_tab' ), 10, 2 );
		add_filter( 'frm_form_options_before_update', array( $this, 'save_form_settings' ), 20, 2 );

		// Send entry data
		add_action( 'frm_trigger_wpfusion_create_action', array( $this, 'after_action_triggered' ), 10, 3 );
		add_action( 'frm_trigger_wpfusion_update_action', array( $this, 'after_action_triggered' ), 10, 3 );

		// User profile updates
		add_action( 'frmreg_after_create_user', array( $this, 'after_create_user' ), 10, 2 );
		add_action( 'frm_trigger_register_action', array( $this, 'after_update_user' ), 20, 3 ); // 20 so it runs after FrmRegUserController::register_user

		// Deprecated
		add_action( 'frm_after_create_entry', array( $this, 'after_entry_created' ), 30, 2 );

		// Registration feeds

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

	}

	/**
	 * Get defaults
	 *
	 * @access  public
	 * @return  array Defaults
	 */

	public function get_defaults() {
		return array(
			'contact_fields' => array(),
			'apply_tags'     => array(),
		);
	}

	/**
	 * Display action settings row
	 *
	 * @access  public
	 * @return  array Sections
	 */

	public function form( $form_action, $args = array() ) {

		extract( $args );
		$action_control = $this;

		$settings = $form_action->post_content;

		$defaults = array(
			'contact_fields' => array(),
			'apply_tags'     => array(),
		);

		$settings = array_merge( $defaults, $settings );

		$form_fields = FrmField::getAll( 'fi.form_id=' . (int) $args['form']->id . " and fi.type not in ('break', 'divider', 'html', 'captcha', 'form')", 'field_order' );

		?>
		<h3 style="padding-top: 20px;"><?php _e( 'Field Mapping', 'wp-fusion' ); ?>
			<span class="frm_help frm_icon_font frm_tooltip_icon"
				  title="For each field in your form, select a corresponding field in <?php echo wp_fusion()->crm->name; ?> to save the entry data."</span>
		</h3>

		<p>Map form fields to <?php echo wp_fusion()->crm->name; ?> fields:</p>

		<table class="form-table settings-field-map-table wpf-field-map">

			<?php
			foreach ( $form_fields as $field ) :

				if ( ! isset( $settings['contact_fields'][ $field->id ] ) ) {
					$settings['contact_fields'][ $field->id ] = array( 'crm_field' => false );
				}

				?>

				<tr>
					<td width="100px">
						<label><?php echo FrmAppHelper::truncate( $field->name, 40 ); ?></label>
					</td>
					<td width="15px">&raquo;</td>
					<td>
						<?php

						wpf_render_crm_field_select( $settings['contact_fields'][ $field->id ]['crm_field'], $action_control->get_field_name( 'contact_fields' ), $field->id );
						?>
					</td>
				</tr>

			<?php endforeach; ?>

		</table>

		<h3 style="padding-top: 20px;"><?php _e( 'Apply Tags', 'formidable' ); ?>
			<span class="frm_help frm_icon_font frm_tooltip_icon"
				  title="These tags will be applied to the contact record when this form is submitted."</span>
		</h3>
		<table class="form-table">
			<tr>
				<td>
					<?php
					wpf_render_tag_multiselect(
						array(
							'setting'   => $settings['apply_tags'],
							'meta_name' => $action_control->get_field_name( 'apply_tags' ),
						)
					);
					?>
				</td>
			</tr>
		</table>

		<?php

	}

	/**
	 * Prepare form data (action method)
	 *
	 * @access  public
	 * @return  void
	 */

	public function after_action_triggered( $action, $entry, $form ) {

		$settings = $action->post_content;

		if ( empty( $settings ) || empty( $settings['contact_fields'] ) ) {
			return;
		}

		$form_fields = FrmField::getAll( 'fi.form_id=' . (int) $form->id . " and fi.type not in ('break', 'divider', 'html', 'captcha', 'form')", 'field_order' );

		// Keep track of field types for wpf_format_field_value
		$types = array();

		// Do some pre-processing on the submitted data for checkboxes and toggles

		foreach ( $form_fields as $field ) {

			if ( $field->type == 'toggle' && ! isset( $entry->metas[ $field->id ] ) ) {

				if ( empty( $field->field_options['toggle_off'] ) ) {
					$entry->metas[ $field->id ] = null;
				} else {
					$entry->metas[ $field->id ] = $field->field_options['toggle_off'];
				}
			} elseif ( $field->type == 'checkbox' && ! isset( $entry->metas[ $field->id ] ) ) {

				$entry->metas[ $field->id ] = null;

			} elseif ( $field->type == 'checkbox' && count( $field->options ) == 1 ) {

				$entry->metas[ $field->id ] = true;

			} elseif ( $field->type == 'file' && isset( $entry->metas[ $field->id ] ) ) {

				if ( is_array( $entry->metas[ $field->id ] ) ) {

					foreach ( $entry->metas[ $field->id ] as $i => $img_id ) {

						$entry->metas[ $field->id ][ $i ] = wp_get_attachment_url( $img_id );

					}
				} else {
					$entry->metas[ $field->id ] = wp_get_attachment_url( $entry->metas[ $field->id ] );
				}
			}

			if ( 'checkbox' == $field->type && count( $field->options ) > 1 ) {
				$field->type = 'multiselect';
			}

			$types[ $field->id ] = $field->type;

		}

		$update_data   = array();
		$email_address = false;

		foreach ( $settings['contact_fields'] as $field_id => $value ) {

			if ( empty( $value['crm_field'] ) || ! isset( $entry->metas[ $field_id ] ) || ( empty( $entry->metas[ $field_id ] ) && ! is_null( $entry->metas[ $field_id ] ) ) ) {
				continue;
			}

			// Don't run the filter on dynamic tagging inputs.
			if ( false !== strpos( $value['crm_field'], 'add_tag_' ) ) {
				$update_data[ $value['crm_field'] ] = $entry->metas[ $field_id ];
				continue;
			}

			$update_data[ $value['crm_field'] ] = apply_filters( 'wpf_format_field_value', $entry->metas[ $field_id ], $types[ $field_id ], $value['crm_field'] );

			if ( $email_address == false && ! is_array( $entry->metas[ $field_id ] ) && is_email( $entry->metas[ $field_id ] ) ) {
				$email_address = $entry->metas[ $field_id ];
			}

			// Array handling
			if ( is_array( $update_data[ $value['crm_field'] ] ) ) {
				$update_data[ $value['crm_field'] ] = implode( ', ', $update_data[ $value['crm_field'] ] );
			}
		}

		if ( ! isset( $settings['apply_tags'] ) ) {
			$settings['apply_tags'] = array();
		}

		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => $settings['apply_tags'],
			'integration_slug' => 'formidable',
			'integration_name' => 'Formidable Forms',
			'form_id'          => $form->id,
			'form_title'       => $form->name,
			'form_edit_link'   => admin_url( 'admin.php?page=formidable&frm_action=edit&id=' . $form->id ),
		);

		$contact_id = WPF_Forms_Helper::process_form_data( $args );

	}

	/**
	 * Sync additional meta after user registration
	 *
	 * @access  public
	 * @return  void
	 */

	public function after_create_user( $user_id, $args ) {

		$password_key = $args['settings']['reg_password'];

		$umeta_keys = array();

		if ( ! empty( $args['settings']['reg_usermeta'] ) ) {

			foreach ( $args['settings']['reg_usermeta'] as $meta ) {
				$umeta_keys[ $meta['field_id'] ] = $meta['meta_name'];
			}
		}

		$update_data = array();

		if ( isset( $args['entry']->metas[ $password_key ] ) ) {
			$update_data['user_pass'] = $args['entry']->metas[ $password_key ];
		}

		foreach ( $umeta_keys as $key => $value ) {

			if ( isset( $args['entry']->metas[ $key ] ) ) {

				$update_data[ $value ] = $args['entry']->metas[ $key ];

			}
		}

		wp_fusion()->user->push_user_meta( $user_id, $update_data );

	}

	/**
	 * Sync additional meta after user profile update
	 *
	 * @access  public
	 * @return  void
	 */

	public function after_update_user( $action, $entry, $form ) {

		if ( ! class_exists( 'FrmRegEntryHelper' ) ) {
			return;
		}

		$user_id_field = FrmRegEntryHelper::get_user_id_field_for_form( $entry->form_id );

		if ( $user_id_field && isset( $entry->metas[ $user_id_field ] ) && $entry->metas[ $user_id_field ] ) {

			wp_fusion()->user->push_user_meta( $entry->metas[ $user_id_field ] );

		}

	}

	//
	// Deprecated
	//

	/**
	 * Add new tab to form settings page (old method)
	 *
	 * @access  public
	 * @return  array Sections
	 */

	public function add_settings_tab( $sections, $values ) {

		$settings = get_option( 'frm_wpf_settings_' . $values['id'] );

		if ( ! empty( $settings ) ) {

			$sections[] = array(
				'name'     => 'WP Fusion',
				'anchor'   => 'wp_fusion',
				'function' => 'display_settings_tab',
				'class'    => 'WPF_Formidable_Forms',
			);

		}

		return $sections;

	}


	/**
	 * Display settings tab (old method)
	 *
	 * @access  public
	 * @return  mixed
	 */

	public static function display_settings_tab( $values ) {

		$form_fields = FrmField::getAll( 'fi.form_id=' . (int) $values['id'] . " and fi.type not in ('break', 'divider', 'html', 'captcha', 'form')", 'field_order' );
		$settings    = maybe_unserialize( get_option( 'frm_wpf_settings_' . $values['id'] ) );

		$defaults = array(
			'contact_fields' => array(),
			'apply_tags'     => array(),
		);

		$settings = array_merge( $defaults, $settings );

		?>
		<h3 class="frm_first_h3"><?php _e( 'Field Mapping', 'wp-fusion' ); ?>
			<span class="frm_help frm_icon_font frm_tooltip_icon"
				  title="For each field in your form, select a corresponding field in <?php echo wp_fusion()->crm->name; ?> to save the entry data."</span>
		</h3>

		<p>Map form fields to <?php echo wp_fusion()->crm->name; ?> fields:</p>

		<table class="form-table settings-field-map-table wpf-field-map">

			<?php
			foreach ( $form_fields as $field ) :

				if ( ! isset( $settings['contact_fields'][ $field->id ] ) ) {
					$settings['contact_fields'][ $field->id ] = array( 'crm_field' => false );
				}

				?>

				<tr>
					<td width="100px">
						<label><?php echo FrmAppHelper::truncate( $field->name, 40 ); ?></label>
					</td>
					<td width="15px">&raquo;</td>
					<td>
						<?php wpf_render_crm_field_select( $settings['contact_fields'][ $field->id ]['crm_field'], 'wpf_settings[contact_fields]', $field->id ); ?>
					</td>
				</tr>

			<?php endforeach; ?>

		</table>

		<h3><?php _e( 'Apply Tags', 'wp-fusion' ); ?>
			<span class="frm_help frm_icon_font frm_tooltip_icon"
				  title="These tags will be applied to the contact record when this form is submitted."></span>
		</h3>
		<table class="form-table">
			<tr>
				<td>
					<?php
					wpf_render_tag_multiselect(
						array(
							'setting'   => $settings['apply_tags'],
							'meta_name' => 'wpf_settings',
							'field_id'  => 'apply_tags',
						)
					);
					?>
				</td>
			</tr>
		</table>
		<br/><br/>

		<?php

	}


	/**
	 * Save form settings (old method)
	 *
	 * @access  public
	 * @return  array Options
	 */

	public function save_form_settings( $options, $values ) {

		if ( isset( $values['wpf_settings'] ) ) {
			$new_values = maybe_serialize( $values['wpf_settings'] );
			update_option( 'frm_wpf_settings_' . $values['id'], $new_values );
		}

		return $options;

	}

	/**
	 * Prepare form data (older method)
	 *
	 * @access  public
	 * @return  void
	 */

	public function after_entry_created( $entry_id, $form_id ) {

		$settings = maybe_unserialize( get_option( 'frm_wpf_settings_' . $form_id ) );

		if ( empty( $settings ) ) {
			return;
		}

		$update_data   = array();
		$email_address = false;

		foreach ( $settings['contact_fields'] as $field_id => $value ) {

			if ( empty( $value['crm_field'] ) || empty( $_POST['item_meta'][ $field_id ] ) ) {
				continue;
			}

			$update_data[ $value['crm_field'] ] = $_POST['item_meta'][ $field_id ];

			if ( $email_address == false && is_email( $_POST['item_meta'][ $field_id ] ) ) {
				$email_address = $_POST['item_meta'][ $field_id ];
			}

			// Array handling
			if ( is_array( $update_data[ $value['crm_field'] ] ) ) {
				$update_data[ $value['crm_field'] ] = implode( ', ', $update_data[ $value['crm_field'] ] );
			}
		}

		if ( ! isset( $settings['apply_tags'] ) ) {
			$settings['apply_tags'] = array();
		}

		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => $settings['apply_tags'],
			'integration_slug' => 'formidable',
			'integration_name' => 'Formidable Forms',
			'form_id'          => null,
			'form_title'       => null,
			'form_edit_link'   => null,
		);

		$contact_id = WPF_Forms_Helper::process_form_data( $args );

	}


	/**
	 * Adds Formidable field group to meta fields list
	 *
	 * @access public
	 * @return array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['formidable'] = array(
			'title'  => 'Formidable Forms Registration',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Adds Formidable meta fields to WPF contact fields list
	 *
	 * @access public
	 * @return array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$where = array(
			'post_type'    => FrmFormActionsController::$action_post_type,
			'post_excerpt' => 'register',
			'post_status'  => 'publish',
		);

		$actions = FrmDb::get_results( 'posts', $where, 'ID' );

		if ( empty( $actions ) ) {
			return $meta_fields;
		}

		foreach ( $actions as $action ) {

			$settings = json_decode( get_the_content( null, false, $action->ID ) );

			if ( ! empty( $settings ) && ! empty( $settings->reg_usermeta ) ) {

				foreach ( $settings->reg_usermeta as $field ) {

					$meta_fields[ $field->meta_name ] = array(
						'label' => ucwords( str_replace( '_', ' ', $field->meta_name ) ),
						'type'  => 'text',
						'group' => 'formidable',
					);
				}
			}
		}

		return $meta_fields;

	}

}
