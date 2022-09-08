<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_WooCommerce_Wholesale_Lead_Capture extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'woo-wholesale-lead-capture';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WooCommerce wholesale lead capture';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_user_register', array( $this, 'filter_form_fields' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'filter_form_fields' ), 10, 2 );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

	}


	/**
	 * Filters registration data before sending to the CRM
	 *
	 * @access public
	 * @return array Registration / Update Data
	 */

	public function filter_form_fields( $post_data, $user_id ) {

		if ( isset( $post_data['user_data'] ) && is_array( $post_data['user_data'] ) ) {

			foreach ( $post_data['user_data'] as $key => $value ) {
				$post_data[ $key ] = $value;
			}
		}

		return $post_data;

	}

	/**
	 * Adds field group to contact fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['woo_wholesale_lead'] ) ) {
			$field_groups['woo_wholesale_lead'] = array(
				'title'  => 'WooCommerce Wholesale Lead Capture',
				'fields' => array(),
			);
		}

		return $field_groups;

	}


	/**
	 * Sets field labels and types
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['wwlc_phone']        = array(
			'label' => 'Phone Number',
			'type'  => 'text',
			'group' => 'woo_wholesale_lead',
		);
		$meta_fields['wwlc_company_name'] = array(
			'label' => 'Company Name',
			'type'  => 'text',
			'group' => 'woo_wholesale_lead',
		);

		$meta_fields['wwlc_address']   = array(
			'label' => 'Address',
			'type'  => 'text',
			'group' => 'woo_wholesale_lead',
		);
		$meta_fields['wwlc_address_2'] = array(
			'label' => 'Address 2',
			'type'  => 'text',
			'group' => 'woo_wholesale_lead',
		);
		$meta_fields['wwlc_city']      = array(
			'label' => 'City',
			'type'  => 'text',
			'group' => 'woo_wholesale_lead',
		);
		$meta_fields['wwlc_state']     = array(
			'label' => 'State',
			'type'  => 'text',
			'group' => 'woo_wholesale_lead',
		);
		$meta_fields['wwlc_postcode']  = array(
			'label' => 'Postcode',
			'type'  => 'text',
			'group' => 'woo_wholesale_lead',
		);
		$meta_fields['wwlc_country']   = array(
			'label' => 'Country',
			'type'  => 'text',
			'group' => 'woo_wholesale_lead',
		);

		$fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS );

		if ( ! empty( $fields ) && ! is_array( $fields ) ) {
			$fields = unserialize( base64_decode( $fields ) );
		}

		if ( ! empty( $fields ) ) {

			foreach ( $fields as $key => $field ) {

				$meta_fields[ $key ] = array(
					'label' => $field['field_name'],
					'type'  => $field['field_type'],
					'group' => 'woo_wholesale_lead',
				);

			}
		}

		return $meta_fields;

	}


}

new WPF_WooCommerce_Wholesale_Lead_Capture();
