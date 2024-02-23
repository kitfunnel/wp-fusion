<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WPF_AmeliaBooking extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.42.10
	 * @var string $slug
	 */

	public $slug = 'ameliabooking';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.42.10
	 * @var string $name
	 */
	public $name = 'Amelia';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.42.10
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/amelia/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */
	public function init() {

		// Admin settings.
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 40 );

		// Sync new appointments.
		add_action( 'AmeliaBookingAddedBeforeNotify', array( $this, 'booking_created' ), 10, 2 );

		// WPF stuff.
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		// Add settings.
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

		// Batch operations.
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_filter( 'wpf_batch_ameliabooking_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_ameliabooking', array( $this, 'batch_step' ) );
	}

	/**
	 * Create Update Customer
	 * Create or update customer in CRM based on customer ID and appointment ID.
	 *
	 * @since 3.42.9
	 *
	 * @param int $customer_id    The customer ID.
	 * @param int $appointment_id The appointment ID.
	 *
	 * @return int $contact_id The contact ID in the CRM.
	 */
	public function create_update_customer( $customer_id, $appointment_id ) {

		global $wpdb;

		$amelia_users_table = $wpdb->prefix . 'amelia_users';

		// Get the customer's email so we can get the contact ID.
		$customer_email = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT email FROM {$amelia_users_table} WHERE id = %d",
				$customer_id
			)
		);

		// Get custom fields from the customers table.
		// These are the custom fields used in the booking.
		$customers_table = $wpdb->prefix . 'amelia_customer_bookings';

		$custom_fields = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT customFields FROM {$customers_table} WHERE id = %d",
				$appointment_id
			)
		);

		$custom_fields = json_decode( $custom_fields[0]->customFields, true );

		// Saving custom fields.
		foreach ( $custom_fields as $field ) {

			$key               = str_replace( ' ', '_', strtolower( $field['label'] ) );
			$user_meta[ $key ] = $field['value'];
		}

		$appointments_table = $wpdb->prefix . 'amelia_appointments';

		// We always sync the appointment start date/time and the service name.
		$user_meta['appointment_date_time'] = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT bookingStart FROM {$appointments_table} WHERE id = %d",
				$appointment_id
			)
		);

		// Sync the service name.
		$service_id                = $this->get_service_id( $appointment_id );
		$user_meta['service_name'] = $this->get_service_name( $service_id );

		$user = get_user_by( 'email', $customer_email );

		$customer_data = array(
			'user_email' => $customer_email,
			'first_name' => get_user_meta( $user->ID, 'first_name', true ),
			'last_name'  => get_user_meta( $user->ID, 'last_name', true ),
		);

		$user_meta = array_merge( $user_meta, $customer_data );

		// Sync it to the CRM.

		if ( false !== $user ) {

			// Registered users.

			wp_fusion()->user->push_user_meta( $user->ID, $user_meta );

			$contact_id = wp_fusion()->user->get_contact_id( $user->ID ); // we'll use this in the next step.

		} else {

			// Helper function for creating/updating contact in the CRM from a guest checkout.

			$contact_id = $this->guest_registration( $customer_email, $user_meta );

		}

		return $contact_id;
	}

	/**
	 * Process Appointment
	 * Process an appointment, create customer, and apply tags.
	 *
	 * @since 3.42.9
	 *
	 * @param int $appointment_id Amelia appointment ID.
	 */
	public function process_appointment( $appointment_id ) {

		global $wpdb;

		// Get the customer id who booked the appointment.
		$customers_table = $wpdb->prefix . 'amelia_customer_bookings';

		$customer_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT customerId FROM {$customers_table} WHERE appointmentId = %d",
				$appointment_id
			)
		);

		// Get the Amelia Appointment so that we can add a note to it.
		// Ameliabooking doesn't have a filter to edit the data before it's saved, so we have to do it this way.
		$appointments_table = $wpdb->prefix . 'amelia_appointments';

		$appointment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT internalNotes FROM {$appointments_table} WHERE id = %d",
				$appointment_id
			)
		);
		$appointment->internalNotes = 'wpf_create ' . current_time( 'mysql' ) . "\n" . $appointment->internalNotes;

		$wpdb->update(
			$appointments_table,
			array(
				'internalNotes' => $appointment->internalNotes,
			),
			array(
				'id' => $appointment_id,
			)
		);

		$contact_id = $this->create_update_customer( $customer_id, $appointment_id );
		$user_id    = $this->get_user_id( $customer_id );
		$service_id = $this->get_service_id( $appointment_id );

		// Get the service settings.
		$service_settings = $this->get_service_settings( $service_id );

		$apply_tags = $service_settings['apply_tags'];

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
	 * Booking Created.
	 *
	 * Syncs new appointments to CRM.
	 *
	 * @since 3.42.10
	 *
	 * @param array  $data The appointment data.
	 * @param object $container The Amelia container.
	 */
	public function booking_created( $data, $container ) {

		// Only sync if the sync setting is on.
		$sync_settings = wpf_get_option( 'amelia_guests', array() );

		if ( false === $sync_settings ) {
			return;
		}

		$this->process_appointment( $data['appointment']['id'] );
	}

	/**
	 * Add Meta Field Group
	 * Adds field group to meta fields list.
	 *
	 * @since 3.42.10
	 *
	 * @param array $field_groups Field groups.
	 *
	 * @return array Field groups
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['ameliabooking'] = array(
			'title'  => 'Amelia Booking',
			'fields' => array(),
		);

		return $field_groups;
	}


	/**
	 * Prepare Meta Fields
	 * Adds meta fields to WPF contact fields list.
	 *
	 * @since 3.42.10
	 *
	 * @param array $meta_fields Meta fields.
	 *
	 * @return array Meta Fields
	 */
	public function prepare_meta_fields( $meta_fields = array() ) {

		global $wpdb;

		$meta_fields['service_name']          = array(
			'label' => 'Service Name',
			'type'  => 'text',
			'group' => 'ameliabooking',
		);
		$meta_fields['appointment_date_time'] = array(
			'label' => 'Appointment Date / Time',
			'type'  => 'datetime',
			'group' => 'ameliabooking',
		);

		// Get all of the Amelia custom fields.
		$table = $wpdb->prefix . 'amelia_custom_fields';

		$query         = $wpdb->prepare( "SELECT id, label, type FROM $table" );
		$custom_fields = $wpdb->get_results( $query );

		foreach ( $custom_fields as $id => $field ) {
			$key = str_replace( ' ', '_', strtolower( $field->label ) );

			if ( 'datetime' === $field->type ) {
				$field->type = 'date';
			}

			$meta_fields[ $key ] = array(
				'label' => $field->label,
				'type'  => $field->type,
				'group' => 'ameliabooking',
			);
		}

		return $meta_fields;
	}

	/**
	 * Admin Menu
	 * Creates WPF submenu item.
	 *
	 * @since 3.42.10
	 */
	public function admin_menu() {

		$id = add_submenu_page(
			'amelia',
			/* translators: %s: CRM Name */
			sprintf( __( '%s Integration', 'wp-fusion' ), wp_fusion()->crm->name ),
			__( 'WP Fusion', 'wp-fusion' ),
			'manage_options',
			'wpamelia-wpf-settings',
			array( $this, 'render_admin_menu' ),
			14,
		);

		add_action( 'load-' . $id, array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue Scripts
	 * Enqueues WPF scripts and styles.
	 *
	 * @since 3.42.10
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'options-css', WPF_DIR_URL . 'includes/admin/options/css/options.css' );
		wp_enqueue_style( 'wpf-options', WPF_DIR_URL . 'assets/css/wpf-options.css' );
	}

	/**
	 * Get User ID.
	 * Gets a customer's user ID from customer ID.
	 *
	 * @since 3.42.9
	 *
	 * @param int $customer_id The customer ID.
	 *
	 * @return int|null The user ID or null if the customer doesn't have a user account.
	 */
	public function get_user_id( $customer_id ) {

		global $wpdb;

		$amelia_users_table = $wpdb->prefix . 'amelia_users';

		// Get the customer's user ID.
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT externalId FROM {$amelia_users_table} WHERE id = %d",
				$customer_id
			)
		);
	}

	/**
	 * Get Service ID.
	 * Gets the service ID by appointment ID.
	 *
	 * @since 3.42.9
	 *
	 * @param int $appointment_id The appointment ID.
	 *
	 * @return int $service_id The service ID.
	 */
	public function get_service_id( $appointment_id ) {

		global $wpdb;

		$appointments_table = $wpdb->prefix . 'amelia_appointments';

		// Get the service ID.
		$service_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT serviceId FROM {$appointments_table} WHERE id = %d",
				$appointment_id
			)
		);

		return $service_id;
	}

	/**
	 * Get Service Name
	 * Gets the service name by service ID.
	 *
	 * @since 3.42.9
	 *
	 * @param int $service_id The service ID.
	 *
	 * @return string $service_name The service name.
	 */
	public function get_service_name( $service_id ) {

		global $wpdb;

		$services_table = $wpdb->prefix . 'amelia_services';

		// Get the name of the service.
		$service_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$services_table} WHERE id = %d",
				$service_id
			)
		);

		return $service_name;
	}

	/**
	 * Get Service Settings
	 * Gets the tags to apply for a service by service ID.
	 *
	 * @since 3.42.9
	 *
	 * @param int $service_id The service ID.
	 *
	 * @return array $settings The service settings.
	 */
	public function get_service_settings( $service_id ) {

		global $wpdb;

		$table = $wpdb->prefix . 'amelia_services';

		// Get the settings for the service.
		$service_settings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT settings FROM {$table} WHERE id = %d",
				$service_id
			)
		);

		$settings = json_decode( $service_settings, true );

		return $settings;
	}

	/**
	 * Render Admin Menu
	 * Renders WPF submenu item.
	 *
	 * @since 3.42.10
	 * @since 3.42.9 Internationalized strings to comply with I18n standards.
	 */
	public function render_admin_menu() {

		// Get the Amelia Services.
		global $wpdb;
		$table = $wpdb->prefix . 'amelia_services';

		$query    = $wpdb->prepare( "SELECT id, name FROM $table" );
		$services = $wpdb->get_results( $query );

		?>

		<div class="wrap">

			<?php /* translators: %s: CRM Name */ ?>
			<h1><?php printf( esc_html__( '%s Integration', 'wp-fusion' ), wp_fusion()->crm->name ); ?> </h1>

			<?php

			// Save settings.
			// We unset any apply_tags before saving, then add them back in. This allows no tags to be selected.
			if ( isset( $_POST['wpf_amelia_settings_nonce'] ) && wp_verify_nonce( $_POST['wpf_amelia_settings_nonce'], 'wpf_amelia_settings' ) ) {

				if ( isset( $_POST['wpf_amelia_sync_settings'] ) ) {
					wp_fusion()->settings->set( 'amelia_guests', true );
				} else {
					wp_fusion()->settings->set( 'amelia_guests', false );
				}

				// Check all the services for any apply tags settings.
				foreach ( $services as $service => $data ) {
					$service_settings = $this->get_service_settings( $data->id );

					if ( array_key_exists( 'apply_tags', $service_settings ) ) {
						unset( $service_settings['apply_tags'] );
					}

					if ( isset( $_POST['wpf_amelia_settings_services'] ) && isset( $_POST['wpf_amelia_settings_services'][ $data->id ]['apply_tags'] ) ) {
						foreach ( $_POST['wpf_amelia_settings_services'][ $data->id ]['apply_tags'] as $tag_id ) {
							$service_settings['apply_tags'][] = $tag_id;
						}
					}

					$service_settings = wp_json_encode( $service_settings );

					$wpdb->update(
						$table,
						array(
							'settings' => $service_settings,
						),
						array(
							'id' => $data->id,
						)
					);
				}

				echo '<div id="message" class="updated fade"><p><strong>' . esc_html__( 'Settings Saved', 'wp-fusion' ) . '</strong></p></div>';
			}

			?>

			<form id="wpf-amelia-settings" action="" method="post" style="width: 100%; max-width: 1200px;">

				<?php wp_nonce_field( 'wpf_amelia_settings', 'wpf_amelia_settings_nonce' ); ?>

				<input type="hidden" name="action" value="update">

					<table class="table table-hover wpf-settings-table">
						<thead>
							<tr>
								<?php /* translators: %s: CRM Name */ ?>
								<th scope="row">
									<?php printf( esc_html__( 'Sync guest bookings to %s', 'wp-fusion' ), wp_fusion()->crm->name ); ?>
									<p class="description" style="font-weight: 500;";><?php printf( esc_html__( 'Bookings by registered users will always be synced.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></p>
								</th>
								<td><input type="checkbox" name="wpf_amelia_sync_settings" <?php checked( wpf_get_option( 'amelia_guests' ) ); ?> /></td>
							</tr>
						</thead>
				</table>

				<h4><?php esc_html_e( 'Apply Tags For Services', 'wp-fusion' ); ?></h4>
				<p class="description"><?php esc_html_e( 'You can automate the application of tags when a user books a service in Amelia. For each service, select one or more tags to be applied when the service is booked.', 'wp-fusion' ); ?></p>
				<br/>

				<table class="table table-hover wpf-settings-table">
					<thead>
						<tr>

							<th scope="row"><?php esc_html_e( 'Amelia Service', 'wp-fusion' ); ?></th>
							<th scope="row"><?php esc_html_e( 'Apply Tags', 'wp-fusion' ); ?></th>

						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $services as $service ) :

							$service_settings = $this->get_service_settings( $service->id );

							?>

							<tr>
								<td><?php echo esc_html( $service->name ); ?></td>
								<td>

									<?php

									if ( ! isset( $service_settings['apply_tags'] ) ) {
										$service_settings['apply_tags'] = array();
									}

									$args = array(
										'setting'   => $service_settings['apply_tags'],
										'meta_name' => "wpf_amelia_settings_services[{$service->id}][apply_tags]",
									);

									wpf_render_tag_multiselect( $args );

									?>

								</td>

							</tr>

						<?php endforeach; ?>

					</tbody>

				</table>

				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes"/></p>

			</form>
		</div>
		<?php
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

		$settings['amelia_header'] = array(
			'title'   => __( 'Amelia Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['amelia_guests'] = array(
			'title'   => __( 'Sync Guests', 'wp-fusion' ),
			/* translators: %s: CRM Name */
			'desc'    => sprintf( __( 'Sync guest bookings with %s.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'std'     => 1,
			'type'    => 'checkbox',
			'section' => 'integrations',
			'tooltip' => __( 'Bookings by registered users will always be synced.', 'wp-fusion' ),
		);

		return $settings;
	}

	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds AmeliaBooking to available export options
	 *
	 * @since  3.42.10
	 *
	 * @param  array $options The export options.
	 * @return array The export options.
	 */
	public function export_options( $options ) {

		$options['ameliabooking'] = array(
			'label'         => __( 'Amelia Booking appointments', 'wp-fusion' ),
			'title'         => __( 'Appointments', 'wp-fusion' ),
			'process_again' => true,
			/* translators: %s: CRM Name */
			'tooltip'       => sprintf( __( 'For each appointment, syncs any available fields to %s, and applies any configured appointment tags.', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		return $options;
	}

	/**
	 * Batch Init.
	 * Get all appointments to be processed.
	 *
	 * @since  3.42.10
	 *
	 * @param  array $args The batch arguments.
	 *
	 * @return array|bool The appointment IDs, or false if the sync setting is off.
	 */
	public function batch_init( $args ) {

		// Don't sync if the sync setting is off.
		if ( ! wpf_get_option( 'amelia_guests' ) ) {
			wpf_log( 'notice', get_current_user_id(), 'WPF Amelia Booking sync setting is disabled, stopping batch operation.' );
			return false;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'amelia_appointments';

		$query = "SELECT id FROM {$table}";

		// Skip any appointments that have been processed if the skip processed entries is on.
		if ( isset( $args['skip_processed'] ) ) {
			$query .= " WHERE internalNotes NOT LIKE '%wpf_create%'";
		}

		$appointments = $wpdb->get_results( $query );

		$appointment_ids = wp_list_pluck( $appointments, 'id' );

		return $appointment_ids;
	}

	/**
	 * Batch Step.
	 * Processes appointments in batches.
	 *
	 * @since 3.42.10
	 * @see WPF_AmeliaBooking::wpf_service_helper();
	 *
	 * @param int $appointment_id The appointment ID.
	 */
	public function batch_step( $appointment_id ) {

		$this->process_appointment( $appointment_id );
	}
}

new WPF_AmeliaBooking();
