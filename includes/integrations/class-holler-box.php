<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Holler_Box extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.20
	 * @var string $slug
	 */

	public $slug = 'holler-box';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.20
	 * @var string $name
	 */
	public $name = 'Holler Box';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.20
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started.
	 *
	 * @since 3.40.20
	 */

	public function init() {

		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 8, 2 );

		add_action( 'hollerbox/admin/scripts', array( $this, 'add_controls_script' ) );

		add_filter( 'hollerbox/popup/can_show', array( $this, 'control_access' ), 10, 2 );

		add_action( 'hollerbox/submitted', array( $this, 'form_submission' ), 10, 2 );

		add_action( 'hollerbox/init_display_conditions', array( $this, 'add_defaults' ) );

	}

	/**
	 * Add default values to controls.
	 *
	 * @since 3.40.20
	 */
	public function add_defaults() {
		$popup = new Holler_Popup();
		$popup::add_display_condition( 'wpf_visibility_button', '__return_true' );
		$popup::add_display_condition( 'wpf_visibility', '__return_true' );
		$popup::add_display_condition( 'wpf_show_any', '__return_true' );
		$popup::add_display_condition( 'wpf_hide_any', '__return_true' );
	}

	/**
	 * Control access to the popup.
	 * 
	 * @since 3.40.20
	 *
	 * @param bool         $can_access If the user can access the popup.
	 * @param Holler_Popup $popup      The popup.
	 * @return bool Can access.
	 */
	public function control_access( $can_access, $popup ) {
		return $this->can_access( $popup );
	}

	/**
	 * Add Holler box controls script.
	 *
	 * @since 3.40.20
	 */
	public function add_controls_script() {

		wp_enqueue_script( 'wpf-holler-box', WPF_DIR_URL . 'assets/js/wpf-holler-box.js', array(), WP_FUSION_VERSION, true );

		$data = wp_fusion()->settings->get_available_tags_flat();

		wp_localize_script(
			'wpf-holler-box',
			'wpf_holler_object',
			array(
				'tags'               => $data,
				'wpf_visibility'     => array(
					'label'   => __( 'Show to Logged In or Logged Out users', 'wp-fusion' ),
					'options' => array(
						'everyone'  => __( 'Everyone', 'wp-fusion' ),
						'loggedin'  => __( 'Logged In Users', 'wp-fusion' ),
						'loggedout' => __( 'Logged Out Users', 'wp-fusion' ),
					),
				),
				'select_placeholder' => __( 'Select tags', 'wp-fusion' ),
				'wpf_show_any'       => sprintf( __( 'Show if user has any %s tags', 'wp-fusion' ), wp_fusion()->crm->name ),
				'wpf_hide_any'       => sprintf( __( 'Hide if user has any %s tags', 'wp-fusion' ), wp_fusion()->crm->name ),
			)
		);
	}


	/**
	 * Determines if a user has access to a popup.
	 * 
	 * @since 3.40.20
	 *
	 * @param  Holler_Popup $popup The popup.
	 * @return bool Whether or not the user can access the popup.
	 */
	private function can_access( $popup ) {

		if ( is_admin() ) {
			return true;
		}

		$visibility      = ( isset( $popup->advanced_rules['wpf_visibility_button']['wpf_visibility'] ) ? $popup->advanced_rules['wpf_visibility_button']['wpf_visibility'] : '' );
		$widget_tags     = ( isset( $popup->advanced_rules['wpf_show_any']['wpf_show_any'] ) && $popup->advanced_rules['wpf_show_any']['enabled'] === true ? $popup->advanced_rules['wpf_show_any']['wpf_show_any'] : '' );
		$widget_tags_not = ( isset( $popup->advanced_rules['wpf_hide_any']['wpf_hide_any'] ) && $popup->advanced_rules['wpf_hide_any']['enabled'] === true ? $popup->advanced_rules['wpf_hide_any']['wpf_hide_any'] : '' );
		$widget_tags_all = '';

		if ( 'everyone' === $visibility && empty( $widget_tags ) && empty( $widget_tags_all ) && empty( $widget_tags_not ) ) {

			// No settings, allow access.

			$can_access = apply_filters( 'wpf_holler_box_can_access', true, $popup );

			return apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), false );

		}

		if ( wpf_admin_override() ) {
			return true;
		}

		$can_access = true;

		if ( wpf_is_user_logged_in() ) {

			$user_tags = wp_fusion()->user->get_tags();

			if ( 'everyone' === $visibility || 'loggedin' === $visibility ) {

				// See if user has required tags.

				if ( ! empty( $widget_tags ) ) {

					// Required tags (any).

					$result = array_intersect( $widget_tags, $user_tags );

					if ( empty( $result ) ) {
						$can_access = false;
					}
				}

				if ( true === $can_access && ! empty( $widget_tags_all ) ) {

					// Required tags (all).

					$result = array_intersect( $widget_tags_all, $user_tags );

					if ( count( $result ) !== count( $widget_tags_all ) ) {
						$can_access = false;
					}
				}

				if ( true === $can_access && ! empty( $widget_tags_not ) ) {

					// Required tags (not).

					$result = array_intersect( $widget_tags_not, $user_tags );

					if ( ! empty( $result ) ) {
						$can_access = false;
					}
				}
			} elseif ( 'loggedout' === $visibility ) {

				// The user is logged in but the widget is set to logged-out only.
				$can_access = false;

			}
		} else {

			// Not logged in.

			if ( 'loggedin' === $visibility ) {

				// The user is not logged in but the widget is set to logged-in only.
				$can_access = false;

			} elseif ( 'everyone' === $visibility ) {

				// Also deny access if tags are specified.

				if ( ! empty( $widget_tags ) || ! empty( $widget_tags_all ) ) {
					$can_access = false;
				}
			}
		}

		$can_access = apply_filters( 'wpf_holler_box_can_access', $can_access, $popup );

		$can_access = apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), false );

		if ( $can_access ) {
			return true;
		} else {
			return false;
		}

	}


	/**
	 * Register the settings.
	 *
	 * @since 3.40.20
	 *
	 * @param array $settings The configurable settings.
	 * @param array $options  The plugin options in the database.
	 * @return array $settings The updated settings.
	 */
	public function register_settings( $settings, $options ) {

		$settings['hb_header'] = array(
			'title'   => __( 'Holler Box Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['hb_add_contacts'] = array(
			'title'   => __( 'Add Contacts', 'wp-fusion' ),
			'desc'    => sprintf( __( 'Add contacts to %s when a Holler Box form is submitted.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'std'     => 1,
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;

	}


	/**
	 * Sync Holler box form submissions to the CRM.
	 * 
	 * @since 3.40.20
	 *
	 * @param Holler_Popup $popup The popup.
	 * @param object       $lead  The lead.
	 */
	public function form_submission( $popup, $lead ) {

		if ( ! wpf_get_option( 'hb_add_contacts' ) ) {
			return;
		}

		$contact_data = array(
			'first_name' => $lead->first_name,
			'last_name'  => $lead->last_name,
			'user_email' => $lead->email,
		);

		// Send the meta data
		if ( wpf_is_user_logged_in() ) {

			wp_fusion()->user->push_user_meta( wpf_get_current_user_id(), $contact_data );

		} else {

			$contact_id = $this->guest_registration( $contact_data['user_email'], $contact_data );

		}

	}
}

new WPF_Holler_Box();
