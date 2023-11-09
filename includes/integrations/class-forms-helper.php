<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WPF_Forms_Helper {

	/**
	 * Sends data to the CRM from form plugins.
	 *
	 * @since   3.24.0
	 * @return  Contact ID / WP_Error
	 */
	public static function process_form_data( $args ) {

		$defaults = array(
			'email_address'    => false,
			'update_data'      => array(),
			'apply_tags'       => array(),
			'auto_login'       => false,
			'integration_slug' => false,
			'integration_name' => false,
			'form_id'          => 0,
			'form_title'       => false,
			'form_edit_link'   => false,
			'entry_id'         => false,
		);

		$args = wp_parse_args( $args, $defaults );

		$args = apply_filters( 'wpf_forms_args', $args );

		// $email_address, $update_data, $apply_tags, $auto_login, $integration_slug, $integration_name, $form_id, $form_title, $form_edit_link

		extract( $args );

		// If no email and user not logged in don't bother.

		if ( empty( $email_address ) && ! wpf_is_user_logged_in() ) {

			wpf_log(
				'error',
				0,
				'Unable to process feed. No email address found.',
				array(
					'source'              => sanitize_title( $integration_name ),
					'meta_array_nofilter' => $update_data,
				)
			);

			return new WP_Error( 'error', 'Unable to process feed. No email address found.' );

		} elseif ( empty( $email_address ) && wpf_is_user_logged_in() ) {

			// If no email address, but user is logged in.

			$contact_id = wpf_get_contact_id();
			$user_id    = wpf_get_current_user_id();

			if ( empty( $contact_id ) ) {

				// If not found, check in the CRM and update locally.
				$contact_id = wpf_get_contact_id( $user_id, true );
			}
		} else {

			// Email is set.

			if ( is_object( get_user_by( 'email', $email_address ) ) ) {

				// Check and see if a local user exists with that email

				$user       = get_user_by( 'email', $email_address );
				$contact_id = wp_fusion()->user->get_contact_id( $user->ID );

				if ( empty( $contact_id ) ) {

					// If not found, check in the CRM and update locally
					$contact_id = wp_fusion()->user->get_contact_id( $user->ID, true );

				}

				$user_id = $user->ID;

			} elseif ( doing_wpf_auto_login() ) {

				// Auto login situations
				$user_id    = wpf_get_current_user_id();
				$contact_id = wp_fusion()->user->get_contact_id( $user_id );

			}
		}

		if ( empty( $user_id ) && wpf_is_user_logged_in() ) {

			$user_id = wpf_get_current_user_id();

		} elseif ( empty( $user_id ) && ! wpf_is_user_logged_in() ) {

			$user_id = false;

		}

		// Try and look up CID.
		if ( empty( $contact_id ) ) {

			if ( wpf_get_option( 'leads' ) ) {
				$contact_id = wp_fusion()->crm->get_lead_id( $email_address );
			} else {
				$contact_id = wp_fusion()->crm->get_contact_id( $email_address );
			}

			$current_user_contact_id = wp_fusion()->user->get_contact_id();

			// Update contact ID if not set locally.
			if ( wpf_is_user_logged_in() && ! empty( $contact_id ) && ! is_object( $contact_id ) && empty( $current_user_contact_id ) ) {
				update_user_meta( $user_id, WPF_CONTACT_ID_META_KEY, $contact_id );
			}
		}

		// Log string for Contacts vs Leads.
		if ( wpf_get_option( 'leads' ) ) {
			$object_type = 'lead';
		} else {
			$object_type = 'contact';
		}

		if ( is_wp_error( $contact_id ) ) {
			wpf_log( $contact_id->get_error_code(), $user_id, 'Error getting ' . $object_type . ' ID: ' . $contact_id->get_error_message(), array( 'source' => sanitize_title( $integration_name ) ) );
			return $contact_id;
		}

		/**
		 * Filter the contact ID.
		 *
		 * @since 3.24.0
		 *
		 * @link  https://wpfusion.com/documentation/filters/wpf_forms_pre_submission_contact_id/
		 *
		 * @param string|false $contact_id  The contact ID.
		 * @param array        $update_data The data being synced to the CRM.
		 * @param int|false    $user_id     The user ID (or false if guest).
		 * @param int          $form_id     The ID of the submitted form.
		 */

		$contact_id = apply_filters( 'wpf_forms_pre_submission_contact_id', $contact_id, $update_data, $user_id, $form_id );
		$contact_id = apply_filters( 'wpf_' . $integration_slug . '_pre_submission_contact_id', $contact_id, $update_data, $user_id, $form_id );

		/**
		 * Filter the update data.
		 *
		 * @since 3.24.0
		 *
		 * @link  https://wpfusion.com/documentation/filters/wpf_forms_pre_submission/
		 *
		 * @param array        $update_data The data being synced to the CRM.
		 * @param int|false    $user_id     The user ID (or false if guest).
		 * @param string|false $contact_id  The contact ID.
		 * @param int          $form_id     The ID of the submitted form.
		 */

		$update_data = apply_filters( 'wpf_forms_pre_submission', $update_data, $user_id, $contact_id, $form_id );
		$update_data = apply_filters( 'wpf_' . $integration_slug . '_pre_submission', $update_data, $user_id, $contact_id, $form_id );

		if ( null === $update_data ) {
			wpf_log( 'info', $user_id, $integration_name . ' <a href="' . $form_edit_link . '">' . $form_title . '</a> will be ignored (<code>null</code> returned from <code>wpf_forms_pre_submission</code>).', array( 'source' => sanitize_title( $integration_name ) ) );
			return;
		}

		// Dynamic tagging.

		if ( ! is_array( $apply_tags ) ) {
			$apply_tags = array();
		}

		if ( in_array( 'add_tags', wp_fusion()->crm->supports ) ) {

			foreach ( $update_data as $key => $value ) {

				if ( false !== strpos( $key, 'add_tag_' ) ) {

					if ( is_array( $value ) ) {

						$apply_tags = array_merge( $apply_tags, $value );

					} elseif ( ! empty( $value ) ) {

						$apply_tags[] = $value;

					}

					unset( $update_data[ $key ] );

				}
			}
		}

		// Logging.

		$log_text = $integration_name . ' <a href="' . $form_edit_link . '">' . $form_title . '</a> submission, at ';

		// Record the page the form is on.


		if ( isset( $_REQUEST['referrer'] ) ) {
			$log_text .= '<a href="' . $_REQUEST['referrer'] . '" target="_blank">' . $_REQUEST['referrer'] . '</a>. ';
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$log_text .= '<a href="' . $_SERVER['HTTP_REFERER'] . '" target="_blank">' . $_SERVER['HTTP_REFERER'] . '</a>. ';
		} elseif ( false === strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) ) {
			$log_text .= '<a href="' . $_SERVER['REQUEST_URI'] . '" target="_blank">' . $_SERVER['REQUEST_URI'] . '</a>. ';
		}

		// Are we creating a new contact or updating one?

		if ( ! empty( $contact_id ) ) {
			$log_text .= ' Updating existing ' . $object_type . ' #' . $contact_id . ': ';
		} else {
			$log_text .= ' Creating new ' . $object_type . ': ';
		}

		wpf_log(
			'info',
			$user_id,
			$log_text,
			array(
				'meta_array_nofilter' => $update_data,
				'source'              => sanitize_title( $integration_name ),
			)
		);

		if ( ! empty( $contact_id ) && isset( $add_only ) && $add_only == true ) {

			wpf_log( 'info', $user_id, ucwords( $object_type ) . ' already exists and <em>Add Only</em> is enabled. Aborting.', array( 'source' => sanitize_title( $integration_name ) ) );
			return;

		}

		if ( ! empty( $contact_id ) ) {

			// Update CRM if contact ID exists.

			add_filter( 'wpf_use_api_queue', function( $use_queue, $method, $args ) {

				// We need to bypass the API queue for cases where a user
				// registration during an auto-login session needs to update an
				// subscriber's email address (via the form integration) before
				// registering a new user.

				if ( 'update_contact' === $method ) {
					$use_queue = false;
				}

				return $use_queue;

			}, 10, 3 );

			if ( wpf_get_option( 'leads' ) ) {
				$result = wp_fusion()->crm->update_lead( $contact_id, $update_data );
			} else {
				$result = wp_fusion()->crm->update_contact( $contact_id, $update_data, $map_meta_fields = false );
			}

			if ( is_wp_error( $result ) ) {

				wpf_log( $result->get_error_code(), $user_id, 'Error updating ' . $object_type . ': ' . $result->get_error_message(), array( 'source' => sanitize_title( $integration_name ) ) );

				return new WP_Error( 'error', 'Error updating ' . $object_type . ': ' . $result->get_error_message() );

			}

			do_action( 'wpf_guest_contact_updated', $contact_id, $email_address );

		} else {

			// Add contact if doesn't exist yet.

			if ( wpf_get_option( 'leads' ) ) {
				$contact_id = wp_fusion()->crm->add_lead( $update_data );
			} else {
				$contact_id = wp_fusion()->crm->add_contact( $update_data, false );
			}

			if ( is_wp_error( $contact_id ) ) {

				wpf_log( $contact_id->get_error_code(), $user_id, 'Error adding ' . $object_type . ' to ' . wp_fusion()->crm->name . ': ' . $contact_id->get_error_message(), array( 'source' => sanitize_title( $integration_name ) ) );

				return new WP_Error( 'error', 'Error adding ' . $object_type . ' to ' . wp_fusion()->crm->name . ': ' . $contact_id->get_error_message() );

			} elseif ( empty( $contact_id ) ) {

				wpf_log( 'error', $user_id, 'Unknown error adding ' . $object_type . ' to ' . wp_fusion()->crm->name . ': no ' . $object_type . ' ID was returned.', array( 'source' => sanitize_title( $integration_name ) ) );

				return new WP_Error( 'error', 'Unknown error adding ' . $object_type . ' to ' . wp_fusion()->crm->name . ': no ' . $object_type . ' ID was returned.' );

			} else {

				wpf_log( 'info', $user_id, 'Successfully created ' . $object_type . ' #' . $contact_id . '.', array( 'source' => sanitize_title( $integration_name ) ) );

			}

			do_action( 'wpf_guest_contact_created', $contact_id, $email_address );

		}

		// If the user is logged in but doesn't have a contact ID, we'll set that here so that subsequent calls to apply_tags() work.

		if ( wpf_is_user_logged_in() && ! wpf_get_contact_id() ) {
			update_user_meta( $user_id, WPF_CONTACT_ID_META_KEY, $contact_id );
		}

		// Start auto login for guests (before tags are applied).

		if ( ( wpf_get_option( 'auto_login_forms' ) || true === $auto_login ) && ! wpf_is_user_logged_in() ) {

			wpf_log( 'info', 0, 'Starting auto-login session from form submission for ' . $object_type . ' #' . $contact_id . '.', array( 'source' => sanitize_title( $integration_name ) ) );
			$user_id = wp_fusion()->auto_login->start_auto_login( $contact_id );

		}

		/**
		 * Filter the tags.
		 *
		 * @since 3.24.0
		 *
		 * @link  https://wpfusion.com/documentation/filters/wpf_forms_apply_tags/
		 *
		 * @param array     $apply_tags The tags to apply in the CRM.
		 * @param int|false $user_id    The user ID (or false if guest).
		 * @param string    $contact_id The contact ID.
		 * @param int       $form_id    The ID of the submitted form.
		 */

		$apply_tags = apply_filters( 'wpf_forms_apply_tags', $apply_tags, $user_id, $contact_id, $form_id );
		$apply_tags = apply_filters( 'wpf_' . $integration_slug . '_apply_tags', $apply_tags, $user_id, $contact_id, $form_id );
		$apply_tags = apply_filters( 'wpf_' . $integration_slug . '_apply_tags_' . $form_id, $apply_tags, $user_id, $contact_id, $form_id );

		// This fixes mixed up array pointers due to unsetting, merging, etc.
		$apply_tags = array_values( array_filter( $apply_tags ) );

		// Apply tags if set.
		if ( ! empty( $apply_tags ) ) {

			// Even if the user is logged in, they may have submitted the form with a different email. This makes sure the tags are applied to the right record.
			if ( ! empty( $user_id ) && ! doing_wpf_auto_login() ) {

				$user_info = get_userdata( $user_id );

			} elseif ( doing_wpf_auto_login() ) {

				// If we're doing an auto login get the userdata from wp_usermeta (instead of wp_users).

				$user_id    = wpf_get_current_user_id();
				$user_email = get_user_meta( $user_id, 'user_email', true );
				$user_info  = (object) array( 'user_email' => $user_email );

			} else {

				$user_info = false;

			}

			if ( is_object( $user_info ) && ( $user_info->user_email === $email_address || empty( $email_address ) ) ) {

				// If user exists locally and the email address matches, apply the tags locally as well.
				wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			} else {

				// Logger.
				wpf_log(
					'info',
					0,
					$integration_name . ' applying tags: ',
					array(
						'tag_array' => $apply_tags,
						'source'    => sanitize_title( $integration_name ),
					)
				);

				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}

			// In cases where the form submission might also be registering a
			// new user, the tags applied via this form submission might not be
			// available in the CRM right away for loading. This will pass the
			// tags applied by the form submission over to the new user so they
			// can immediately access their content.

			add_filter(
				'wpf_loaded_tags',
				function( $user_tags, $user_id, $user_contact_id ) use ( &$contact_id, &$apply_tags ) {

					if ( $user_contact_id === $contact_id && empty( array_intersect( (array) $user_tags, $apply_tags ) ) ) {
						$user_tags = array_merge( $user_tags, $apply_tags );
					}

					return $user_tags;

				},
				10,
				3
			);

		}

		/**
		 * Triggers after the form was successfully processed by WP Fusion.
		 *
		 * @link https://wpfusion.com/documentation/actions/wpf_forms_post_submission
		 *
		 * @param array           $update_data The data that was synced to the CRM.
		 * @param int|false       $user_id     The user ID, or false.
		 * @param string|WP_Error $contact_id  The contact ID in the CRM, or WP_Error.
		 * @param int             $form_id     The form ID.
		 * @param int|false       $entry_id    The entry ID, or false if unknown.
		 */
		do_action( 'wpf_forms_post_submission', $update_data, $user_id, $contact_id, $form_id, $entry_id );
		do_action( 'wpf_' . $integration_slug . '_post_submission', $update_data, $user_id, $contact_id, $form_id, $entry_id );
		do_action( 'wpf_' . $integration_slug . '_post_submission_' . $form_id, $update_data, $user_id, $contact_id, $form_id, $entry_id );

		return $contact_id;

	}

}
