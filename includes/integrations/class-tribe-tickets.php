<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Tribe_Tickets extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'tribe-tickets';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Tribe-tickets';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/gamification/badgeos/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		$this->name = 'Tribe Tickets';

		// Making Custom contact fields for WPF settings
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

		// Moving one attendee to another event
		add_action( 'tribe_tickets_ticket_moved', array( $this, 'tickets_ticket_moved' ), 10, 6 );

		// Saving in post_meta
		add_action( 'event_tickets_after_save_ticket', array( $this, 'tickets_after_save_ticket' ), 10, 4 );
		// add_action( 'wp_ajax_wpf_tribe_tickets_save', array( $this, 'ajax_save_ticket' ) );
		add_action( 'tribe_tickets_ticket_add', array( $this, 'ajax_save_ticket' ), 10, 3 );

		// Metabox
		add_action( 'tribe_events_tickets_metabox_advanced', array( $this, 'tickets_metabox' ), 10, 2 );
		add_action( 'tribe_events_tickets_metabox_edit_main', array( $this, 'tickets_metabox_new' ), 10, 2 );

		// Transfering and preparing ticket/rsvp/edd info to be able to get picked up by CRM
		add_action( 'event_tickets_rsvp_ticket_created', array( $this, 'rsvp_ticket_created' ), 20, 4 );

		// Push ticket meta for EDD tickets after purchase
		add_action( 'event_tickets_edd_ticket_created', array( $this, 'edd_ticket_created' ), 20, 4 ); // 20 so the ticket meta is saved

		// Push event date for WooCommere tickets after purchase
		add_action( 'event_tickets_woocommerce_ticket_created', array( $this, 'woocommerce_ticket_created' ), 20, 4 ); // 20 so the ticket meta is saved

		// Sync check-ins
		add_action( 'rsvp_checkin', array( $this, 'checkin' ), 10, 2 );
		add_action( 'eddtickets_checkin', array( $this, 'checkin' ), 10, 2 );
		add_action( 'wootickets_checkin', array( $this, 'checkin' ), 10, 2 );

		// Delete attendee
		add_action( 'before_delete_post', array( $this, 'delete_attendee' ) );

		// Batch operations
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_filter( 'wpf_batch_tribe_tickets_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_tribe_tickets', array( $this, 'batch_step' ) );

	}


	/**
	 * Adds field group for Tribe Tickets to contact fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['tribe_events_event'] = array(
			'title'  => 'The Events Calendar - Event',
			'fields' => array(),
		);

		$field_groups['tribe_events_attendee'] = array(
			'title'  => 'The Events Calendar - Attendee',
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

		$meta_fields['ticket_name'] = array(
			'label' => 'Ticket Name',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['event_name'] = array(
			'label' => 'Event Name',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['event_date'] = array(
			'label' => 'Event Date',
			'type'  => 'date',
			'group' => 'tribe_events_event',
		);

		$meta_fields['event_time'] = array(
			'label' => 'Event Time',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['venue_name'] = array(
			'label' => 'Venue Name',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['event_address'] = array(
			'label' => 'Event Address',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['event_city'] = array(
			'label' => 'Event City',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['event_state'] = array(
			'label' => 'Event State',
			'type'  => 'state',
			'group' => 'tribe_events_event',
		);

		$meta_fields['event_province'] = array(
			'label' => 'Event Province',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['event_country'] = array(
			'label' => 'Event Country',
			'type'  => 'country',
			'group' => 'tribe_events_event',
		);

		$meta_fields['event_zip'] = array(
			'label' => 'Event Zip',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['organizer_name'] = array(
			'label' => 'Organizer Name',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['organizer_phone'] = array(
			'label' => 'Organizer Phone',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['organizer_website'] = array(
			'label' => 'Organizer Website',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$meta_fields['organizer_email'] = array(
			'label' => 'Organizer Email',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		// Custom event fields

		$custom_fields = tribe_get_option( 'custom-fields' );

		if ( ! empty( $custom_fields ) ) {

			foreach ( $custom_fields as $field ) {

				$meta_fields[ $field['name'] ] = array(
					'label' => $field['label'],
					'type'  => $field['type'],
					'group' => 'tribe_events_event',
				);

			}
		}

		$meta_fields['event_checkin'] = array(
			'label' => 'Event Check-in',
			'type'  => 'checkbox',
			'group' => 'tribe_events_attendee',
		);

		$meta_fields['event_checkin_event'] = array(
			'label' => 'Event Check-in - Event Name',
			'type'  => 'text',
			'group' => 'tribe_events_event',
		);

		$args = array(
			'post_type'    => array( 'download', 'tribe_rsvp_tickets', 'product' ),
			'nopaging'     => true,
			'fields'       => 'ids',
			'meta_key'     => '_tribe_tickets_meta',
			'meta_compare' => 'EXISTS',
		);

		$tickets = get_posts( $args );

		if ( ! empty( $tickets ) ) {

			foreach ( $tickets as $post_id ) {

				$event_fields = get_post_meta( $post_id, '_tribe_tickets_meta', true );

				if ( empty( $event_fields ) ) {
					continue;
				}

				foreach ( $event_fields as $field ) {

					$meta_fields[ $field['slug'] ] = array(
						'label' => $field['label'],
						'type'  => $field['type'],
						'group' => 'tribe_events_attendee',
					);

				}
			}
		}

		// Fieldsets

		$args = array(
			'post_type'      => 'ticket-meta-fieldset',
			'posts_per_page' => 100,
			'fields'         => 'ids',
		);

		$fieldsets = get_posts( $args );

		if ( ! empty( $fieldsets ) ) {

			foreach ( $fieldsets as $post_id ) {

				$fields = get_post_meta( $post_id, '_tribe_tickets_meta_template', true );

				if ( empty( $fields ) ) {
					continue;
				}

				foreach ( $fields as $field ) {

					$type = $field['type'];

					if ( ! empty( $field['extra'] ) && isset( $field['extra']['options'] ) ) {
						$type = 'mulitselect';
					}

					$meta_fields[ $field['slug'] ] = array(
						'label' => $field['label'],
						'type'  => $type,
						'group' => 'tribe_events_attendee',
					);

				}
			}
		}

		return $meta_fields;

	}


	/**
	 * Gets all the attendee and event meta from an attendee ID.
	 *
	 * @since  3.37.13
	 *
	 * @param  Int $attendee_id The attendee ID.
	 * @return array The data to sync to the CRM.
	 */
	public function get_attendee_meta( $attendee_id ) {

		// Get the event ID (Tribe annoyingly stores these all in different keys)

		$event_id  = get_post_meta( $attendee_id, '_tribe_wooticket_event', true );
		$ticket_id = get_post_meta( $attendee_id, '_tribe_wooticket_product', true );

		if ( empty( $event_id ) ) {
			$event_id  = get_post_meta( $attendee_id, '_tribe_eddticket_event', true );
			$ticket_id = get_post_meta( $attendee_id, '_tribe_eddticket_product', true );
		}

		if ( empty( $event_id ) ) {
			$event_id  = get_post_meta( $attendee_id, '_tribe_rsvp_event', true );
			$ticket_id = get_post_meta( $attendee_id, '_tribe_rsvp_product', true );
		}

		// This is a bit tricky. When Allow Individual Attendee Collection is off, this will be the purchaser's
		// email. Also if it's on, but left blank.

		$attendee_email = get_post_meta( $attendee_id, '_tribe_tickets_email', true );

		if ( empty( $attendee_email ) ) {
			$attendee_email = get_post_meta( $attendee_id, '_tribe_rsvp_email', true );
		}

		$venue_id       = get_post_meta( $event_id, '_EventVenueID', true );
		$event_date     = get_post_meta( $event_id, '_EventStartDate', true );
		$event_address  = get_post_meta( $venue_id, '_VenueAddress', true );
		$event_city     = get_post_meta( $venue_id, '_VenueCity', true );
		$event_country  = get_post_meta( $venue_id, '_VenueCountry', true );
		$event_state    = get_post_meta( $venue_id, '_VenueState', true );
		$event_province = get_post_meta( $venue_id, '_VenueProvince', true );
		$event_zip      = get_post_meta( $venue_id, '_VenueZip', true );

		$event_time = date( 'g:ia', strtotime( $event_date ) );

		$update_data = array(
			'user_email'     => $attendee_email,
			'ticket_name'    => get_the_title( $ticket_id ),
			'event_name'     => get_the_title( $event_id ),
			'event_date'     => $event_date,
			'event_time'     => $event_time,
			'venue_name'     => get_the_title( $venue_id ),
			'event_address'  => $event_address,
			'event_city'     => $event_city,
			'event_state'    => $event_state,
			'event_province' => $event_province,
			'event_country'  => $event_country,
			'event_zip'      => $event_zip,
		);

		// Name

		$full_name = get_post_meta( $attendee_id, '_tribe_tickets_full_name', true );

		if ( empty( $full_name ) ) {
			$full_name = get_post_meta( $attendee_id, '_tribe_rsvp_full_name', true );
		}

		if ( ! empty( $full_name ) ) {

			$parts                     = explode( ' ', $full_name, 2 );
			$update_data['first_name'] = $parts[0];

			if ( isset( $parts[1] ) ) {
				$update_data['last_name'] = $parts[1];
			}
		}

		// Organizer

		$organizer_id = get_post_meta( $event_id, '_EventOrganizerID', true );

		if ( ! empty( $organizer_id ) ) {

			$organizer_data = array(
				'organizer_name'    => get_the_title( $organizer_id ),
				'organizer_phone'   => get_post_meta( $organizer_id, '_OrganizerPhone', true ),
				'organizer_website' => get_post_meta( $organizer_id, '_OrganizerWebsite', true ),
				'organizer_email'   => get_post_meta( $organizer_id, '_OrganizerEmail', true ),
			);

			$update_data = array_merge( $update_data, $organizer_data );

		}

		$ticket_meta = get_post_meta( $attendee_id, '_tribe_tickets_meta', true );

		if ( ! empty( $ticket_meta ) ) {

			// Clean up multiselects / multi-checkboxes from (i.e.) event-checkbox_1f3870be274f6c49b3e31a0c6728957f to arrays

			foreach ( $ticket_meta as $key => $value ) {

				if ( false !== strpos( $key, '_' ) ) {

					$array_parts = explode( '_', $key );

					if ( 32 == strlen( $array_parts[1] ) ) {

						if ( ! isset( $ticket_meta[ $array_parts[0] ] ) ) {
							$ticket_meta[ $array_parts[0] ] = array();
						}

						$ticket_meta[ $array_parts[0] ][] = $value;

					}
				}
			}

			$update_data = array_merge( $update_data, $ticket_meta );

			// Cases where a custom email field needs to take priority over the standard email field

			foreach ( $ticket_meta as $key => $value ) {

				if ( ! is_array( $value ) && is_email( $value ) && wpf_is_field_active( $key ) && wpf_get_crm_field( $key ) == wpf_get_crm_field( 'user_email' ) ) {
					$update_data['user_email'] = $value;
				}
			}
		}

		// Possible additional event meta

		$event_meta = get_post_meta( $event_id );
		if ( ! empty( $event_meta ) && is_array( $event_meta ) ) {
			foreach ( $event_meta as $key => $value ) {

				if ( 0 === strpos( $key, '_ecp_custom_' ) ) {
					$update_data[ $key ] = $value[0];
				}
			}
		}

		/**
		 * Filter the attendee data.
		 *
		 * @since 3.37.13
		 *
		 * @link  https://wpfusion.com/documentation/filters/wpf_event_tickets_attendee_data/
		 *
		 * @param array $update_data The attendee data to sync to the CRM.
		 * @param int   $attendee_id The attendee ID.
		 */

		$update_data = apply_filters( 'wpf_event_tickets_attendee_data', $update_data, $attendee_id );

		return $update_data;

	}

	/**
	 * Creates / updates a contact record for a single attendee, and applies tags
	 *
	 * @access  public
	 * @return  int Contact ID
	 */

	public function process_attendee( $attendee_id, $apply_tags = array() ) {

		$update_data = $this->get_attendee_meta( $attendee_id );

		$email_address = false;

		foreach ( $update_data as $key => $value ) {
			if ( is_email( $value ) && 'organizer_email' !== $key ) {
				$email_address = $value;
				break;
			}
		}

		if ( false === $email_address ) {
			wpf_log( 'notice', 0, 'Unable to sync event attendee, no email address found:', array( 'meta_array' => $update_data ) );
			return;
		}

		$update_data['user_email'] = $email_address;

		$user = get_user_by( 'email', $email_address );

		if ( ! empty( $user ) ) {

			wp_fusion()->user->push_user_meta( $user->ID, $update_data );

			$contact_id = wp_fusion()->user->get_contact_id( $user->ID );

		} else {

			$contact_id = $this->guest_registration( $email_address, $update_data );

		}

		// Get any dynamic tags out of the update data

		$apply_tags = array_merge( $apply_tags, $this->get_dynamic_tags( $update_data ) );

		if ( ! empty( $apply_tags ) ) {

			if ( ! empty( $user ) ) {

				wp_fusion()->user->apply_tags( $apply_tags, $user->ID );

			} elseif ( ! empty( $contact_id ) ) {

				wpf_log( 'info', 0, 'Applying event tag(s) for guest checkout: ', array( 'tag_array' => $apply_tags ) );
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}
		}

		// Save the contact ID to the attendee meta
		update_post_meta( $attendee_id, WPF_CONTACT_ID_META_KEY, $contact_id );

		update_post_meta( $attendee_id, '_wpf_attendee_complete', true );

		return $contact_id;

	}

	/**
	 * Fires when a ticket is relocated from ticket type to another, which may be in
	 * a different post altogether.
	 *
	 * @param int $ticket_id                the ticket which has been moved
	 * @param int $src_ticket_type_id       the ticket type it belonged to originally
	 * @param int $tgt_ticket_type_id       the ticket type it now belongs to
	 * @param int $src_event_id             the event/post which the ticket originally belonged to
	 * @param int $tgt_event_id             the event/post which the ticket now belongs to
	 * @param int $instigator_id            the user who initiated the change
	 *
	 * @access  public
	 * @return  void
	 */

	public function tickets_ticket_moved( $attendee_id, $src_ticket_type_id, $tgt_ticket_type_id, $src_event_id, $tgt_event_id, $instigator_id ) {

		$attendee_user_id = get_post_meta( $attendee_id, '_tribe_tickets_attendee_user_id', true );
		$contact_id       = get_post_meta( $attendee_id, WPF_CONTACT_ID_META_KEY, true );

		if ( empty( $attendee_user_id ) && empty( $contact_id ) ) {
			wpf_log( 'notice', 0, 'Attendee #' . $attendee_id . ' moved from ticket <strong>' . get_the_title( $src_ticket_type_id ) . '</strong> to <strong>' . get_the_title( $tgt_ticket_type_id ) . '</strong> but no user ID or contact ID found for the attendee, so nothing will be synced.' );
			return;
		}

		wpf_log( 'notice', $attendee_user_id, 'Attendee #' . $attendee_id . ' moved from ticket <strong>' . get_the_title( $src_ticket_type_id ) . '</strong> to <strong>' . get_the_title( $tgt_ticket_type_id ) . '</strong>.' );

		// Remove old tags

		if ( get_post_type( $src_ticket_type_id ) == 'download' ) {
			$settings = get_post_meta( $src_ticket_type_id, 'wpf-settings-edd', true );
		} else {
			$settings = get_post_meta( $src_ticket_type_id, 'wpf_settings', true );
		}

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {

			if ( ! empty( $attendee_user_id ) ) {
				wp_fusion()->user->remove_tags( $settings['apply_tags'], $attendee_user_id );
			} else {
				wp_fusion()->crm->remove_tags( $settings['apply_tags'], $contact_id );
			}
		}

		// Sync meta

		$update_data = $this->get_attendee_meta( $attendee_id );

		if ( ! empty( $attendee_user_id ) ) {
			wp_fusion()->user->push_user_meta( $attendee_user_id, $update_data );
		} else {
			wp_fusion()->crm->update_contact( $contact_id, $update_data );
		}

		// Apply new tags

		if ( get_post_type( $tgt_ticket_type_id ) == 'download' ) {
			$settings = get_post_meta( $tgt_ticket_type_id, 'wpf-settings-edd', true );
		} else {
			$settings = get_post_meta( $tgt_ticket_type_id, 'wpf_settings', true );
		}

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {

			if ( ! empty( $attendee_user_id ) ) {
				wp_fusion()->user->apply_tags( $settings['apply_tags'], $attendee_user_id );
			} else {
				wp_fusion()->crm->apply_tags( $settings['apply_tags'], $contact_id );
			}
		}

	}


	/**
	 * RSVP ticket created
	 *
	 * @access  public
	 * @return  void
	 */

	public function rsvp_ticket_created( $attendee_id, $post_id, $ticket_id, $order_attendee_id ) {

		// Get settings.

		$settings = get_post_meta( $ticket_id, 'wpf_settings', true );
		$defaults = array( 'apply_tags' => array() );
		$settings = wp_parse_args( $settings, $defaults );

		if ( 1 == $order_attendee_id ) {

			if ( ! empty( $_POST['attendee'] ) ) {

				// Get the attendee info from the POST data for the first order attendee (classic RSVP experience)

				$full_name = $_POST['attendee']['full_name'];
				$email     = $_POST['attendee']['email'];

			} else {

				$full_name = get_post_meta( $attendee_id, '_tribe_rsvp_full_name', true );
				$email     = get_post_meta( $attendee_id, '_tribe_rsvp_email', true );

			}

			$update_data = $this->get_attendee_meta( $attendee_id );

			// Split the full name into two fields

			$names = explode( ' ', $full_name );

			$update_data['first_name'] = $names[0];

			unset( $names[0] );

			if ( ! empty( $names ) ) {

				$update_data['last_name'] = implode( ' ', $names );

			}

			$update_data['user_email'] = $email;

			$user = false;

			if ( empty( $settings['add_attendees'] ) ) {
				$user = wpf_get_current_user(); // If we're not syncing attendees, we'll sync the data to the current user, if applicable
			}

			// If the email is for a registered user, use the WPF_User methods

			if ( false == $user ) {
				$user = get_user_by( 'email', $email );
			}

			if ( $user ) {

				wp_fusion()->user->push_user_meta( $user->ID, $update_data );

				$contact_id = wp_fusion()->user->get_contact_id( $user->ID );

			} else {

				$contact_id = $this->guest_registration( $email, $update_data );

			}

			if ( ! empty( $settings['apply_tags'] ) ) {

				if ( $user ) {

					wp_fusion()->user->apply_tags( $settings['apply_tags'], $user->ID );

				} else {

					wpf_log( 'info', $user_id, 'Event Tickets guest RSVP applying tag(s): ', array( 'tag_array' => $settings['apply_tags'] ) );

					wp_fusion()->crm->apply_tags( $settings['apply_tags'], $contact_id );

				}
			}

			update_post_meta( $attendee_id, WPF_CONTACT_ID_META_KEY, $contact_id );
			update_post_meta( $attendee_id, '_wpf_attendee_complete', true );

		} elseif ( ! empty( $settings['add_attendees'] ) ) {

			// Subsequent attendees, if enabled.

			$this->process_attendee( $attendee_id, $settings['apply_tags'] );

		}

	}

	/**
	 * EDD ticket created
	 *
	 * @access  public
	 * @return  void
	 */

	public function edd_ticket_created( $attendee_id, $order_id, $product_id, $order_attendee_id ) {

		$payment = new EDD_Payment( $order_id );

		// We only need to run on the first attendee.
		if ( ! empty( $payment->get_meta( '_wpf_tribe_complete', true ) ) ) {
			return;
		}

		$update_data = $this->get_attendee_meta( $attendee_id );

		if ( $payment->user_id > 0 ) {

			wp_fusion()->user->push_user_meta( $payment->user_id, $update_data );

			$contact_id = wp_fusion()->user->get_contact_id( $payment->user_id );

		} else {

			wp_fusion()->crm->update_contact( $contact_id, $update_data );

			$contact_id = $this->guest_registration( $payment->email, $update_data );

		}

		$ticket_id = get_post_meta( $attendee_id, '_tribe_eddticket_product', true );
		$settings  = get_post_meta( $ticket_id, 'wpf_settings', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {

			if ( ! empty( $user_id ) ) {

				wp_fusion()->user->apply_tags( $settings['apply_tags'], $payment->user_id );

			} elseif ( ! empty( $contact_id ) ) {

				wpf_log( 'info', 0, 'Applying event tag(s) for guest checkout: ', array( 'tag_array' => $settings['apply_tags'] ) );
				wp_fusion()->crm->apply_tags( $settings['apply_tags'], $contact_id );

			}
		}

		// Save the contact ID to the attendee meta.
		update_post_meta( $attendee_id, WPF_CONTACT_ID_META_KEY, $contact_id );
		update_post_meta( $attendee_id, '_wpf_attendee_complete', true );

		// Mark the order as processed.
		$payment->update_meta( '_wpf_tribe_complete', true );

	}

	/**
	 * WooCommerce ticket created
	 *
	 * @access  public
	 * @return  void
	 */

	public function woocommerce_ticket_created( $attendee_id, $order_id, $product_id, $order_attendee_id ) {

		// Get settings.
		$ticket_id = get_post_meta( $attendee_id, '_tribe_wooticket_product', true );
		$settings  = get_post_meta( $ticket_id, 'wpf_settings', true );

		if ( empty( $settings ) ) {
			$settings = array( 'apply_tags' => array() );
		}

		if ( empty( $settings['add_attendees'] ) && 0 == $order_attendee_id ) {

			// If we're not syncing attendees, then send the data relative to the customer who made the order, just once.

			$order       = wc_get_order( $order_id );
			$user_id     = $order->get_user_id();
			$update_data = $this->get_attendee_meta( $attendee_id );

			if ( ! empty( $user_id ) ) {

				wp_fusion()->user->push_user_meta( $user_id, $update_data );

				$contact_id = wp_fusion()->user->get_contact_id( $user_id );

			} else {

				$contact_id = $this->guest_registration( $order->get_billing_email(), $update_data );

			}

			if ( ! empty( $settings['apply_tags'] ) ) {

				if ( ! empty( $user_id ) ) {

					wp_fusion()->user->apply_tags( $settings['apply_tags'], $user_id );

				} elseif ( ! empty( $contact_id ) ) {

					wpf_log( 'info', 0, 'Applying event tag(s) for guest checkout: ', array( 'tag_array' => $settings['apply_tags'] ) );
					wp_fusion()->crm->apply_tags( $settings['apply_tags'], $contact_id );

				}
			}

			update_post_meta( $attendee_id, '_wpf_attendee_complete', true );
		} elseif ( ! empty( $settings['add_attendees'] ) ) {

			// If we are syncing attendees

			$this->process_attendee( $attendee_id, $settings['apply_tags'] );

		}

		// Mark the order as processed
		update_post_meta( $order_id, '_wpf_tribe_complete', true );

	}

	/**
	 * Sync checkin status
	 *
	 * @access  public
	 * @return  void
	 */

	public function checkin( $attendee_id, $qr ) {
		$event_name = '';
		$settings   = array();

		$ticket_id = get_post_meta( $attendee_id, '_tribe_rsvp_product', true );
		if ( intval( $ticket_id ) !== 0 ) {
			$settings = get_post_meta( $ticket_id, 'wpf_settings', true );
		}

		$event_id = get_post_meta( $attendee_id, '_tribe_rsvp_event', true );
		if ( intval( $event_id ) !== 0 ) {
			$event_name = get_the_title( $event_id );
		}

		$user_id = get_post_meta( $attendee_id, '_tribe_tickets_attendee_user_id', true );

		if ( ! empty( $user_id ) ) {

			wp_fusion()->user->push_user_meta(
				$user_id,
				array(
					'event_checkin'       => true,
					'event_checkin_event' => $event_name,
				)
			);

			if ( ! empty( $settings ) ) {
				wp_fusion()->user->apply_tags( $settings['apply_tags_checkin'], $user_id );
			}
		} else {

			$contact_id = get_post_meta( $attendee_id, WPF_CONTACT_ID_META_KEY, true );

			if ( ! empty( $contact_id ) ) {

				wp_fusion()->crm->update_contact(
					$contact_id,
					array(
						'event_checkin'       => true,
						'event_checkin_event' => $event_name,
					)
				);

				if ( ! empty( $settings ) ) {
					wp_fusion()->crm->apply_tags( $settings['apply_tags_checkin'], $contact_id );
				}
			}
		}

	}


	/**
	 * Displays WPF tag option to ticket meta box.
	 *
	 * @access  public
	 * @return  mixed Settings fields
	 */

	public function tickets_metabox( $event_id, $ticket_id ) {

		if ( ! is_admin() || isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'tribe-ticket-edit-Tribe__Tickets_Plus__Commerce__EDD__Main' ) {
			return;
		}

		$settings = array(
			'apply_tags'         => array(),
			'apply_tags_deleted' => array(),
		);

		if ( get_post_meta( $ticket_id, 'wpf_settings', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $ticket_id, 'wpf_settings', true ) );
		}

		/*
		// Apply tags
		*/

		echo '<tr class="ticket wpf-ticket-wrapper' . ( ! empty( $ticket_id ) ? ' has-id' : ' no-id' ) . '" data-id="' . $ticket_id . '">';
		echo '<td>';
		echo '<p><label for="wpf-tet-apply-tags">Apply these tags in ' . wp_fusion()->crm->name . ':</label><br /></p>';
		echo '</td>';
		echo '<td>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags'],
					'meta_name' => 'ticket_wpf_settings',
					'field_id'  => 'apply_tags',
					'class'     => 'ticket_field ' . $ticket_id,
				)
			);

		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'tribe-ticket-edit-Tribe__Tickets__RSVP' ) {
			echo '<script type="text/javascript"> initializeTagsSelect("#ticket_form_table"); </script>';
		}

		echo '</td>';
		echo '</tr>';

		// Apply tags when attendee is deleted.
		echo '<tr class="ticket wpf-ticket-wrapper' . ( ! empty( $ticket_id ) ? ' has-id' : ' no-id' ) . '" data-id="' . $ticket_id . '">';
		echo '<td>';
		echo '<p><label for="wpf-tet-apply-tags">Apply these tags in ' . wp_fusion()->crm->name . ':</label><br /></p>';
		echo '</td>';
		echo '<td>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags_deleted'],
					'meta_name' => 'ticket_wpf_settings',
					'field_id'  => 'apply_tags_deleted',
					'class'     => 'ticket_field ' . $ticket_id,
				)
			);

		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'tribe-ticket-edit-Tribe__Tickets__RSVP' ) {
			echo '<script type="text/javascript"> initializeTagsSelect("#ticket_form_table"); </script>';
		}

		echo '</td>';
		echo '</tr>';

	}

	/**
	 * Displays WPF tag option to ticket meta box (v4.7.2 and up)
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function tickets_metabox_new( $event_id, $ticket_id ) {

		// Don't run on the frontend for Community Events
		if ( ! is_admin() ) {
			return;
		}

		$settings = array(
			'apply_tags'         => array(),
			'apply_tags_deleted' => array(),
			'apply_tags_checkin' => array(),
			'add_attendees'      => false,
		);

		if ( get_post_meta( $ticket_id, 'wpf_settings', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $ticket_id, 'wpf_settings', true ) );
		}

		/*
		// Apply tags
		*/

		echo '<div class="input_block" style="margin: 20px 0;">';

			echo '<label style="width: 132px;" class="ticket_form_label ticket_form_left" for="wpf-tet-apply-tags">' . __( 'Apply tags', 'wp-fusion' ) . ':</label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags'],
					'meta_name' => 'ticket_wpf_settings',
					'field_id'  => 'apply_tags',
					'class'     => 'ticket_form_right ticket_field',
				)
			);

			echo '<span class="tribe_soft_note ticket_form_right" style="margin-top: 5px;">' . sprintf( __( 'These tags will be applied in %s when someone RSVPs or purchases this ticket.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';

		echo '</div>';

		/*
		// Add tags an attendee is deleted from the event.
		*/
		echo '<div class="input_block" style="margin: 20px 0;">';

			echo '<label style="width: 132px;" class="ticket_form_label ticket_form_left" for="wpf-tet-apply-tags">' . __( 'Apply tags - Deleted', 'wp-fusion' ) . ':</label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags_deleted'],
					'meta_name' => 'ticket_wpf_settings',
					'field_id'  => 'apply_tags_deleted',
					'class'     => 'ticket_form_right ticket_field',
				)
			);

			echo '<span class="tribe_soft_note ticket_form_right" style="margin-top: 5px;">' . sprintf( __( 'These tags will be applied in %s when an attendee is deleted from the event.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';

		echo '</div>';

		/*
		// Add tags an attendee checkin in an event.
		*/
		echo '<div class="input_block" style="margin: 20px 0;">';

			echo '<label style="width: 132px;" class="ticket_form_label ticket_form_left" for="wpf-tet-apply-tags">' . __( 'Apply tags - Check-in', 'wp-fusion' ) . ':</label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['apply_tags_checkin'],
					'meta_name' => 'ticket_wpf_settings',
					'field_id'  => 'apply_tags_checkin',
					'class'     => 'ticket_form_right ticket_field',
				)
			);

			echo '<span class="tribe_soft_note ticket_form_right" style="margin-top: 5px;">' . sprintf( __( 'These tags will be applied in %s when an attendee has checked in to an event.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';

		echo '</div>';

		echo '<div class="input_block" style="margin: 10px 0 25px;">';
			echo '<label style="width: 132px;" class="ticket_form_label ticket_form_left" for="wpf-add-attendees">' . __( 'Add attendees:', 'wp-fusion' ) . '</label>';
			echo '<input class="checkbox" type="checkbox" style="" id="wpf-add-attendees" name="ticket_wpf_settings[add_attendees]" value="1" ' . checked( $settings['add_attendees'], 1, false ) . ' />';
			echo '<span class="tribe_soft_note">' . sprintf( __( 'Add each event attendee as a separate contact in %s. Requires <a href="https://theeventscalendar.com/knowledgebase/k/collecting-attendee-information/" target="_blank">Individual Attendee Collection</a> to be enabled for this ticket.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</div>';

		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'tribe-ticket-edit' ) {
			echo '<script type="text/javascript">initializeTagsSelect( "#ticket_form_table" );</script>';
		}

	}

	/**
	 * Save meta box data
	 *
	 * @access  public
	 * @return  void
	 */

	public function tickets_after_save_ticket( $post_id, $ticket, $raw_data, $class ) {

		$settings = get_post_meta( $ticket->ID, 'wpf_settings', true );
		if ( empty( $settings ) ) {
			$settings = array();
		}

		if ( isset( $raw_data['ticket_wpf_settings'] ) ) {

			if ( isset( $raw_data['ticket_wpf_settings']['add_attendees'] ) ) {
				$settings['add_attendees'] = true;
			}

			update_post_meta( $ticket->ID, 'wpf_settings', $settings );

		} else {

			if ( ! empty( $settings['add_attendees'] ) ) {

				$settings['add_attendees'] = false;

				update_post_meta( $ticket->ID, 'wpf_settings', $settings );

			}
		}

	}

	/**
	 * Save Ticket/RSVP in ajax.
	 *
	 * @param integer $post_id
	 * @param object  $ticket
	 * @param array   $data
	 * @return void
	 */
	public function ajax_save_ticket( $post_id, $ticket, $data ) {
		if ( $ticket->ID != '' ) {
			// In case removing all tags and it will not return any values.
			$apply_tags = array();
			if ( isset( $data['ticket_wpf_settings'] ) ) {
				$apply_tags = $data['ticket_wpf_settings'];
			}
			update_post_meta( $ticket->ID, 'wpf_settings', $apply_tags );
		}
	}

	/**
	 * Add deleted tags when an attendee is removed.
	 *
	 * @param integer $post_id
	 * @return void
	 */
	public function delete_attendee( $post_id ) {
		// Check if its an attendee being deleted.
		if ( get_post_type( $post_id ) !== 'tribe_rsvp_attendees' ) {
			return;
		}
		$attendee_email = get_post_meta( $post_id, '_tribe_rsvp_email', true );
		$ticket_id      = absint( get_post_meta( $post_id, '_tribe_rsvp_product', true ) );

		if ( $ticket_id === 0 || $attendee_email == '' ) {
			return;
		}

		$settings = get_post_meta( $ticket_id, 'wpf_settings', true );
		if ( ! empty( $settings ) && isset( $settings['apply_tags_deleted'] ) && ! empty( $settings['apply_tags_deleted'] ) ) {
			// Check if user exist in CRM.

			$user_id = get_post_meta( $post_id, '_tribe_tickets_attendee_user_id', true );

			if ( ! empty( $user_id ) ) {
				wp_fusion()->user->apply_tags( $settings['apply_tags_deleted'], $user_id );
			} else {

				$contact_id = get_post_meta( $post_id, WPF_CONTACT_ID_META_KEY, true );

				if ( ! empty( $contact_id ) ) {
					wp_fusion()->crm->apply_tags( $settings['apply_tags_deleted'], $contact_id );
				}
			}
		}
	}

	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds Event Tickets checkbox to available export options.
	 *
	 * @since  3.37.24
	 *
	 * @param  array $options The options.
	 * @return array The options.
	 */
	public function export_options( $options ) {

		$options['tribe_tickets'] = array(
			'label'   => __( 'Event Tickets attendees', 'wp-fusion' ),
			'title'   => __( 'Attendees', 'wp-fusion' ),
			'tooltip' => sprintf( __( 'Find Event Tickets attendees that have not been processed by WP Fusion and creates contact records %s and applies tags based on the settings on the corresponding event.', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		return $options;

	}

	/**
	 * Gets total attendees to be processed.
	 *
	 * @since  3.37.24
	 *
	 * @return array The attendee IDs.
	 */
	public function batch_init() {
		$args = array(
			'post_type'      => 'tribe_rsvp_attendees',
			'posts_per_page' => 1000,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_wpf_attendee_complete',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$attendees = get_posts( $args );

		if ( empty( $attendees ) ) {
			return array();
		}

		$attendees = $this->filter_out_repeated_attendees( $attendees );

		return $attendees;

	}

	/**
	 * Filter out duplicate attendees by email.
	 *
	 * @since  3.37.24
	 *
	 * @param  array $attendees The attendee IDs.
	 * @return array The filtered attendee IDs.
	 */
	private function filter_out_repeated_attendees( $attendees ) {
		$filterd = array();
		$emails  = array();
		if ( empty( $attendees ) ) {
			return $filterd;
		}

		foreach ( $attendees as $attendee_id ) {
			$attendee_email = get_post_meta( $attendee_id, '_tribe_tickets_email', true );
			if ( empty( $attendee_email ) ) {
				$attendee_email = get_post_meta( $attendee_id, '_tribe_rsvp_email', true );
			}

			if ( ! in_array( $attendee_email, $emails ) ) {
				$emails[]  = $attendee_email;
				$filterd[] = $attendee_id;
			}
		}
		return $filterd;
	}

	/**
	 * Process individual attendees.
	 *
	 * @since  3.37.24
	 *
	 * @param  int $attendee_id The attendee ID.
	 */
	public function batch_step( $attendee_id ) {

		$event_id  = get_post_meta( $attendee_id, '_tribe_wooticket_event', true );
		$ticket_id = get_post_meta( $attendee_id, '_tribe_wooticket_product', true );

		if ( empty( $event_id ) ) {
			$event_id  = get_post_meta( $attendee_id, '_tribe_eddticket_event', true );
			$ticket_id = get_post_meta( $attendee_id, '_tribe_eddticket_product', true );
		}

		if ( empty( $event_id ) ) {
			$event_id  = get_post_meta( $attendee_id, '_tribe_rsvp_event', true );
			$ticket_id = get_post_meta( $attendee_id, '_tribe_rsvp_product', true );
		}

		$this->rsvp_ticket_created( $attendee_id, $event_id, $ticket_id, 1 );

	}

}

new WPF_Tribe_Tickets();
