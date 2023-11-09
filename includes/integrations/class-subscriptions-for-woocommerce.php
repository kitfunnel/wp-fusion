<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Subscriptions_For_WooCommerce extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.23
	 * @var string $slug
	 */

	public $slug = 'subscriptions-for-woocommerce';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.23
	 * @var string $name
	 */
	public $name = 'Subscriptions for Woocommerce';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.23
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started.
	 *
	 * @since 3.40.23
	 */
	public function init() {

		// Admin settings.
		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ), 7 );

		// User cancel.
		add_action( 'wps_sfw_cancel_susbcription', array( $this, 'cancelled_subscription' ) );

		// Admin cancel
		add_action( 'wps_sfw_subscription_cancel', array( $this, 'cancelled_subscription' ) );

	}

	/**
	 * Run when a subscription is cancelled.
	 *
	 * @since 3.40.23
	 * @param int $sub_id The subscription ID.
	 */
	public function cancelled_subscription( $sub_id ) {

		$product_id = intval( get_post_meta( $sub_id, 'product_id', true ) );
		if ( 0 === $product_id ) {
			return false;
		}

		$user_id = intval( get_post_meta( $sub_id, 'wps_customer_id', true ) );
		if ( 0 === $user_id ) {
			return false;
		}

		$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

		if ( empty( $settings ) ) {
			return false;
		}

		// If checkbox is active.
		if ( ! empty( $settings['sfw_remove_tags'] ) ) {
			wp_fusion()->user->remove_tags( $settings['apply_tags'], $user_id );
		}

		// Add tags.
		if ( ! empty( $settings['sfw_apply_tags_cancelled'] ) ) {
			wp_fusion()->user->apply_tags( $settings['sfw_apply_tags_cancelled'], $user_id );
		}

	}



	/**
	 * Writes subscriptions options to WPF/Woo panel
	 *
	 * @since 3.40.23
	 *
	 * @param int $post_id The Post ID.
	 */
	public function panel_content( $post_id ) {

		$settings = array(
			'sfw_remove_tags'          => 0,
			'sfw_apply_tags_cancelled' => array(),
		);

		if ( get_post_meta( $post_id, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group wps_sfw_product_options">';

		echo '<p class="form-field"><label><strong>' . esc_html__( 'Subscriptions for Woocommerce', 'wp-fusion' ) . '</strong></label></p>';

		echo '<p class="form-field"><label for="wpf-sfw-apply-tags-woo">' . esc_html__( 'Remove tags', 'wp-fusion' ) . '</label>';
		echo '<input class="checkbox" type="checkbox" id="wpf-sfw-apply-tags-woo" name="wpf-settings-woo[sfw_remove_tags]" value="1" ' . checked( $settings['sfw_remove_tags'], 1, false ) . ' />';
		echo '<span class="description">' . esc_html__( 'Remove original tags (above) when the subscription is cancelled', 'wp-fusion' ) . '.</span>';
		echo '</p>';

		// Cancelled.
		echo '<p class="form-field"><label>' . esc_html__( 'Cancelled', 'wp-fusion' ) . '</label>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['sfw_apply_tags_cancelled'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'sfw_apply_tags_cancelled',
			)
		);
		echo '<span class="description">' . esc_html__( 'Apply these tags when a subscription is cancelled', 'wp-fusion' ) . '.</span>';
		echo '</p>';

		echo '</div>';

	}


}

new WPF_Subscriptions_For_WooCommerce();
