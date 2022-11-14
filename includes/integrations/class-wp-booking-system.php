<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WP Booking System integration.
 *
 * @since 3.40.5
 *
 * @link https://wpfusion.com/documentation/events/wp-booking-system/
 */
class WPF_WP_Booking_System extends WPF_Integrations_Base {

	/**
	 * This identifies the integration internally and makes it available at
	 * wp_fusion()->integrations->{'my-plugin-slug'}
	 *
	 * @since 3.40.5
	 * @var  string
	 */

	public $slug = 'wp-booking-system';

	/**
	 * The human-readable name of the integration.
	 *
	 * @since 3.40.5
	 * @var  string
	 */

	public $name = 'WP Booking System';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.5
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/wp-booking-system/';

	/**
	 * Get things started.
	 *
	 * @since 3.40.5
	 */
	public function init() {

		// Add the sub-tab to the Form Options page.
		add_action( 'wpbs_submenu_page_edit_form_sub_tabs', array( $this, 'form_settings_panel_tab' ), 70, 1 );

		// Add the form to the sub-tab.
		add_action( 'wpbs_submenu_page_edit_form_tabs_form_options_wpf_field_mapping', array( $this, 'form_settings_panel_content' ), 10, 1 );

		// Save form meta fields.
		add_action( 'wpbs_edit_forms_meta_fields', array( $this, 'save_form_settings' ), 10, 1 );

		// Process the form submission.
		add_action( 'wpbs_submit_form_after', array( $this, 'form_submission' ), 10, 5 );

	}

	/**
	 * Saves form settings.
	 *
	 * @since  3.40.5
	 *
	 * @param  array $meta_fields The meta fields.
	 * @return array The meta fields.
	 */
	public function save_form_settings( $meta_fields ) {

		$meta_fields['wpbs_wpf_fields_map'] = array(
			'translations' => false,
			'sanitization' => '_wpbs_array_esc_attr_text_field',
		);

		return $meta_fields;
	}

	/**
	 * Adds the form settings panel tab.
	 *
	 * @since  3.40.5
	 *
	 * @param  array $tabs   The tabs.
	 * @return array The tabs.
	 */
	public function form_settings_panel_tab( $tabs ) {

		$tabs['form-options']['wpf_field_mapping'] = __( 'WP Fusion', 'wp-fusion' );
		return $tabs;

	}

	/**
	 * Render the form settings panel tab content.
	 *
	 * @since 3.40.5
	 *
	 * @return mixed The form settings.
	 */
	public function form_settings_panel_content() {

		$form_id = absint( ! empty( $_GET['form_id'] ) ? $_GET['form_id'] : 0 );
		$form    = wpbs_get_form( $form_id );

		if ( is_null( $form ) ) {
			return;
		}

		$form_meta = wpbs_get_form_meta( $form_id );
		$form_data = $form->get( 'fields' );
		$wpf_data  = isset( $form_meta['wpbs_wpf_fields_map'][0] ) ? unserialize( $form_meta['wpbs_wpf_fields_map'][0] ) : array();
		?>

		<div class="wpbs-settings-field-wrapper wpbs-settings-field-inline wpbs-settings-field-heading wpbs-settings-field-large">
			<label class="wpbs-settings-field-label"><?php esc_html_e( 'Field Mapping', 'wp-fusion' ); ?></label>
			<div class="wpbs-settings-field-inner">&nbsp;</div>
		</div>

		<?php
		foreach ( $form_data as $field ) :
			if ( in_array( $field['type'], wpbs_get_excluded_fields( array( 'hidden' ) ) ) ) {
				continue;}
			?>
			<!-- Field -->
			<div class="wpbs-settings-field-wrapper wpbs-settings-field-inline wpbs-settings-field-large">

				<label class="wpbs-settings-field-label" for="<?php echo esc_attr( $field['id'] ); ?>">
					<?php echo esc_html( $field['values']['default']['label'] ); ?>
				</label>

				<div class="wpbs-settings-field-inner">
					<?php wpf_render_crm_field_select( ( isset( $wpf_data[ $field['id'] ] ) ? $wpf_data[ $field['id'] ]['crm_field'] : '' ), 'wpbs_wpf_fields_map', $field['id'] ); ?>
				</div>

			</div>
		<?php endforeach; ?>

		<div class="wpbs-settings-field-wrapper wpbs-settings-field-inline wpbs-settings-field-heading wpbs-settings-field-large">
			<label class="wpbs-settings-field-label"><?php esc_html_e( 'Options', 'wp-fusion' ); ?></label>
			<div class="wpbs-settings-field-inner">&nbsp;</div>
		</div>

		<!-- Apply Tags -->
		<div class="wpbs-settings-field-wrapper wpbs-settings-field-inline wpbs-settings-field-large">

			<label class="wpbs-settings-field-label">
				<?php esc_html_e( 'Apply tags', 'wp-fusion' ); ?>
				<?php echo wpbs_get_output_tooltip( sprintf( __( 'The selected tags will be applied to the contact in %s when the form is submitted.', 'wp-fusion' ), wp_fusion()->crm->name ) ); ?>

			</label>

			<div class="wpbs-settings-field-inner">

				<?php
				$args = array(
					'setting'   => ( isset( $wpf_data['apply_tags'] ) ? $wpf_data['apply_tags'] : '' ),
					'meta_name' => 'wpbs_wpf_fields_map[apply_tags]',
				);

				wpf_render_tag_multiselect( $args );
				?>
			</div>
		</div>

		<?php

	}

	/**
	 * Handles the form submission.
	 *
	 * @since 3.40.5
	 *
	 * @param int   $booking_id  The booking ID.
	 * @param array $post_data   The post data.
	 * @param array $form        The form.
	 * @param array $form_args   The form arguments.
	 * @param array $form_fields The form fields.
	 */
	public function form_submission( $booking_id, $post_data, $form, $form_args, $form_fields ) {

		$booking   = wpbs_get_booking( $booking_id );
		$form_data = $booking->get( 'fields' );
		$form_meta = wpbs_get_form_meta( $form->get( 'id' ) );
		$wpf_data  = isset( $form_meta['wpbs_wpf_fields_map'][0] ) ? unserialize( $form_meta['wpbs_wpf_fields_map'][0] ) : array();

		$update_data   = array(); // data to sync to the CRM.
		$email_address = false; // the primary email used for contact lookups.

		foreach ( $form_data as $field ) {

			if ( ! array_key_exists( $field['id'], $wpf_data ) ) {
				continue;
			}

			if ( empty( $field['user_value'] ) ) {
				continue;
			}

			if ( empty( $wpf_data[ $field['id'] ]['crm_field'] ) ) {
				continue;
			}

			/**
			 * Formats the value for the CRM based on the field type.
			 *
			 * @since 3.40.5
			 *
			 * @link  https://wpfusion.com/documentation/filters/wpf_format_field_value/
			 *
			 * @param mixed  $value     The field value.
			 * @param string $type      The field type.
			 * @param string $crm_field The field to sync the data to in the CRM.
			 */
			$value = apply_filters( 'wpf_format_field_value', $field['user_value'], $this->field_types( $field['type'] ), $wpf_data[ $field['id'] ]['crm_field'] );

			$update_data[ $wpf_data[ $field['id'] ]['crm_field'] ] = $value;

			// For determining the email address, we'll try to find a field
			// mapped to the main lookup field in the CRM, but if not we'll take
			// the first email address on the form.

			if ( 'email' === $field['type'] && false === $email_address ) {
				$email_address = $value;
			}
		}

		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => isset( $wpf_data['apply_tags'] ) ? $wpf_data['apply_tags'] : array(),
			'integration_slug' => $this->slug,
			'integration_name' => $this->name,
			'form_id'          => $form->get( 'id' ),
			'form_title'       => $form->get( 'name' ),
			'form_edit_link'   => admin_url( 'admin.php?page=wpbs-forms&subpage=edit-form&form_id=' . $form->get( 'id' ) ),
		);

		$contact_id = WPF_Forms_Helper::process_form_data( $args );

	}

	/**
	 * Gets the WPF field type from the WP Booking System field type.
	 *
	 * @since  3.40.5
	 *
	 * @param  string $type   The type.
	 * @return string The type.
	 */
	public function field_types( $type ) {

		switch ( $type ) {
			case 'textarea':
			case 'html':
				$field_type = 'textarea';
				break;
			case 'checkbox':
			case 'product_checkbox':
				$field_type = 'multiselect';
				break;
			default:
				$field_type = 'text';
		}

		return $field_type;
	}

}

new WPF_WP_Booking_System();
