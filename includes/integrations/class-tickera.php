<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Tickera extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'tickera';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Tickera';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/tickera/';

	/**
	 * Gets things started.
	 *
	 * @since 3.37.18
	 */

	public function init() {

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_tc_tickets', array( $this, 'save_meta_box_data' ) );

		// Metafield groups.
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

		// Add tag after user registeration to an event.
		add_action( 'tc_order_created', array( $this, 'order_created' ), 10, 4 );

		add_action( 'transition_post_status', array( $this, 'order_status_change' ), 10, 3 );

		// Add tag after user checkin into an event.
		add_action( 'tc_check_in_notification', array( $this, 'checkin_user' ) );

	}

	/**
	 * Create/update attendee into the CRM and apply tags to it when he purchase
	 * a ticket.
	 *
	 * @since 3.37.18
	 *
	 * @param array $update_data The field data.
	 * @param array $apply_tags  The tags to apply.
	 */
	public function register_attendee( $update_data, $apply_tags ) {

		// Send update data.
		$user = get_user_by( 'email', $update_data['user_email'] );

		if ( is_object( $user ) ) {

			wp_fusion()->user->push_user_meta( $user->ID, $update_data );

			if ( ! empty( $apply_tags ) ) {
				wp_fusion()->user->apply_tags( $apply_tags, $user->ID );
			}
		} else {

			// Guest checkouts.
			$contact_id = $this->guest_registration( $update_data['user_email'], $update_data );

			if ( ! empty( $apply_tags ) ) {

				wpf_log(
					'info',
					0,
					'Applying tag(s) for event registration for contact #' . $contact_id,
					array(
						'tag_array' => $apply_tags,
					)
				);

				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}
		}

	}

	/**
	 * Create/update attendee into crm and apply tags to it when he checkin into
	 * an event.
	 *
	 * @since 3.37.18
	 *
	 * @param int $attendee_id The attendee ID.
	 */
	public function checkin_user( $attendee_id ) {

		$ticket_id = get_post_meta( $attendee_id, 'ticket_type_id', true );

		// Check if checkin tag exist.
		$settings = get_post_meta( $ticket_id, 'wpf_settings_tickera', true );

		if ( empty( $settings ) || empty( $settings['apply_tags_checkin'] ) ) {
			return;
		}

		// Check if email attendee field is enabled in the plugin.
		$tc_general_settings = get_option( 'tc_general_setting', false );

		if ( ! isset( $tc_general_settings['show_owner_email_field'] ) || strtolower( $tc_general_settings['show_owner_email_field'] ) !== 'yes' ) {
			wpf_log( 'warning', 0, 'You have to enable "Show E-mail for Option For Ticket Owners" in the Tickera plugin settings in order to apply tags during an event checkin.' );
			return;
		}

		// Get event data from the ticket.
		$event_data = $this->get_event_data( $ticket_id );

		// Get ticket data from ticket id.
		$ticket_data = $this->get_ticket_data( $ticket_id );

		$event_ticket_data = array_merge( $event_data, $ticket_data );

		$person = array(
			'first_name' => get_post_meta( $attendee_id, 'first_name', true ),
			'last_name'  => get_post_meta( $attendee_id, 'last_name', true ),
			'user_email' => get_post_meta( $attendee_id, 'owner_email', true ),
		);

		$update_data = array_merge( $person, $event_ticket_data );

		$this->register_attendee( $update_data, $settings['apply_tags_checkin'] );

	}

	/**
	 * Get event data from ticket ID.
	 *
	 * @since  3.37.18
	 *
	 * @param  int $ticket_id The ticket ID.
	 * @return array The event data.
	 */
	public function get_event_data( $ticket_id ) {

		$event_id = absint( get_post_meta( $ticket_id, 'event_name', true ) );

		return array(
			'tc_event_name'            => get_the_title( $event_id ),
			'tc_event_start_date_time' => get_post_meta( $event_id, 'event_date_time', true ),
			'tc_event_end_date_time'   => get_post_meta( $event_id, 'event_end_date_time', true ),
			'tc_event_location'        => get_post_meta( $event_id, 'event_location', true ),
		);
	}

	/**
	 * Get ticket data from ticket ID.
	 *
	 * @since  3.37.18
	 *
	 * @param  int $ticket_id The ticket ID.
	 * @return array The ticket data.
	 */
	public function get_ticket_data( $ticket_id ) {

		return array(
			'tc_ticket_name'                           => get_the_title( $ticket_id ),
			// 'tc_ticket_fee'                            => get_post_meta( $ticket_id, 'ticket_fee', true ),
			// 'tc_min_tickets_per_order'                 => get_post_meta( $ticket_id, 'min_tickets_per_order', true ),
			// 'tc_max_tickets_per_order'                 => get_post_meta( $ticket_id, 'max_tickets_per_order', true ),
			// 'tc_price_per_ticket'                      => get_post_meta( $ticket_id, 'price_per_ticket', true ),
			'tc_ticket_checkin_availability_from_date' => get_post_meta( $ticket_id, '_ticket_checkin_availability_from_date', true ),
			'tc_ticket_checkin_availability_to_date'   => get_post_meta( $ticket_id, '_ticket_checkin_availability_to_date', true ),
			// 'tc_quantity_available'                    => get_post_meta( $ticket_id, 'quantity_available', true ),
		);
	}

	/**
	 * Sync attendee to the CRM after registering for an event.
	 *
	 * @since 3.37.18
	 *
	 * @param integer $order_id
	 * @param string  $status
	 * @param array   $cart_contents
	 * @param array   $cart_info
	 * @return void
	 */
	public function order_created( $order_id, $status, $cart_contents, $cart_info ) {

		if ( empty( $cart_contents ) || empty( $cart_info ) ) {
			return;
		}

		if ( $cart_info['total'] > 0 && 'order_received' == $status ) {
			return; // Don't run on pending orders (for example when someone )
		}

		$cart_info = $cart_info['owner_data'];

		// Loop through the ticket ids from the cart.

		foreach ( $cart_contents as $ticket_id => $number ) {

			// Check if the ticket has a tag.
			$settings = get_post_meta( $ticket_id, 'wpf_settings_tickera', true );

			if ( false == $settings || empty( $settings['apply_tags'] ) ) {
				continue;
			}

			// Get event data from the ticket.
			$event_data = $this->get_event_data( $ticket_id );

			// Get ticket data from ticket id.
			$ticket_data = $this->get_ticket_data( $ticket_id );

			$event_ticket_data = array_merge( $event_data, $ticket_data );

			// Loop through the ticket attendee from each ticket.

			if ( isset( $cart_info['owner_email_post_meta'] ) && ! empty( $cart_info['owner_email_post_meta'][ $ticket_id ] ) ) {

				// Separate emails for each attendee

				foreach ( $cart_info['owner_email_post_meta'][ $ticket_id ] as $key => $email ) {

					$person = array(
						'first_name' => $cart_info['first_name_post_meta'][ $ticket_id ][ $key ],
						'last_name'  => $cart_info['last_name_post_meta'][ $ticket_id ][ $key ],
						'user_email' => $email,
					);

					$update_data = array_merge( $person, $event_ticket_data );

					$this->register_attendee( $update_data, $settings['apply_tags'] );
				}
			} elseif ( ! empty( $cart_info['buyer_data']['email_post_meta'] ) ) {

				// No attendee emails, use the buyer's email

				$person = array(
					'first_name' => $cart_info['first_name_post_meta'][ $ticket_id ][ $key ],
					'last_name'  => $cart_info['last_name_post_meta'][ $ticket_id ][ $key ],
					'user_email' => $cart_info['buyer_data']['email_post_meta'],
				);

				$update_data = array_merge( $person, $event_ticket_data );

				$this->register_attendee( $update_data, $settings['apply_tags'] );

			}
		}

	}

	/**
	 * Trigger order_created() when an order transitions to paid.
	 *
	 * @since 3.37.19
	 *
	 * @param string  $new_status The new status.
	 * @param string  $old_status The old status
	 * @param WP_Post $post       The post.
	 */
	public function order_status_change( $new_status, $old_status, $post ) {

		if ( 'order_paid' === $new_status && 'order_paid' !== $old_status ) {

			$cart_contents = get_post_meta( $post->ID, 'tc_cart_contents', true );
			$cart_info     = get_post_meta( $post->ID, 'tc_cart_info', true );

			$this->order_created( $post->ID, 'order_paid', $cart_contents, $cart_info );

		}

	}

	/**
	 * Adds TC field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['tickera'] = array(
			'title'  => 'Tickera',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Loads TC fields for inclusion in Contact Fields table
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function add_meta_fields( $meta_fields ) {
		$meta_fields['tc_event_name'] = array(
			'label'  => 'Event Name',
			'type'   => 'text',
			'group'  => 'tickera',
			'pseudo' => true,
		);

		$meta_fields['tc_event_location'] = array(
			'label'  => 'Event Location',
			'type'   => 'text',
			'group'  => 'tickera',
			'pseudo' => true,
		);

		$meta_fields['tc_event_start_date_time'] = array(
			'label'  => 'Event Start Date and Time',
			'type'   => 'date',
			'group'  => 'tickera',
			'pseudo' => true,
		);

		$meta_fields['tc_event_end_date_time'] = array(
			'label'  => 'Event End Date and Time',
			'type'   => 'date',
			'group'  => 'tickera',
			'pseudo' => true,
		);

		$meta_fields['tc_ticket_name'] = array(
			'label'  => 'Ticket Name',
			'type'   => 'text',
			'group'  => 'tickera',
			'pseudo' => true,
		);

		$meta_fields['tc_ticket_checkin_availability_from_date'] = array(
			'label'  => 'Ticket Checkin Availability From Date',
			'type'   => 'date',
			'group'  => 'tickera',
			'pseudo' => true,
		);

		$meta_fields['tc_ticket_checkin_availability_to_date'] = array(
			'label'  => 'Ticket Checkin Availability To Date',
			'type'   => 'date',
			'group'  => 'tickera',
			'pseudo' => true,
		);

		return $meta_fields;

	}



	/**
	 * Adds meta box on the Tickets post type.
	 *
	 * @since 3.37.18
	 */
	public function add_meta_box() {

		add_meta_box( 'tickera-wp-fusion', 'WP Fusion - Ticket Settings', array( $this, 'meta_box_callback' ), 'tc_tickets', 'normal', 'default' );
	}

	/**
	 * Displays meta box content.
	 *
	 * @since 3.37.18
	 *
	 * @param WP_Post $post   The post.
	 */
	public function meta_box_callback( $post ) {
		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wpf_meta_box_tickera', 'wpf_meta_box_tickera_nonce' );

		$settings = array(
			'apply_tags'         => array(),
			'apply_tags_checkin' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf_settings_tickera', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf_settings_tickera', true ) );
		}

		// Apply tags

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags">' . __( 'Apply Tags', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags'],
			'meta_name' => 'wpf_settings_tickera',
			'field_id'  => 'apply_tags',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . sprintf( __( 'These tags will be applied in %s when a customer registers using this ticket.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_checkin">' . __( 'Apply Tags - Checked In', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		// Checkin
		$args = array(
			'setting'   => $settings['apply_tags_checkin'],
			'meta_name' => 'wpf_settings_tickera',
			'field_id'  => 'apply_tags_checkin',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'These tag will be applied when an attendee is checked in to the event.', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		do_action( 'wpf_edd_meta_box_inner', $post, $settings );

		echo '</tbody></table>';

	}

	/**
	 * Saves ticket metabox data.
	 *
	 * @since 3.37.18
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_meta_box_data( $post_id ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_tickera_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_tickera_nonce'], 'wpf_meta_box_tickera' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! empty( $_POST['wpf_settings_tickera'] ) ) {
			update_post_meta( $post_id, 'wpf_settings_tickera', $_POST['wpf_settings_tickera'] );
		} else {
			delete_post_meta( $post_id, 'wpf_settings_tickera' );
		}
	}


}
new WPF_Tickera();
