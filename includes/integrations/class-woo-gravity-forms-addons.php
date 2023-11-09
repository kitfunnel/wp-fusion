<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce Gravity Forms Addons Integration Class.
 *
 * @class   WPF_Woo_Gravity_Forms_Addons
 * @since   3.40.57
 */
class WPF_Woo_Gravity_Forms_Addons extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.57
	 * @var string $slug
	 */

	public $slug = 'woo-gravity-forms-addons';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.57
	 * @var string $name
	 */
	public $name = 'WooCommerce Gravity Forms Product Add-ons';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.57
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/woocommerce-gravity-forms-product-addons/';

	/**
	 * Gets things started.
	 */
	public function init() {

		add_filter( 'wpf_woocommerce_customer_data', array( $this, 'sync_custom_fields' ) );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 18 ); // 18 so it's after 15 in WPF_WooCommerce.

	}

	/**
	 * Merge addon data with the order data.
	 *
	 * @since 3.40.57
	 *
	 * @param array $customer_data The customer data.
	 * @return array The customer data.
	 */
	public function sync_custom_fields( $customer_data ) {

		if ( ! empty( $customer_data['_gravity_forms_history'] ) ) {

			$data    = $customer_data['_gravity_forms_history'];
			$form_id = $data['_gravity_form_lead']['form_id'];

			foreach ( $data['_gravity_form_lead'] as $key => $value ) {

				if ( is_numeric( $key ) ) {

					$customer_data[ 'addon_' . $form_id . '_' . $key ] = $value;
				}
			}
		}

		return $customer_data;

	}

	/**
	 * Adds field group to meta fields list.
	 *
	 * @since 3.40.57
	 *
	 * @param array $field_groups The field groups.
	 * @return array The field groups.
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['woo_gf_addons'] = array(
			'title'  => 'WooCommerce Gravity Forms Product Add-ons',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Adds booking dates field to contact fields list
	 *
	 * @since 3.40.57
	 *
	 * @param array $meta_fields The meta fields.
	 * @return array Meta fields.
	 */
	public function prepare_meta_fields( $meta_fields ) {

		foreach ( wc_gfpa()->gravity_products as $product_id ) {

			$data = get_post_meta( $product_id, '_gravity_form_data', true );
			$form = GFAPI::get_form( $data['id'] );

			foreach ( $form['fields'] as $field ) {

				$meta_fields[ 'addon_' . $field->formId . '_' . $field->id ] = array(
					'label' => $field->label,
					'type'  => $field->type,
					'group' => 'woo_gf_addons',
				);
			}
		}

		return $meta_fields;

	}

}

new WPF_Woo_Gravity_Forms_Addons();
