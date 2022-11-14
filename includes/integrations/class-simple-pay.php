<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use SimplePay\Pro\Payments\Subscription;

/**
 * WP Simple Pay integration.
 *
 * @since 3.30.4
 */

class WPF_Simple_Pay extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'simple-pay';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Simple pay';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/wp-simple-pay/';

	/**
	 * Gets things started.
	 *
	 * @since 3.30.4
	 */
	public function init() {

		$this->name = 'Simple Pay';

		add_action( 'simpay_after_customer_created', array( $this, 'customer_created' ) );

		add_filter( 'simpay_form_settings_meta_tabs_li', array( $this, 'settings_tabs' ), 10, 2 );
		add_action( 'simpay_form_settings_meta_options_panel', array( $this, 'settings_options_panel' ) );
		add_action( 'simpay_save_form_settings', array( $this, 'save_settings' ), 10, 2 );

		add_action( 'simpay_webhook_charge_failed', array( $this, 'charge_failed' ), 10, 2 );
		add_action( 'simpay_webhook_invoice_payment_succeeded', array( $this, 'payment_succeeded' ), 10, 2 );
		add_action( 'simpay_webhook_event', array( $this, 'subscription_cancelled' ) );

	}

	/**
	 * Apply Payment Failed tags when a charge fails in Stripe.
	 *
	 * @since 3.37.13
	 *
	 * @param \Stripe\Event  $event  Stripe webhook event.
	 * @param \Stripe\Charge $charge Stripe Charge.
	 */
	public function charge_failed( $event, $charge ) {

		$settings = get_post_meta( $charge->metadata->simpay_form_id, 'wpf_settings_simple_pay', true );

		if ( empty( $settings ) || false == $settings['enable'] || empty( $settings['apply_tags_payment_failed'] ) ) {
			return;
		}

		$user = get_user_by( 'email', $charge->customer->email );

		if ( $user ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_payment_failed'], $user->ID );

		} else {

			$contact_id = wp_fusion()->crm->get_contact_id( $charge->customer->email );

			if ( $contact_id ) {

				wpf_log( 'info', 0, 'Simple Pay guest payment recurring payment failed, applying tag(s): ', array( 'tag_array' => $settings['apply_tags_payment_failed'] ) );

				wp_fusion()->crm->apply_tags( $settings['apply_tags_payment_failed'], $contact_id );

			} else {
				wpf_log( 'notice', 0, 'Simple Pay guest subscription cancelled, but unable to determine contact ID for email ' . $charge->customer->email );
			}
		}

	}


	/**
	 * Remove Payment Failed tags when a payment succeeds.
	 *
	 * @since 3.37.13
	 *
	 * @param \Stripe\Event   $event   Stripe webhook event.
	 * @param \Stripe\Invoice $invoice Stripe Invoice.
	 */
	public function payment_succeeded( $event, $invoice ) {

		$form_id = end( $invoice->lines->data )->metadata->simpay_form_id;

		$settings = get_post_meta( $form_id, 'wpf_settings_simple_pay', true );

		if ( empty( $settings ) || false == $settings['enable'] || empty( $settings['apply_tags_payment_failed'] ) ) {
			return;
		}

		$user = get_user_by( 'email', $invoice->customer_email );

		if ( $user ) {

			wp_fusion()->user->remove_tags( $settings['apply_tags_payment_failed'], $user->ID );

		} else {

			$contact_id = wp_fusion()->crm->get_contact_id( $invoice->customer_email );

			if ( $contact_id ) {
				wp_fusion()->crm->remove_tags( $settings['apply_tags_payment_failed'], $contact_id );
			}
		}

	}


	/**
	 * Apply Subscription Cancelled tags when a subscription is cancelled in
	 * Stripe.
	 *
	 * @since 3.37.13
	 *
	 * @param \Stripe\Event $event  Stripe webhook event.
	 */
	public function subscription_cancelled( $event ) {

		if ( 'customer.subscription.deleted' !== $event->type ) {
			return;
		}

		$subscription = $event->data->object;

		// Get the form ID

		if ( ! isset( $subscription->metadata->simpay_form_id ) ) {
			return;
		}

		$form_id = $subscription->metadata->simpay_form_id;

		// See if WP Fusion is enabled on the form

		$settings = get_post_meta( $form_id, 'wpf_settings_simple_pay', true );

		if ( empty( $settings ) || false == $settings['enable'] || empty( $settings['apply_tags_subscription_cancelled'] ) ) {
			return;
		}

		$form = simpay_get_form( $form_id );

		if ( false === $form ) {
			return;
		}

		// Retrieve the customer data

		$subscription = Subscription\retrieve(
			array(
				'id'     => $subscription->id,
				'expand' => array(
					'customer',
				),
			),
			$form->get_api_request_args()
		);

		$user = get_user_by( 'email', $subscription->customer->email );

		if ( $user ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_subscription_cancelled'], $user->ID );

			if ( ! empty( $settings['remove_tags'] ) && ! empty( $settings['apply_tags'] ) ) {
				wp_fusion()->user->remove_tags( $settings['apply_tags_subscription_cancelled'], $user->ID );
			}
		} else {

			$contact_id = wp_fusion()->crm->get_contact_id( $charge->customer->email );

			if ( $contact_id ) {

				wpf_log( 'info', 0, 'Simple Pay guest subscription cancelled, applying tag(s): ', array( 'tag_array' => $settings['apply_tags_subscription_cancelled'] ) );

				wp_fusion()->crm->apply_tags( $settings['apply_tags_subscription_cancelled'], $contact_id );

				if ( ! empty( $settings['remove_tags'] ) && ! empty( $settings['apply_tags'] ) ) {
					wp_fusion()->crm->remove_tags( $settings['apply_tags_subscription_cancelled'], $contact_id );
				}
			} else {
				wpf_log( 'notice', 0, 'Simple Pay guest subscription cancelled, but unable to determine contact ID for email ' . $charge->customer->email );
			}
		}

	}


	/**
	 * Sync the customer to the CRM.
	 *
	 * Runs on the simpay_after_customer_created hook and creates / updates the
	 * customer in the connected CRM, applying any tags.
	 *
	 * @since 3.30.4
	 *
	 * @param \Stripe\Customer $customer The customer.
	 */
	public function customer_created( $customer ) {

		$form_id = $customer->metadata->simpay_form_id;

		$settings = get_post_meta( $form_id, 'wpf_settings_simple_pay', true );

		if ( empty( $settings ) || false == $settings['enable'] ) {
			return;
		}

		// Build the name

		$name = explode( ' ', $customer->name );

		$first_name = $name[0];

		unset( $name[0] );

		$last_name = implode( ' ', $name );

		$update_data = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'user_email' => $customer->email,
		);

		if ( wpf_is_user_logged_in() ) {

			wp_fusion()->user->push_user_meta( wpf_get_current_user_id(), $update_data );

			if ( ! empty( $settings['apply_tags'] ) ) {

				wp_fusion()->user->apply_tags( $settings['apply_tags'] );

			}
		} else {

			$contact_id = $this->guest_registration( $customer->email, $update_data );

			if ( $contact_id && ! empty( $settings['apply_tags'] ) ) {

				wpf_log( 'info', 0, 'Simple Pay guest payment applying tag(s): ', array( 'tag_array' => $settings['apply_tags'] ) );

				wp_fusion()->crm->apply_tags( $settings['apply_tags'], $contact_id );

			}
		}

	}


	/**
	 * Register the settings panel.
	 *
	 * @since  3.30.4
	 *
	 * @param  array $tabs    The tabs.
	 * @param  int   $post_id The post ID.
	 * @return array The tabs.
	 */
	public function settings_tabs( $tabs, $post_id ) {

		$tabs['wp_fusion'] = array(
			'label'  => 'WP Fusion',
			'target' => 'wp-fusion-settings-panel',
			'icon'   => wpf_logo_svg( '20px' ),
		);

		return $tabs;

	}


	/**
	 * Settings panel output.
	 *
	 * @since 3.30.4
	 *
	 * @param int $post_id The post ID.
	 * @return mixed The settings panel output.
	 */
	public function settings_options_panel( $post_id ) {

		$defaults = array(
			'enable'                            => true,
			'apply_tags'                        => array(),
			'apply_tags_payment_failed'         => array(),
			'apply_tags_subscription_cancelled' => array(),
			'remove_tags'                       => false,
		);

		$settings = get_post_meta( $post_id, 'wpf_settings_simple_pay', true );

		$settings = wp_parse_args( $settings, $defaults );

		?>

		<div id="wp-fusion-settings-panel" class="simpay-panel simpay-panel-hidden simpay-panel--has-help">

			<table>
				<tbody class="simpay-panel-section">

				<tr class="simpay-panel-field">
					<th>
						<label for="wpf-enable"><?php esc_html_e( 'Enable', 'wp-fusion' ); ?></label>
					</th>
					<td>

						<input class="checkbox" type="checkbox" id="wpf-enable" name="wpf_settings_simple_pay[enable]" value="1" <?php checked( $settings['enable'], 1 ); ?> />
						<label for="wpf-enable"><?php echo sprintf( __( 'Sync customers with %s', 'wp-fusion' ), wp_fusion()->crm->name ); ?></label>

						<?php
						/*
						 <br /><br />
						<p class="description"><?php _e( 'Field mapping can be configured on the Custom Form Fields tab.', 'wp-fusion' ); ?></p> */
						?>

					</td>
				</tr>

				<tr class="simpay-panel-field">
					<th>
						<label for="apply_tags"><?php esc_html_e( 'Apply Tags', 'wp-fusion' ); ?></label>
					</th>
					<td>

						<?php

						wpf_render_tag_multiselect(
							array(
								'setting'   => $settings['apply_tags'],
								'meta_name' => 'wpf_settings_simple_pay',
								'field_id'  => 'apply_tags',
							)
						);

						?>

						<p class="description"><?php echo sprintf( __( 'Select tags to apply in %s when a payment is received.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></p>

					</td>
				</tr>

				<tr class="simpay-panel-field">
					<th>
						<label for="apply_tags"><?php esc_html_e( 'Apply Tags - Payment Failed', 'wp-fusion' ); ?></label>
					</th>
					<td>

						<?php

						wpf_render_tag_multiselect(
							array(
								'setting'   => $settings['apply_tags_payment_failed'],
								'meta_name' => 'wpf_settings_simple_pay',
								'field_id'  => 'apply_tags_payment_failed',
							)
						);

						?>

						<p class="description"><?php echo sprintf( __( 'Select tags to apply in %s when a recurring payment fails.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></p>

					</td>
				</tr>

				<tr class="simpay-panel-field">
					<th>
						<label for="apply_tags"><?php esc_html_e( 'Apply Tags - Subscription Cancelled', 'wp-fusion' ); ?></label>
					</th>
					<td>

						<?php

						wpf_render_tag_multiselect(
							array(
								'setting'   => $settings['apply_tags_subscription_cancelled'],
								'meta_name' => 'wpf_settings_simple_pay',
								'field_id'  => 'apply_tags_subscription_cancelled',
							)
						);

						?>

						<p class="description"><?php echo sprintf( __( 'Select tags to apply in %s when a subscription is cancelled.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></p>

					</td>
				</tr>

				<tr class="simpay-panel-field">
					<th>
						<label for="wpf-remove-tags"><?php esc_html_e( 'Remove Tags', 'wp-fusion' ); ?></label>
					</th>
					<td>

						<input class="checkbox" type="checkbox" id="wpf-remove-tags" name="wpf_settings_simple_pay[remove_tags]" value="1" <?php checked( $settings['remove_tags'], 1 ); ?> />
						<label for="wpf-remove-tags"><?php echo __( 'Remove the tags applied with the initial purchase when a subscription is cancelled.', 'wp-fusion' ); ?></label>

						<?php
						/*
						 <br /><br />
						<p class="description"><?php _e( 'Field mapping can be configured on the Custom Form Fields tab.', 'wp-fusion' ); ?></p> */
						?>

					</td>
				</tr>

				</tbody>
			</table>

			<div class="simpay-docs-link-wrap">
				<a href="http://wpfusion.com/documentation/ecommerce/wp-simple-pay/" target="_blank" rel="noopener noreferrer">Help docs for WP Fusion<span class="dashicons dashicons-editor-help"></span></a>
			</div>

		</div>

		<?php

	}


	/**
	 * Saves settings.
	 *
	 * @since 3.30.4
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post.
	 */
	public function save_settings( $post_id, $post ) {

		if ( isset( $_POST['wpf_settings_simple_pay'] ) ) {
			update_post_meta( $post_id, 'wpf_settings_simple_pay', $_POST['wpf_settings_simple_pay'] );
		} else {
			delete_post_meta( $post_id, 'wpf_settings_simple_pay' );
		}

	}

}

new WPF_Simple_Pay();
