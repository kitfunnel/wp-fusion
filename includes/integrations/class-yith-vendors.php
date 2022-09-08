<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * YITH WooCommerce Multi Vendor integration
 *
 * @since 3.35.20
 */

class WPF_YITH_Vendors extends WPF_Integrations_Base {

	/**
	 * This identifies the integration internally and makes it available at
	 * wp_fusion()->integrations->{'yith-vendors'}
	 *
	 * @var  string
	 * @since 3.35.20
	 */

	public $slug = 'yith-vendors';

	/**
	 * The human-readable name of the integration.
	 *
	 * @var  string
	 * @since 3.35.20
	 */

	public $name = 'YITH WooCommerce Multi Vendor';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/yith-woocommerce-multi-vendor/';


	/**
	 * Get things started.
	 *
	 * @since 3.35.20
	 */

	public function init() {

		// Registration / profile update
		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'user_register' ), 10, 2 );
		add_filter( 'wpf_get_user_meta', array( $this, 'get_user_meta' ), 10, 2 );

		// Defer until activation
		add_action( 'woocommerce_register_post', array( $this, 'pre_user_register' ) );
		add_action( 'yith_vendors_account_approved', array( $this, 'account_approved' ) );

		// Meta fields
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		// Global settings
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

	}


	/**
	 * Filter the meta fields during registration or profile update.
	 *
	 * @since  3.35.20
	 *
	 * @param  array $post_data  The post data.
	 * @param  int   $user_id    The user ID.
	 * @return array  The registration / profile data.
	 */

	public function user_register( $post_data, $user_id ) {

		$field_map = array(
			'username'               => 'user_login',
			'password'               => 'user_pass',
			'email'                  => 'user_email',
			'vendor-owner-firstname' => 'first_name',
			'vendor-owner-lastname'  => 'last_name',
			'vendor-telephone'       => 'phone_number',
			'vendor-city'            => 'vendor-company-city',
			'vendor-state'           => 'vendor-company-province',
			'vendor-country'         => 'vendor-company-country',
			'vendor-zip'             => 'vendor-company-postal',
			'vendor-website'         => 'retailer_website',
			'vendor-location'        => 'location',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		return $post_data;

	}


	/**
	 * Gets the vendor information from the termmeta table.
	 *
	 * @since  3.35.20
	 *
	 * @param  array $post_data  The user meta.
	 * @param  int   $user_id    The user ID.
	 * @return array  The user meta.
	 */

	public function get_user_meta( $user_meta, $user_id ) {

		$vendor = get_user_meta( $user_id, 'yith_product_vendor', true );

		if ( $vendor ) {

			$vendor_meta = get_term_meta( $vendor );

			foreach ( $vendor_meta as $key => $value ) {

				// Kind of messy solution to make the DB keys match the registration form keys

				$key = str_replace( '_', '-', $key );

				$user_meta[ 'vendor-' . $key ] = $value[0];

			}
		}

		return $user_meta;

	}


	/**
	 * Disable the new user sync if Defer Until Activation is enabled.
	 *
	 * @since  3.35.20
	 *
	 * @param  string $username  The username.
	 * @return array   The registration / profile data.
	 */

	public function pre_user_register( $username ) {

		if ( isset( $_POST['vendor-antispam'] ) && true == wpf_get_option( 'yith_vendors_defer' ) ) {
			remove_action( 'user_register', array( wp_fusion()->user, 'user_register' ), 20 );
		}

	}


	/**
	 * If Defer Until Activation is enabled, process the user registration once
	 * the account is approved.
	 *
	 * @since 3.35.20
	 *
	 * @param int $owner_id The ID of the approved vendor.
	 */

	public function account_approved( $owner_id ) {

		if ( true == wpf_get_option( 'yith_vendors_defer' ) ) {
			wp_fusion()->user->user_register( $owner_id );
		}

		$apply_tags = wpf_get_option( 'yith_vendors_approved_tags' );

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $owner_id );
		}

	}


	/**
	 * Registers the meta field group.
	 *
	 * @since  3.35.20
	 *
	 * @param  array $field_groups  The field groups.
	 * @return array  The field groups.
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups[ $this->slug ] = array(
			'title'  => 'YITH Vendors',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Register the YITH meta fields.
	 *
	 * @since  3.35.20
	 *
	 * @param  array $meta_fields  The meta fields.
	 * @return array  The meta fields.
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['vendor-owner-firstname'] = array(
			'label'  => 'Owner First Name',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-owner-lastname'] = array(
			'label'  => 'Owner Last Name',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-name'] = array(
			'label'  => 'Store Name',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-location'] = array(
			'label'  => 'Address',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-email'] = array(
			'label'  => 'Store Email',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-paypal-email'] = array(
			'label'  => 'PayPal Email',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-telephone'] = array(
			'label'  => 'Telephone',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-vat'] = array(
			'label'  => 'VAT/SSN',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-company-city'] = array(
			'label'  => 'Retailer City',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-company-province'] = array(
			'label'  => 'Retailer State',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-company-country'] = array(
			'label'  => 'Retailer Country',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-company-postal'] = array(
			'label'  => 'Retailer Zip',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-company-website'] = array(
			'label'  => 'Retailer Website',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-location'] = array(
			'label'  => 'Retailer Location',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['vendor-store-email'] = array(
			'label'  => 'Vendor Store Email',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		return $meta_fields;

	}

	/**
	 * Adds fields to settings page.
	 *
	 * @since  3.35.20
	 *
	 * @param  array $settings  The registered settings.
	 * @param  array $options   The options in the database.
	 * @return array  The registered settings.
	 */

	public function register_settings( $settings, $options ) {

		$settings['yith_vendors_header'] = array(
			'title'   => __( 'YITH Vendors Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['yith_vendors_defer'] = array(
			'title'   => __( 'Defer Until Activation', 'wp-fusion' ),
			'desc'    => sprintf( __( 'Don\'t send any data to %s until the vendor account has been approved by an administrator.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		$settings['yith_vendors_approved_tags'] = array(
			'title'   => __( 'Apply Tags', 'wp-fusion' ),
			'desc'    => sprintf( __( 'Apply these tags in %s when a vendor is approved.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		return $settings;

	}

}

new WPF_YITH_Vendors();
