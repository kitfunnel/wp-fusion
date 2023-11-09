<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Popup_Maker extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'popup-maker';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Popup Maker';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/popup-maker/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 8, 2 );

		// Form submissions
		add_action( 'pum_sub_form_submission', array( $this, 'form_submission' ), 10, 3 );

		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );

		// Load conditions
		add_filter( 'pum_registered_conditions', array( $this, 'registered_conditions' ) );

	}

	/**
	 * Registers additional Popup Maker settings
	 *
	 * @access  public
	 * @return  array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['pm_header'] = array(
			'title'   => __( 'Popup Maker Integration', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['pm_add_contacts'] = array(
			'title'   => __( 'Add Contacts', 'wp-fusion' ),
			'desc'    => sprintf( __( 'Add contacts to %s when a Popup Maker subscription form is submitted.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'std'     => 1,
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;

	}

	/**
	 * Sync Popup Maker form submissions to the CRM
	 *
	 * @access public
	 * @return void
	 */

	public function form_submission( $values, $response, $errors ) {

		if ( wpf_get_option( 'pm_add_contacts' ) != true ) {
			return;
		}

		// Give up if they didn't opt in
		if ( 'deleted@site.invalid' == $values['email'] ) {
			return;
		}

		$contact_data = array(
			'first_name' => $values['fname'],
			'last_name'  => $values['lname'],
			'user_email' => $values['email'],
		);

		// Send the meta data
		if ( wpf_is_user_logged_in() ) {

			wp_fusion()->user->push_user_meta( wpf_get_current_user_id(), $contact_data );

		} else {

			$contact_id = $this->guest_registration( $contact_data['user_email'], $contact_data );

		}

	}

	/**
	 * Removes standard WPF meta boxes from Popup Maker post type
	 *
	 * @access  public
	 * @return  array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['popup'] );

		return $post_types;

	}

	/**
	 * Loads conditions into Targeting panel
	 *
	 * @access public
	 * @return array Conditions
	 */

	public function registered_conditions( $conditions ) {

		$available_tags = wpf_get_option( 'available_tags', array() );

		if ( is_array( reset( $available_tags ) ) ) {

			// Handling for select with category groupings
			$data = array();

			$tag_categories = array();
			foreach ( $available_tags as $value ) {
				if ( ! isset( $data[ $value['category'] ] ) ) {
					$data[ $value['category'] ] = array();
				}
			}

			foreach ( $available_tags as $id => $value ) {

				$data[ $value['category'] ][ $id ] = $value['label'];

			}
		} else {

			$data = $available_tags;

		}

		$wpf_conditions = array(
			'wpf_tags' => array(
				'group'    => wp_fusion()->crm->name,
				'name'     => __( 'User Tags', 'wp-fusion' ),
				'callback' => array( $this, 'show_popup' ),
				'fields'   => array(
					'selected' => array(
						'placeholder' => __( 'Select tags', 'wp-fusion' ),
						'type'        => 'select',
						'multiple'    => true,
						'select2'     => true,
						'as_array'    => true,
						'class'       => 'select4-wpf-tags-wrapper',
						'options'     => $data,
					),
				),
			),

		);

		$conditions = array_merge( $conditions, $wpf_conditions );

		return $conditions;

	}


	/**
	 * Determine if the user should see the popup
	 *
	 * @access public
	 * @return bool
	 */

	public function show_popup( $settings ) {

		if ( ! wpf_is_user_logged_in() ) {
			return false;
		}

		if ( empty( $settings['selected'] ) ) {
			return true;
		}

		if ( wpf_admin_override() ) {
			return true;
		}

		$user_tags = wp_fusion()->user->get_tags();

		$result = array_intersect( (array) $settings['selected'], $user_tags );

		if ( ! empty( $result ) ) {
			return true;
		} else {
			return false;
		}

	}

}

new WPF_Popup_Maker();
