<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_WP_Crowdfunding extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wp-crowdfunding';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Wp crowdfunding';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/wp-crowdfunding/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.33.10
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_woocommerce_customer_data', array( $this, 'merge_customer_data' ), 10, 2 );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

	}


	/**
	 * Merge order meta into customer data
	 *
	 * @access  public
	 * @return  array Customer Data
	 */

	public function merge_customer_data( $customer_data, $order ) {

		$order_data = $order->get_data();

		foreach ( $order_data['meta_data'] as $meta ) {

			if ( is_a( $meta, 'WC_Meta_Data' ) ) {

				$data = $meta->get_data();

				if ( 'wpneo_selected_reward' == $data['key'] ) {

					$reward_data = json_decode( $data['value'], true );

					if ( is_array( $reward_data ) ) {
						$customer_data = array_merge( $customer_data, $reward_data );
					}
				}
			}
		}

		return $customer_data;

	}


	/**
	 * Adds Crowdfunding field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['wp-crowdfunding'] = array(
			'title'  => 'WP Crowdfunding',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Sets field labels and types for Crowdfunding custom fields
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['wpneo_rewards_pladge_amount'] = array(
			'label' => 'Pledge Amount',
			'type'  => 'int',
			'group' => 'wp-crowdfunding',
		);

		$meta_fields['wpneo_rewards_description'] = array(
			'label' => 'Reward Description',
			'type'  => 'text',
			'group' => 'wp-crowdfunding',
		);

		$meta_fields['wpneo_rewards_endmonth'] = array(
			'label' => 'Reward End Month',
			'type'  => 'text',
			'group' => 'wp-crowdfunding',
		);

		$meta_fields['wpneo_rewards_endyear'] = array(
			'label' => 'Reward End Year',
			'type'  => 'text',
			'group' => 'wp-crowdfunding',
		);

		return $meta_fields;

	}


}

new WPF_WP_Crowdfunding();
