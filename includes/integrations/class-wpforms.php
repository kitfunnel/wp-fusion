<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_WPForms extends WPForms_Provider {

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		wp_fusion()->integrations->wpforms = $this;

		$this->version  = WP_FUSION_VERSION;
		$this->name     = 'WP Fusion';
		$this->slug     = 'wp-fusion';
		$this->priority = 50;
		$this->icon     = WPF_DIR_URL . 'assets/img/logo.png';

		add_action( 'wp_ajax_wpforms_save_form', array( $this, 'save_form' ), 5 );

		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );
		add_action( 'wpforms_user_registered', array( $this, 'user_registered' ), 10, 4 ); // deprecated in WPForms User Registration 2.0.0.
		add_action( 'wpforms_user_registration_process_registration_process_completed_after', array( $this, 'user_registered' ), 10, 4 ); // WPForms User Registration 2.0.0+.

	}


	/**
	 * With WPForms User Registration, stop WP Fusion from syncing the new user
	 * to the CRM until after the usermeta has been saved.
	 *
	 * @since  3.37.31
	 *
	 * @param  array $post_data The registration POST data.
	 * @param  int   $user_id   The user ID.
	 * @return array The POST data.
	 */
	public function user_register( $post_data, $user_id ) {

		if ( isset( $post_data['wpforms'] ) ) {
			return null;
		}

		return $post_data;

	}

	/**
	 * After WPForms User Registration has completed, run WP Fusion's
	 * user_register() function.
	 *
	 * @since 3.37.31
	 *
	 * @param int   $user_id   The user ID.
	 * @param array $fields    The form fields.
	 * @param array $form_data The registration form data.
	 * @param array $userdata  The userdata for the new user.
	 */
	public function user_registered( $user_id, $fields, $form_data, $userdata ) {

		if ( doing_action( 'wpforms_user_registration_process_registration_process_completed_after' ) && did_action( 'wpforms_user_registered' ) ) {
			// Don't do this twice on WPForms User Registration < 2.0.0.
			return;
		}

		wp_fusion()->user->user_register( $user_id, $userdata );

	}

	/**
	 * Process and submit entry to CRM
	 *
	 * @param array $fields WPForms form array of fields.
	 * @param array $entry
	 * @param array $form_data
	 * @param int   $entry_id
	 */

	public function process_entry( $fields, $entry, $form_data, $entry_id = 0 ) {

		// Only run if this form has connections for this provider.
		if ( empty( $form_data['providers'][ $this->slug ] ) ) {
			return;
		}

		// Fire for each connection. ------------------------------------------//

		foreach ( $form_data['providers'][ $this->slug ] as $connection ) {

			// Check for conditional logic.
			$pass = $this->process_conditionals( $fields, $entry, $form_data, $connection );

			if ( ! $pass ) {

				wpforms_log(
					__( 'WP Fusion feed stopped by conditional logic.', 'wp-fusion' ),
					$fields,
					array(
						'type'    => array( 'provider', 'conditional_logic' ),
						'parent'  => $entry_id,
						'form_id' => $form_data['id'],
					)
				);

				continue;

			}

			$email_address = false;
			$update_data   = array();

			// Format fields,

			foreach ( $fields as $i => $field ) {

				if ( false === $email_address && 'email' === $field['type'] && is_email( $field['value'] ) ) {

					$email_address = $field['value'];

				} elseif ( 'name' === $field['type'] ) {

					$field_first          = $field;
					$field_first['id']    = $field['id'] . '-first';
					$field_first['value'] = $field['first'];

					$fields[] = $field_first;

					$field_last          = $field;
					$field_last['id']    = $field['id'] . '-last';
					$field_last['value'] = $field['last'];

					$fields[] = $field_last;

				} elseif ( 'checkbox' === $field['type'] || ( 'select' === $field['type'] && false !== strpos( $field['value_raw'], PHP_EOL ) ) ) {

					$fields[ $i ]['value'] = explode( PHP_EOL, $field['value_raw'] );

				}
			}

			// Map fields.

			foreach ( $fields as $field ) {

				if ( isset( $connection['fields'][ $field['id'] ] ) && ! empty( $connection['fields'][ $field['id'] ]['crm_field'] ) ) {

					$crm_field = $connection['fields'][ $field['id'] ]['crm_field'];

					if ( 'checkbox' === $field['type'] ) {
						$field['type'] = 'checkboxes';
					}

					if ( false !== strpos( $crm_field, 'add_tag_' ) ) {

						// Don't run the filter on dynamic tagging inputs.
						$update_data[ $crm_field ] = $field['value'];
						continue;

					}

					$update_data[ $crm_field ] = apply_filters( 'wpf_format_field_value', $field['value'], $field['type'], $crm_field );

				}
			}

			if ( ! isset( $connection['options']['apply_tags'] ) ) {
				$connection['options']['apply_tags'] = array();
			}

			$args = array(
				'email_address'    => $email_address,
				'update_data'      => $update_data,
				'apply_tags'       => $connection['options']['apply_tags'],
				'integration_slug' => 'wpforms',
				'integration_name' => 'WPForms',
				'form_id'          => $form_data['id'],
				'form_title'       => $form_data['settings']['form_title'],
				'form_edit_link'   => admin_url( 'admin.php?page=wpforms-builder&view=providers&form_id=' . $form_data['id'] ),
			);

			$contact_id = WPF_Forms_Helper::process_form_data( $args );

		}

	}


	/**
	 * Fix WPForms issue with saving WPF tags multiselect data
	 *
	 * @return void
	 */

	public function save_form() {

		$form_post = json_decode( stripslashes( $_POST['data'] ) );

		$i = 0;

		foreach ( $form_post as $n => $post_item ) {

			if ( strpos( $post_item->name, 'apply_tags' ) !== false ) {
				$form_post[ $n ]->name = str_replace( '[]', '[' . $i . ']', $post_item->name );
				$i++;
			}
		}

		$_POST['data'] = addslashes( wp_json_encode( $form_post ) );

	}


	/**
	 * Add integration
	 *
	 * @return void
	 */

	public function output_auth() {

		$providers = get_option( 'wpforms_providers', array() );

		$providers[ $this->slug ]['wp-fusion'] = array(
			'label' => 'wp-fusion',
			'date'  => time(),
		);

		update_option( 'wpforms_providers', $providers );

	}


	/**
	 * Provider account select HTML.
	 *
	 * @param string $connection_id Unique connection ID.
	 * @param array  $connection Array of connection data.
	 *
	 * @return string
	 */

	public function output_accounts( $connection_id = '', $connection = array() ) {

		return '<input type="hidden" name="providers[' . $this->slug . '][' . $connection_id . '][account_id]" value="wp-fusion" />';

	}


	/**
	 * Provider account lists HTML.
	 *
	 * @param string $connection_id
	 * @param array  $connection
	 *
	 * @return WP_Error|string
	 */
	public function output_lists( $connection_id = '', $connection = array() ) {

		return '';

	}


	/**
	 * Provider account list fields HTML.
	 *
	 * @param string $connection_id
	 * @param array  $connection
	 * @param mixed  $form
	 *
	 * @return WP_Error|string
	 */

	public function output_fields( $connection_id = '', $connection = array(), $form = '' ) {

		if ( empty( $connection_id ) || empty( $form ) ) {
			return '';
		}

		if ( ! isset( $connection['fields'] ) ) {
			$connection['fields'] = array();
		}

		$form_fields = $this->get_form_fields( $form );

		// Create separate fields from Firs / Last name field

		foreach ( (array) $form_fields as $i => $form_field ) {

			if ( isset( $form_field['format'] ) && $form_field['format'] == 'first-last' ) {

				$form_field_first          = $form_field;
				$form_field_first['id']    = $form_field['id'] . '-first';
				$form_field_first['label'] = $form_field['label'] . ' - First';

				array_splice( $form_fields, $i + 1, 0, array( $form_field_first ) );

				$form_field_last          = $form_field;
				$form_field_last['id']    = $form_field['id'] . '-last';
				$form_field_last['label'] = $form_field['label'] . ' - Last';

				array_splice( $form_fields, $i + 2, 0, array( $form_field_last ) );

			}
		}

		$output = '<div class="wpforms-provider-fields wpforms-connection-block">';

		$output .= '<h4>Fields</h4>';

		$output .= '<table>';

		$output .= sprintf( '<thead><tr><th>%s</th><th>%s</th></thead>', esc_html__( 'Available Form Fields', 'wpforms' ), esc_html__( 'CRM Field', 'wpforms' ) );

		$output .= '<tbody>';

		if ( ! empty( $form_fields ) ) {

			foreach ( $form_fields as $form_field ) {

				$output .= '<tr>';

				$output .= '<td>';

				$output .= esc_html( $form_field['label'] );

				$output .= '<td>';

				if ( ! isset( $connection['fields'][ $form_field['id'] ] ) ) {
					$connection['fields'][ $form_field['id'] ] = array( 'crm_field' => false );
				}

				$setting = $connection['fields'][ $form_field['id'] ]['crm_field'];

				// CRM field

				$output .= '<select class="select4-crm-field" name="providers[' . $this->slug . '][' . $connection_id . '][fields][' . $form_field['id'] . '][crm_field]" data-placeholder="Select a field">';

					$output .= '<option></option>';

					$crm_fields = wpf_get_option( 'crm_fields' );

				if ( ! empty( $crm_fields ) ) {

					foreach ( $crm_fields as $group_header => $fields ) {

						// For CRMs with separate custom and built in fields
						if ( is_array( $fields ) ) {

							$output .= '<optgroup label="' . $group_header . '">';

							foreach ( $crm_fields[ $group_header ] as $field => $label ) {

								if ( is_array( $label ) ) {
									$label = $label['label'];
								}

								$output .= '<option ' . selected( esc_attr( $setting ), $field, false ) . ' value="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</option>';
							}

							$output .= '</optgroup>';

						} else {

							$field = $group_header;
							$label = $fields;

							$output .= '<option ' . selected( esc_attr( $setting ), $field, false ) . ' value="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</option>';

						}
					}
				}

					// Save custom added fields to the DB
				if ( in_array( 'add_fields', wp_fusion()->crm->supports ) ) {

					$field_check = array();

					// Collapse fields if they're grouped
					if ( isset( $crm_fields['Custom Fields'] ) ) {

						foreach ( $crm_fields as $field_group ) {

							foreach ( $field_group as $field => $label ) {
								$field_check[ $field ] = $label;
							}
						}
					} else {

						$field_check = $crm_fields;

					}

					// Check to see if new custom fields have been added
					if ( ! empty( $setting ) && ! isset( $field_check[ $setting ] ) ) {

						$output .= '<option value="' . esc_attr( $setting ) . '" selected="selected">' . esc_html( $setting ) . '</option>';

						if ( isset( $crm_fields['Custom Fields'] ) ) {

							$crm_fields['Custom Fields'][ $setting ] = $setting;

						} else {
							$crm_fields[ $setting ] = $setting;
						}

						wp_fusion()->settings->set( 'crm_fields', $crm_fields );

						// Save safe crm field to DB
						$contact_fields                               = wpf_get_option( 'contact_fields' );
						$contact_fields[ $field_sub_id ]['crm_field'] = $setting;
						wp_fusion()->settings->set( 'contact_fields', $contact_fields );

					}
				}

				if ( in_array( 'add_tags', wp_fusion()->crm->supports ) ) {

					$output .= '<optgroup label="Tagging">';

						$output .= '<option ' . selected( esc_attr( $setting ), 'add_tag_' . $form_field['id'] ) . ' value="add_tag_' . $form_field['id'] . '">+ Create tag(s) from value</option>';

					$output .= '</optgroup>';

				}

					$output .= '</select>';

				$output .= '</td>';

				$output .= '</tr>';

			}
		}

		$output .= '</tbody>';

		$output .= '</table>';

		$output .= '</div>';

		return $output;

	}


	/**
	 * Output options
	 *
	 * @param string $connection_id
	 * @param array  $connection
	 *
	 * @return string
	 */

	public function output_options( $connection_id = '', $connection = array() ) {

		if ( empty( $connection_id ) ) {
			return '';
		}

		$output = '<div class="wpforms-provider-options wpforms-connection-block">';

		$output .= '<h4>' . __( 'Apply Tags', 'wp-fusion' ) . '</h4>';

		if ( empty( $connection['options'] ) ) {
			$connection['options'] = array( 'apply_tags' => array() );
		}

		if ( ! isset( $connection['options']['apply_tags'] ) ) {
			$connection['options']['apply_tags'] = array();
		}

		$args = array(
			'setting'   => $connection['options']['apply_tags'],
			'meta_name' => "providers[{$this->slug}][{$connection_id}][options][apply_tags]",
			'return'    => true,
		);

		$output .= wpf_render_tag_multiselect( $args );

		$output .= '<span class="description">' . sprintf( __( 'The selected tags will be applied in %s when the form is submitted.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';

		$output .= '</div>';

		return $output;

	}


}

new WPF_WPForms();
