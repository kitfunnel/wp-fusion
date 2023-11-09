<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Contact_Form_7 extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'cf7';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Contact form 7';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/lead-generation/contact-form-7/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpcf7_editor_panels', array( $this, 'add_panel' ) );
		add_action( 'wpcf7_after_save', array( $this, 'save_form' ) );
		add_action( 'wpcf7_mail_sent', array( $this, 'send_data' ), 5 );

	}

	/**
	 * Adds panel to CF7 settings page
	 *
	 * @access  public
	 * @return  array Panels
	 */

	public function add_panel( $panels ) {

		$panels['wp-fusion-tab'] = array(
			'title'    => 'WP Fusion',
			'callback' => array( $this, 'add_form' ),
		);

		return $panels;

	}


	/**
	 * Adds form content to panel
	 *
	 * @access  public
	 * @return  mixed Panel content
	 */

	public function add_form( $info ) {

		wp_nonce_field( 'cf7_wpf_nonce', 'cf7_wpf_nonce' );

		$post_id = $info->id();
		$content = $info->prop( 'form' );
		$inputs  = array();

		// Get inputs from saved form config
		preg_match_all( '/\[.*\]/', $content, $matches );

		foreach ( $matches[0] as $input ) {
			$input    = substr( $input, 1, - 1 );
			$input    = str_replace( '*', '', $input );
			$elements = explode( ' ', $input );

			if ( isset( $elements[1] ) && $elements[1] != '"Send"' ) {
				$inputs[ $elements[1] ] = $elements[0];
			}
		}

		$defaults = array(
			'tags'     => array(),
			'add_only' => false,
		);

		$settings = get_post_meta( $post_id, 'cf7_wpf_settings', true );
		$settings = wp_parse_args( $settings, $defaults );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		echo '<h2>' . wp_fusion()->crm->name . ' Settings</h2>';

		echo '<fieldset><legend>' . sprintf( __( 'For each field in the form, select a field to sync with in %s.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</legend>';

		echo '<table id="wpf-cf7-table">';
		echo '<tbody id="wp-fusion-inputs">';
		foreach ( $inputs as $name => $type ) {

			$capital_name = str_replace( '-', '  ', $name );

			if ( ! isset( $settings[ $name ] ) ) {
				$settings[ $name ] = array( 'crm_field' => '' );
			}

			if ( ! isset( $settings[ $name ]['crm_field'] ) ) {
				$settings[ $name ]['crm_field'] = '';
			}

			if ( 'checkbox' === $type ) {
				$type = 'multiselect';
			}

			echo '<tr id="input-row">';
			echo '<td><label> ' . ucwords( $capital_name ) . ' <label></td>';
			echo '<td><label for ="cf7_wpf_settings"> ' . $type . ' <label></td>';
			echo '<td class="crm-field">';
			wpf_render_crm_field_select( $settings[ $name ]['crm_field'], 'cf7_wpf_settings', $name );
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';

		echo '<p class="description"><label for="tags">' . sprintf( __( 'Select tags to be applied in %s when this form is submitted.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</label><br />';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['tags'],
				'meta_name' => 'cf7_wpf_settings',
				'field_id'  => 'tags',
			)
		);

		echo '</p>';

		echo '<br /><label for="wpf-add-only">';

		echo '<input type="checkbox" id="wpf-add-only" name="cf7_wpf_settings[add_only]" ' . checked( $settings['add_only'], 1, false ) . ' class="toggle-form-table" value="1">';

		_e( 'Add Only', 'wp-fusion' );

		echo '</label>';

		echo '<p class="description">' . __( 'Only add new contacts, don\'t update existing ones.', 'wp-fusion' ) . '</p>';

		echo '</fieldset>';

	}

	/**
	 * Save WPF settings fields
	 *
	 * @access public
	 *
	 * @param unknown $contact_form
	 */

	public function save_form( $contact_form ) {

		if ( empty( $_POST ) || ! isset( $_POST['cf7_wpf_nonce'] ) ) {
			return;
		}

		$post_id = $contact_form->id();

		update_post_meta( $post_id, 'cf7_wpf_settings', $_POST['cf7_wpf_settings'] );

	}

	/**
	 * Send data to CRM on form submission
	 *
	 * @access  public
	 * @return  array Classes
	 */

	public function send_data( $contact_form ) {

		$contact_form_id = $contact_form->id();
		$submission      = WPCF7_Submission::get_instance();
		$posted_data     = $submission->get_posted_data();

		$wpf_settings = get_post_meta( $contact_form_id, 'cf7_wpf_settings', true );

		if ( empty( $wpf_settings ) ) {
			return;
		}

		$email_address = false;
		$update_data   = array();

		foreach ( $posted_data as $key => $value ) {

			if ( ! isset( $wpf_settings[ $key ] ) || empty( $wpf_settings[ $key ]['crm_field'] ) || empty( $value ) ) {
				continue;
			}

			if ( false !== strpos( $wpf_settings[ $key ]['crm_field'], 'add_tag_' ) ) {

				// Don't run the filter on dynamic tagging inputs.
				$update_data[ $wpf_settings[ $key ]['crm_field'] ] = $value;
				continue;
			}

			$type = 'text';

			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
				$type  = 'multiselect';
			}

			if ( is_email( $value ) ) {
				$email_address = $value;
			}

			$value = apply_filters( 'wpf_format_field_value', $value, $type, $wpf_settings[ $key ]['crm_field'] );

			$update_data[ $wpf_settings[ $key ]['crm_field'] ] = $value;

		}

		if ( ! isset( $wpf_settings['tags'] ) ) {
			$wpf_settings['tags'] = array();
		}

		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => $wpf_settings['tags'],
			'add_only'         => ! empty( $wpf_settings['add_only'] ) ? true : false,
			'integration_slug' => 'cf7',
			'integration_name' => 'Contact Form 7',
			'form_id'          => $contact_form_id,
			'form_title'       => get_the_title( $contact_form_id ),
			'form_edit_link'   => admin_url( 'admin.php?page=wpcf7&post=' . $contact_form_id . '&action=edit' ),
		);

		$contact_id = WPF_Forms_Helper::process_form_data( $args );

	}

}

new WPF_Contact_Form_7();
