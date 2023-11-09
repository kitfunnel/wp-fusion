<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Piotnet Forms integration
 *
 * @since 3.37.4
 */

class WPF_Piotnet_Forms extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'pionet-forms';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Pionet Forms';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/lead-generation/piotnet-forms/';

	/**
	 * Gets things started.
	 */
	public function init() {

		$this->name = 'Piotnet Forms';

		add_action( 'piotnetforms/form_builder/new_record', array( $this, 'process_entry' ) );

		add_action( 'add_meta_boxes_piotnetforms', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_piotnetforms', array( $this, 'save_meta_box' ) );
		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );

	}

	/**
	 * Sync data to CRM on form submission.
	 *
	 * @since  3.37.4
	 *
	 * @param  array $fields The fields submitted with the entry.
	 * @return string The contact ID created by the entry.
	 */
	public function process_entry( $fields ) {

		$form_id  = absint( $_POST['post_id'] );
		$settings = get_post_meta( $form_id, 'wpf_settings', true );

		if ( empty( $settings ) ) {
			return; // Nothing to be done
		}

		$email_address = false;
		$update_data   = array();

		foreach ( $fields as $field ) {

			$key = $field['name'];

			if ( ! isset( $settings['field_map'][ $key ] ) || empty( $settings['field_map'][ $key ]['crm_field'] ) ) {
				continue;
			}

			$value     = $field['value'];
			$type      = $settings['field_map'][ $key ]['type'];
			$crm_field = $settings['field_map'][ $key ]['crm_field'];

			if ( false !== strpos( $crm_field, 'add_tag_' ) ) {

				// Don't run the filter on dynamic tagging inputs.
				$update_data[ $crm_field ] = $value;
				continue;

			}

			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
				$type  = 'multiselect';
			}

			if ( false === $email_address && is_email( $value ) ) {
				$email_address = $value;
			}

			$value = apply_filters( 'wpf_format_field_value', $value, $type, $crm_field );

			if ( ! empty( $value ) || 0 === $value || '0' === $value ) {

				// Don't sync empty values unless they're actually the number 0

				$update_data[ $crm_field ] = $value;

			}
		}

		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => empty( $settings['apply_tags'] ) ? array() : $settings['apply_tags'],
			'integration_slug' => $this->slug,
			'integration_name' => $this->name,
			'form_id'          => $form_id,
			'form_title'       => get_the_title( $form_id ),
			'form_edit_link'   => admin_url( 'post.php?post=' . $form_id . '&action=edit' ),
		);

		$contact_id = WPF_Forms_Helper::process_form_data( $args );

		return $contact_id; // "the senseless ways action sometimes succeeds..."

	}

	/**
	 * Don't show the access control meta box on forms in the admin.
	 *
	 * @since  3.37.4
	 *
	 * @param  array $post_types The post types.
	 * @return array The post types.
	 */
	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['piotnetforms'] );

		return $post_types;

	}

	/**
	 * Adds WPF meta box.
	 *
	 * @since 3.37.4
	 */
	public function add_meta_box() {

		add_meta_box( 'wpf-meta', __( 'WP Fusion', 'wp-fusion' ), array( $this, 'meta_box_callback' ), 'piotnetforms', 'normal', 'high' );

	}

	/**
	 * Displays meta box content.
	 *
	 * @since 3.37.4
	 *
	 * @param WP_Post $post   The post being edited.
	 * @return mixed HTML output.
	 */

	public function meta_box_callback( $post ) {

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wpf_meta_box_piotnet_forms', 'wpf_piotnet_forms_nonce' );

		$settings = array(
			'field_map'  => array(),
			'apply_tags' => array(),
		);

		$settings = wp_parse_args( get_post_meta( $post->ID, 'wpf_settings', true ), $settings );

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row">' . __( 'Field Mapping', 'wp-fusion' ) . '</label>';

		echo '<td>';

		/*
		// Form field mapping
		*/

		$form_fields = json_decode( get_post_meta( $post->ID, '_piotnetforms_data', true ), true );

		if ( empty( $form_fields['widgets'] ) ) {
			_e( 'Please add some fields to your form using the Piotnet form editor before configuring field mapping.', 'wp-fusion' );
		}

		// Field mapping table

		echo '<table><tbody>';

		foreach ( (array) $form_fields['widgets'] as $id => $widget ) {

			if ( 'field' !== $widget['type'] ) {
				continue;
			}

			if ( ! isset( $settings['field_map'][ $id ] ) ) {

				// Set defaults to prevent PHP notices.

				$settings['field_map'][ $id ] = array(
					'type'      => 'text',
					'crm_field' => false,
				);

			}

			echo '<tr>';

			// Field label

			echo '<th scope="row">' . $widget['settings']['field_label'] . '</th>';

			echo '<td>';

			// CRM field select

			echo '<input name="wpf_settings[field_map][' . $id . '][type]" type="hidden" value="' . $widget['settings']['field_type'] . '" />';

			wpf_render_crm_field_select( $settings['field_map'][ $id ]['crm_field'], 'wpf_settings[field_map]', $id );

			echo '</td></tr>';

		}

		echo '</tbody></table>';

		echo '<span class="description">' . sprintf( __( 'For each field on the form, select a corresponding custom field in %s.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';

		echo '</td>';
		echo '</tr>';

		/*
		// Apply tags
		*/

		echo '<th scope="row"><label for="wpf_apply_tags">' . __( 'Apply Tags', 'wp-fusion' ) . '</label></th>';

		echo '<td>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags'],
				'meta_name' => 'wpf_settings[apply_tags]',
			)
		);
		echo '<span class="description">' . sprintf( __( 'Select tags to be applied in %s when this form is submitted.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';

		echo '</td>';
		echo '</tr>';

		echo '</tbody></table>';

	}

	/**
	 * Save WPF settings.
	 *
	 * @since 3.37.4
	 *
	 * @param int $post_id The post ID being saved.
	 */
	public function save_meta_box( $post_id ) {

		// Check if our nonce is set.

		if ( ! isset( $_POST['wpf_piotnet_forms_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.

		if ( ! wp_verify_nonce( $_POST['wpf_piotnet_forms_nonce'], 'wpf_meta_box_piotnet_forms' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Save it.

		if ( ! empty( $_POST['wpf_settings'] ) ) {
			update_post_meta( $post_id, 'wpf_settings', $_POST['wpf_settings'] );
		} else {
			delete_post_meta( $post_id, 'wpf_settings' );
		}

	}


}

new WPF_Piotnet_Forms();
