<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Simply Schedule Appointments integration.
 *
 * @since 3.38.10
 *
 * @link https://wpfusion.com/documentation/events/simply-schedule-appointments/
 */
class WPF_Simply_Schedule_Appointments extends WPF_Integrations_Base {

	/**
	 * This identifies the integration internally and makes it available at
	 * wp_fusion()->integrations->{'my-plugin-slug'}
	 *
	 * @var  string
	 * @since 3.38.10
	 */

	public $slug = 'simply-schedule-appointments';

	/**
	 * The human-readable name of the integration.
	 *
	 * @var  string
	 * @since 3.38.10
	 */

	public $name = 'Simply Schedule Appointments';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/simply-schedule-appointments/';


	/**
	 * Get things started.
	 *
	 * @since 3.38.10
	 */
	public function init() {

		// Metafield groups.
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

		// Booking actions.
		add_action( 'ssa/appointment/booked', array( $this, 'new_booking' ), 10, 4 );
		add_action( 'ssa/appointment/edited', array( $this, 'edited_booking' ), 10, 4 );
		add_action( 'ssa/appointment/canceled', array( $this, 'cancelled_booking' ), 10, 4 );

		// Add settings.
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

	}

	/**
	 * Add or update customer when a booking is edited.
	 *
	 * @since 3.38.10
	 *
	 * @param int   $appointment_id The appointment ID.
	 * @param array $data_after     The data after.
	 * @param array $data_before    The data before.
	 * @param array $response       The response.
	 */
	public function cancelled_booking( $appointment_id, $data_after, $data_before, $response ) {

		$appointment = new SSA_Appointment_Object( $appointment_id );
		$data        = $appointment->get_webhook_payload( 'wpfusion' );

		$contact_id = $this->create_update_customer( $data );

		if ( empty( $contact_id ) ) {
			return; // If creating the contact failed for some reason.
		}

		$apply_tags = wpf_get_option( 'ssa_cancelled_tags' );
		$user_id    = intval( $data['appointment']['customer_id'] );

		if ( ! empty( $apply_tags ) ) {

			if ( empty( $user_id ) ) {

				// Guest checkout.
				wpf_log( 'info', 0, 'Applying tags for cancelled appointment to contact #' . $contact_id . ': ', array( 'tag_array' => $apply_tags ) );
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			} else {

				// Registered users.
				wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			}
		}
	}

	/**
	 * Add or update customer when a booking is edited.
	 *
	 * @since 3.38.10
	 *
	 * @param int   $appointment_id The appointment ID.
	 * @param array $data_after     The data after.
	 * @param array $data_before    The data before.
	 * @param array $response       The response.
	 */
	public function edited_booking( $appointment_id, $data_after, $data_before, $response ) {

		$appointment = new SSA_Appointment_Object( $appointment_id );
		$data        = $appointment->get_webhook_payload( 'wpfusion' );

		$contact_id = $this->create_update_customer( $data );

		if ( empty( $contact_id ) ) {
			return; // If creating the contact failed for some reason.
		}

		$apply_tags = wpf_get_option( 'ssa_edited_tags' );
		$user_id    = intval( $data['appointment']['customer_id'] );

		if ( ! empty( $apply_tags ) ) {

			if ( empty( $user_id ) ) {

				// Guest checkout.
				wpf_log( 'info', 0, 'Applying tags for edited appointment to contact #' . $contact_id . ': ', array( 'tag_array' => $apply_tags ) );
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			} else {

				// Registered users.
				wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			}
		}
	}


	/**
	 * Get customer data.
	 *
	 * @since  3.38.10
	 *
	 * @param  array $data   Appointment data.
	 * @return array Customer data.
	 */
	public function get_customer_data( $data ) {

		$appointment_data = $data['appointment'];
		$update_data      = array();
		$user_data        = $appointment_data['customer_information'];

		$update_data = array(
			'user_email'             => $user_data['Email'],
			'phone'                  => ( isset( $user_data['Phone'] ) ? $user_data['Phone'] : '' ),
			'address'                => ( isset( $user_data['Address'] ) ? $user_data['Address'] : '' ),
			'city'                   => ( isset( $user_data['City'] ) ? $user_data['City'] : '' ),
			'state'                  => ( isset( $user_data['State'] ) ? $user_data['State'] : '' ),
			'zip'                    => ( isset( $user_data['Zip'] ) ? $user_data['Zip'] : '' ),
			'notes'                  => ( isset( $user_data['Notes'] ) ? $user_data['Notes'] : '' ),
			'description'            => ( isset( $appointment_data['description'] ) ? $appointment_data['description'] : '' ),
			'web_meeting_url'        => ( isset( $appointment_data['web_meeting_url'] ) ? $appointment_data['web_meeting_url'] : '' ),
			'customer_timezone'      => $appointment_data['customer_timezone'],
			'start_date'             => $appointment_data['start_date'],
			'end_date'               => $appointment_data['end_date'],
			'appointment_type_title' => $appointment_data['appointment_type_title'],
			'price_full'             => $appointment_data['price_full'],
			'payment_method'         => $appointment_data['payment_method'],
			'status'                 => $appointment_data['status'],
		);

		$name                      = explode( ' ', $user_data['Name'] );
		$update_data['first_name'] = $name[0];
		if ( count( $name ) > 1 ) {
			$update_data['last_name'] = $name[1];
		}

		return $update_data;
	}

	/**
	 * Create or update customer in CRM.
	 *
	 * @since  3.38.10
	 *
	 * @param array $data   The appointment data.
	 * @return string|false The contact ID in the CRM or false if disabled.
	 */
	public function create_update_customer( $data ) {

		$appointment_data    = $data['appointment'];
		$appointment_type_id = $appointment_data['appointment_type_id'];
		$appointment_id      = $appointment_data['id'];
		$user_id             = intval( $appointment_data['customer_id'] );

		if ( ! wpf_get_option( 'ssa_guests', true ) && empty( $user_id ) ) {
			return false; // If guests are disabled.
		}

		if ( empty( $appointment_data['customer_information']['Email'] ) && empty( $user_id ) ) {

			wpf_log( 'error', 0, 'No email address specified for the appointment <a href="' . admin_url( 'admin.php?page=simply-schedule-appointments#/ssa/appointment/' . $appointment_id ) . '" target="_blank">#' . $appointment_id . '</a>. Aborting.' );
			return false;

		}

		if ( user_can( $user_id, 'manage_options' ) ) { // debug notice.
			wpf_log( 'notice', $user_id, 'You\'re currently logged into the site as an administrator. This checkout will update your existing contact record in ' . wp_fusion()->crm->name . '. If you\'re testing checkouts, it\'s recommended to use an incognito browser window.' );
		}

		// Get the customer data.

		$customer_data = $this->get_customer_data( $data );

		// Sync it to the CRM.

		if ( 0 !== $user_id ) {

			// Registered users.

			wp_fusion()->user->push_user_meta( $user_id, $customer_data );

			$contact_id = wp_fusion()->user->get_contact_id( $user_id ); // we'll use this in the next step.

		} else {

			// Helper function for creating/updating contact in the CRM from a guest checkout.

			$contact_id = $this->guest_registration( $appointment_data['customer_information']['Email'], $customer_data );

		}

		return $contact_id;
	}

	/**
	 * Add or update customer when a new booking is made.
	 *
	 * @since 3.38.10
	 *
	 * @param int   $appointment_id The appointment ID.
	 * @param array $data_after     The data after.
	 * @param array $data_before    The data before.
	 * @param array $response       The response.
	 */
	public function new_booking( $appointment_id, $data_after, $data_before, $response ) {

		$appointment = new SSA_Appointment_Object( $appointment_id );
		$data        = $appointment->get_webhook_payload( 'wpfusion' );
		$contact_id  = $this->create_update_customer( $data );

		if ( empty( $contact_id ) ) {
			return; // If creating the contact failed for some reason.
		}

		$apply_tags = wpf_get_option( 'ssa_tags' );
		$user_id    = intval( $data['appointment']['customer_id'] );

		if ( ! empty( $apply_tags ) ) {

			if ( empty( $user_id ) ) {

				// Guest checkout.
				wpf_log( 'info', 0, 'Applying tags for guest appointment booking to contact #' . $contact_id . ': ', array( 'tag_array' => $apply_tags ) );
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			} else {

				// Registered users.
				wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			}
		}
	}

	/**
	 * Adds SSA field group to meta fields list.
	 *
	 * @since  3.38.10
	 *
	 * @param  array $field_groups The field groups.
	 * @return array  Field groups.
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['simply-schedule-appointments'] = array(
			'title'  => 'Simply Schedule Appointments',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Loads SSA fields for inclusion in Contact Fields table
	 *
	 * @since  3.38.10
	 *
	 * @param  array $meta_fields The meta fields.
	 * @return array  Meta Fields.
	 */
	public function add_meta_fields( $meta_fields ) {

		$meta_fields['customer_id'] = array(
			'label'  => 'Customer ID',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['phone'] = array(
			'label'  => 'Phone',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['address'] = array(
			'label'  => 'Address',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['city'] = array(
			'label'  => 'City',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['state'] = array(
			'label'  => 'State',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['zip'] = array(
			'label'  => 'Zip',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['start_date'] = array(
			'label'  => 'Appointment Start Date',
			'type'   => 'date',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['end_date'] = array(
			'label'  => 'Appointment End Date',
			'type'   => 'date',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['notes'] = array(
			'label'  => 'Appointment Notes',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['customer_timezone'] = array(
			'label'  => 'Customer Timezone',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['appointment_type_title'] = array(
			'label'  => 'Appointment Type Title',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['description'] = array(
			'label'  => 'Description',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['price_full'] = array(
			'label'  => 'Price Full',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['payment_method'] = array(
			'label'  => 'Payment Method',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['web_meeting_url'] = array(
			'label'  => 'Web Meeting Url',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		$meta_fields['status'] = array(
			'label'  => 'Appointment Status',
			'type'   => 'text',
			'group'  => 'simply-schedule-appointments',
			'pseudo' => true,
		);

		return $meta_fields;

	}


	/**
	 * Add custom fields to the Integrations tab in the WP Fusion settings.
	 *
	 * @since  3.38.10
	 *
	 * @param  array $settings The registered settings.
	 * @param  array $options  The options in the database.
	 * @return array The registered settings.
	 */
	public function register_settings( $settings, $options ) {

		$settings['ssa_header'] = array(
			'title'   => __( 'Simply Schedule Appointments Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['ssa_guests'] = array(
			'title'   => __( 'Sync Guests', 'wp-fusion' ),
			/* translators: %s: CRM Name */
			'desc'    => sprintf( __( 'Sync guest bookings with %s.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'std'     => 1,
			'type'    => 'checkbox',
			'section' => 'integrations',
			'tooltip' => __( 'Bookings by registered users will always be synced.', 'wp-fusion' ),
		);

		$settings['ssa_tags'] = array(
			'title'   => __( 'Apply Tags - Bookings', 'wp-fusion' ),
			'desc'    => __( 'These tags will be applied to users when they book an appointment.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['ssa_edited_tags'] = array(
			'title'   => __( 'Apply Tags - Rescheduled', 'wp-fusion' ),
			'desc'    => __( 'These tags will be applied to users when they reschedule an appointment.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['ssa_cancelled_tags'] = array(
			'title'   => __( 'Apply Tags - Cancelled', 'wp-fusion' ),
			'desc'    => __( 'These tags will be applied to users when they cancel an appointment.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		return $settings;

	}


}

new WPF_Simply_Schedule_Appointments();
