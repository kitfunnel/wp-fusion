<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_GravityView extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'gravityview';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'GravityView';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/lead-generation/gravityview/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_gform_settings_fields', array( $this, 'settings_fields' ) );
		add_action( 'gravityview/approve_entries/approved', array( $this, 'approved' ) );
		add_action( 'admin_init', array( $this, 'check_for_approved_entries' ) );

	}


	/**
	 * Adds GravityView settings to WPF feed settings
	 *
	 * @access public
	 * @return array Fields
	 */

	public function settings_fields( $fields ) {

		$new_field = array(
			'name'    => 'wpf_tags_gravityview_approved',
			'label'   => __( 'Apply Tags - Approved', 'wp-fusion' ),
			'type'    => 'wpf_tags',
			'tooltip' => __( 'Select tags to be applied to the contact when this entry is marked Approved in GravityView.', 'wp-fusion' ),
		);

		array_splice( $fields, 3, 0, array( $new_field ) );

		return $fields;

	}

	/**
	 * Applies tags when an entry is marked approved
	 *
	 * @access public
	 * @return void
	 */

	public function approved( $entry_id ) {

		$entry = GFAPI::get_entry( $entry_id );
		$form  = GFAPI::get_form( $entry['form_id'] );
		$feeds = GFAPI::get_feeds( null, $entry['form_id'], 'wpfgforms' );

		if ( ! empty( $feeds ) ) {

			foreach ( $feeds as $feed ) {

				if ( ! empty( $feed['meta']['wpf_tags_gravityview_approved'] ) ) {

					$user_id = rgar( $entry, 'created_by' );

					if ( ! empty( $user_id ) ) {

						wp_fusion()->user->apply_tags( $feed['meta']['wpf_tags_gravityview_approved'], $user_id );

					} else {

						$contact_id = gform_get_meta( $entry['id'], 'wpf_contact_id' );

						if ( ! empty( $contact_id ) ) {

							wpf_log( 'info', 0, 'Entry #' . $entry_id . ' marked approved in GravityView. Applying tags to contact ID #' . $contact_id, array( 'tag_array' => $feed['meta']['wpf_tags_gravityview_approved'] ) );

							wp_fusion()->crm->apply_tags( $feed['meta']['wpf_tags_gravityview_approved'], $contact_id );

						}
					}
				}
			}
		}

	}

	/**
	 * Checks to see if entries have been approved via bulk edit
	 *
	 * @access public
	 * @return void
	 */

	public function check_for_approved_entries() {

		// Have to check the POST vars because gravityview/approve_entries/approved runs too late :()

		if ( isset( $_POST['action'] ) && false !== strpos( $_POST['action'], 'gvapprove' ) && ! empty( $_POST['entry'] ) ) {

			foreach ( $_POST['entry'] as $entry_id ) {

				$approved = gform_get_meta( $entry_id, 'is_approved' );

				if ( ! $approved ) {
					continue;
				}

				$this->approved( $entry_id );

			}
		}

	}


}

new WPF_GravityView();
