<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Forminator integration hooks class.
 *
 * @since 3.42.0
 *
 * @link https://wpfusion.com/documentation/lead-generation/forminator/
 */

class Forminator_Addon_WP_Fusion_Form_Hooks extends Forminator_Addon_Form_Hooks_Abstract {

	/**
	 * Addon instance are auto available form abstract
	 * Its added here for development purpose,
	 * Auto-complete will resolve addon directly to `WP_Fusion` instance instead of the abstract
	 * And its public properties can be exposed
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @var Forminator_Addon_WP_Fusion
	 */
	protected $addon;

	/**
	 * Form Settings Instance
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @var Forminator_Addon_WP_Fusion_Form_Settings | null
	 */
	protected $form_settings_instance;

	/**
	 * Forminator_Addon_WP_Fusion_Form_Hooks constructor.
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
		$this->_submit_form_error_message = esc_html__( 'WP Fusion failed to process submitted data. Please check your form and try again', 'wp-fusion' );
	}

	/**
	 * Save status of request sent and received for each connected WP Fusion Connection
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @since 3.42.0 Add $form_entry_fields
	 *
	 * @param array $submitted_data
	 * @param array $form_entry_fields
	 *
	 * @return array
	 */
	public function add_entry_fields( $submitted_data, $form_entry_fields = array() ) {

		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 * Filter WPFusion submitted form data to be processed
		 *
		 * @since 3.42.0
		 *
		 * @param array                                         $submitted_data
		 * @param int                                           $form_id                current Form ID.
		 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WPFusion Addon Form Settings instance.
		 */
		$submitted_data = apply_filters(
			'forminator_addon_wp_fusion_form_submitted_data',
			$submitted_data,
			$form_id,
			$form_settings_instance
		);

		forminator_addon_maybe_log( __METHOD__, $submitted_data );

		$addon_setting_values = $this->form_settings_instance->get_form_settings_values();
		$form_settings        = $this->form_settings_instance->get_form_settings();

		$data = array();

		/**
		 * Fires before sending contact sync to WP Fusion
		 *
		 * @since 3.42.0
		 *
		 * @param int                                           $form_id                current Form ID.
		 * @param array                                         $submitted_data
		 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WPFusion Addon Form Settings instance.
		 */
		do_action( 'forminator_addon_wp_fusion_before_contact_sync', $form_id, $submitted_data, $form_settings_instance );

		foreach ( $addon_setting_values as $key => $addon_setting_value ) {
			// save it on entry field, with name `status-$MULTI_ID`, and value is the return result on sending data to WP Fusion.
			if ( $form_settings_instance->is_multi_form_settings_complete( $key ) ) {
				// exec only on completed connection.
				$data[] = array(
					'name'  => 'status-' . $key,
					'value' => $this->get_status_on_contact_sync( $key, $submitted_data, $addon_setting_value, $form_settings, $form_entry_fields ),
				);
			}
		}

		$entry_fields = $data;
		/**
		 * Filter WPFusion entry fields to be saved to entry model
		 *
		 * @since 3.42.0
		 *
		 * @param array                                         $entry_fields
		 * @param int                                           $form_id                current Form ID.
		 * @param array                                         $submitted_data
		 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WPFusion Addon Form Settings instance.
		 */
		$data = apply_filters(
			'forminator_addon_wp_fusion_entry_fields',
			$entry_fields,
			$form_id,
			$submitted_data,
			$form_settings_instance
		);

		return $data;
	}

	/**
	 * Get status on contact sync to WP Fusion
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @since 3.42.0 Add $form_entry_fields
	 *
	 * @param       $connection_id
	 * @param       $submitted_data
	 * @param       $connection_settings
	 * @param       $form_settings
	 * @param array               $form_entry_fields
	 *
	 * @return array `is_sent` true means its success send data to WPFusion, false otherwise
	 */
	private function get_status_on_contact_sync( $connection_id, $submitted_data, $connection_settings, $form_settings, $form_entry_fields ) {
		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		// check required fields
		try {
			$args = array();

			if ( wpf_get_option( 'available_lists' ) && ! isset( $connection_settings['list_id'] ) ) {
				throw new Forminator_Addon_WP_Fusion_Exception( esc_html__( 'List ID not properly set up.', 'wp-fusion' ) );
			}

			$fields_map = $connection_settings['fields_map'];

			$email_element_id = $connection_settings['fields_map']['email'];
			if ( ! isset( $submitted_data[ $email_element_id ] ) || empty( $submitted_data[ $email_element_id ] ) ) {
				throw new Forminator_Addon_WP_Fusion_Exception( esc_html__( 'Email on element not found or not filled on submitted data.', 'wp-fusion' ) );
			}
			$email         = $submitted_data[ $email_element_id ];
			$email         = strtolower( trim( $email ) );
			$args['email'] = $email;

			// processed.
			unset( $fields_map['email'] );

			// process rest extra fields if available.
			foreach ( $fields_map as $field_id => $element_id ) {
				if ( ! empty( $element_id ) && isset( $submitted_data[ $element_id ] ) ) {
					$args[ $field_id ] = $submitted_data[ $element_id ];
				}
			}

			$tags = array();
			// process tags.
			if ( isset( $connection_settings['tags'] ) && is_array( $connection_settings['tags'] ) ) {
				$tags = array();
				foreach ( $connection_settings['tags'] as $tag ) {
					$tags[] = $tag;
				}
			}

			/**
			 * Filter arguments to passed on to Contact Sync WP Fusion API
			 *
			 * @since 3.42.0
			 *
			 * @param array                                         $args
			 * @param int                                           $form_id                Current Form id.
			 * @param string                                        $connection_id          ID of current connection.
			 * @param array                                         $submitted_data
			 * @param array                                         $connection_settings    current connection setting, contains options of like `name`, `list_id` etc.
			 * @param array                                         $form_settings          Displayed Form settings.
			 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WPFusion Addon Form Settings instance.
			 */
			$args = apply_filters(
				'forminator_addon_wp_fusion_contact_sync_args',
				$args,
				$form_id,
				$connection_id,
				$submitted_data,
				$connection_settings,
				$form_settings,
				$form_settings_instance
			);

			$form_args = array(
				'email_address'    => $args['email'],
				'update_data'      => $args,
				'apply_tags'       => $tags,
				'add_only'         => false,
				'integration_slug' => 'wp-fusion',
				'integration_name' => 'Forminator',
				'form_id'          => $form_id,
				'form_title'       => $form_settings['form_name'],
				'form_edit_link'   => admin_url( 'admin.php?page=forminator-cform-wizard&id=' . $form_id ),
			);

			$contact_id = WPF_Forms_Helper::process_form_data( $form_args );

			if ( is_wp_error( $contact_id ) ) {
				return array(
					'is_sent'         => false,
					'connection_name' => $connection_settings['name'],
				);
			}

			forminator_addon_maybe_log( __METHOD__, 'Success Send Data' );

			return array(
				'is_sent'         => true,
				'connection_name' => $connection_settings['name'],
				'description'     => esc_html__( 'Successfully send data to WP Fusion', 'wp-fusion' ),
			);

		} catch ( Forminator_Addon_WP_Fusion_Exception $e ) {
			forminator_addon_maybe_log( __METHOD__, 'Failed to Send to WP Fusion' );

			return array(
				'is_sent'         => false,
				'description'     => $e->getMessage(),
				'connection_name' => $connection_settings['name'],
			);
		}
	}

	/**
	 * It wil add new row on entry table of submission page, with couple of subentries
	 * subentries included are defined in @see Forminator_Addon_WP_Fusion_Form_Hooks::get_additional_entry_item()
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param Forminator_Form_Entry_Model $entry_model
	 * @param                             $addon_meta_data
	 *
	 * @return array
	 */
	public function on_render_entry( Forminator_Form_Entry_Model $entry_model, $addon_meta_data ) {

		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 *
		 * Filter WP Fusion metadata that previously saved on db to be processed
		 *
		 * @since 3.42.0
		 *
		 * @param array                                         $addon_meta_data
		 * @param int                                           $form_id                current Form ID.
		 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WPFusion Addon Form Settings instance.
		 */
		$addon_meta_data = apply_filters(
			'forminator_addon_wp_fusion_metadata',
			$addon_meta_data,
			$form_id,
			$form_settings_instance
		);

		$addon_meta_datas = $addon_meta_data;
		if ( ! isset( $addon_meta_data[0] ) || ! is_array( $addon_meta_data[0] ) ) {
			return array();
		}

		return $this->on_render_entry_multi_connection( $addon_meta_datas );
	}

	/**
	 * Loop through addon meta data on multiple WP Fusion(s)
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param $addon_meta_datas
	 *
	 * @return array
	 */
	private function on_render_entry_multi_connection( $addon_meta_datas ) {
		$additional_entry_item = array();
		foreach ( $addon_meta_datas as $addon_meta_data ) {
			$additional_entry_item[] = $this->get_additional_entry_item( $addon_meta_data );
		}

		return $additional_entry_item;
	}

	/**
	 * Format additional entry item as label and value arrays
	 *
	 * - Integration Name : its defined by user when they adding WP_Fusion integration on their form
	 * - Sent To WP_Fusion : will be Yes/No value, that indicates whether sending data to WP_Fusion was successful
	 * - Info : Text that are generated by addon when building and sending data to WP_Fusion @see Forminator_Addon_WP_Fusion_Form_Hooks::add_entry_fields()
	 * - Below subentries will be added if full log enabled, @see Forminator_Addon_WP_Fusion::is_show_full_log() @see FORMINATOR_ADDON_WP_FUSION_SHOW_FULL_LOG
	 *      - API URL : URL that wes requested when sending data to WP_Fusion
	 *      - Data sent to WP_Fusion : encoded body request that was sent
	 *      - Data received from WP_Fusion : json encoded body response that was received
	 *
	 * @param $addon_meta_data
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return array
	 */
	private function get_additional_entry_item( $addon_meta_data ) {

		if ( ! isset( $addon_meta_data['value'] ) || ! is_array( $addon_meta_data['value'] ) ) {
			return array();
		}
		$status                = $addon_meta_data['value'];
		$additional_entry_item = array(
			'label' => esc_html__( 'WP Fusion Integration', 'wp-fusion' ),
			'value' => '',
		);

		$sub_entries = array();
		if ( isset( $status['connection_name'] ) ) {
			$sub_entries[] = array(
				'label' => esc_html__( 'Integration Name', 'wp-fusion' ),
				'value' => $status['connection_name'],
			);
		}

		if ( isset( $status['is_sent'] ) ) {
			$is_sent       = true === $status['is_sent'] ? esc_html__( 'Yes', 'wp-fusion' ) : esc_html__( 'No', 'wp-fusion' );
			$sub_entries[] = array(
				'label' => esc_html__( 'Sent To WP Fusion', 'wp-fusion' ),
				'value' => $is_sent,
			);
		}

		if ( isset( $status['description'] ) ) {
			$sub_entries[] = array(
				'label' => esc_html__( 'Info', 'wp-fusion' ),
				'value' => $status['description'],
			);
		}

		if ( Forminator_Addon_WP_Fusion::is_show_full_log() ) {
			// too long to be added on entry data enable this with `define('FORMINATOR_ADDON_WP_FUSION_SHOW_FULL_LOG', true)`.
			if ( isset( $status['url_request'] ) ) {
				$sub_entries[] = array(
					'label' => esc_html__( 'API URL', 'wp-fusion' ),
					'value' => $status['url_request'],
				);
			}

			if ( isset( $status['data_sent'] ) ) {
				$sub_entries[] = array(
					'label' => esc_html__( 'Data sent to WP Fusion', 'wp-fusion' ),
					'value' => '<pre class="sui-code-snippet">' . wp_json_encode( $status['data_sent'], JSON_PRETTY_PRINT ) . '</pre>',
				);
			}

			if ( isset( $status['data_received'] ) ) {
				$sub_entries[] = array(
					'label' => esc_html__( 'Data received from WP Fusion', 'wp-fusion' ),
					'value' => '<pre class="sui-code-snippet">' . wp_json_encode( $status['data_received'], JSON_PRETTY_PRINT ) . '</pre>',
				);
			}
		}

		$additional_entry_item['sub_entries'] = $sub_entries;

		// return single array.
		return $additional_entry_item;
	}

	/**
	 * WP_Fusion will add a column on the title/header row
	 * its called `WP Fusion Info` which can be translated on forminator lang
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 * @return array
	 */
	public function on_export_render_title_row() {

		$export_headers = array(
			'info' => esc_html__( 'WP Fusion Info', 'wp-fusion' ),
		);

		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 * Filter WP_Fusion headers on export file
		 *
		 * @since 3.42.0
		 *
		 * @param array                                         $export_headers         headers to be displayed on export file.
		 * @param int                                           $form_id                current Form ID.
		 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WP_Fusion Form Settings instance.
		 */
		$export_headers = apply_filters(
			'forminator_addon_wp_fusion_export_headers',
			$export_headers,
			$form_id,
			$form_settings_instance
		);

		return $export_headers;
	}

	/**
	 * WP_Fusion will add a column that give user information whether sending data to WP_Fusion successfully or not
	 * It will only add one column even its multiple connection, every connection will be separated by comma
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param Forminator_Form_Entry_Model $entry_model
	 * @param                             $addon_meta_data
	 *
	 * @return array
	 */
	public function on_export_render_entry( Forminator_Form_Entry_Model $entry_model, $addon_meta_data ) {

		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 *
		 * Filter WP_Fusion metadata that previously saved on db to be processed
		 *
		 * @since 3.42.0
		 *
		 * @param array                                         $addon_meta_data
		 * @param int                                           $form_id                current Form ID.
		 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WP_Fusion Form Settings instance.
		 */
		$addon_meta_data = apply_filters(
			'forminator_addon_wp_fusion_metadata',
			$addon_meta_data,
			$form_id,
			$form_settings_instance
		);

		$export_columns = array(
			'info' => $this->get_from_addon_meta_data( $addon_meta_data, 'description', '' ),
		);

		/**
		 * Filter WP_Fusion columns to be displayed on export submissions
		 *
		 * @since 3.42.0
		 *
		 * @param array                                         $export_columns         column to be exported.
		 * @param int                                           $form_id                current Form ID.
		 * @param Forminator_Form_Entry_Model                   $entry_model            Form Entry Model.
		 * @param array                                         $addon_meta_data        meta data saved by addon on entry fields.
		 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WP_Fusion Form Settings instance.
		 */
		$export_columns = apply_filters(
			'forminator_addon_wp_fusion_export_columns',
			$export_columns,
			$form_id,
			$entry_model,
			$addon_meta_data,
			$form_settings_instance
		);

		return $export_columns;
	}

	/**
	 * Get Addon meta data, will be recursive if meta data is multiple because of multiple connection added
	 *
	 * @since 3.42.0 WP_Fusion Addon
	 *
	 * @param        $addon_meta_data
	 * @param        $key
	 * @param string          $default
	 *
	 * @return string
	 */
	private function get_from_addon_meta_data( $addon_meta_data, $key, $default = '' ) {
		$addon_meta_datas = $addon_meta_data;
		if ( ! isset( $addon_meta_data[0] ) || ! is_array( $addon_meta_data[0] ) ) {
			return $default;
		}

		$addon_meta_data = $addon_meta_data[0];

		// make sure its `status`, because we only add this.
		if ( 'status' !== $addon_meta_data['name'] ) {
			if ( stripos( $addon_meta_data['name'], 'status-' ) === 0 ) {
				$meta_data = array();
				foreach ( $addon_meta_datas as $addon_meta_data ) {
					// make it like single value so it will be processed like single meta data.
					$addon_meta_data['name'] = 'status';

					// add it on an array for next recursive process.
					$meta_data[] = $this->get_from_addon_meta_data( array( $addon_meta_data ), $key, $default );
				}

				return implode( ', ', $meta_data );
			}

			return $default;

		}

		if ( ! isset( $addon_meta_data['value'] ) || ! is_array( $addon_meta_data['value'] ) ) {
			return $default;
		}
		$status = $addon_meta_data['value'];
		if ( isset( $status[ $key ] ) ) {
			$connection_name = '';
			if ( 'connection_name' !== $key ) {
				if ( isset( $status['connection_name'] ) ) {
					$connection_name = '[' . $status['connection_name'] . '] ';
				}
			}

			return $connection_name . $status[ $key ];
		}

		return $default;
	}

	/**
	 * It will delete contact on WP Fusion list
	 *
	 * @since 3.42.0 WPFusion Addon
	 *
	 * @param Forminator_Form_Entry_Model $entry_model
	 * @param  array                       $addon_meta_data
	 *
	 * @return bool
	 */
	public function on_before_delete_entry( Forminator_Form_Entry_Model $entry_model, $addon_meta_data ) {
		// attach hook first.
		$form_id                = $this->form_id;
		$form_settings_instance = $this->form_settings_instance;

		/**
		 *
		 * Filter WPFusion addon metadata that previously saved on db to be processed
		 *
		 * @since 3.42.0
		 *
		 * @param array                                         $addon_meta_data
		 * @param int                                           $form_id                current Form ID.
		 * @param Forminator_Form_Entry_Model                   $entry_model            Forminator Entry Model.
		 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WP_Fusion Form Settings instance.
		 */
		$addon_meta_data = apply_filters(
			'forminator_addon_wp_fusion_metadata',
			$addon_meta_data,
			$form_id,
			$entry_model,
			$form_settings_instance
		);

		/**
		 * Fires when WP_Fusion connected form delete a submission
		 *
		 * @since 3.42.0
		 *
		 * @param int                                           $form_id                current Form ID.
		 * @param Forminator_Form_Entry_Model                   $entry_model            Forminator Entry Model.
		 * @param array                                         $addon_meta_data        addon meta data.
		 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WP_Fusion Form Settings instance.
		 */
		do_action(
			'forminator_addon_wp_fusion_on_before_delete_submission',
			$form_id,
			$entry_model,
			$addon_meta_data,
			$form_settings_instance
		);

		if ( ! Forminator_Addon_WP_Fusion::is_enable_delete_contact() ) {
			// its disabled, go for it!
			return true;
		}
		$ac_api = null;
		try {
			$subscriber_ids_to_delete = array();

			if ( is_array( $addon_meta_data ) ) {
				foreach ( $addon_meta_data as $addon_meta_datum ) {

					/** data received reference
					 *
					 * data_received: {
					 *      subscriber_id: 1,
					 *      sendlast_should: 0,
					 *      sendlast_did: 0,
					 *      result_code: 1,
					 *      result_message: Contact added,
					 *      result_output: json
					 * }
					 */

					if ( isset( $addon_meta_datum['value'] ) && is_array( $addon_meta_datum['value'] ) ) {
						$addon_meta_datum_value = $addon_meta_datum['value'];
						if ( isset( $addon_meta_datum_value['is_sent'] ) && $addon_meta_datum_value['is_sent'] ) {
							if ( isset( $addon_meta_datum_value['data_received'] ) && is_object( $addon_meta_datum_value['data_received'] ) ) {
								$addon_meta_datum_received = $addon_meta_datum_value['data_received'];
								if ( isset( $addon_meta_datum_received->subscriber_id ) && ! empty( $addon_meta_datum_received->subscriber_id ) ) {
									$subscriber_ids_to_delete [] = $addon_meta_datum_received->subscriber_id;
								}
							}
						}
					}
				}
			}

			/**
			 * Filter subscriber ids to delete
			 *
			 * @since 3.42.0
			 *
			 * @param array                                         $subscriber_ids_to_delete
			 * @param int                                           $form_id                current Form ID.
			 * @param array                                         $addon_meta_data        addon meta data.
			 * @param Forminator_Addon_WP_Fusion_Form_Settings $form_settings_instance WP_Fusion Form Settings instance.
			 */
			$subscriber_ids_to_delete = apply_filters(
				'forminator_addon_wp_fusion_subscriber_ids_to_delete',
				$subscriber_ids_to_delete,
				$form_id,
				$addon_meta_data,
				$form_settings_instance
			);

			if ( ! empty( $subscriber_ids_to_delete ) ) {
				$ac_api = $this->addon->get_api();
				foreach ( $subscriber_ids_to_delete as $subscriber_id_to_delete ) {
					$ac_api->contact_delete(
						array(
							'id' => $subscriber_id_to_delete,
						)
					);
				}
			}

			return true;

		} catch ( Forminator_Addon_WP_Fusion_Exception $e ) {
			// handle all internal addon exceptions with `Forminator_Addon_WP_Fusion_Exception`.

			// use wp_error, for future usage it can be returned to page entries.
			$wp_error = new WP_Error( 'forminator_addon_wp_fusion_delete_contact', $e->getMessage() );
			// handle this in addon by self, since page entries cant handle error messages on delete yet.
			wp_die(
				esc_html( $wp_error->get_error_message() ),
				esc_html( $this->addon->get_title() ),
				array(
					'response'  => 200,
					'back_link' => true,
				)
			);

			return false;
		}
	}
}
