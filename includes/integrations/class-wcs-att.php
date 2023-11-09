<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_WCS_ATT extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wcs-att';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'All Products for WooCommerce Subscriptions';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/woocommerce-all-products-for-subscriptions/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.30.2
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_woocommerce_apply_tags_checkout', array( $this, 'apply_tags_checkout' ), 10, 2 );
		add_action( 'wpf_woocommerce_product_subscription_active', array( $this, 'subscription_active' ), 10, 2 );

		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ), 5 );

	}


	/**
	 * Merge in custom tags during checkout
	 *
	 * @access public
	 * @return array Apply Tags
	 */

	public function apply_tags_checkout( $apply_tags, $order ) {

		foreach ( $order->get_items() as $item ) {

			if ( $item->meta_exists( '_wcsatt_scheme' ) ) {

				$product_id = $item->get_product_id();

				$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

				if ( ! empty( $settings ) && ! empty( $settings['apply_tags_subscribed'] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_subscribed'] );
				}
			}
		}

		return $apply_tags;

	}

	/**
	 * Applies WCS ATT tags when a subscription is changed to active (including at checkout), or as part of the batch process
	 *
	 * @access public
	 * @return void
	 */

	public function subscription_active( $product_id, $subscription ) {

		$user_id = $subscription->get_user_id();

		$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_subscribed'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_subscribed'], $user_id );
		}

	}

	/**
	 * Writes ATT options to panel
	 *
	 * @access public
	 * @return mixed
	 */

	public function panel_content( $post_id ) {

		$settings = array(
			'apply_tags_subscribed' => array(),
		);

		if ( get_post_meta( $post_id, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group show_if_simple show_if_variable show_if_bundle">';

		echo '<p class="form-field"><label><strong>' . __( 'Subscribe All The Things' ) . '</strong></label></p>';

		echo '<p class="form-field"><label>' . __( 'Apply tags when subscribed' ) . '</label>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_subscribed'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_subscribed',
			)
		);
		echo '<span class="description">' . __( 'Apply these tags when someone subscribes to this product', 'wp-fusion' ) . '.</span>';
		echo '</p>';

		echo '</div>';

	}


}

new WPF_WCS_ATT();
