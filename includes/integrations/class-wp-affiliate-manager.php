<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_WP_Affiliate_Manager extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wp-affiliate-manager';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WP Affiliate Manager';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/affiliates/wp-affiliate-manager/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'init', array( $this, 'maybe_add_affiliate' ) );
		add_filter( 'wpf_user_register', array( $this, 'filter_form_fields' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'filter_form_fields' ), 10, 2 );
		add_action( 'wpf_user_created', array( $this, 'after_add_affiliate' ), 10, 3 );

		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

	}


	/**
	 * Adds an affiliate when the registration form is submitted
	 *
	 * @access public
	 * @return void
	 */

	public function maybe_add_affiliate() {

		if ( get_option( WPAM_PluginConfig::$AutoAffiliateApproveIsEnabledOption ) == false && isset( $_REQUEST['wpam_reg_submit'] ) ) {

			$field_map = array(
				'_firstName'   => 'first_name',
				'_lastName'    => 'last_name',
				'_phoneNumber' => 'billing_phone',
				'_email'       => 'user_email',
			);

			$post_data = $this->map_meta_fields( $_POST, $field_map );

			$contact_id = wp_fusion()->crm->get_contact_id( $post_data['user_email'] );

			wpf_log( 'info', 0, 'Adding pending affiliate to ' . wp_fusion()->crm->name . ':', array( 'meta_array' => $post_data ) );

			if ( $contact_id == false ) {

				$contact_id = wp_fusion()->crm->add_contact( $post_data );

			} else {

				wp_fusion()->crm->update_contact( $contact_id, $post_data );

			}

			$apply_tags = wpf_get_option( 'wpam_tags_pending' );

			if ( ! empty( $apply_tags ) ) {
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

		$field_map = array(
			'_firstName'   => 'first_name',
			'_lastName'    => 'last_name',
			'_phoneNumber' => 'billing_phone',
			'_email'       => 'user_email',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		return $post_data;

	}


	/**
	 * Applies Approved tags to affiliate after approval
	 *
	 * @access public
	 * @return void
	 */

	public function after_add_affiliate( $user_id, $contact_id, $post_data ) {

		if (
			( isset( $_REQUEST['handler'] ) && 'approveApplication' === $_REQUEST['handler'] ) // approval.
			|| isset( $_REQUEST['wpam_add_affiliate'] ) // new aff added and auto approved via admin.
			|| isset( $_REQUEST['wpam_reg_submit'] ) // new aff registered and auto approved via frontend.
		) {

			$apply_tags = wpf_get_option( 'wpam_tags_accepted' );

			if ( ! empty( $apply_tags ) ) {
				wp_fusion()->user->apply_tags( $apply_tags, $user_id );
			}
		}

	}

	/**
	 * Registers additional Woocommerce settings
	 *
	 * @access  public
	 * @return  array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['wpam_header'] = array(
			'title'   => __( 'WP Affiliate Manager Integration', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['wpam_tags_pending'] = array(
			'title'   => __( 'Apply Tags - Pending', 'wp-fusion' ),
			'desc'    => __( 'These tags will be applied when affiliates apply.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['wpam_tags_accepted'] = array(
			'title'   => __( 'Apply Tags - Accepted', 'wp-fusion' ),
			'desc'    => __( 'These tags will be applied when affiliates are accepted.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		return $settings;

	}


}

new WPF_WP_Affiliate_Manager();
