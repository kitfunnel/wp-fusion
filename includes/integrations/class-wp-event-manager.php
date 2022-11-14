<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_WP_Event_Manager extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wp-event-manager';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WP Event Manager';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/integrations/wp-event-manager/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'new_event_registration', array( $this, 'new_event_registration' ), 20, 2 );
		add_action( 'event_manager_registrations_save_event_registration', array( $this, 'save_event_registration' ), 10, 2 );
		add_action( 'waiting_to_confirmed', array( $this, 'registration_confirmed' ) ); // when a pending order is confirmed.

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'set_contact_field_names' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'save_post_event_listing', array( $this, 'save_meta_box_data' ) );

	}


	/**
	 * Send the data to the CRM when someone registers for an event
	 *
	 * @access  public
	 * @return  void
	 */

	public function new_event_registration( $registration_id, $event_id ) {

		// Sometimes a WP_Post of the registration is passed instead of the event ID
		if ( is_object( $event_id ) ) {
			$event_id = $event_id->post_parent;
		}

		$registration_data = get_post_meta( $registration_id );

		if ( empty( $registration_data ) ) {
			return;
		}

		// Collapse the array
		$registration_data = array_map(
			function( $n ) {
					return maybe_unserialize( $n[0] );
			},
			$registration_data
		);

		foreach ( $registration_data as $value ) {
			if ( is_string( $value ) && is_email( $value ) ) {
				$registration_data['user_email'] = $value;
			}
		}

		// If we're adding the registration in the admin, wait until the meta has been saved
		// (on event_manager_registrations_save_event_registration).

		if ( empty( $registration_data['user_email'] ) && is_admin() && ! did_action( 'event_manager_registrations_save_event_registration' ) ) {
			return;
		}

		// Break the name into two parts

		if ( ! empty( $registration_data['full-name'] ) ) {
			$name = explode( ' ', $registration_data['full-name'] ); // old version.
		} elseif ( ! empty( $registration_data['attendee_name'] ) ) {
			$name = explode( ' ', $registration_data['attendee_name'] ); // newer versions.
		} else {
			$name = explode( ' ', get_the_title( $registration_id ) ); // the attendee name is also used as the post title.
		}

		$registration_data['first_name'] = $name[0];

		if ( count( $name ) > 1 ) {
			unset( $name[0] );
			$registration_data['last_name'] = implode( ' ', $name );
		}

		if ( empty( $registration_data['user_email'] ) ) {
			wpf_log( 'notice', 0, 'Unable to sync event registration <a href="' . admin_url( 'post.php?post=' . $registration_id . '&action=edit' ) . '" target="_blank">#' . $registration_id . '</a>, no email address provided.', array( 'meta_array' => $registration_data ) );
			return;
		}

		$event_data = array(
			'event_name'       => get_the_title( $event_id ),
			'event_start_date' => get_post_meta( $event_id, '_event_start_date', true ),
			'event_start_time' => get_post_meta( $event_id, '_event_start_time', true ),
			'event_address'    => get_post_meta( $event_id, '_event_address', true ),
			'event_location'   => get_post_meta( $event_id, '_event_location', true ),
			'event_postcode'   => get_post_meta( $event_id, '_event_pincode', true ),
		);

		$registration_data = array_merge( $registration_data, $event_data );

		// Added for leadersinstitute.com
		$update_existing = apply_filters( 'wpf_wp_event_manager_update_existing_user', true );

		// Send the meta data

		$user = wpf_get_current_user();

		if ( ! empty( $registration_data['_attendee_user_id'] ) && $user->user_email == $registration_data['user_email'] && $update_existing ) {

			wp_fusion()->user->push_user_meta( $registration_data['_attendee_user_id'], $registration_data );

		} else {

			$contact_id = $this->guest_registration( $registration_data['user_email'], $registration_data );

		}

		// Apply the tags

		$settings = get_post_meta( $event_id, 'wpf_settings_event', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {

			if ( ! empty( $registration_data['_attendee_user_id'] ) && $update_existing ) {

				wp_fusion()->user->apply_tags( $settings['apply_tags'], $registration_data['_attendee_user_id'] );

			} else {

				wpf_log( 'info', 0, 'WP Event Manager guest registration applying tag(s): ', array( 'tag_array' => $settings['apply_tags'] ) );

				wp_fusion()->crm->apply_tags( $settings['apply_tags'], $contact_id );

			}
		}

	}

	/**
	 * Syncs the attendee details when a registration is saved in the admin.
	 *
	 * @since 3.40.15
	 *
	 * @param int     $registration_id The registration ID.
	 * @param WP_Post $registration    The registration.
	 */
	public function save_event_registration( $registration_id, $registration ) {

		$this->new_event_registration( $registration_id, $registration->post_parent );


	}


	/**
	 * Syncs the attendee details when a registration is transitioned from
	 * waiting to confirmed.
	 *
	 * @since 3.40.17
	 *
	 * @param WP_Post $registration    The registration.
	 */
	public function registration_confirmed( $registration ) {

		$this->new_event_registration( $registration->ID, $registration->post_parent );


	}

	/**
	 * Adds field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['wp-event-manager'] = array(
			'title'  => 'WP Event Manager',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Loads fields for inclusion in Contact Fields table
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function set_contact_field_names( $meta_fields ) {

		$meta_fields['event_name'] = array(
			'label' => 'Event Name',
			'type'  => 'text',
			'group' => 'wp-event-manager',
		);

		$meta_fields['event_start_date'] = array(
			'label' => 'Event Start Date',
			'type'  => 'date',
			'group' => 'wp-event-manager',
		);

		$meta_fields['event_start_time'] = array(
			'label' => 'Event Start Time',
			'type'  => 'text',
			'group' => 'wp-event-manager',
		);

		$meta_fields['event_address'] = array(
			'label' => 'Event Address',
			'type'  => 'text',
			'group' => 'wp-event-manager',
		);

		$meta_fields['event_location'] = array(
			'label' => 'Event Location',
			'type'  => 'text',
			'group' => 'wp-event-manager',
		);

		$meta_fields['event_postcode'] = array(
			'label' => 'Event Postcode',
			'type'  => 'text',
			'group' => 'wp-event-manager',
		);

		$fields = get_option( 'event_registration_form_fields', array() );

		foreach ( $fields as $key => $field ) {

			if ( in_array( 'from_name', $field['rules'] ) ) {

				// Name fields

				$meta_fields[ $key . '_first' ] = array(
					'label' => $field['label'] . ' - First',
					'type'  => $field['type'],
					'group' => 'wp-event-manager',
				);

				$meta_fields[ $key . '_last' ] = array(
					'label' => $field['label'] . ' - Last',
					'type'  => $field['type'],
					'group' => 'wp-event-manager',
				);

			} else {

				$meta_fields[ $key ] = array(
					'label' => $field['label'],
					'type'  => $field['type'],
					'group' => 'wp-event-manager',
				);

			}

		}

		return $meta_fields;

	}

	/**
	 * Adds meta box
	 *
	 * @access public
	 * @return mixed
	 */

	public function add_meta_box( $post_id, $data ) {

		add_meta_box( 'wpf-event-meta', 'WP Fusion - Event Settings', array( $this, 'meta_box_callback' ), 'event_listing' );

	}

	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback( $post ) {

		$settings = array(
			'apply_tags' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf_settings_event', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf_settings_event', true ) );
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">' . __( 'Apply tags', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags'],
			'meta_name' => 'wpf_settings_event',
			'field_id'  => 'apply_tags',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . sprintf( __( 'The selected tags will be applied in %s when someone registers for this event.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

	}

	/**
	 * Runs when WPF meta box is saved
	 *
	 * @access public
	 * @return void
	 */

	public function save_meta_box_data( $post_id ) {

		// Update the meta field in the database.

		if ( ! empty( $_POST['wpf_settings_event'] ) ) {
			update_post_meta( $post_id, 'wpf_settings_event', $_POST['wpf_settings_event'] );
		}

	}


}

new WPF_WP_Event_Manager();
