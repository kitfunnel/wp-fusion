<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Event_Espresso extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'event-espresso';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Event espresso';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/event-espresso/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'AHEE__EE_Registration__set_status__after_update', array( $this, 'registration_status_update' ), 10, 4 );
		add_action( 'AHEE__EE_Base_Class__save__end', array( $this, 'save_checkin' ), 10, 2 );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

		add_action( 'AHEE__event_tickets_datetime_ticket_row_template__advanced_details_end', array( $this, 'show_admin_settings' ), 10, 2 );
		add_action( 'save_post_espresso_events', array( $this, 'save_meta_box_data' ) );

		// Export functions
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_ee_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_ee', array( $this, 'batch_step' ) );

		// Super secret admin / debugging tools
		add_action( 'wpf_settings_page_init', array( $this, 'settings_page_init' ) );

	}


	/**
	 * Create / update contacts and apply tags after checkout
	 *
	 * @access  public
	 * @return  void
	 */

	public function registration_status_update( $registration, $old_status_id = false, $new_status_id = false, $context = false ) {

		// Get the WPF settings
		$ticket_id = $registration->ticket_ID();
		$event_id  = $registration->event_ID();
		$settings  = get_post_meta( $event_id, 'wpf_settings_event_espresso', true );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		// Maybe only run on first registration if syncing attendees is disabled

		if ( ! $registration->is_primary_registrant() && isset( $settings['add_attendees'] ) && ! isset( $settings['add_attendees'][ $ticket_id ] ) ) {
			return;
		}

		$event       = $registration->event();
		$ticket      = $registration->ticket();
		$attendee    = $registration->attendee();
		$event_title = get_the_title( $event_id );

		//
		// Attendee data
		//

		$update_data = array();

		if ( ! empty( $attendee ) ) {

			$attendee_data = array(
				'ee_fname'    => $attendee->fname(),
				'ee_lname'    => $attendee->lname(),
				'ee_email'    => $attendee->email(),
				'ee_address'  => $attendee->address(),
				'ee_address2' => $attendee->address2(),
				'ee_city'     => $attendee->city(),
				'ee_country'  => $attendee->country(),
				'ee_state'    => $attendee->state(),
				'ee_zip'      => $attendee->zip(),
				'ee_phone'    => $attendee->phone(),
				'first_name'  => $attendee->fname(),
				'last_name'   => $attendee->lname(),
				'user_email'  => $attendee->email(),
			);

			$update_data = array_merge( $update_data, $attendee_data );

		}

		// Event data

		$first_datetime = $ticket->first_datetime();

		$event_data = array(
			'ee_registration_status' => EEH_Template::pretty_status( $new_status_id, false, 'sentence' ),
			'ee_ticket_name'         => $ticket->name(),
			'ee_event_name'          => $event->name(),
			'ee_event_start_date'    => $first_datetime->start_date_and_time(),
			'ee_event_start_time'    => $first_datetime->start_time(),
		);

		$update_data = array_merge( $update_data, $event_data );

		// Venue data

		$venues = $event->venues();

		if ( ! empty( $venues ) ) {

			foreach ( $venues as $venue ) {

				$update_data['ee_event_venue_name']         = $venue->name();
				$update_data['ee_event_venue_address']      = $venue->address();
				$update_data['ee_event_venue_address_2']    = $venue->address2();
				$update_data['ee_event_venue_city']         = $venue->city();
				$update_data['ee_event_venue_state_name']   = $venue->state_name();
				$update_data['ee_event_venue_country_name'] = $venue->country_name();
				$update_data['ee_event_venue_zip']          = $venue->zip();

			}
		}

		// Custom fields
		$answers = $registration->answers();

		if ( ! empty( $answers ) ) {

			foreach ( $answers as $answer ) {

				$update_data[ 'ee_' . $answer->question_ID() ] = $answer->value();

			}
		}

		$update_data = apply_filters( 'wpf_event_espresso_customer_data', $update_data, $registration );

		// Allow for cancelling

		if ( false === $update_data ) {
			return false;
		}

		//
		// Get tags to apply
		//

		$apply_tags = array();

		if ( EEM_Registration::status_id_approved == $new_status_id && ! empty( $settings['apply_tags'][ $ticket_id ] ) ) {

			$apply_tags = $settings['apply_tags'][ $ticket_id ];

		} elseif ( EEM_Registration::status_id_pending_payment == $new_status_id && ! empty( $settings['apply_tags_pending'][ $ticket_id ] ) ) {

			$apply_tags = $settings['apply_tags_pending'][ $ticket_id ];

		}

		// Get dynamic tags
		if ( EEM_Registration::status_id_approved == $new_status_id ) {

			$dynamic_tags = $this->get_dynamic_tags( $update_data );

			$apply_tags = array_merge( $apply_tags, $dynamic_tags );

		}

		// Get tags for status
		$apply_tags = array_merge( $apply_tags, wpf_get_option( 'ee_status_tagging_' . $new_status_id, array() ) );

		// Send update data
		$user = get_user_by( 'email', $update_data['user_email'] );

		if ( is_object( $user ) ) {

			// Logged in checkouts. Only sync meta on Pending, don't need to do it again once approved (unless we're doing an export)

			if ( EEM_Registration::status_id_pending_payment == $new_status_id || false == $old_status_id ) {
				wp_fusion()->user->push_user_meta( $user->ID, $update_data );
			}

			if ( ! empty( $apply_tags ) ) {
				wp_fusion()->user->apply_tags( $apply_tags, $user->ID );
			}

			$contact_id = wp_fusion()->user->get_contact_id( $user->ID );

		} else {

			// Guest checkouts

			// Get contact ID from registration meta if we've just come from pending (saves an API call)

			if ( EEM_Registration::status_id_pending_payment == $old_status_id ) {

				$contact_id = $registration->get_extra_meta( WPF_CONTACT_ID_META_KEY, true );

			} else {

				$contact_id = wp_fusion()->crm->get_contact_id( $update_data['user_email'] );

			}

			if ( EEM_Registration::status_id_pending_payment == $new_status_id || false == $old_status_id ) {

				// Only create a contact / sync meta on Pending, don't need to do it again once approved (unless we're doing an export)

				wpf_log(
					'info',
					0,
					'New registration <a href="' . admin_url( 'admin.php?page=espresso_registrations&action=view_registration&_REG_ID=' . $registration->ID() ) . '" target="_blank">#' . $registration->ID() . '</a> for event <a href="' . admin_url( 'admin.php?page=espresso_events&action=edit&post=' . $event_id . '&action=edit' ) . '" target="_blank">' . $event_title . '</a>: ',
					array(
						'meta_array' => $update_data,
						'source'     => 'event-espresso',
					)
				);

				if ( ! is_wp_error( $contact_id ) && false !== $contact_id ) {

					// Existing contact
					wp_fusion()->crm->update_contact( $contact_id, $update_data );

					do_action( 'wpf_guest_contact_updated', $contact_id, $update_data['user_email'] );

				} else {

					// New contact
					$contact_id = wp_fusion()->crm->add_contact( $update_data );

					if ( is_wp_error( $contact_id ) ) {

						wpf_log( $contact_id->get_error_code(), 0, 'Error adding contact: ' . $contact_id->get_error_message(), array( 'source' => 'event-espresso' ) );
						return false;

					}

					do_action( 'wpf_guest_contact_created', $contact_id, $update_data['user_email'] );
				}
			}

			if ( ! empty( $apply_tags ) ) {

				wpf_log(
					'info',
					0,
					'Applying tag(s) for event registration <a href="' . admin_url( 'admin.php?page=espresso_registrations&action=view_registration&_REG_ID=' . $registration->ID() ) . '" target="_blank">#' . $registration->ID() . '</a>: ',
					array(
						'tag_array' => $apply_tags,
						'source'    => 'event-espresso',
					)
				);

				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}
		}

		// Save contact ID
		$registration->update_extra_meta( WPF_CONTACT_ID_META_KEY, $contact_id );

		// Update some stuff in the transaction as well in case we need it
		$transaction = $registration->transaction();

		if ( EEM_Registration::status_id_pending_payment == $new_status_id ) {

			$transaction->update_extra_meta( 'wpf_complete_pending', true );
			$registration->update_extra_meta( 'wpf_complete_pending', true );

		} elseif ( EEM_Registration::status_id_approved == $new_status_id ) {

			// Only do Payment Complete once on the transaction
			$complete = $transaction->get_extra_meta( 'wpf_complete', true );

			if ( empty( $complete ) ) {

				do_action( 'wpf_event_espresso_payment_complete', $registration, $contact_id );

			}

			$registration->update_extra_meta( 'wpf_complete', current_time( 'Y-m-d H:i:s' ) );
			$transaction->update_extra_meta( 'wpf_complete', current_time( 'Y-m-d H:i:s' ) );

		}

	}

	/**
	 * Create / update contacts and apply tags after checkout
	 *
	 * @access  public
	 * @return  void
	 */

	public function save_checkin( $checkin, $results ) {

		if ( ! is_a( $checkin, 'EE_Checkin' ) ) {
			return;
		}

		$registration = EEM_Registration::instance()->get_one_by_ID( $checkin->registration_id() );
		$attendee     = $registration->attendee();
		$user         = get_user_by( 'email', $attendee->email() );

		if ( ! $user ) {

			$contact_id = $registration->get_extra_meta( WPF_CONTACT_ID_META_KEY, true );

			if ( empty( $contact_id ) ) {
				$contact_id = wp_fusion()->crm->get_contact_id( $attendee->email() );
			}
		}

		$ticket_id = $registration->ticket_ID();
		$event_id  = $registration->event_ID();
		$settings  = get_post_meta( $event_id, 'wpf_settings_event_espresso', true );

		if ( empty( $settings ) ) {
			return;
		}

		if ( true == $checkin->status() && ! empty( $settings['apply_tags_checked_in'] ) && ! empty( $settings['apply_tags_checked_in'][ $ticket_id ] ) ) {

			if ( $user ) {

				// Registered user
				wp_fusion()->user->apply_tags( $settings['apply_tags_checked_in'][ $ticket_id ], $user->ID );

			} elseif ( ! empty( $contact_id ) ) {

				// Identified contact
				wpf_log(
					'info',
					0,
					'Event check-in applying tag(s) to contact ID #' . $contact_id . ' (' . $attendee->email() . '): ',
					array(
						'tag_array' => $settings['apply_tags_checked_in'][ $ticket_id ],
					)
				);

				wp_fusion()->crm->apply_tags( $settings['apply_tags_checked_in'][ $ticket_id ], $contact_id );

			} else {

				// No contact record found
				wpf_log( 'notice', 0, 'Unable to apply check-in tags, couldn\'t find contact record for email ' . $attendee->email() );

			}
		} elseif ( ! empty( $settings['apply_tags_checked_out'] ) && ! empty( $settings['apply_tags_checked_out'][ $ticket_id ] ) ) {

			if ( $user ) {

				// Registered user
				wp_fusion()->user->apply_tags( $settings['apply_tags_checked_out'][ $ticket_id ], $user->ID );

			} elseif ( ! empty( $contact_id ) ) {

				wpf_log(
					'info',
					0,
					'Event check-out applying tag(s) to contact ID #' . $contact_id . ' (' . $attendee->email() . '): ',
					array(
						'tag_array' => $settings['apply_tags_checked_out'][ $ticket_id ],
					)
				);

				wp_fusion()->crm->apply_tags( $settings['apply_tags_checked_out'][ $ticket_id ], $contact_id );

				// Identified contact
			} else {

				wpf_log( 'notice', 0, 'Unable to apply check-out tags, couldn\'t find contact record for email ' . $attendee->email() );

				// No contact record found
			}
		}

	}


	/**
	 * Adds EE field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['event-espresso'] ) ) {
			$field_groups['event-espresso'] = array(
				'title'  => 'Event Espresso',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Loads EE fields for inclusion in Contact Fields table
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function add_meta_fields( $meta_fields ) {

		$questions = EEM_Question::instance();
		$ee_fields = $questions->get_all();

		foreach ( $ee_fields as $field ) {

			if ( $field->type() == 'DATE' ) {
				$type = 'date';
			} else {
				$type = 'text';
			}

			$key = $field->system_ID();

			if ( empty( $key ) ) {
				$key = $field->get( 'QST_ID' );
			}

			$meta_fields[ 'ee_' . $key ] = array(
				'label'  => $field->display_text(),
				'type'   => $type,
				'group'  => 'event-espresso',
				'pseudo' => true,
			);

		}

		$meta_fields['ee_registration_status'] = array(
			'label'  => 'Registration Status',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_ticket_name'] = array(
			'label'  => 'Ticket Name',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_event_name'] = array(
			'label'  => 'Event Name',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_event_start_date'] = array(
			'label'  => 'Event Start Date and Time',
			'type'   => 'date',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_event_start_time'] = array(
			'label'  => 'Event Start Time',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_event_venue_name'] = array(
			'label'  => 'Event Venue Name',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_event_venue_address'] = array(
			'label'  => 'Event Venue Address 1',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_event_venue_address_2'] = array(
			'label'  => 'Event Venue Address 2',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_event_venue_city'] = array(
			'label'  => 'Event Venue City',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_event_venue_state_name'] = array(
			'label'  => 'Event Venue State',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_event_venue_country_name'] = array(
			'label'  => 'Event Venue Country',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		$meta_fields['ee_event_venue_zip'] = array(
			'label'  => 'Event Venue Postcode',
			'type'   => 'text',
			'group'  => 'event-espresso',
			'pseudo' => true,
		);

		return $meta_fields;

	}


	/**
	 * Registers additional Woocommerce settings
	 *
	 * @access  public
	 * @return  array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['ee_header'] = array(
			'title'   => __( 'Event Espresso Registration Statuses', 'wp-fusion' ),
			'desc'    => __( 'The settings here let you apply tags to a contact when a registration status is changed in Event Espresso.', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$statuses = EEM_Registration::reg_statuses();

		foreach ( $statuses as $status ) {

			$settings[ 'ee_status_tagging_' . $status ] = array(
				'title'   => EEH_Template::pretty_status( $status, false, 'sentence' ),
				'type'    => 'assign_tags',
				'section' => 'integrations',
			);

		}

		return $settings;

	}

	/**
	 * Adds EE field group to meta fields list
	 *
	 * @access  public
	 * @return  mixed HTML Output
	 */

	public function show_admin_settings( $ticket_row, $ticket_id ) {

		global $post;

		$settings = get_post_meta( $post->ID, 'wpf_settings_event_espresso', true );

		$defaults = array(
			'apply_tags'             => array( $ticket_id => array() ),
			'apply_tags_pending'     => array( $ticket_id => array() ),
			'apply_tags_checked_in'  => array( $ticket_id => array() ),
			'apply_tags_checked_out' => array( $ticket_id => array() ),
			'add_attendees'          => array( $ticket_id => false ),
		);

		$settings = wp_parse_args( $settings, $defaults );

		echo '<h4 class="tickets-heading">' . __( 'WP Fusion — Approved', 'wp-fusion' ) . '</h4><br />';

		$args = array(
			'setting'   => $settings['apply_tags'][ $ticket_id ],
			'meta_name' => "ticket_wpf_settings[apply_tags][{$ticket_id}]",
			'class'     => 'ticket_field ' . $ticket_id,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">';
		echo sprintf( __( 'Select the tags to be applied in %s when someone registers using this ticket and is approved.', 'wp-fusion' ), wp_fusion()->crm->name );
		echo '</span>';

		echo '<h4 class="tickets-heading">' . __( 'WP Fusion — Pending', 'wp-fusion' ) . '</h4><br />';

		// echo '<input type="checkbox" value="1" name="ticket_wpf_settings[add_attendees][' . $ticket_id . ']" ' . checked( $settings['add_attendees'][ $ticket_id ], true, false ) . ' />';
		// echo sprintf( __( 'Add contacts in %s when someone registers using this ticket and is pending payment.', 'wp-fusion' ), wp_fusion()->crm->name );
		// echo '<br /><br />';
		$args = array(
			'setting'   => $settings['apply_tags_pending'][ $ticket_id ],
			'meta_name' => "ticket_wpf_settings[apply_tags_pending][{$ticket_id}]",
			'class'     => 'ticket_field ' . $ticket_id,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">';
		echo sprintf( __( 'Select the tags to be applied in %s when someone registers using this ticket and is pending payment.', 'wp-fusion' ), wp_fusion()->crm->name );
		echo '</span>';

		// Add Attendees checkbox
		echo '<h4 class="tickets-heading">' . __( 'Add Attendees', 'wp-fusion' ) . '</h4><br />';
		echo '<input type="checkbox" value="1" name="ticket_wpf_settings[add_attendees][' . $ticket_id . ']" ' . checked( $settings['add_attendees'][ $ticket_id ], true, false ) . ' />';
		echo sprintf( __( 'Add each attendee as a separate contact in %s.', 'wp-fusion' ), wp_fusion()->crm->name );
		echo '<br />';

		echo '<h4 class="tickets-heading">' . __( 'WP Fusion — Checked In', 'wp-fusion' ) . '</h4><br />';

		$args = array(
			'setting'   => $settings['apply_tags_checked_in'][ $ticket_id ],
			'meta_name' => "ticket_wpf_settings[apply_tags_checked_in][{$ticket_id}]",
			'class'     => 'ticket_field ' . $ticket_id,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'Apply these tags when an attendee is checked in to an event.', 'wp-fusion' ) . '</span>';

		echo '<h4 class="tickets-heading">' . __( 'WP Fusion — Checked Out', 'wp-fusion' ) . '</h4><br />';

		$args = array(
			'setting'   => $settings['apply_tags_checked_out'][ $ticket_id ],
			'meta_name' => "ticket_wpf_settings[apply_tags_checked_out][{$ticket_id}]",
			'class'     => 'ticket_field ' . $ticket_id,
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'Apply these tags when an attendee is checked out of an event.', 'wp-fusion' ) . '</span>';

	}


	/**
	 * Saves WPF configuration to product
	 *
	 * @access public
	 * @return mixed
	 */

	public function save_meta_box_data( $post_id ) {

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( isset( $_POST['ticket_wpf_settings'] ) ) {
			$data = $_POST['ticket_wpf_settings'];
		} else {
			$data = array();
		}

		if ( ! isset( $data['add_attendees'] ) ) {
			$data['add_attendees'] = array();
		}

		// Update the meta field in the database.
		update_post_meta( $post_id, 'wpf_settings_event_espresso', $data );

	}


	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds EE to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['ee'] = array(
			'label'   => __( 'Event Espresso registrations', 'wp-fusion' ),
			'title'   => __( 'Registrations', 'wp-fusion' ),
			'tooltip' => __( 'Finds Event Espresso registrations that are Approved and have not been processed by WP Fusion, and adds/updates contacts while applying tags based on the associated event.', 'wp-fusion' ),
		);

		return $options;

	}

	/**
	 * Counts total number of orders to be processed
	 *
	 * @access public
	 * @return int Count
	 */

	public function batch_init() {

		$query_args = array(
			'limit' => 1000,
			array(
				'STS_ID' => array(
					'IN',
					array( EEM_Registration::status_id_approved ),
				),
			),
		);

		$registrations = EEM_Registration::instance()->get_all( $query_args );

		$ids = array();

		if ( ! empty( $registrations ) ) {

			foreach ( $registrations as $registration ) {

				$complete = $registration->get_extra_meta( 'wpf_complete', true );

				if ( empty( $complete ) ) {
					$ids[] = $registration->ID();
				}
			}
		}

		return $ids;

	}

	/**
	 * Processes order actions in batches
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step( $registration_id ) {

		$registration = EEM_Registration::instance()->get_one_by_ID( $registration_id );

		$this->registration_status_update( $registration, false, EEM_Registration::status_id_approved );

	}

	/**
	 * Support utilities
	 *
	 * @access public
	 * @return void
	 */

	public function settings_page_init() {

		if ( isset( $_GET['ee_reset_wpf_complete'] ) ) {

			$query_args = array(
				'limit' => 1000,
				array(
					'STS_ID' => array(
						'IN',
						array( EEM_Registration::status_id_approved ),
					),
				),
			);

			$registrations = EEM_Registration::instance()->get_all( $query_args );

			if ( ! empty( $registrations ) ) {

				foreach ( $registrations as $registration ) {
					$complete = $registration->delete_extra_meta( 'wpf_complete' );
				}
			}

			echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><strong>Success:</strong><code>wpf_complete</code> meta key removed from ' . count( $registrations ) . ' registrations.</p></div>';

		}

	}


}

new WPF_Event_Espresso();
