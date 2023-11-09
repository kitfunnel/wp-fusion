<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce Product Options by Barn2 integration.
 *
 * @since 3.41.0
 */
class WPF_Woo_Product_Options extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.41.0
	 * @var string $slug
	 */

	public $slug = 'woo-product-options';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.41.0
	 * @var string $name
	 */
	public $name = 'WooCommerce Product Options';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.41.0
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/woocommerce-product-options/';


	/**
	 * Gets things started.
	 *
	 * @since 3.41.0
	 */
	public function init() {

		add_filter( 'wpf_woocommerce_customer_data', array( $this, 'customer_data' ), 10, 2 );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

	}

	/**
	 * Merge the selected custom field values into the data synced to the CRM.
	 *
	 * @since 3.41.0
	 *
	 * @param array    $customer_data The customer data.
	 * @param WC_Order $order         The order.
	 * @return array The customer data.
	 */
	public function customer_data( $customer_data, $order ) {

		foreach ( $order->get_items() as $item ) {

			$data = $item->get_meta( '_wpo_options' );

			if ( ! empty( $data ) ) {

				foreach ( $data as $option ) {

					$value = false;

					if ( 1 < count( $option['choice_data'] ) ) {
						$value = wp_list_pluck( $option['choice_data'], 'label' ); // checkboxes.
					} else {
						$value = $option['value'];
					}

					$customer_data[ 'wpo_' . $option['group_id'] . '_' . $option['option_id'] ] = $value;

				}
			}

		}

		return $customer_data;

	}

	/**
	 * Adds the WooCommerce Product Options meta field group to the list of available fields.
	 *
	 * @since 3.41.0
	 *
	 * @param array $field_groups The fielg groups.
	 * @return array The fielg groups.
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['woocommerce_product_options'] = array(
			'title'  => 'WooCommerce Product Options',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Prepares the WooCommerce Product Options meta fields for output.
	 *
	 * @since 3.41.0
	 *
	 * @param array $meta_fields The meta fields.
	 * @return array The meta fields.
	 */
	public function prepare_meta_fields( $meta_fields ) {

		$options = Barn2\Plugin\WC_Product_Options\Model\Option::orderBy( 'menu_order', 'asc' )->get();

		foreach ( $options as $option ) {

			if ( 'checkbox' === $option->type ) {
				$option->type = 'multiselect';
			} elseif ( 'datepicker' === $option->type ) {
				$option->type = 'date';
			}

			$meta_fields[ 'wpo_' . $option->group_id . '_' . $option->id ] = array(
				'label'  => $option->name,
				'type'   => $option->type,
				'group'  => 'woocommerce_product_options',
				'pseudo' => true,
			);

		}

		return $meta_fields;

	}

}

new WPF_Woo_Product_Options();
