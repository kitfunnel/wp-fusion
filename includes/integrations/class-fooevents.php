<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_FooEvents extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'fooevents';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'FooEvents';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/fooevents/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_woocommerce_customer_data', array( $this, 'merge_custom_fields' ), 10, 2 );
		add_filter( 'wpf_woocommerce_apply_tags_checkout', array( $this, 'merge_attendee_tags' ), 10, 2 );
		add_action( 'wpf_woocommerce_payment_complete', array( $this, 'add_attendee_data' ), 20, 2 );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

		// Product settings
		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ) );
		add_action( 'wpf_woocommerce_variation_panel', array( $this, 'variation_panel_content' ), 10, 2 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'order_status_refunded' ), 10 );
	}

	/**
	 * Merges custom fields for the primary contact on the order
	 *
	 * @access  public
	 * @return  array Customer Data
	 */

	public function merge_custom_fields( $customer_data, $order ) {

		foreach ( $this->get_attendees_from_order( $order ) as $attendee ) {

			if ( ! isset( $customer_data['event_name'] ) ) {

				// Going to merge the event and venue fields into the main customer even if they aren't an attendee, just to save confusion

				$product_id = $attendee['WooCommerceEventsProductID'];

				$hour    = get_post_meta( $product_id, 'WooCommerceEventsHour', true );
				$minutes = get_post_meta( $product_id, 'WooCommerceEventsMinutes', true );
				$period  = get_post_meta( $product_id, 'WooCommerceEventsPeriod', true );

				$event_fields = array(
					'event_name'       => get_the_title( $product_id ),
					'event_start_date' => get_post_meta( $product_id, 'WooCommerceEventsDate', true ),
					'event_start_time' => $hour . ':' . $minutes . ' ' . $period,
					'event_venue_name' => get_post_meta( $product_id, 'WooCommerceEventsLocation', true ),
					'zoom_meeting_id'  => get_post_meta( $product_id, 'WooCommerceEventsZoomWebinar', true ),
					'zoom_join_url'    => get_post_meta( $product_id, 'wp_fusion_zoom_join_url', true ),
				);

				// Zoom.

				if ( ! empty( $event_fields['zoom_meeting_id'] ) && empty( $event_fields['zoom_join_url'] ) && wpf_is_field_active( 'zoom_join_url' ) && class_exists( 'FooEvents_Zoom_API_Helper' ) ) {

					// The Zoom integration currently doesn't cache the meeting URL in the database so we'll fetch it one time here.

					$config = new FooEvents_Config();
					$helper = new FooEvents_Zoom_API_Helper( $config );
					$result = $helper->do_fooevents_fetch_zoom_meeting( $event_fields['zoom_meeting_id'] );

					if ( ! empty( $result['status'] ) && 'success' === $result['status'] ) {
						$event_fields['zoom_join_url'] = $result['data']['join_url'];
						update_post_meta( $product_id, 'wp_fusion_zoom_join_url', $event_fields['zoom_join_url'] );
					}
				}

				// Bookings extension.

				if ( ! empty( $attendee['WooCommerceEventsBookingOptions'] ) ) {

					$slot = $attendee['WooCommerceEventsBookingOptions']['slot'];
					$date = $attendee['WooCommerceEventsBookingOptions']['date'];

					$booking_options = get_post_meta( $product_id, 'fooevents_bookings_options_serialized', true );
					$booking_options = json_decode( $booking_options, true );

					if ( ! empty( $booking_options ) && ! empty( $booking_options[ $slot ] ) && isset( $booking_options[ $slot ]['formatted_time'] ) ) {

						$time = trim( $booking_options[ $slot ]['formatted_time'], '()' );
						$date = $booking_options[ $slot ]['add_date'][ $date ]['date'];

						$event_fields['booking_date'] = $date . ' ' . $time;
						$event_fields['booking_time'] = $time;

					}
				}

				$customer_data = array_merge( $customer_data, $event_fields );

			}

			if ( $attendee['WooCommerceEventsAttendeeEmail'] == $order->get_billing_email() || empty( $attendee['WooCommerceEventsAttendeeEmail'] ) ) {

				// Merge name fields if blank on the main order.
				if ( empty( $customer_data['first_name'] ) ) {
					$customer_data['first_name'] = $attendee['WooCommerceEventsAttendeeName'];
				}

				if ( empty( $customer_data['billing_first_name'] ) ) {
					$customer_data['billing_first_name'] = $attendee['WooCommerceEventsAttendeeName'];
				}

				if ( empty( $customer_data['last_name'] ) ) {
					$customer_data['last_name'] = $attendee['WooCommerceEventsAttendeeLastName'];
				}

				if ( empty( $customer_data['billing_last_name'] ) ) {
					$customer_data['billing_last_name'] = $attendee['WooCommerceEventsAttendeeLastName'];
				}

				// Misc. fields.

				$misc_data = array(
					'attendee_first_name'  => $attendee['WooCommerceEventsAttendeeName'],
					'attendee_last_name'   => $attendee['WooCommerceEventsAttendeeLastName'],
					'attendee_email'       => $attendee['WooCommerceEventsAttendeeEmail'],
					'billing_phone'        => $attendee['WooCommerceEventsAttendeeTelephone'],
					'phone_number'         => $attendee['WooCommerceEventsAttendeeTelephone'],
					'attendee_phone'       => $attendee['WooCommerceEventsAttendeeTelephone'],
					'billing_company'      => $attendee['WooCommerceEventsAttendeeCompany'],
					'company'              => $attendee['WooCommerceEventsAttendeeCompany'],
					'attendee_company'     => $attendee['WooCommerceEventsAttendeeCompany'],
					'attendee_designation' => $attendee['WooCommerceEventsAttendeeDesignation'],
				);

				$customer_data = array_merge( $customer_data, $misc_data );

				// Merge custom fields, they only go if the customer is also an attendee.
				if ( ! empty( $attendee['WooCommerceEventsCustomAttendeeFields'] ) ) {

					// New v5.5+ method

					$customer_data = array_merge( $customer_data, $attendee['WooCommerceEventsCustomAttendeeFields'] );

					// Old method:

					foreach ( $attendee['WooCommerceEventsCustomAttendeeFields'] as $key => $value ) {

						$key = strtolower( str_replace( 'fooevents_custom_', '', $key ) );

						$customer_data[ $key ] = $value;

					}
				}

				$customer_data = apply_filters( 'wpf_woocommerce_attendee_data', $customer_data, $attendee, $order->get_id() );

			}
		}

		return $customer_data;

	}

	/**
	 * If the purchaser is also an attendee, apply the attendee tags
	 *
	 * @access  public
	 * @return  array Apply Tags
	 */

	public function merge_attendee_tags( $apply_tags, $order ) {

		foreach ( $this->get_attendees_from_order( $order ) as $attendee ) {

			$settings = get_post_meta( $attendee['WooCommerceEventsProductID'], 'wpf-settings-woo', true );

			if ( empty( $settings ) ) {
				return $apply_tags;
			}

			// This was already sent in the main order data so it doesn't need to be sent again

			if ( $attendee['WooCommerceEventsAttendeeEmail'] != $order->get_billing_email() ) {
				continue;
			}

			// Product settings

			if ( ! empty( $settings['apply_tags_event_attendees'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_event_attendees'] );
			}

			// Variation settings

			if ( ! empty( $attendee['WooCommerceEventsVariationID'] ) ) {

				$settings = get_post_meta( $attendee['WooCommerceEventsVariationID'], 'wpf-settings-woo', true );

				if ( ! empty( $settings ) && ! empty( $settings['apply_tags_event_attendees_variation'] ) && ! empty( $settings['apply_tags_event_attendees_variation'][ $attendee['WooCommerceEventsVariationID'] ] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_event_attendees_variation'][ $attendee['WooCommerceEventsVariationID'] ] );
				}
			}
		}

		return $apply_tags;

	}


	/**
	 * Add / tag contacts for event attendees
	 *
	 * @access  public
	 * @return  void
	 */

	public function add_attendee_data( $order_id, $contact_id ) {

		$order = wc_get_order( $order_id );

		foreach ( $this->get_attendees_from_order( $order ) as $attendee ) {

			$settings = get_post_meta( $attendee['WooCommerceEventsProductID'], 'wpf-settings-woo', true );

			if ( empty( $settings ) || ! isset( $settings['add_attendees'] ) || $settings['add_attendees'] != true ) {
				continue;
			}

			// This was already sent in the main order data so it doesn't need to be sent again

			if ( $attendee['WooCommerceEventsAttendeeEmail'] == $order->get_billing_email() ) {
				continue;
			}

			if ( empty( $attendee['WooCommerceEventsAttendeeEmail'] ) ) {
				wpf_log( 'notice', 0, 'Unable to sync attendee data, no email address provided. To sync attendees you must enable <strong>Capture attendee full name and email address?</strong> when editing the FooEvent product.' );
				continue;
			}

			$update_data = array(
				'first_name'           => $attendee['WooCommerceEventsAttendeeName'],
				'attendee_first_name'  => $attendee['WooCommerceEventsAttendeeName'],
				'last_name'            => $attendee['WooCommerceEventsAttendeeLastName'],
				'attendee_last_name'   => $attendee['WooCommerceEventsAttendeeLastName'],
				'user_email'           => $attendee['WooCommerceEventsAttendeeEmail'],
				'attendee_email'       => $attendee['WooCommerceEventsAttendeeEmail'],
				'billing_phone'        => $attendee['WooCommerceEventsAttendeeTelephone'],
				'phone_number'         => $attendee['WooCommerceEventsAttendeeTelephone'],
				'attendee_phone'       => $attendee['WooCommerceEventsAttendeeTelephone'],
				'billing_company'      => $attendee['WooCommerceEventsAttendeeCompany'],
				'company'              => $attendee['WooCommerceEventsAttendeeCompany'],
				'attendee_company'     => $attendee['WooCommerceEventsAttendeeCompany'],
				'attendee_designation' => $attendee['WooCommerceEventsAttendeeDesignation'],
			);

			// Merge event and venue fields
			$product_id = $attendee['WooCommerceEventsProductID'];

			$hour    = get_post_meta( $product_id, 'WooCommerceEventsHour', true );
			$minutes = get_post_meta( $product_id, 'WooCommerceEventsMinutes', true );
			$period  = get_post_meta( $product_id, 'WooCommerceEventsPeriod', true );

			$event_fields = array(
				'event_name'       => get_the_title( $product_id ),
				'event_start_date' => get_post_meta( $product_id, 'WooCommerceEventsDate', true ),
				'event_start_time' => $hour . ':' . $minutes . ' ' . $period,
				'event_venue_name' => get_post_meta( $product_id, 'WooCommerceEventsLocation', true ),
				'zoom_meeting_id'  => get_post_meta( $product_id, 'WooCommerceEventsZoomWebinar', true ),
				'zoom_join_url'    => get_post_meta( $product_id, 'wp_fusion_zoom_join_url', true ),
			);

			// Zoom.

			if ( ! empty( $event_fields['zoom_meeting_id'] ) && empty( $event_fields['zoom_join_url'] ) && wpf_is_field_active( 'zoom_join_url' ) && class_exists( 'FooEvents_Zoom_API_Helper' ) ) {

				// The Zoom integration currently doesn't cache the meeting URL in the database so we'll fetch it one time here.

				$config = new FooEvents_Config();
				$helper = new FooEvents_Zoom_API_Helper( $config );
				$result = $helper->do_fooevents_fetch_zoom_meeting( $event_fields['zoom_meeting_id'] );

				if ( ! empty( $result['status'] ) && 'success' === $result['status'] ) {
					$event_fields['zoom_join_url'] = $result['data']['join_url'];
					update_post_meta( $product_id, 'wp_fusion_zoom_join_url', $event_fields['zoom_join_url'] );
				}
			}

			// Bookings extension.

			if ( ! empty( $attendee['WooCommerceEventsBookingOptions'] ) ) {

				$slot = $attendee['WooCommerceEventsBookingOptions']['slot'];
				$date = $attendee['WooCommerceEventsBookingOptions']['date'];

				$booking_options = get_post_meta( $product_id, 'fooevents_bookings_options_serialized', true );
				$booking_options = json_decode( $booking_options, true );

				if ( ! empty( $booking_options ) && isset( $booking_options[ $slot ] ) ) {

					$time = trim( $booking_options[ $slot ]['formatted_time'], '()' );
					$date = $booking_options[ $slot ]['add_date'][ $date ]['date'];

					$event_fields['booking_date'] = $date . ' ' . $time;
					$event_fields['booking_time'] = $time;

				}
			}

			$update_data = array_merge( $update_data, $event_fields );

			// Merge custom fields.
			if ( ! empty( $attendee['WooCommerceEventsCustomAttendeeFields'] ) ) {

				// New v5.5+ method

				$update_data = array_merge( $update_data, $attendee['WooCommerceEventsCustomAttendeeFields'] );

				// Old method:

				foreach ( $attendee['WooCommerceEventsCustomAttendeeFields'] as $key => $value ) {

					$key = strtolower( str_replace( 'fooevents_custom_', '', $key ) );

					$update_data[ $key ] = $value;

				}
			}

			$update_data = apply_filters( 'wpf_woocommerce_attendee_data', $update_data, $attendee, $order_id );

			$contact_id = wp_fusion()->crm->get_contact_id( $update_data['user_email'] );

			if ( is_wp_error( $contact_id ) ) {
				wpf_log( 'error', 0, 'Error while looking up contact ID: ' . $contact_id->get_error_message() . '. Proceeding to try and create a new contact.' );
			}

			if ( empty( $contact_id ) || is_wp_error( $contact_id ) ) {

				wpf_log( 'info', 0, 'Processing FooEvents event attendee for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>:', array( 'meta_array' => $update_data ) );

				$contact_id = wp_fusion()->crm->add_contact( $update_data );

				if ( is_wp_error( $contact_id ) ) {
					wpf_log( 'error', 0, 'Error while adding contact: ' . $contact_id->get_error_message() . '. Tags will not be applied.' );
					continue;
				}
			} else {

				wpf_log( 'info', 0, 'Processing FooEvents event attendee for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>, for existing contact #' . $contact_id . ':', array( 'meta_array' => $update_data ) );

				$result = wp_fusion()->crm->update_contact( $contact_id, $update_data );

				if ( is_wp_error( $result ) ) {
					wpf_log( 'error', 0, 'Error while updating contact: ' . $result->get_error_message() );
				}
			}

			$apply_tags = array();

			// Product settings

			if ( ! empty( $settings['apply_tags_event_attendees'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_event_attendees'] );
			}

			// Variation settings

			if ( ! empty( $attendee['WooCommerceEventsVariationID'] ) ) {

				$settings = get_post_meta( $attendee['WooCommerceEventsVariationID'], 'wpf-settings-woo', true );

				if ( ! empty( $settings ) && ! empty( $settings['apply_tags_event_attendees_variation'] ) && ! empty( $settings['apply_tags_event_attendees_variation'][ $attendee['WooCommerceEventsVariationID'] ] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_event_attendees_variation'][ $attendee['WooCommerceEventsVariationID'] ] );
				}
			}

			if ( ! empty( $apply_tags ) ) {

				wpf_log( 'info', 0, 'Applying tags to FooEvents attendee for contact #' . $contact_id . ': ', array( 'tag_array' => $apply_tags ) );

				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}
		}

	}

	/**
	 * Utility function for getting any FooEvents attendees from a WooCommerce order
	 *
	 * @access  public
	 * @return  array Attendees
	 */

	private function get_attendees_from_order( $order ) {

		$attendees = array();

		$order_data = $order->get_data();

		foreach ( $order_data['meta_data'] as $meta ) {

			if ( ! is_a( $meta, 'WC_Meta_Data' ) ) {
				continue;
			}

			$data = $meta->get_data();

			if ( 'WooCommerceEventsOrderTickets' != $data['key'] ) {
				continue;
			}

			foreach ( $data['value'] as $sub_value ) {

				if ( ! is_array( $sub_value ) ) {
					continue;
				}

				foreach ( $sub_value as $attendee ) {

					$attendees[] = $attendee;

				}
			}
		}

		return $attendees;

	}

	/**
	 * Remove tags from attendees when order is refunded.
	 *
	 * @since 3.37.25
	 *
	 * @param int $order_id The WooCommerce order ID.
	 */
	public function order_status_refunded( $order_id ) {

		$order     = wc_get_order( $order_id );
		$attendees = $this->get_attendees_from_order( $order );

		if ( empty( $attendees ) ) {
			return;
		}

		foreach ( $attendees as $attendee ) {

			$settings = get_post_meta( $attendee['WooCommerceEventsProductID'], 'wpf-settings-woo', true );

			if ( empty( $settings ) || ! isset( $settings['add_attendees'] ) || $settings['add_attendees'] != true ) {
				continue;
			}

			if ( ! isset( $settings['apply_tags_event_attendees'] ) || empty( $settings['apply_tags_event_attendees'] ) ) {
				continue;
			}

			// Get attendee from CRM
			$contact_id = wp_fusion()->crm->get_contact_id( $attendee['WooCommerceEventsAttendeeEmail'] );

			// If attendee does not exist then no need to remove tags
			if ( empty( $contact_id ) ) {
				continue;
			}

			$remove_tags = array();

			// Product settings
			if ( ! empty( $settings['apply_tags_event_attendees'] ) ) {
				$remove_tags = array_merge( $remove_tags, $settings['apply_tags_event_attendees'] );
			}

			// Variation settings
			if ( ! empty( $attendee['WooCommerceEventsVariationID'] ) ) {

				$settings = get_post_meta( $attendee['WooCommerceEventsVariationID'], 'wpf-settings-woo', true );

				if ( ! empty( $settings ) && ! empty( $settings['apply_tags_event_attendees_variation'] ) && ! empty( $settings['apply_tags_event_attendees_variation'][ $attendee['WooCommerceEventsVariationID'] ] ) ) {
					$remove_tags = array_merge( $remove_tags, $settings['apply_tags_event_attendees_variation'][ $attendee['WooCommerceEventsVariationID'] ] );
				}
			}

			if ( ! empty( $remove_tags ) ) {
				wpf_log( 'info', 0, 'Removing tags from FooEvents attendee for contact #' . $contact_id . ' due to refund: ', array( 'tag_array' => $remove_tags ) );
				wp_fusion()->crm->remove_tags( $remove_tags, $contact_id );
			}
		}

	}

	/**
	 * Adds FE field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['fooevents_attendee'] = array(
			'title'  => __( 'FooEvents Attendee', 'wp-fusion' ),
			'fields' => array(),
		);

		$field_groups['fooevents_event'] = array(
			'title'  => __( 'FooEvents Event', 'wp-fusion' ),
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Loads FE fields for inclusion in Contact Fields table
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function add_meta_fields( $meta_fields ) {

		$meta_fields['event_name'] = array(
			'label'  => 'Event Name',
			'type'   => 'text',
			'group'  => 'fooevents_event',
			'pseudo' => true,
		);

		$meta_fields['event_start_date'] = array(
			'label'  => 'Event Start Date',
			'type'   => 'date',
			'group'  => 'fooevents_event',
			'pseudo' => true,
		);

		$meta_fields['event_start_time'] = array(
			'label'  => 'Event Start Time',
			'type'   => 'text',
			'group'  => 'fooevents_event',
			'pseudo' => true,
		);

		$meta_fields['event_venue_name'] = array(
			'label'  => 'Event Venue Name',
			'type'   => 'text',
			'group'  => 'fooevents_event',
			'pseudo' => true,
		);

		if ( class_exists( 'FooEvents_Bookings' ) ) {

			$meta_fields['booking_date'] = array(
				'label'  => 'Booking Date',
				'type'   => 'date',
				'group'  => 'fooevents_event',
				'pseudo' => true,
			);

			$meta_fields['booking_time'] = array(
				'label'  => 'Booking Time',
				'type'   => 'text',
				'group'  => 'fooevents_event',
				'pseudo' => true,
			);
		}

		$meta_fields['zoom_meeting_id'] = array(
			'label'  => 'Zoom Meeting ID',
			'type'   => 'int',
			'group'  => 'fooevents_event',
			'pseudo' => true,
		);

		$meta_fields['zoom_join_url'] = array(
			'label'  => 'Zoom Join URL',
			'type'   => 'text',
			'group'  => 'fooevents_event',
			'pseudo' => true,
		);

		$meta_fields['attendee_first_name'] = array(
			'label'  => 'Attendee First Name',
			'type'   => 'text',
			'group'  => 'fooevents_attendee',
			'pseudo' => true,
		);

		$meta_fields['attendee_last_name'] = array(
			'label'  => 'Attendee Last Name',
			'type'   => 'text',
			'group'  => 'fooevents_attendee',
			'pseudo' => true,
		);

		$meta_fields['attendee_email'] = array(
			'label'  => 'Attendee Email',
			'type'   => 'text',
			'group'  => 'fooevents_attendee',
			'pseudo' => true,
		);

		$meta_fields['attendee_phone'] = array(
			'label'  => 'Attendee Phone',
			'type'   => 'text',
			'group'  => 'fooevents_attendee',
			'pseudo' => true,
		);

		$meta_fields['attendee_company'] = array(
			'label'  => 'Attendee Company',
			'type'   => 'text',
			'group'  => 'fooevents_attendee',
			'pseudo' => true,
		);

		$meta_fields['attendee_designation'] = array(
			'label'  => 'Attendee Designation',
			'type'   => 'text',
			'group'  => 'fooevents_attendee',
			'pseudo' => true,
		);

		if ( class_exists( 'Fooevents_Custom_Attendee_Fields' ) ) {

			$args = array(
				'numberposts' => 100,
				'post_type'   => 'product',
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'key'     => 'fooevents_custom_attendee_fields_options_serialized',
						'compare' => 'EXISTS',
					),
				),
			);

			$products = get_posts( $args );

			if ( ! empty( $products ) ) {

				foreach ( $products as $product_id ) {

					$fields = get_post_meta( $product_id, 'fooevents_custom_attendee_fields_options_serialized', true );

					$fields = json_decode( $fields );

					if ( ! empty( $fields ) ) {

						foreach ( $fields as $key => $field ) {

							if ( false !== strpos( $key, '_option' ) ) {

								// Pre 5.5 field storage
								$slug = 'fooevents_custom_option_' . str_replace( '_option', '', $key );

							} else {

								// New 5.5+ field storage

								$slug = 'fooevents_custom_' . $key;

							}

							if ( ! isset( $field->{ $key . '_label' } ) ) {

								// I don't even know. This is such a mess
								$key = str_replace( '_option', '', $key );

							}

							$meta_fields[ $slug ] = array(
								'label'  => $field->{ $key . '_label' },
								'type'   => $field->{ $key . '_type' },
								'group'  => 'fooevents_attendee',
								'pseudo' => true,
							);

						}
					}
				}
			}
		}

		return $meta_fields;

	}


	/**
	 * Display event settings
	 *
	 * @access public
	 * @return mixed
	 */

	public function panel_content( $post_id ) {

		$settings = array(
			'apply_tags_event_attendees' => array(),
			'add_attendees'              => false,
		);

		if ( get_post_meta( $post_id, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group wpf-product">';

		echo '<p class="form-field"><label><strong>FooEvents</strong></label></p>';

		echo '<p class="form-field"><label for="wpf-add-attendees">' . __( 'Add attendees', 'wp-fusion' ) . '</label>';
		echo '<input class="checkbox" type="checkbox" id="wpf-add-attendees" name="wpf-settings-woo[add_attendees]" data-unlock="wpf-settings-woo-apply_tags_event_attendees" value="1" ' . checked( $settings['add_attendees'], 1, false ) . ' />';
		echo '<span class="description">' . sprintf( __( 'Add each event attendee as a separate contact in %s.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</p>';

		echo '<p class="form-field"><label for="wpf-apply-tags-woo">' . __( 'Apply tags to event attendees', 'wp-fusion' );

		echo ' <span class="dashicons dashicons-editor-help wpf-tip wpf-tip-bottom" data-tip="' . __( 'These tags will only be applied to event attendees entered on the registration form, not the customer who placed the order. <strong>Add attendees</strong> must be enabled.', 'wp-fusion' ) . '"></span>';

		echo '</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_event_attendees'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_event_attendees',
				'disabled'  => $settings['add_attendees'] ? false : true,
			)
		);

		echo '</p>';

		echo '</div>';

	}


	/**
	 * Display event settings (Variations)
	 *
	 * @access public
	 * @return mixed
	 */

	public function variation_panel_content( $variation_id, $settings ) {

		$defaults = array(
			'apply_tags_event_attendees_variation' => array( $variation_id => array() ),
		);

		$settings = array_merge( $defaults, $settings );

		echo '<div><p class="form-row form-row-full">';
		echo '<label for="wpf-settings-woo-variation-apply_tags_event_attendees_variation-' . $variation_id . '">';
		_e( 'Apply tags to event attendees at this variation:', 'wp-fusion' );

		echo ' <span class="dashicons dashicons-editor-help wpf-tip wpf-tip-bottom" data-tip="' . __( 'These tags will only be applied to event attendees entered on the registration form, not the customer who placed the order. <strong>Add attendees</strong> must be enabled on the main WP Fusion settings panel.', 'wp-fusion' ) . '"></span>';

		echo '</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_event_attendees_variation'][ $variation_id ],
				'meta_name' => "wpf-settings-woo-variation[apply_tags_event_attendees_variation][{$variation_id}]",
			)
		);

		echo '</p></div>';

	}


}

new WPF_FooEvents();
