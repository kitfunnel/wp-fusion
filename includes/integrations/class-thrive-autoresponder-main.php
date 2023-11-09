<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * WP Fusion's Thrive Leads / Autoresponder integration.
 *
 * @since 3.40.24
 */
class WPF_Thrive_Autoresponder_Main extends Thrive_Dash_List_Connection_Abstract {

	/**
	 * APi instance.
	 *
	 * @since 3.40.24
	 * @var strong
	 */
	private $api_instance;

	const API_KEY = 'wpfusion';

	/**
	 * Get autoresponder title.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public function get_title() {
		return sprintf( __( 'WP Fusion - %s', 'wp-fusion' ), wp_fusion()->crm->name );
	}

	/**
	 * Get autoresponder key.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public function get_key() {
		return static::API_KEY;
	}


	/**
	 * Constructor.
	 *
	 * @since 3.40.24
	 */
	public function __construct() {
		new WPF_Thrive_Autoresponder_API();
	}

	/**
	 * Thrive Leads breaks without this.
	 *
	 * @since 3.40.38
	 */
	// public function get_api_data( $params = [], $force = false ) {
	// 	return array(
	// 		'lists'             => $this->get_lists(),
	// 		'custom_fields'     => $this->get_custom_fields(),
	// 		'api_custom_fields' => $this->get_crm_fields( false ),
	// 	);
	// }


	/**
	 * Return API Instance.
	 *
	 * @since 3.40.24
	 * @return object
	 */
	public function get_api_instance() {
		if ( empty( $this->api_instance ) ) {
			try {
				$this->api_instance = new WPF_Thrive_Autoresponder_API();
			} catch ( \Exception $e ) {
				Utils::log_error( 'Error while instantiating the API! Error message: ' . $e->getMessage() );
			}
		}

		return $this->api_instance;
	}


	/**
	 * Get form args from posted data.
	 *
	 * @since 3.40.24
	 *
	 * @param array $data
	 * @return array
	 */
	private function wpf_get_form_args( $data ) {

		$email_address = false;
		$update_data   = array();

		// Email field.
		if ( ! empty( $data['email'] ) ) {
			$email_address = $data['email'];
		} else {

			foreach ( $data as $key => $value ) {
				if ( is_string( $value ) && is_email( $value ) ) {
					$email_address = $value;
					break;
				}
			}
		}

		// Format name to first, last.
		if ( ! empty( $data['name'] ) ) {

			$names                          = explode( ' ', $data['name'], 2 );
			$first_name_key                 = wpf_get_crm_field( 'first_name' );
			$update_data[ $first_name_key ] = $names[0];

			if ( isset( $names[1] ) ) {

				$last_name_key                 = wpf_get_crm_field( 'last_name' );
				$update_data[ $last_name_key ] = $names[1];

			}
		}

		// Map custom fields.

		if ( isset( $data['tve_mapping'] ) ) {

			$form_data = thrive_safe_unserialize( base64_decode( $data['tve_mapping'] ) );

			if ( ! empty( $form_data ) ) {
				foreach ( $form_data as $key => $value ) {
					if ( ! empty( $value['wpfusion'] ) ) {
						$update_data[ $value['wpfusion'] ] = $data[ $key ];
					}
				}
			}
		}

		// Make sure the email isn't messed with.
		$email_field                 = wpf_get_crm_field( 'user_email' );
		$update_data[ $email_field ] = $email_address;

		// Tags.
		$apply_tags = array();

		if ( ! empty( $data['wpfusion_tags'] ) ) {
			$apply_tags = $this->format_tags( explode( ',', $data['wpfusion_tags'] ) );
		}

		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => $apply_tags,
			'integration_slug' => 'thrive-leads',
			'integration_name' => 'Thrive Leads',
			'form_id'          => false,
			'form_title'       => false,
			'form_edit_link'   => false,
		);

		if ( isset( $data['thrive_leads'] ) ) {
			$args['form_id']        = $data['thrive_leads']['tl_data']['form_type_id'];
			$args['form_title']     = $data['thrive_leads']['tl_data']['form_name'];
			$args['form_edit_link'] = admin_url( 'post.php?action=architect&tve=true&post=' . $data['thrive_leads']['tl_data']['form_type_id'] . '&_key=' . $data['thrive_leads']['tl_data']['_key'] );
		}

		return $args;

	}

	/**
	 * @param string $list_identifier - the ID of the mailing list
	 * @param array  $data            - an array of what we want to send as subscriber data
	 * @param bool   $is_update
	 *
	 * @since 3.40.24
	 * @return bool
	 */
	public function add_subscriber( $list_identifier, $data, $is_update = false ) {

		$success = false;

		try {

			$args       = $this->wpf_get_form_args( $data );
			$contact_id = WPF_Forms_Helper::process_form_data( $args );

			$success = true;

		} catch ( \Exception $e ) {
			Utils::log_error( 'Error while adding/updating the subscriber! Error message: ' . $e->getMessage() );
		}

		return $success;
	}

	/**
	 * Check to see if it's connected.
	 *
	 * @since 3.40.24
	 * @return bool
	 */
	public function is_connected() {
		return wpf_get_option( 'connection_configured' );
	}

	/**
	 * Test connection.
	 *
	 * @since 3.40.24
	 * @return bool Whether or not the connection is configured.
	 */
	public function test_connection() {

		return wpf_get_option( 'connection_configured' );

	}

	/**
	 * Get CRM lists.
	 *
	 * @since 3.40.24
	 *
	 * @param bool $is_testing_connection Check if testing.
	 * @return array
	 */
	public function get_lists( $is_testing_connection = false ) {
		if ( ! $this->is_connected() ) {
			return array();
		}

		$formatted_lists = array();
		$lists           = wp_fusion()->settings->get( 'available_lists', array() );

		if ( ! empty( $lists ) ) {
			foreach ( $lists as $key => $val ) {
				$formatted_lists[] = array(
					'id'   => $key,
					'name' => $val,
				);
			}
		} else {
			$formatted_lists[] = array(
				'id'   => 'default',
				'name' => __( 'Default List', 'wp-fusion' ),
			);
		}

		return $formatted_lists;
	}

	/**
	 * Since custom fields are enabled, this is set to true.
	 *
	 * @since 3.40.24
	 * @return bool
	 */
	public function has_custom_fields() {
		return true;
	}

	/**
	 * Returns all the types of custom field mappings.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public function get_custom_fields_mapping() {
		return $this->get_formatted_custom_fields_for_mapping();
	}

	/**
	 * Get formatted custom fields for mapping.
	 *
	 * @since 3.41.0
	 * @return array
	 */
	public function get_formatted_custom_fields_for_mapping() {
		$fields = $this->get_api_custom_fields();

		if ( empty( $fields ) ) {
			return array();
		}

		foreach ( $fields as $key => $value ) {
			if ( strtolower( $value['name'] ) === 'email' ) {
				$value['id']        = 'email';
				$value['mandatory'] = true;
			} else {
				$value['mandatory'] = false;
			}

			$fields[ $key ] = $value;
		}

		return $fields;
	}

	/**
	 * Get crm lists.
	 *
	 * @since 3.40.54
	 * @return array
	 */
	public function _get_lists() {
		return $this->get_lists();
	}

	/**
	 * Output the setup form HTML (not applicable with WP Fusion)
	 *
	 * @since 3.41.0
	 *
	 * @return string The HTML.
	 */
	public function output_setup_form() {
		return sprintf( esc_html__( 'You can configure WP Fusion\'s integration with %1$s in the %2$sWP Fusion settings%3$s.', 'wp-fusion' ), esc_html( wp_fusion()->crm->name ), '<a href="' . admin_url( 'options-general.php?page=wpf-settings#setup' ) . '">', '</a>' );
	}

	/**
	 * Marks us as connected.
	 *
	 * @since 3.41.0
	 *
	 * @return string Success message.
	 */
	public function read_credentials() {

		return $this->success( 'Connection configured' );

	}

	/**
	 * Get Custom fields mapper
	 *
	 * @since 3.40.54
	 * @return array
	 */
	public function get_default_fields_mapper() {
		return $this->get_formatted_custom_fields_for_mapping();
	}

	/**
	 * Retrieves all the used custom fields. Currently it returns all the inter-group (global) ones.
	 *
	 * @param array $params  which may contain `list_id`
	 * @param bool  $force
	 * @param bool  $get_all whether to get lists with their custom fields
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public function get_api_custom_fields( $params = array(), $force = false, $get_all = true ) {
		return $this->get_crm_fields();
	}

	/**
	 * Get CRM default and custom fields.
	 *
	 * @since 3.41.0
	 *
	 * @param bool $email
	 * @param bool $placeholder
	 *
	 * @return array
	 */
	public function get_crm_fields( $email = true, $placeholder = false ) {

		$crm_fields = wp_fusion()->settings->get_crm_fields_flat();

		$api_fields = array();

		if ( empty( $crm_fields ) ) {
			return array();
		}

		foreach ( $crm_fields as $key => $value ) {
			// Don't incldue email field.
			if ( ! $email && strtolower( $value ) === 'email' ) {
				continue;
			}

			if ( $placeholder ) {
				$api_fields[] = array(
					'id'          => $key,
					'placeholder' => $value,
				);
			} else {
				$api_fields[] = array(
					'id'    => $key,
					'name'  => $value,
					'label' => $value,
					'type'  => $key,
				);
			}
		}

		return $api_fields;
	}


	/**
	 * Builds custom fields mapping for automations.
	 * Called from Thrive Automator when the custom fields are processed.
	 *
	 * @param $automation_data Automation data.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public function build_automation_custom_fields( $automation_data ) {
		return $this->get_api_custom_fields();
	}

	/**
	 * Enables the tag feature inside Thrive Architect & Automator.
	 *
	 * @since 3.40.24
	 * @return bool
	 */
	public function has_tags() {
		return true;
	}

	/**
	 * API-unique tag identifier.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public function get_tags_key() {
		return $this->get_key() . '_tags';
	}

	/**
	 * Enables the mailing list, forms, opt-in type and tag features inside Thrive Automator.
	 * Check the parent method for an explanation of the config structure.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public function get_automator_add_autoresponder_mapping_fields() {
		/**
		 * Some usage examples for this:
		 *
		 * A basic configuration only for mailing lists is "[ 'autoresponder' => [ 'mailing_list' ] ]".
		 * If the custom fields rely on the mailing list, they are added like this: "[ 'autoresponder' => [ 'mailing_list' => [ 'api_fields' ] ] ]"
		 * If the custom fields don't rely on the mailing list ( global custom fields ), the config is: "[ 'autoresponder' => [ 'mailing_list', 'api_fields' ] ]"
		 *
		 * Config for mailing list, custom fields (global), tags: "[ 'autoresponder' => [ 'mailing_list', 'api_fields', 'tag_input' ] ]"
		 *
		 * Config for mailing list, tags, and forms that depend on the mailing lists:
		 * "[ 'autoresponder' => [ 'mailing_list' => [ 'form_list' ], 'api_fields' => [], 'tag_input' => [] ] ]"
		 * ^ If one of the keys has a corresponding array, empty arrays must be added to the other keys in order to respect the structure.
		 */

		return array(
			'autoresponder' => array(
				'api_fields' => array(),
				'tag_input'  => array(),
			),
		);
	}

	/**
	 * Get field mappings specific to an API with tags. Has to be set like this in order to enable tags inside Automator.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public function get_automator_tag_autoresponder_mapping_fields() {
		return array( 'autoresponder' => array( 'tag_input' ) );
	}

	/**
	 * Converts tag names to IDs and removes invalid entries.
	 *
	 * @since 3.40.39
	 *
	 * @param array $tags The tags.
	 * @return array The tags.
	 */
	public function format_tags( $tags ) {

		foreach ( $tags as $i => $tag ) {

			$tag_id = wp_fusion()->user->get_tag_id( $tag );

			if ( false === $tag_id ) {

				wpf_log( 'notice', 0, 'Warning: ' . $tag . ' is not a valid tag name or ID.' );
				unset( $tags[ $i ] );
				continue;

			}

			$tags[ $i ] = $tag_id;

		}

		return $tags;

	}

	/**
	 * This is called from Thrive Automator when the 'Tag user' automation is triggered.
	 * In this case, we want to add the received tags to the received subscriber and mailing list.
	 * This is only done if the subscriber already exists.
	 *
	 * @since 3.40.24
	 *
	 * @param string $email The subscriber's email address.
	 * @param string $tags  The tags to apply.
	 * @param array  $extra ???.
	 * @return bool Whether or not the subscriber exists.
	 */
	public function update_tags( $email, $tags = '', $extra = array() ) {

		$subscriber_exists = false;
		$contact_id        = wp_fusion()->crm->get_contact_id( $email );
		$tags              = $this->format_tags( explode( ',', $tags ) );

		if ( $contact_id ) {
			try {
				wp_fusion()->crm->apply_tags( $tags, $contact_id );
				$subscriber_exists = true;
			} catch ( \Exception $e ) {
				Utils::log_error( 'Error while fetching the subscriber! Error message: ' . $e->getMessage() );
			}
		}

		return $subscriber_exists;
	}

	/**
	 * Get the thumbnail url.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_thumbnail() {
		return WPF_DIR_URL . '/assets/img/logo-wide-color.png';
	}

	/**
	 * Get link to the option page of crm.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_link_to_controls_page() {
		return get_admin_url() . '/options-general.php?page=wpf-settings#integrations';
	}
}
