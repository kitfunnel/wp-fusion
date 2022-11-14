<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Modern_Events_Calendar extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'modern-events-calendar';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Modern events calendar';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/modern-events-calendar/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// Metabox
		add_action( 'custom_field_ticket', array( $this, 'tickets_metabox' ), 10, 2 );
		add_action( 'mec_after_publish_admin_event', array( $this, 'save_tickets' ), 10, 2 );

		// Sync data and apply tags when a booking is placed
		add_action( 'mec_booking_added', array( $this, 'booking_added' ) );

		// Register fields for sync
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

	}


	/**
	 * Gets all the attendee and event meta from a booking ID and attendee ID
	 *
	 * @access  public
	 * @return  array Update data
	 */

	public function get_attendee_meta( $booking_id, $attendee_id ) {

		$attendees = get_post_meta( $booking_id, 'mec_attendees', true );
		$event_id  = get_post_meta( $booking_id, 'mec_event_id', true );

		$start_date         = get_post_meta( $event_id, 'mec_start_date', true );
		$start_time_hour    = get_post_meta( $event_id, 'mec_start_time_hour', true );
		$start_time_minutes = get_post_meta( $event_id, 'mec_start_time_minutes', true );
		$start_time_ampm    = get_post_meta( $event_id, 'mec_start_time_ampm', true );

		$start_time  = sprintf( '%02d', $start_time_hour ) . ':';
		$start_time .= sprintf( '%02d', $start_time_minutes ) . ' ';
		$start_time .= $start_time_ampm;

		$names = explode( ' ', $attendees[ $attendee_id ]['name'] );

		$firstname = $names[0];

		unset( $names[0] );

		if ( ! empty( $names ) ) {
			$lastname = implode( ' ', $names );
		} else {
			$lastname = '';
		}

		$update_data = array(
			'first_name' => $firstname,
			'last_name'  => $lastname,
			'user_email' => $attendees[ $attendee_id ]['email'],
			'event_name' => get_the_title( $event_id ),
			'event_date' => $start_date,
			'event_time' => $start_time,
		);

		return $update_data;

	}


	/**
	 * Sync data and apply tags when a booking is created
	 *
	 * @access  public
	 * @return  void
	 */

	public function booking_added( $booking_id ) {

		$event_id  = get_post_meta( $booking_id, 'mec_event_id', true );
		$settings  = get_post_meta( $event_id, 'wpf_ticket_settings', true );
		$attendees = get_post_meta( $booking_id, 'mec_attendees', true );

		// Only act on each email address once
		$did_emails = array();

		foreach ( $attendees as $i => $attendee ) {

			if ( in_array( $attendee['email'], $did_emails ) ) {
				continue;
			}

			$did_emails[] = $attendee['email'];

			// Maybe quit after the first one if Add Attendees isn't checked for the ticket

			if ( $i > 0 ) {

				if ( empty( $settings ) || empty( $settings[ $attendee['id'] ] ) || empty( $settings[ $attendee['id'] ]['add_attendees'] ) ) {
					break;
				}
			}

			// Get attendee meta and sync it

			$update_data = $this->get_attendee_meta( $booking_id, $i );

			$user = get_user_by( 'email', $attendee['email'] );

			if ( ! empty( $user ) ) {

				wp_fusion()->user->push_user_meta( $user->ID, $update_data );

			} else {

				$contact_id = $this->guest_registration( $attendee['email'], $update_data );

			}

			// Apply the tags

			if ( ! empty( $settings ) && ! empty( $settings[ $attendee['id'] ] ) && ! empty( $settings[ $attendee['id'] ]['apply_tags'] ) ) {

				if ( ! empty( $user ) ) {

					wp_fusion()->user->apply_tags( $settings[ $attendee['id'] ]['apply_tags'], $user->ID );

				} elseif ( ! empty( $contact_id ) && ! is_wp_error( $contact_id ) ) {

					wpf_log( 'info', 0, 'Applying event tag(s) for guest booking: ', array( 'tag_array' => $settings[ $attendee['id'] ]['apply_tags'] ) );
					wp_fusion()->crm->apply_tags( $settings[ $attendee['id'] ]['apply_tags'], $contact_id );

				}
			}
		}

	}


	/**
	 * Displays WPF tag option in ticket meta box
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function tickets_metabox( $ticket, $key ) {

		$defaults = array(
			'apply_tags'    => array(),
			'add_attendees' => false,
		);

		global $post;

		$settings = get_post_meta( $post->ID, 'wpf_ticket_settings', true );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		if ( empty( $settings[ $key ] ) ) {
			$settings[ $key ] = array();
		}

		$settings[ $key ] = array_merge( $defaults, $settings[ $key ] );

		/*
		// Apply tags
		*/

		echo '<div class="mec-form-row">';

		echo '<h4>' . __( 'WP Fusion Settings', 'wp-fusion' ) . '</h4>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings[ $key ]['apply_tags'],
					'meta_name' => "wpf_ticket_settings[{$key}][apply_tags]",
				)
			);

			echo '<span class="mec-tooltip" style="bottom: 7px;">';
				echo '<div class="box top">';
					echo '<h5 class="title">' . __( 'Apply tags', 'wp-fusion' ) . '</h5>';
					echo '<div class="content">';
						echo '<p>' . sprintf( __( 'These tags will be applied in %s when someone purchases this ticket.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</p>';
					echo '</div>';
				echo '</div>';
				echo '<i title="" class="dashicons-before dashicons-editor-help"></i>';
			echo '</span>';

		echo '</div>';

		echo '<div class="mec-form-row">';
			echo '<input class="checkbox" type="checkbox" style="" id="wpf-add-attendees" name="wpf_ticket_settings[' . $key . '][add_attendees]" value="1" ' . checked( $settings[ $key ]['add_attendees'], 1, false ) . ' />';
			echo '<span>' . sprintf( __( 'Add each event attendee as a separate contact in %s.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</div>';

	}

	/**
	 * Save metabox data
	 *
	 * @access  public
	 * @return  void
	 */

	public function save_tickets( $event_id, $mec_update ) {

		if ( isset( $_POST['wpf_ticket_settings'] ) ) {

			update_post_meta( $event_id, 'wpf_ticket_settings', $_POST['wpf_ticket_settings'] );

		} else {

			delete_post_meta( $event_id, 'wpf_ticket_settings' );

		}

	}


	/**
	 * Adds field group for Tribe Tickets to contact fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['modern_events_event'] = array(
			'title'  => 'Modern Events Calendar - Event',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Sets field labels and types for event fields
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['event_name'] = array(
			'label' => 'Event Name',
			'type'  => 'text',
			'group' => 'modern_events_event',
		);

		$meta_fields['event_date'] = array(
			'label' => 'Event Date',
			'type'  => 'date',
			'group' => 'modern_events_event',
		);

		$meta_fields['event_time'] = array(
			'label' => 'Event Time',
			'type'  => 'text',
			'group' => 'modern_events_event',
		);

		return $meta_fields;

	}

}

new WPF_Modern_Events_Calendar();
