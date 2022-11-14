<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * EventON integration.
 *
 * @since 3.38.5
 *
 * @link https://wpfusion.com/documentation/events/eventon/
 */
class WPF_EventON extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'eventon';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'EventON';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/eventon/';

	/**
	 * Gets things started.
	 *
	 * @since 3.38.5
	 */
	public function init() {

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

		add_filter( 'wpf_woocommerce_customer_data', array( $this, 'merge_custom_fields' ), 10, 2 );

		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ) );
		add_action( 'wpf_woocommerce_payment_complete', array( $this, 'add_attendee_data' ), 20, 2 );

		// Check-ins.
		add_action( 'updated_post_meta', array( $this, 'checkin' ), 10, 4 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10 );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ), 20, 2 );

	}



	/**
	 * Utility function for getting any EventON attendees from a WooCommerce
	 * order.
	 *
	 * @since  3.40.4
	 *
	 * @param  int $order_id The order ID.
	 * @return array Array of attendee IDs.
	 */
	private function get_attendees_from_order( $order_id ) {

		$order_attendees = get_post_meta( $order_id, '_tixids', true );
		if ( empty( $order_attendees ) ) {
			return array();
		}

		$attendee_ids = array();
		// Extract attendee ids.
		foreach ( $order_attendees as $order_attendee ) {
			$attendee_id    = explode( '-', $order_attendee );
			$attendee_ids[] = intval( $attendee_id[0] );
		}

		return $attendee_ids;

	}


	/**
	 * Add / tag contacts for event attendees
	 *
	 * @since 3.40.4
	 *
	 * @param int    $order_id   The order ID.
	 * @param string $contact_id The CRM contact ID.
	 */
	public function add_attendee_data( $order_id, $contact_id ) {

		if ( empty( get_option( 'evcal_options_evcal_tx' )['evotx_add_fields'] ) || ! in_array( 'email', get_option( 'evcal_options_evcal_tx' )['evotx_add_fields'] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		foreach ( $this->get_attendees_from_order( $order_id ) as $attendee_id ) {

			$attendee_data  = get_post_meta( $attendee_id, '_ticket_holder_data', true );
			$product_id     = intval( get_post_meta( $attendee_id, 'wcid', true ) );
			$attendee_email = $attendee_data['email'];

			$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

			if ( empty( $settings ) || empty( $settings['add_attendees_eventon'] ) ) {
				continue;
			}

			// This was already sent in the main order data so it doesn't need to be sent again.
			if ( $attendee_email === $order->get_billing_email() ) {
				continue;
			}

			if ( empty( $attendee_email ) ) {
				wpf_log( 'notice', 0, 'Unable to sync attendee data, no email address provided. To sync attendees you must enable <strong>Capture attendee full name and email address?</strong> when editing the EventON product.' );
				continue;
			}

			$name       = $attendee_data['name'];
			$first_name = explode( ' ', $name )[0];
			$last_name  = explode( ' ', $name )[1];

			$update_data = array(
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'user_email'   => $attendee_email,
				'phone_number' => $attendee_data['phone'],
			);

			// Merge attendee and event fields.
			$event_id = get_post_meta( $attendee_id, '_eventid', true );

			$event_fields = array(
				'event_name'       => get_the_title( $event_id ),
				'event_start_date' => get_post_meta( $event_id, 'evcal_srow', true ),
				'event_end_date'   => get_post_meta( $event_id, 'evcal_erow', true ),
				'event_start_time' => get_post_meta( $event_id, '_start_hour', true ) . ':' . get_post_meta( $event_id, '_start_minute', true ) . ' ' . get_post_meta( $event_id, '_start_ampm', true ),
				'event_end_time'   => get_post_meta( $event_id, '_end_hour', true ) . ':' . get_post_meta( $event_id, '_end_minute', true ) . ' ' . get_post_meta( $event_id, '_end_ampm', true ),
			);

			$update_data = array_merge( $update_data, $event_fields );

			$update_data = apply_filters( 'wpf_eventon_attendee_data', $update_data, $attendee_id, $order_id );

			$contact_id = wp_fusion()->crm->get_contact_id( $update_data['user_email'] );

			if ( empty( $contact_id ) ) {

				wpf_log( 'info', 0, 'Processing EventON event attendee for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>:', array( 'meta_array' => $update_data ) );

				$contact_id = wp_fusion()->crm->add_contact( $update_data );

				if ( is_wp_error( $contact_id ) ) {
					wpf_log( 'error', 0, 'Error while adding contact: ' . $contact_id->get_error_message() . '. Tags will not be applied.' );
					continue;
				}
			} else {

				wpf_log( 'info', 0, 'Processing EventON event attendee for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>, for existing contact #' . $contact_id . ':', array( 'meta_array' => $update_data ) );

				$result = wp_fusion()->crm->update_contact( $contact_id, $update_data );

				if ( is_wp_error( $result ) ) {
					wpf_log( 'error', 0, 'Error while updating contact: ' . $result->get_error_message() );
				}
			}

			$apply_tags = array();

			// Product settings.

			if ( ! empty( $settings['apply_tags_attendees_eventon'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_attendees_eventon'] );
			}

			if ( ! empty( $apply_tags ) ) {

				wpf_log( 'info', 0, 'Applying tags to EventON attendee for contact #' . $contact_id . ': ', array( 'tag_array' => $apply_tags ) );

				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}

			// Save the contact ID.

			update_post_meta( $attendee_id, WPF_CONTACT_ID_META_KEY, $contact_id );

		}

	}



	/**
	 * Display event settings.
	 *
	 * @since 3.40.4
	 *
	 * @param int $post_id The product ID.
	 */
	public function panel_content( $post_id ) {

		if ( ! get_post_meta( $post_id, '_eventid', true ) ) {
			return;
		}

		$settings = array(
			'apply_tags_attendees_eventon' => array(),
			'add_attendees_eventon'        => false,
		);

		if ( get_post_meta( $post_id, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group wpf-product">';

		echo '<p class="form-field"><label><strong>' . __( 'EventON', 'wp-fusion' ) . '</strong></label></p>';

		if ( empty( get_option( 'evcal_options_evcal_tx' )['evotx_add_fields'] ) || ! in_array( 'email', get_option( 'evcal_options_evcal_tx' )['evotx_add_fields'] ) ) {
			echo '<p><b>This will not work until you enable the email address field in the additional fields option in <a href="' . admin_url( 'admin.php?page=eventon&tab=evcal_tx#evotx2a' ) . '">EventON tickets settings</a>.</b></p>';
		}

		echo '<p class="form-field"><label for="wpf-add-attendees">' . __( 'Add attendees', 'wp-fusion' ) . '</label>';
		echo '<input class="checkbox" type="checkbox" id="wpf-add-attendees" name="wpf-settings-woo[add_attendees_eventon]" data-unlock="wpf-settings-woo-apply_tags_attendees_eventon" value="1" ' . checked( $settings['add_attendees_eventon'], 1, false ) . ' />';
		echo '<span class="description">' . sprintf( __( 'Add each event attendee as a separate contact in %s.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</p>';

		echo '<p class="form-field"><label for="wpf-apply-tags-woo">' . __( 'Apply tags to event attendees', 'wp-fusion' );

		echo ' <span class="dashicons dashicons-editor-help wpf-tip wpf-tip-bottom" data-tip="' . __( 'These tags will only be applied to event attendees entered on the registration form, not the customer who placed the order. <strong>Add attendees</strong> must be enabled.', 'wp-fusion' ) . '"></span>';

		echo '</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_attendees_eventon'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_attendees_eventon',
				'disabled'  => $settings['add_attendees_eventon'] ? false : true,
			)
		);

		echo '</p>';

		echo '</div>';

	}


	/**
	 * Adds meta box to the event post type.
	 *
	 * @since 3.40.4
	 */
	public function add_meta_box() {

		add_meta_box( 'wpf-eventon', 'WP Fusion', array( $this, 'meta_box_callback' ), 'ajde_events' );

	}



	/**
	 * Displays meta box content.
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback( $post ) {

		wp_nonce_field( 'wpf_meta_box_eventon', 'wpf_meta_box_eventon_nonce' );

		$settings = array(
			'apply_tags_checkin' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf_settings_eventon', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf_settings_eventon', true ) );
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">' . __( 'Apply tags - Check-in', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_checkin'],
			'meta_name' => 'wpf_settings_eventon',
			'field_id'  => 'apply_tags_checkin',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . sprintf( __( 'These tags will be applied in %s when an attendee has checked in to an event.', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

	}


	/**
	 * Sync check-in status.
	 *
	 * @since 3.40.4
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $ticket_id  The ticket ID.
	 * @param string $meta_key   The meta key.
	 * @param string $meta_value The meta value.
	 */
	public function checkin( $meta_id, $ticket_id, $meta_key, $meta_value ) {

		if ( 'status' !== $meta_key ) {
			return;
		}

		if ( 'evo-tix' !== get_post_type( $ticket_id ) ) {
			return;
		}

		if ( 'checked' !== $meta_value ) {
			return;
		}

		$event_name = '';
		$settings   = get_post_meta( $ticket_id, 'wpf_settings_eventon', true );

		$event_id = get_post_meta( $ticket_id, '_eventid', true );
		if ( intval( $event_id ) !== 0 ) {
			$event_name = get_the_title( $event_id );
		}

		$user_id = get_post_meta( $ticket_id, '_customerid', true );

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

			$order_id   = get_post_meta( $ticket_id, '_orderid', true );
			$contact_id = get_post_meta( $order_id, WPF_CONTACT_ID_META_KEY, true );

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
	 * Runs when WPF meta box is saved.
	 *
	 * @since 3.40.4
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_meta_box_data( $post_id ) {

		// Check if our data is set.
		if ( ! isset( $_POST['wpf_settings_eventon'] ) ) {
			return;
		}

		$data = WPF_Admin_Interfaces::sanitize_tags_settings( wp_unslash( $_POST['wpf_settings_eventon'] ) );

		if ( ! empty( $data ) ) {
			update_post_meta( $post_id, 'wpf_settings_eventon', $data );
		} else {
			delete_post_meta( $post_id, 'wpf_settings_eventon' );
		}

	}



	/**
	 * Merges custom fields for the primary contact on the order
	 *
	 * @since  3.38.5
	 *
	 * @param  array    $customer_data The customer data to sync to the CRM.
	 * @param  WC_Order $order         The WooCommerce order.
	 * @return array    Customer data.
	 */
	public function merge_custom_fields( $customer_data, $order ) {

		foreach ( $order->get_items() as $item_id => $item ) {

			$product_id = $item->get_product_id();
			$event_id   = get_post_meta( $product_id, '_eventid', true );

			if ( empty( $event_id ) ) {
				continue;
			}

			$event_fields = array(
				'event_name'       => get_the_title( $event_id ),
				'event_start_date' => get_post_meta( $event_id, 'evcal_srow', true ),
				'event_end_date'   => get_post_meta( $event_id, 'evcal_erow', true ),
				'event_start_time' => get_post_meta( $event_id, '_start_hour', true ) . ':' . get_post_meta( $event_id, '_start_minute', true ) . ' ' . get_post_meta( $event_id, '_start_ampm', true ),
				'event_end_time'   => get_post_meta( $event_id, '_end_hour', true ) . ':' . get_post_meta( $event_id, '_end_minute', true ) . ' ' . get_post_meta( $event_id, '_end_ampm', true ),
			);

			$customer_data = array_merge( $customer_data, $event_fields );

			break;

		}

		return $customer_data;

	}

	/**
	 * Adds EventON field group to meta fields list.
	 *
	 * @since  3.38.5
	 *
	 * @param  array $field_groups The field groups.
	 * @return array  Field groups.
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['eventon'] = array(
			'title'  => 'EventON',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Loads EventON fields for inclusion in Contact Fields table.
	 *
	 * @since  3.38.5
	 *
	 * @param  array $meta_fields The meta fields.
	 * @return array The meta fields.
	 */
	public function add_meta_fields( $meta_fields ) {

		$meta_fields['event_name'] = array(
			'label'  => 'Event Name',
			'type'   => 'text',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		$meta_fields['event_start_date'] = array(
			'label'  => 'Event Start Date',
			'type'   => 'date',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		$meta_fields['event_start_time'] = array(
			'label'  => 'Event Start Time',
			'type'   => 'date',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		$meta_fields['event_end_date'] = array(
			'label'  => 'Event End Date',
			'type'   => 'date',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		$meta_fields['event_end_time'] = array(
			'label'  => 'Event End Time',
			'type'   => 'date',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		$meta_fields['event_checkin'] = array(
			'label'  => 'Event Check-in',
			'type'   => 'checkbox',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		$meta_fields['event_checkin_event'] = array(
			'label'  => 'Event Check-in Event Name',
			'type'   => 'text',
			'group'  => 'eventon',
			'pseudo' => true,
		);

		return $meta_fields;

	}


}

new WPF_EventON();
