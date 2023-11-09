<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_LD_Group_Registration extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'ld-group-registration';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Ld group registration';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/wisdm-group-registration-learndash/';

	/**
	 * Gets things started.
	 *
	 * @since 3.37.7
	 */
	public function init() {

		add_action( 'wdm_created_new_group_using_ldgr', array( $this, 'group_user_added' ), 10, 3 );
		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ) );
	}


	/**
	 * Apply tags when a user is added to a group.
	 *
	 * @since 3.37.7
	 *
	 * @param int $group_id   The group that was created.
	 * @param int $product_id The product ID that triggered the group
	 *                        creation.
	 * @param int $order_id   The new WooCommerce order.
	 */
	public function group_user_added( $group_id, $product_id, $order_id ) {

		$product_tags = get_post_meta( $product_id, 'wpf-settings-woo', true );

		if ( ! empty( $product_tags ) && ! empty( $product_tags['apply_tags_ld_group_user_added'] ) ) {

			$group_tags = array(
				'apply_tags_enrolled' => $product_tags['apply_tags_ld_group_user_added'],
			);

			update_post_meta( $group_id, 'wpf-settings-learndash', $group_tags );

			// Add product tags to the user
			$order   = wc_get_order( $order_id );
			$user_id = $order->get_user_id();

			wp_fusion()->user->apply_tags( $product_tags['apply_tags_ld_group_user_added'], $user_id );

		}

	}


	/**
	 * Output settings to Woo product panel.
	 *
	 * @since 3.37.7
	 *
	 * @param int $post_id The product ID.
	 */
	public function panel_content( $post_id ) {

		$settings = array(
			'apply_tags_ld_group_user_added' => array(),
		);

		if ( get_post_meta( $post_id, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group wpf-product show_if_courses">';

		echo '<p class="form-field"><label><strong>' . __( 'Group Registration', 'wp-fusion' ) . '</strong></label></p>';

		echo '<p class="form-field"><label for="wpf-apply-tags-woo">' . __( 'Apply tags when a user is added to this LearnDash group', 'wp-fusion' ) . '</label>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_ld_group_user_added'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_ld_group_user_added',
			)
		);

		echo '</p>';

		echo '</div>';

	}


}

new WPF_LD_Group_Registration();
