<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Events_Manager extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'events-manager';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Events Manager';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/events-manager/';

	/**
	 * Gets things started
	 *
	 * @since   3.33
	 */
	public function init() {

		add_filter( 'em_booking_add_registration_result', array( $this, 'add_registration' ), 10, 3 );
		add_filter( 'em_booking_set_status', array( $this, 'approve_booking' ), 10, 2 );
		add_filter( 'em_booking_set_status', array( $this, 'booking_cancelled' ), 10, 2 );
		add_action( 'em_bookings_deleted', array( $this, 'booking_deleted' ), 10, 2 );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'save_post_event', array( $this, 'save_meta_box_data' ) );

		// Taxonomy settings.
		add_action( 'admin_init', array( $this, 'register_taxonomy_form_fields' ) );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

		// General settings.
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 20, 2 );

		// Export functions.
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_events_manager_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_events_manager', array( $this, 'batch_step' ) );

	}

	/**
	 * Remove / apply cancelled tags for deleted/rejected/cancelled bookings.
	 *
	 * @since 3.38.43
	 *
	 * @param object $booking The booking.
	 */
	private function cancel_booking( $booking ) {

		$settings = get_post_meta( $booking->event->post_id, 'wpf_settings_event', true );

		// Remove applied tags.
		if ( ! empty( $settings ) && boolval( $settings['remove_tags_cancelled'] ) === true && ! empty( $settings['apply_tags'] ) ) {

			if ( absint( $booking->person_id ) === 0 ) {

				$contact_id = $booking->booking_meta['contact_id'];

				if ( $contact_id ) {

					wpf_log( 'info', 0, 'Booking ' . $booking->get_status() . '. Removing tags from Events Manager attendee for contact #' . $contact_id . ': ', array( 'tag_array' => $settings['apply_tags'] ) );

					wp_fusion()->crm->remove_tags( $settings['apply_tags'], $contact_id );
				}
			} else {
				wp_fusion()->user->remove_tags( $settings['apply_tags'], $booking->person_id );
			}
		}

		// Apply tags Cancelled.
		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_cancelled'] ) ) {

			if ( absint( $booking->person_id ) === 0 ) {

				$contact_id = $booking->booking_meta['contact_id'];

				if ( $contact_id ) {

					wpf_log( 'info', 0, 'Booking cancelled. Applying tags to Events Manager attendee for contact #' . $contact_id . ': ', array( 'tag_array' => $settings['apply_tags_cancelled'] ) );

					wp_fusion()->crm->apply_tags( $settings['apply_tags_cancelled'], $contact_id );
				}
			} else {
				wp_fusion()->user->apply_tags( $settings['apply_tags_cancelled'], $booking->person_id );
			}
		}
	}

	/**
	 * Triggered when bookings are deleted.
	 *
	 * @since 3.38.44
	 *
	 * @param int   $result      The result.
	 * @param array $booking_ids The booking IDs.
	 */
	public function booking_deleted( $result, $booking_ids ) {
		foreach ( $booking_ids as $booking_id ) {
			$booking = em_get_booking( $booking_id );
			$this->cancel_booking( $booking );
		}
	}


	/**
	 * Apply/Remove tags to user after booking is cancelled.
	 *
	 * @since  3.38.42
	 *
	 * @param  int    $result  The result.
	 * @param  object $booking The booking.
	 * @return int    Result
	 */
	public function booking_cancelled( $result, $booking ) {

		if ( 2 === $booking->booking_status || 3 === $booking->booking_status ) {
			// Rejected or cancelled.
			$this->cancel_booking( $booking );
		}

		return $result;
	}

	/**
	 * Apply tags to user after booking is approved.
	 *
	 * @since  3.37.4
	 *
	 * @param  int    $result  The result.
	 * @param  object $booking The booking.
	 * @return int    Result
	 */
	public function approve_booking( $result, $booking ) {

		// Check status to be approved.

		if ( 1 !== $booking->booking_status ) {
			return $result;
		}

		$post_id = $booking->event->post_id;

		if ( empty( $post_id ) ) {

			// Sometimes the booking class is returned without an event like with Stripe payments.

			$new_booking = em_get_booking( $booking->booking_id );
			$event       = $new_booking->get_event();
			$post_id     = $event->post_id;
		}

		// Add tag to user if booking is approved.
		$settings = get_post_meta( $post_id, 'wpf_settings_event', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_approved'] ) ) {

			if ( absint( $booking->person_id ) === 0 ) {

				$contact_id = $booking->booking_meta['contact_id'];

				if ( $contact_id ) {

					wpf_log( 'info', 0, 'Applying tags for Events Manager attendee for contact #' . $contact_id . ': ', array( 'tag_array' => $apply_tags ) );

					wp_fusion()->crm->apply_tags( $settings['apply_tags_approved'], $contact_id );
				}
			} else {
				wp_fusion()->user->apply_tags( $settings['apply_tags_approved'], $booking->person_id );
			}
		}

		return $result;
	}

	/**
	 * Apply tags and sync data after event registration
	 *
	 * @access public
	 * @return object Registration
	 */

	public function add_registration( $registration, $booking, $notices ) {

		if ( ! wpf_get_option( 'events_manager_guests', true ) && empty( $booking->person_id ) ) {
			return $registration; // If guests are disabled.
		}

		// Sync data

		$update_data = array();
		if ( isset( $booking->booking_meta['registration'] ) && isset( $booking->booking_meta['booking'] ) ) {
			$update_data = array_merge( $booking->booking_meta['registration'], $booking->booking_meta['booking'] );
		}

		$event = em_get_event( $booking->event->post_id, 'post_id' );

		$event_data = array(
			'event_name' => $event->event_name,
			'event_date' => get_post_meta( $booking->event->post_id, '_event_start_local', true ),
			'event_time' => get_post_meta( $booking->event->post_id, '_event_start_time', true ),
		);

		$categories = get_the_terms( $booking->event->post_id, 'event-categories' );

		if ( false !== $categories ) {
			$event_data['event_categories'] = wp_list_pluck( $categories, 'name' );
		}

		$update_data = array_merge( $update_data, $event_data );

		// Ticket

		if ( ! empty( $booking->tickets_bookings->tickets_bookings ) ) {
			$ticket                     = reset( $booking->tickets_bookings->tickets_bookings );
			$ticket                     = $ticket->get_ticket();
			$update_data['ticket_name'] = $ticket->name;
		}

		// Location

		$location = $event->get_location();

		if ( $location->location_id ) {
			$update_data['event_location_name']    = $location->location_name;
			$update_data['event_location_address'] = $location->get_full_address();
		}

		// Guest Registeration
		if ( absint( $booking->person_id ) === 0 ) {
			$guest_email = $booking->booking_meta['registration']['user_email'];
			if ( empty( $guest_email ) ) {
				return $registration;
			}
			$contact_id  = $this->guest_registration( $guest_email, $update_data );
			$update_data = array_merge( $update_data, $booking->booking_meta['registration'] );
			if ( ! is_wp_error( $contact_id ) && empty( $contact_id ) ) {

				// Add new contact
				$contact_id = wp_fusion()->crm->add_contact( $update_data );

				if ( is_wp_error( $contact_id ) ) {

					wpf_log( $contact_id->get_error_code(), 0, 'Error adding contact to ' . wp_fusion()->crm->name . ': ' . $contact_id->get_error_message() );
					return $registration;

				}
			} elseif ( ! is_wp_error( $contact_id ) && ! empty( $contact_id ) ) {
				wp_fusion()->crm->update_contact( $contact_id, $update_data );
			}
			// Save contact id to use it for approval
			$this->add_contact_id_to_booking_meta( $booking, $contact_id );

		} else {
			wp_fusion()->user->push_user_meta( $booking->person_id, $update_data );
		}

		// Apply tags.

		$apply_tags = array();

		$settings = get_post_meta( $booking->event->post_id, 'wpf_settings_event', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags'] );
		}

		// Approved bookings.

		if ( 1 === $booking->booking_status ) {

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_approved'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_approved'] );
			}
		}

		// Categories.

		foreach ( get_object_taxonomies( 'event' ) as $event_taxonomy ) {

			$event_terms = get_the_terms( $booking->event->post_id, $event_taxonomy );

			if ( ! empty( $event_terms ) ) {

				foreach ( $event_terms as $term ) {

					$term_tags = get_term_meta( $term->term_id, 'wpf_settings_event', true );

					if ( ! empty( $term_tags ) && ! empty( $term_tags['apply_tags'] ) ) {
						$apply_tags = array_merge( $apply_tags, $term_tags['apply_tags'] );
					}
				}
			}
		}

		if ( ! empty( array_filter( $apply_tags ) ) ) {
			if ( absint( $booking->person_id ) === 0 ) {
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );
			} else {
				wp_fusion()->user->apply_tags( $apply_tags, $booking->person_id );
			}
		}

		return $registration;

	}


	/**
	 * Add contact id from CRM into booking meta in case of guest registeration.
	 *
	 * @since  3.37.30
	 *
	 * @param  object $booking    The booking.
	 * @param  string $contact_id The contact ID.
	 * @return bool   Success or fail.
	 */
	private function add_contact_id_to_booking_meta( $booking, $contact_id ) {
		global $wpdb;
		$booking->booking_meta['contact_id'] = $contact_id;
		$booking_meta                        = serialize( $booking->booking_meta );
		return $wpdb->update( EM_BOOKINGS_TABLE, array( 'booking_meta' => $booking_meta ), array( 'booking_id' => $booking->booking_id ) );
	}

	/**
	 * Adds meta box
	 *
	 * @access public
	 * @return void
	 */

	public function add_meta_box( $post_id, $data ) {

		add_meta_box( 'wpf-event-meta', 'WP Fusion - Event Settings <a href="wpfusion.com/documentation/events/events-manager/" target="_blank">' . esc_html__( 'View documentation', 'wp-fusion' ) . ' &rarr;</a>', array( $this, 'meta_box_callback' ), 'event' );
	}


	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback( $post ) {

		$settings = array(
			'apply_tags'            => array(),
			'apply_tags_approved'   => array(),
			'apply_tags_cancelled'  => array(),
			'remove_tags_cancelled' => false,
		);

		if ( get_post_meta( $post->ID, 'wpf_settings_event', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf_settings_event', true ) );
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags">' . __( 'Apply Tags', 'wp-fusion' ) . ':</label></th>';
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

		echo '<tr>';

		echo '<th scope="row"><label for="remove_tags_cancelled">' . __( 'Remove Tags', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		echo '<div><input class="checkbox" type="checkbox" id="remove_tags_cancelled" name="wpf_settings_event[remove_tags_cancelled]" value="1" ' . checked( $settings['remove_tags_cancelled'], 1, false ) . ' />';

		echo '<label class="description" for="remove_tags_cancelled">' . sprintf( __( 'Remove the tags specified in Apply Tags (above) if the booking is cancelled.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</div></td>';

		echo '</tr>';

		// Approve booking tags
		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags">' . __( 'Apply Tags - Approved', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_approved'],
			'meta_name' => 'wpf_settings_event',
			'field_id'  => 'apply_tags_approved',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . sprintf( __( 'The selected tags will be applied in %s when an event booking is approved.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</td>';

		echo '</tr>';

		// Apply tags - Cancelled
		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_cancelled">' . __( 'Apply Tags - Cancelled', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_cancelled'],
			'meta_name' => 'wpf_settings_event',
			'field_id'  => 'apply_tags_cancelled',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . sprintf( __( 'The selected tags will be applied in %s when an event booking is cancelled.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
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
		} else {
			delete_post_meta( $post_id, 'wpf_settings_event' );
		}

	}


	/**
	 * Add settings to event taxonomies.
	 *
	 * @since 3.37.29
	 */
	public function register_taxonomy_form_fields() {

		$taxonomies = get_object_taxonomies( 'event' );

		foreach ( $taxonomies as $slug ) {
			add_action( $slug . '_edit_form_fields', array( $this, 'taxonomy_form_fields' ), 10, 2 );
			add_action( 'edited_' . $slug, array( $this, 'save_taxonomy_form_fields' ), 10, 2 );
		}

	}

	/**
	 * Output settings on event taxonomies.
	 *
	 * @since 3.37.29
	 *
	 * @param WP_Term $term   The term.
	 */
	public function taxonomy_form_fields( $term ) {

		?>

		<tr class="form-field">
			<th style="padding-bottom: 0px;" colspan="2"><h3><?php _e( 'WP Fusion - Event Settings', 'wp-fusion' ); ?></h3></th>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label><?php _e( 'Apply Tags', 'wp-fusion' ); ?></label></th>
			<td>
				<?php

				// retrieve values for tags to be applied
				$settings = get_term_meta( $term->term_id, 'wpf_settings_event', true );

				if ( empty( $settings ) ) {
					$settings = array( 'apply_tags' => array() );
				}

				$args = array(
					'setting'   => $settings['apply_tags'],
					'meta_name' => 'wpf_settings_event',
					'field_id'  => 'apply_tags',
				);

				wpf_render_tag_multiselect( $args );
				?>

				<span class="description"><?php printf( __( 'Apply these tags in %s when someone registers for an event in this category.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></span>

			</td>
		</tr>

			<?php

	}

	/**
	 * Save event taxonomy settings.
	 *
	 * @since 3.37.29
	 *
	 * @param int $term_id The term ID.
	 */
	public function save_taxonomy_form_fields( $term_id ) {

		if ( ! empty( $_POST['wpf_settings_event'] ) ) {

			update_term_meta( $term_id, 'wpf_settings_event', $_POST['wpf_settings_event'] );

		} else {

			delete_term_meta( $term_id, 'wpf_settings_event' );

		}

	}


	/**
	 * Adds field group for Tribe Tickets to contact fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['events_manager_user'] = array(
			'title'  => 'Events Manager - User',
			'fields' => array(),
		);

		$field_groups['events_manager_event'] = array(
			'title'  => 'Events Manager - Event',
			'fields' => array(),
		);

		$field_groups['events_manager_booking'] = array(
			'title'  => 'Events Manager - Booking',
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

		// Custom user fields

		$user_fields = get_option( 'em_user_fields', array() );

		foreach ( $user_fields as $meta_key => $field ) {

			$meta_fields[ $meta_key ] = array(
				'label' => $field['label'],
				'type'  => $field['type'],
				'group' => 'events_manager_user',
			);
		}

		// Event fields

		$meta_fields['ticket_name'] = array(
			'label' => 'Ticket Name',
			'type'  => 'text',
			'group' => 'events_manager_event',
		);

		$meta_fields['event_name'] = array(
			'label' => 'Event Name',
			'type'  => 'text',
			'group' => 'events_manager_event',
		);

		$meta_fields['event_date'] = array(
			'label' => 'Event Date',
			'type'  => 'date',
			'group' => 'events_manager_event',
		);

		$meta_fields['event_time'] = array(
			'label' => 'Event Time',
			'type'  => 'text',
			'group' => 'events_manager_event',
		);

		$meta_fields['event_location_name'] = array(
			'label' => 'Event Location Name',
			'type'  => 'text',
			'group' => 'events_manager_event',
		);

		$meta_fields['event_location_address'] = array(
			'label' => 'Event Location Address',
			'type'  => 'text',
			'group' => 'events_manager_event',
		);

		$meta_fields['event_categories'] = array(
			'label' => 'Event Categories',
			'type'  => 'multiselect',
			'group' => 'events_manager_event',
		);

		// Custom booking fields

		if ( defined( 'EM_META_TABLE' ) ) {

			global $wpdb;
			$forms_data = $wpdb->get_results( 'SELECT meta_id, meta_value FROM ' . EM_META_TABLE . " WHERE meta_key = 'booking-form'" );

			foreach ( $forms_data as $form_data ) {

				$form = unserialize( $form_data->meta_value );

				foreach ( $form['form'] as $meta_key => $field ) {

					if ( ! isset( $meta_fields[ $meta_key ] ) ) {

						$meta_fields[ $meta_key ] = array(
							'label' => $field['label'],
							'type'  => $field['type'],
							'group' => 'events_manager_booking',
						);

					}
				}
			}
		}

		return $meta_fields;

	}


	/**
	 * Add a custom field to the Integrations tab in the WP Fusion settings.
	 *
	 * @since  3.38.10
	 *
	 * @param  array $settings The registered settings.
	 * @param  array $options  The options in the database.
	 * @return array The registered settings.
	 */
	public function register_settings( $settings, $options ) {

		$settings['events_manager_header'] = array(
			'title'   => __( 'Events Manager', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['events_manager_guests'] = array(
			'title'   => __( 'Sync Guests', 'wp-fusion' ),
			'desc'    => sprintf( __( 'Sync guest bookings with %s.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'std'     => 1,
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;

	}


	/**
	 * Adds Events manager to available export options.
	 *
	 * @since  3.37.25
	 *
	 * @param  array $options The export options.
	 * @return array The export options.
	 */
	public function export_options( $options ) {

		$options['events_manager'] = array(
			'label'   => __( 'Events Manager bookings', 'wp-fusion' ),
			'title'   => __( 'Bookings', 'wp-fusion' ),
			'tooltip' => __( 'For all Events Manager bookings, adds/updates contacts while applying tags based on the associated event.', 'wp-fusion' ),
		);

		return $options;

	}

	/**
	 * Get total bookings to be processed.
	 *
	 * @since  3.37.25
	 *
	 * @return array Array of booking IDs.
	 */
	public function batch_init() {

		$args = array(
			'limit'  => 10000,
			'scope'  => 'all',
			'status' => 'all',
		);

		$bookings    = EM_Bookings::get( $args );
		$booking_ids = array();

		if ( ! empty( $bookings ) ) {
			foreach ( $bookings as $booking ) {
				$booking_ids[] = $booking->booking_id;
			}
		}

		return $booking_ids;
	}

	/**
	 * Processes bookings one at a time.
	 *
	 * @since 3.37.25
	 *
	 * @param int $booking_id The booking ID.
	 */
	public function batch_step( $booking_id ) {
		$booking = em_get_booking( $booking_id );
		$event   = $booking->get_event();
		$booking->get_tickets_bookings()->tickets_bookings;
		$this->add_registration( true, $booking, array() );
	}




}

new WPF_Events_Manager();
