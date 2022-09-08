<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Woo_Appointments extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'woo-appointments';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Woo-appointments';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/woocommerce-appointments/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.33.10
	 * @return  void
	 */

	public function init() {

		// Status changes
		add_action( 'woocommerce_appointment_unpaid', array( $this, 'status_transition' ), 10, 2 );
		add_action( 'woocommerce_appointment_pending-confirmation', array( $this, 'status_transition' ), 10, 2 );
		add_action( 'woocommerce_appointment_confirmed', array( $this, 'status_transition' ), 10, 2 );
		add_action( 'woocommerce_appointment_cancelled', array( $this, 'status_transition' ), 10, 2 );
		add_action( 'woocommerce_appointment_complete', array( $this, 'status_transition' ), 10, 2 );

		add_filter( 'wpf_woocommerce_customer_data', array( $this, 'sync_appointment_date' ), 10, 2 );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_field' ), 30 );
		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ) );

	}

	/**
	 * Apply tags during status changes
	 *
	 * @access public
	 * @return void
	 */

	public function status_transition( $appointment_id, $appointment ) {

		$product_id = $appointment->get_product_id();
		$status     = $appointment->get_status();
		$settings   = get_post_meta( $product_id, 'wpf-settings-woo', true );

		if ( ! empty( $settings ) && ! empty( $settings[ 'apply_tags_' . $status ] ) ) {

			$order   = $appointment->get_order();
			$user_id = $order->get_user_id();

			if ( ! empty( $user_id ) ) {

				if ( 'pending-confirmation' == $status || 'confirmed' == $status ) {

					// Sync the date for pending appointments, see https://secure.helpscout.net/conversation/1503280685/15818?folderId=726355

					$update_data = array(
						'appointment_date' => date( wpf_get_datetime_format(), $appointment->get_start() ),
						'appointment_time' => date( get_option( 'time_format' ), $appointment->get_start() ),
					);

					wp_fusion()->user->push_user_meta( $user_id, $update_data );

				}

				wp_fusion()->user->apply_tags( $settings[ 'apply_tags_' . $status ], $user_id );

			} else {

				// Guests

				$contact_id = wp_fusion()->integrations->woocommerce->maybe_create_contact_from_order( $order->get_id() );

				if ( ! empty( $contact_id ) && ! is_wp_error( $contact_id ) ) {

					if ( 'pending-confirmation' == $status || 'confirmed' == $status ) {

						$update_data = array(
							'appointment_date' => date( wpf_get_datetime_format(), $appointment->get_start() ),
							'appointment_time' => date( get_option( 'time_format' ), $appointment->get_start() ),
						);

						wp_fusion()->crm->update_contact( $contact_id, $update_data );

					}

					wpf_log( 'info', 0, 'WooCommerce Appointments guest booking applying tag(s) to contact #' . $contact_id . ': ', array( 'tag_array' => $settings[ 'apply_tags_' . $status ] ) );

					wp_fusion()->crm->apply_tags( $settings[ 'apply_tags_' . $status ], $contact_id );

				}
			}
		}

	}

	/**
	 * Merge appointment info into the order data
	 *
	 * @access public
	 * @return array Customer Data
	 */

	public function sync_appointment_date( $customer_data, $order ) {

		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order->get_id() );

		if ( ! empty( $appointment_ids ) ) {

			$appointment = get_wc_appointment( $appointment_ids[0] );

			$start = $appointment->get_start();

			$start_time = date( wpf_get_datetime_format(), $start );

			$customer_data['appointment_date'] = $start_time;
			$customer_data['appointment_time'] = date( get_option( 'time_format' ), $appointment->get_start() );

		}

		return $customer_data;

	}

	/**
	 * Adds field group to contact fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['woocommerce_appointments'] = array(
			'title'  => 'WooCommerce Appointments',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Adds booking date field to contact fields list
	 *
	 * @access public
	 * @return array Settings
	 */

	public function add_meta_field( $meta_fields ) {

		$meta_fields['appointment_date'] = array(
			'label' => 'Appointment Date',
			'type'  => 'date',
			'group' => 'woocommerce_appointments',
		);

		$meta_fields['appointment_time'] = array(
			'label' => 'Appointment Time',
			'type'  => 'text',
			'group' => 'woocommerce_appointments',
		);

		return $meta_fields;

	}


	/**
	 * Writes subscriptions options to WPF/Woo panel
	 *
	 * @access public
	 * @return mixed
	 */

	public function panel_content( $post_id ) {

		$statuses = get_wc_appointment_statuses( 'user', true );

		// Set defaults

		$settings = array();

		foreach ( $statuses as $key => $label ) {
			$settings[ 'apply_tags_' . $key ] = array();
		}

		if ( get_post_meta( $post_id, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group show_if_appointment">';

		echo '<p class="form-field"><label><strong>' . __( 'Appointment', 'wp-fusion' ) . '</strong></label></p>';

		echo '<p>' . sprintf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/events/woocommerce-appointments/" target="_blank">', '</a>' ) . '</p>';

		foreach ( $statuses as $key => $label ) {

			// Payment failed
			echo '<p class="form-field"><label>' . $label . '</label>';
			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings[ 'apply_tags_' . $key ],
					'meta_name' => 'wpf-settings-woo',
					'field_id'  => 'apply_tags_' . $key,
				)
			);
			echo '<span class="description">' . sprintf( __( 'Apply these tags when an appointment status is set to %s.', 'wp-fusion' ), strtolower( $label ) ) . '</span>';
			echo '</p>';

		}

		echo '</div>';

	}

}

new WPF_Woo_Appointments();
