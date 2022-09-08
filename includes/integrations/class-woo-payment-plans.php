<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce Payment Plans integration.
 *
 * @since 3.38.11
 *
 * @link https://wpfusion.com/documentation/events/woocommerce-payment-plans/
 */
class WPF_Woo_Payment_Plans extends WPF_Integrations_Base {

	/**
	 * This identifies the integration internally and makes it available at
	 * wp_fusion()->integrations->{'my-plugin-slug'}
	 *
	 * @since 3.38.11
	 * @var  string
	 */

	public $slug = 'woo-payment-plans';

	/**
	 * The human-readable name of the integration.
	 *
	 * @since 3.38.11
	 * @var  string
	 */

	public $name = 'WooCommerce Payment Plans';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/woocommerce-payment-plans/';

	/**
	 * Get things started.
	 *
	 * @since 3.38.11
	 */
	public function init() {

		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ) );
		add_action( 'wpf_woocommerce_subscription_status_apply_tags', array( $this, 'apply_tags_payment_plans' ), 10, 3 );

	}

	/**
	 * Writes payment plans options to WPF/Woo panel.
	 *
	 * @since 3.38.11
	 *
	 * @param int $post_id The product ID.
	 * @return mixed The HTML settings.
	 */
	public function panel_content( $post_id ) {

		$settings = array(
			'apply_tags_partially_paid' => array(),
			'apply_tags_fully_paid'     => array(),
		);

		$settings = wp_parse_args( get_post_meta( $post_id, 'wpf-settings-woo', true ), $settings );

		echo '<div class="options_group show_if_simple show_if_variable show_if_bundle">';

		echo '<p class="form-field"><label><strong>' . esc_html__( 'Payment Plans', 'wp-fusion' ) . '</strong></label></p>';
		echo '<p class="form-field"><label>' . esc_html__( 'Partially Paid', 'wp-fusion' ) . '</label>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_partially_paid'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_partially_paid',
			)
		);
		echo '<span class="description">' . esc_html__( 'Apply these tags when the payment plan is partially paid.', 'wp-fusion' ) . '</span>';
		echo '</p>';

		echo '<p class="form-field"><label>' . esc_html__( 'Fully Paid', 'wp-fusion' ) . '</label>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_fully_paid'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_fully_paid',
			)
		);
		echo '<span class="description">' . esc_html__( 'Apply these tags when the payment plan has been fully paid (subscription is set to expired).', 'wp-fusion' ) . '</span>';
		echo '</p>';

		echo '</div>';

	}

	/**
	 * Apply tags for payment plans.
	 *
	 * @since  3.38.11
	 *
	 * @param  array           $apply_tags   The tags to apply.
	 * @param  string          $status       The status.
	 * @param  WC_Subscription $subscription The subscription.
	 * @return arrray          The tags to apply in the CRM.
	 */
	public function apply_tags_payment_plans( $apply_tags, $status, $subscription ) {

		foreach ( $subscription->get_items() as $line_item ) {

			$product_id = $line_item->get_product_id();

			// Check if product has payment plan.
			if ( empty( get_post_meta( $product_id, '_wcsatt_schemes', true ) ) ) {
				continue;
			}

			$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

			if ( empty( $settings ) ) {
				continue;
			}

			if ( 'active' === $status && ! empty( $settings['apply_tags_partially_paid'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_partially_paid'] );
			}

			if ( 'expired' === $status && ! empty( $settings['apply_tags_fully_paid'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_fully_paid'] );
			}
		}

		return $apply_tags;

	}

}

new WPF_Woo_Payment_Plans();
