<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Salon_Booking extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'salon-booking';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Salon-booking';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/integrations/salon-booking/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'sln.booking_builder.create.booking_created', array( $this, 'booking_created' ) );

		add_filter( 'wpf_user_register', array( $this, 'filter_form_fields' ), 10, 2 );

		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

	}

	/**
	 * Booking created
	 *
	 * @access public
	 * @return void
	 */

	public function booking_created( $booking ) {

		// Maybe create contact if it's a guest booking

		$user_id = $booking->getUserId();

		$contact_data = array(
			'first_name'   => $booking->getFirstName(),
			'last_name'    => $booking->getLastName(),
			'user_email'   => $booking->getEmail(),
			'_sln_address' => $booking->getAddress(),
			'_sln_phone'   => $booking->getPhone(),
		);

		if ( ! empty( $user_id ) ) {

			wp_fusion()->user->push_user_meta( $user_id, $contact_data );

		} else {

			wpf_log( 'info', 0, 'Salon Bookings guest booking:', array( 'meta_array' => $contact_data ) );

			$contact_id = wp_fusion()->crm->get_contact_id( $contact_data['user_email'] );

			if ( false == $contact_id ) {

				$contact_id = wp_fusion()->crm->add_contact( $contact_data );

			} else {

				wp_fusion()->crm->update_contact( $contact_id, $contact_data );

			}

			if ( is_wp_error( $contact_id ) ) {

				wpf_log( $contact_id->get_error_code(), 0, 'Error adding contact: ' . $contact_id->get_error_message() );
				return;

			}

			update_post_meta( $booking->getId(), WPF_CONTACT_ID_META_KEY, $contact_id );

		}

		// Apply tags
		$apply_tags = array();

		$services = $booking->getServicesIds();

		foreach ( $services as $service_id ) {

			$settings = get_post_meta( $service_id, 'wpf-settings', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_service'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_service'] );
			}
		}

		if ( ! empty( $apply_tags ) ) {

			if ( ! empty( $user_id ) ) {

				wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			} else {

				wpf_log( 'info', $user_id, 'Salon Bookings guest booking applying tag(s): ', array( 'tag_array' => $apply_tags ) );

				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}
		}

	}


	/**
	 * Filters registration data before sending to the CRM
	 *
	 * @access public
	 * @return array Registration / Update Data
	 */

	public function filter_form_fields( $post_data, $user_id ) {

		if ( ! isset( $post_data['sln'] ) ) {
			return $post_data;
		}

		$post_data = array_merge( $post_data, $post_data['sln'] );

		$field_map = array(
			'firstname' => 'first_name',
			'lastname'  => 'last_name',
			'email'     => 'user_email',
			'password'  => 'user_pass',
			'phone'     => '_sln_phone',
			'address'   => '_sln_address',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		return $post_data;

	}

	/**
	 * Adds Salon Booking field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['salon_booking'] = array(
			'title'  => 'Salon Booking',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Set field keys / labels for Salon Booking fields
	 *
	 * @access public
	 * @return array Settings
	 */

	public function add_meta_fields( $meta_fields ) {

		$meta_fields['_sln_address'] = array(
			'label' => 'Address',
			'group' => 'salon_booking',
		);

		$meta_fields['_sln_phone'] = array(
			'label' => 'Phone Number',
			'group' => 'salon_booking',
		);

		return $meta_fields;

	}


	/**
	 * Removes standard WPF meta boxes from Salon related post types
	 *
	 * @access  public
	 * @return  array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['sln_booking'] );
		unset( $post_types['sln_service'] );
		unset( $post_types['sln_attendant'] );

		return $post_types;

	}


	/**
	 * Register WPF meta boxes
	 *
	 * @access  public
	 * @return  void
	 */

	public function add_meta_boxes( $service ) {

		add_meta_box( 'wpf-service-meta', 'WP Fusion - Service Settings', array( $this, 'meta_box_callback_service' ), 'sln_service' );

	}


	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback_service( $post ) {

		$settings = array(
			'apply_tags_service' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings', true ) );
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">' . __( 'Apply tags', 'wp-fusion' ) . '</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_service'],
			'meta_name' => 'wpf-settings',
			'field_id'  => 'apply_tags_service',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . __( 'Select tags to be applied when someone books this service', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

	}


}

new WPF_Salon_Booking();
