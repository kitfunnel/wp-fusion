<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_WCS_Gifting extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wcs-gifting';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Gifting for WooCommerce Subscriptions';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/gifting-for-woocommerce-subscriptions/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.33
	 * @return  void
	 */

	public function init() {

		add_action( 'wpf_woocommerce_product_subscription_active', array( $this, 'subscription_active' ), 10, 2 );
		add_action( 'wpf_woocommerce_product_subscription_inactive', array( $this, 'subscription_inactive' ), 10, 2 );

		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );

		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ) );

	}


	/**
	 * Apply tags to the recipient
	 *
	 * @access public
	 * @return void
	 */

	public function subscription_active( $product_id, $subscription ) {

		if ( WCS_Gifting::is_gifted_subscription( $subscription ) ) {

			$recipient_user_id = WCS_Gifting::get_recipient_user( $subscription );

			$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_recipient'] ) ) {

				wp_fusion()->user->apply_tags( $settings['apply_tags_recipient'], $recipient_user_id );

				// Maybe remove tags from customer

				if ( ! empty( $settings['remove_tags_customer'] ) ) {

					wp_fusion()->user->remove_tags( $settings['apply_tags_recipient'], $subscription->get_user_id() );

				}
			}
		}

	}

	/**
	 * Remove tags when subscription is put on hold / cancelled
	 *
	 * @access public
	 * @return void
	 */

	public function subscription_inactive( $product_id, $subscription ) {

		if ( WCS_Gifting::is_gifted_subscription( $subscription ) ) {

			$recipient_user_id = WCS_Gifting::get_recipient_user( $subscription );

			$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_recipient'] ) && true == $settings['remove_tags_recipient'] ) {

				wp_fusion()->user->remove_tags( $settings['apply_tags_recipient'], $recipient_user_id );

			}
		}

	}

	/**
	 * Make sure gift recipient gets their own contact record
	 *
	 * @access public
	 * @return array Post data
	 */

	public function user_register( $user_meta, $user_id ) {

		if ( ! doing_action( 'woocommerce_checkout_create_order_line_item' ) ) {
			return $user_meta;
		}

		$user = get_userdata( $user_id );

		if ( $user_meta['user_email'] !== $user->user_email || $user_meta['billing_email'] !== $user->user_email ) {

			$new_user_meta = wp_fusion()->user->get_user_meta( $user_id );

			wpf_log( 'info', $user_id, 'Creating new contact record for gift recipient with email <strong>' . $new_user_meta['user_email'] . '</strong>' );

			$new_user_meta['first_name'] = null; // force these blank to override the auto-name detection in WPF_User::maybe_set_first_last_name.
			$new_user_meta['last_name']  = null;

			// Merge the shipping data back in. If enabled, it will be synced to the
			// recipient's contact record.

			foreach ( $user_meta as $key => $value ) {

				if ( 0 === strpos( $key, 'shipping' ) ) {
					$new_user_meta[ $key ] = $value;
				}
			}

			return $new_user_meta;

		}

		return $user_meta;

	}

	/**
	 * Writes options to panel
	 *
	 * @access public
	 * @return mixed
	 */

	public function panel_content( $post_id ) {

		$settings = array(
			'apply_tags_recipient'  => array(),
			'remove_tags_recipient' => false,
			'remove_tags_customer'  => false,
		);

		if ( get_post_meta( $post_id, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group show_if_subscription show_if_variable-subscription">';

		echo '<p class="form-field"><label><strong>' . __( 'Subscriptions Gifting', 'wp-fusion' ) . '</strong></label></p>';

		echo '<p>' . sprintf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/ecommerce/gifting-for-woocommerce-subscriptions/" target="_blank">', '</a>' ) . '</p>';

		echo '<p class="form-field"><label>' . __( 'Apply tags to recipient', 'wp-fusion' ) . '</label>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_recipient'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_recipient',
			)
		);
		echo '<span class="description">' . __( 'Apply these tags to the recipient when this subscription is purchased as a gift', 'wp-fusion' ) . '.</span>';
		echo '</p>';

		echo '<p class="form-field"><label for="wpf-remove-tags-recipient">' . __( 'Remove tags', 'wp-fusion' ) . '</label>';
		echo '<input class="checkbox" type="checkbox" id="wpf-remove-tags-recipient" name="wpf-settings-woo[remove_tags_recipient]" value="1" ' . checked( $settings['remove_tags_recipient'], 1, false ) . ' />';
		echo '<span class="description">' . __( 'Remove original tags (above) when the subscription is cancelled, put on hold, expires, or is switched.', 'wp-fusion' ) . '</span>';
		echo '</p>';

		echo '<p class="form-field"><label for="wpf-remove-tags-from-customer">' . __( 'Remove tags from customer', 'wp-fusion' ) . '</label>';
		echo '<input class="checkbox" type="checkbox" id="wpf-remove-tags-from-customer" name="wpf-settings-woo[remove_tags_customer]" value="1" ' . checked( $settings['remove_tags_customer'], 1, false ) . ' />';
		echo '<span class="description">' . __( 'Remove the tags specified specified above from the customer who made the original purchase.', 'wp-fusion' ) . '</span>';
		echo '</p>';

		echo '</div>';

	}

}

new WPF_WCS_Gifting();
