<?php

use Upsell\Modules\Notes\Entities\Note;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Upsell Plugin integration.
 *
 * @since 3.37.25
 *
 * @link  https://wpfusion.com/documentation/ecommerce/upsell-plugin/
 */
class WPF_Upsell extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'upsell';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Upsell';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/upsell-plugin/';

	/**
	 * Gets things started.
	 *
	 * @since 3.37.25
	 */

	public function init() {

		// Custom Fields
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

		// Add settings

		if ( is_admin() ) {
			add_filter( 'upsell_product_field_group', array( $this, 'add_settings' ), 100, 2 );
			add_filter( 'upsell_coupon_field_group', array( $this, 'add_coupon_settings' ), 100, 2 );
			add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 5, 2 );
		}

		// Apply tags when order/subscription is created
		add_action( 'upsell_process_checkout_completed', array( $this, 'order_created' ) );

		// Subscription status changes
		add_action( 'upsell_subscription_cancelled', array( $this, 'subscription_status_changed' ) );
		// add_action( 'upsell_subscription_soft_cancelled', array( $this, 'subscription_status_changed' ) ); // upsell doesn't track cancellations with time left
		add_action( 'upsell_subscription_status_failed', array( $this, 'subscription_status_changed' ) );
		add_action( 'upsell_subscription_status_expired', array( $this, 'subscription_status_changed' ) );

		// Apply tags when refunded
		add_action( 'upsell_order_status_refunded', array( $this, 'order_refunded' ) );

		// Batch operations
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_filter( 'wpf_batch_upsell_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_upsell', array( $this, 'batch_step' ) );

		add_filter( 'wpf_batch_upsell_sub_init', array( $this, 'batch_init_sub' ) );
		add_action( 'wpf_batch_upsell_sub', array( $this, 'batch_step_sub' ) );

		add_filter( 'wpf_batch_upsell_meta_init', array( $this, 'batch_init_meta' ) );
		add_action( 'wpf_batch_upsell_meta', array( $this, 'batch_step_meta' ) );
	}

	/**
	 * Gets the contact ID from an order, looking it up in the CRM if not found.
	 *
	 * @since  3.37.25
	 *
	 * @param  object $order  The order.
	 * @return string|false The contact ID or false.
	 */
	public function get_contact_id( $order ) {

		$contact_id = false;

		if ( property_exists( $order, 'customer' ) && null !== $order->customer ) {

			$user_id = $this->get_user_id( $order );

			// User ID is preferred

			if ( $user_id ) {
				$contact_id = wp_fusion()->user->get_contact_id( $user_id );
			}

			// Then try it from the customer record alone (for guests)

			if ( empty( $contact_id ) ) {
				$contact_id = $order->customer->getAttribute( '_' . WPF_CONTACT_ID_META_KEY );
			}
		}

		// Finally, make an API call and look it up in the CRM

		if ( empty( $contact_id ) ) {

			$contact_id = wp_fusion()->crm->get_contact_id( $order->getAttribute( 'customer_email' ) );

			// If found, update the customer

			if ( false !== $contact_id ) {
				if ( property_exists( $order, 'customer' ) && null !== $order->customer ) {
					$order->customer->setAttribute( '_' . WPF_CONTACT_ID_META_KEY, $contact_id );
				}
			}
		}

		return $contact_id;

	}

	/**
	 * Upsell doesn't attach user IDs to orders for some reason so we'll look
	 * folks up by email here.
	 *
	 * @since  3.37.25
	 *
	 * @param  object $order  The order.
	 * @return int|false The user ID or false.
	 */
	private function get_user_id( $order ) {

		if ( $order->customer()->attribute( 'user_id' ) ) {
			return $order->customer()->attribute( 'user_id' );
		}

		$user_id = false;
		$email   = $order->attribute( 'customer_email' );
		$user    = get_user_by( 'email', $email );

		if ( $user ) {
			$user_id = $user->ID;
		}

		return apply_filters( 'wpf_upsell_user_id', $user_id, $order );

	}


	/**
	 * Removes tags for an order, based on the customer who made the purchase.
	 *
	 * @since 3.37.25
	 *
	 * @param array $remove_tags The tags to remove.
	 * @param Order $order       The order.
	 */
	private function remove_tags_for_order( $remove_tags, $order ) {

		$user_id = $this->get_user_id( $order );

		if ( $user_id ) {

			// Registered users
			wp_fusion()->user->remove_tags( $remove_tags, $user_id );

		} else {

			// Guests
			$contact_id = $this->get_contact_id( $order );

			if ( $contact_id ) {

				wp_fusion()->crm->remove_tags( $remove_tags, $contact_id );

			} else {
				wpf_log( 'notice', $user_id, 'Unable to remove tags for Upsell order <a href="' . admin_url( 'post.php?post=' . $order->id . '&action=edit' ) . '" target="_blank">#' . $order->id . '</a>: could not determine contact ID from customer ' . $order->getAttribute( 'customer_email' ) );
			}
		}

	}

	/**
	 * Applies tags for an order, based on the customer who made the purchase.
	 *
	 * @since 3.37.25
	 *
	 * @param array $apply_tags The tags to apply.
	 * @param Order $order      The order.
	 */
	private function apply_tags_for_order( $apply_tags, $order ) {

		$user_id = $this->get_user_id( $order );

		if ( $user_id ) {

			// Registered users
			wp_fusion()->user->apply_tags( $apply_tags, $user_id );

		} else {

			// Guests

			$contact_id = $this->get_contact_id( $order );

			wpf_log( 'info', 0, 'Applying tags for guest checkout: ', array( 'tag_array' => $apply_tags ) );

			if ( $contact_id ) {
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );
			} else {
				wpf_log( 'notice', $user_id, 'Unable to apply tags for Upsell order <a href="' . admin_url( 'post.php?post=' . $order->id . '&action=edit' ) . '" target="_blank">#' . $order->id . '</a>: could not determine contact ID from customer ' . $order->getAttribute( 'customer_email' ) );
			}
		}

	}



	/**
	 * Get subscription data from order.
	 *
	 * @since  3.37.25
	 *
	 * @param  object $order The order.
	 * @return array The subscription data.
	 */
	public function get_subscription_data( $order ) {

		if ( absint( $order->subscription_id ) === 0 ) {
			return array();
		}

		$sub = array();

		$subscription = \Upsell\Entities\Subscription::find( $order->subscription_id );

		if ( $subscription->getAttribute( 'payment_gateway' ) == 'stripe' ) {
			$sub = $subscription->getAttribute( 'stripe_subscription' );
		}

		if ( $subscription->getAttribute( 'payment_gateway' ) == 'paypal' ) {
			$sub = $subscription->getAttribute( 'paypal_subscription' );
		}

		return array(
			'sub_id'           => $order->getAttribute( 'subscription_id' ),
			'sub_status'       => $subscription->getAttribute( 'status' ),
			'sub_product_name' => $sub['metadata']['products'],
			'sub_start_date'   => $sub['current_period_start'],
			'sub_end_date'     => $sub['current_period_end'],
			'sub_renewal_date' => $sub['current_period_end'],
		);
	}

	/**
	 * Get customer data from order.
	 *
	 * @since  3.37.25
	 *
	 * @param  object $order The order.
	 * @return array The user data.
	 */
	public function get_customer_data( $order ) {

		$customer_data = array(
			'user_email'                => $order->getAttribute( 'customer_email' ),
			'first_name'                => $order->getAttribute( 'customer_first_name' ),
			'last_name'                 => $order->getAttribute( 'customer_last_name' ),
			'customer_id'               => $order->getAttribute( 'customer_id' ),
			'customer_billing_street'   => $order->getAttribute( 'customer_billing_street' ),
			'customer_billing_city'     => $order->getAttribute( 'customer_billing_city' ),
			'customer_billing_state'    => $order->getAttribute( 'customer_billing_state' ),
			'customer_billing_country'  => $order->getAttribute( 'customer_billing_country' ),
			'customer_billing_zip'      => $order->getAttribute( 'customer_billing_zip' ),
			'customer_billing_phone'    => $order->getAttribute( 'customer_billing_phone' ),
			'customer_billing_vat'      => $order->getAttribute( 'customer_billing_vat' ),
			'customer_shipping_street'  => $order->getAttribute( 'customer_shipping_street' ),
			'customer_shipping_city'    => $order->getAttribute( 'customer_shipping_city' ),
			'customer_shipping_state'   => $order->getAttribute( 'customer_shipping_state' ),
			'customer_shipping_country' => $order->getAttribute( 'customer_shipping_country' ),
			'customer_shipping_zip'     => $order->getAttribute( 'customer_shipping_zip' ),
		);

		return apply_filters( 'wpf_upsell_customer_data', $customer_data, $order );
	}

	/**
	 * Remove tags applied at checkout when an order is refunded.
	 *
	 * @since 3.37.25
	 *
	 * @param object $order  The order that was refunded.
	 */
	public function order_refunded( $order ) {

		$products = $order->getAttribute( 'items' );

		if ( empty( $products ) ) {
			return;
		}

		$remove_tags = array();
		$apply_tags  = array();

		foreach ( $products as $product_id => $val ) {

			// Purchase tags

			$purchase_tags = get_post_meta( $product_id, 'wpf_settings_upsell_apply_tags', true );

			if ( ! empty( $purchase_tags ) ) {
				$remove_tags = array_merge( $remove_tags, $purchase_tags );
			}

			// Refund tags

			$refund_tags = get_post_meta( $product_id, 'wpf_settings_upsell_apply_tags_refunded', true );

			if ( ! empty( $refund_tags ) ) {
				$apply_tags = array_merge( $apply_tags, $refund_tags );
			}
		}

		if ( ! empty( $remove_tags ) ) {
			$this->remove_tags_for_order( $remove_tags, $order );
		}

		if ( ! empty( $apply_tags ) ) {
			$this->apply_tags_for_order( $apply_tags, $order );
		}

	}


	/**
	 * Apply tags and sync meta fields when a subscription status changes.
	 *
	 * @param object $order The order that had a subscription status change.
	 */
	public function subscription_status_changed( $subscription ) {

		$this->sync_subscription_fields( $subscription );

		$this->apply_tags_for_subscription_status( $subscription );
	}

	/**
	 * Sends relevant data from a subscription to the connected CRM
	 *
	 * @access public
	 * @return void
	 */

	public function sync_subscription_fields( $subscription ) {

		$update_data = $this->get_subscription_data( $subscription->order() );
		$user_id     = $this->get_user_id( $subscription->order() );

		if ( $user_id ) {

			wp_fusion()->user->push_user_meta( $user_id, $update_data );

		} else {

			// Guests

			$contact_id = $this->get_contact_id( $subscription->order() );

			if ( $contact_id ) {

				wp_fusion()->crm->update_contact( $contact_id, $update_data );

			}
		}

	}

	/**
	 * Apply and remove tags for an order based on subscription status.
	 *
	 * @since  3.37.25
	 *
	 * @param  object $subscription The subscription.
	 * @return void
	 */
	public function apply_tags_for_subscription_status( $subscription ) {

		$apply_tags  = array();
		$remove_tags = array();
		$product     = $subscription->product();

		if ( null === $product ) {
			return;
		}

		$status          = $subscription->getAttribute( 'status' );
		$failed_statuses = array( 'failed', 'cancelled', 'expired' );

		// $status is active, cancelled, failed, expired

		$setting = get_post_meta( $product->id, "wpf_settings_upsell_apply_tags_subscription_status_{$status}", true );

		// Main tags for status

		if ( ! empty( $setting ) ) {
			$apply_tags = array_merge( $apply_tags, $setting );
		}

		if ( in_array( $status, $failed_statuses ) ) {

			if ( ! empty( get_post_meta( $product->id, 'wpf_settings_upsell_subscription_remove_tags', true ) ) ) {

				// Remove active tags if Remove Tags is checked

				$active_tags = get_post_meta( $product->id, 'wpf_settings_upsell_apply_tags_subscription_status_active', true );

				if ( ! empty( $active_tags ) ) {
					$remove_tags = array_merge( $remove_tags, $active_tags );
				}
			}
		} elseif ( 'active' == $status ) {

			// If the subscription is now active, remove the failed tags

			foreach ( $failed_statuses as $failed_status ) {

				$failed_setting = get_post_meta( $product->id, "wpf_settings_upsell_apply_tags_subscription_status_{$failed_status}", true );

				if ( ! empty( $failed_setting ) ) {
					$remove_tags = array_merge( $remove_tags, $failed_setting );
				}
			}
		}

		/**
		 * Filters the tags to apply to the user based on subscription status.
		 *
		 * @since 3.37.25
		 *
		 * @param array  $apply_tags   The tags to apply.
		 * @param string $status       The subscription status.
		 * @param object $subscription The subscription object.
		 */

		$apply_tags = apply_filters( 'wpf_upsell_subscription_status_apply_tags', $apply_tags, $status, $subscription );

		/**
		 * Filters the tags to remove from the user based on subscription
		 * status.
		 *
		 * @since 3.37.25
		 *
		 * @param array  $remove_tags  The tags to remove.
		 * @param string $status       The subscription status.
		 * @param object $subscription The subscription object.
		 */

		$remove_tags = apply_filters( 'wpf_upsell_subscription_status_remove_tags', $remove_tags, $status, $subscription );

		// If there's nothing to be done, don't bother logging it
		if ( empty( $apply_tags ) && empty( $remove_tags ) ) {
			return true;
		}

		wpf_log( 'info', $this->get_user_id( $subscription->order() ), 'Applying tags for Upsell subscription <a href="' . admin_url( 'post.php?post=' . $subscription->getId() . '&action=edit' ) . '" target="_blank">#' . $subscription->getId() . '</a> with status <strong>' . ucwords( $status ) . '</strong>.' );

		if ( ! empty( $remove_tags ) ) {
			$this->remove_tags_for_order( $remove_tags, $subscription->order() );
		}

		if ( ! empty( $apply_tags ) ) {
			$this->apply_tags_for_order( $apply_tags, $subscription->order() );
		}

	}

	/**
	 * Order / Subscription is created.
	 *
	 * @since 3.37.25
	 *
	 * @param object $order  The order.
	 */
	public function order_created( $order ) {

		$products = $order->getAttribute( 'items' );

		if ( empty( $products ) ) {
			return;
		}

		// Create customer

		$this->create_update_customer( $order );

		// Apply tags. Start with global tags, for all customers

		$apply_tags = wpf_get_option( 'upsell_main_tags', array() );

		foreach ( $products as $product_id => $val ) {

			// Product purchase

			$purchase_tags = get_post_meta( $product_id, 'wpf_settings_upsell_apply_tags', true );

			if ( ! empty( $purchase_tags ) ) {
				$apply_tags = array_merge( $apply_tags, $purchase_tags );
			}

			// If it's a subscription

			if ( 0 !== $order->subscription_id ) {
				$sub_tags = get_post_meta( $product_id, 'wpf_settings_upsell_apply_tags_subscription_status_active', true );
				if ( ! empty( $sub_tags ) ) {
					$apply_tags = array_merge( $sub_tags, $apply_tags );
				}
			}
		}

		// Coupons

		if ( $order->getAttribute( 'discount' ) && ! empty( $order->getAttribute( 'coupons' ) ) ) {

			$coupons = $order->getAttribute( 'coupons' );
			$coupon  = \Upsell\Modules\Coupons\Entities\Coupon::findByCode( $coupons[0] );

			$coupon_tags = get_post_meta( $coupon->id, 'wpf_settings_upsell_apply_tags_coupon', true );

			if ( ! empty( $coupon_tags ) ) {
				$apply_tags = array_merge( $apply_tags, $coupon_tags );
			}
		}

		if ( ! empty( $apply_tags ) ) {
			$this->apply_tags_for_order( $apply_tags, $order );
		}

	}

	/**
	 * Creates or updates a customer for an order.
	 *
	 * @since  3.37.25
	 *
	 * @param  object $order  The order.
	 */
	public function create_update_customer( $order ) {

		$email    = apply_filters( 'wpf_upsell_billing_email', $order->getAttribute( 'customer_email' ), $order );
		$user_id  = $this->get_user_id( $order );
		$order_id = $order->id;

		if ( empty( $email ) && empty( $user_id ) ) {

			wpf_log( 'error', 0, 'No email address specified for Upsell order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>. Aborting.' );
			return false;

		}

		if ( ! empty( $user_id ) ) {

			// If user is found, lookup the contact ID
			$contact_id = wp_fusion()->user->get_contact_id( $user_id );

			if ( empty( $contact_id ) ) {
				// If not found, check in the CRM and update locally
				$contact_id = wp_fusion()->user->get_contact_id( $user_id, true );
			}
		} else {

			// Try seeing if an existing contact ID exists
			$contact_id = wp_fusion()->crm->get_contact_id( $email );

			if ( is_wp_error( $contact_id ) ) {

				wpf_log( $contact_id->get_error_code(), $user_id, 'Error getting contact ID for <strong>' . $email . '</strong>: ' . $contact_id->get_error_message() );
				return false;

			}
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			wpf_log( 'notice', $user_id, 'You\'re currently logged into the site as an administrator. This checkout will update your existing contact ID #' . $contact_id . ' in ' . wp_fusion()->crm->name . '. If you\'re testing checkouts, it\'s recommended to use an incognito browser window.' );
		}

		// Format order data
		$order_data = array_merge( $this->get_customer_data( $order ), $this->get_subscription_data( $order ) );

		if ( is_array( $order_data ) && ( empty( $order_data ) || empty( $order_data['user_email'] ) ) ) {

			// If getting the order data (or the wpf_woocommerce_customer_data filter) messed up somehow

			wpf_log( 'error', $user_id, 'Aborted checkout for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>, no email address found.' );
			return false;

		} elseif ( false == $order_data || null == $order_data ) {

			// It was intentionally cancelled so we'll quit silently

			// We can't mark it complete in case it was cancelled because it's not yet at the right status.
			// For example it might become "complete" and need to be synced later.

			wpf_log( 'info', $user_id, 'Order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a> will be ignored (nothing returned from <code>wpf_upsell_customer_data</code>).' );
			return false;

		}

		// If contact doesn't exist in CRM
		if ( empty( $contact_id ) ) {

			// Logger
			wpf_log(
				'info',
				0,
				'Processing guest checkout for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>:',
				array(
					'source'     => 'upsell',
					'meta_array' => $order_data,
				)
			);

			$contact_id = wp_fusion()->crm->add_contact( $order_data );

			if ( is_wp_error( $contact_id ) ) {

				wpf_log( 'error', $user_id, 'Error while adding contact: ' . $contact_id->get_error_message(), array( 'source' => wp_fusion()->crm->slug ) );
				return false;

			}

			$note_args = array(
				'comment_post_ID' => $order_id,
				'comment_content' => wp_fusion()->crm->name . ' contact #' . $contact_id . ' created via guest-checkout.',
				'user_id'         => $user_id,
			);

			$note = Note::create( $note_args );

			// Set contact ID locally
			if ( ! empty( $user_id ) ) {
				update_user_meta( $user_id, WPF_CONTACT_ID_META_KEY, $contact_id );
			}

			do_action( 'wpf_guest_contact_created', $contact_id, $order_data['user_email'] );

		} else {

			if ( empty( $user_id ) ) {

				wpf_log(
					'info',
					0,
					'Processing guest checkout for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>, for existing contact ID ' . $contact_id . ':',
					array(
						'source'     => 'upsell',
						'meta_array' => $order_data,
					)
				);

				$result = wp_fusion()->crm->update_contact( $contact_id, $order_data );

				if ( is_wp_error( $result ) ) {
					wpf_log( 'error', $user_id, 'Error while updating contact: ' . $result->get_error_message(), array( 'source' => wp_fusion()->crm->slug ) );
					return false;
				}

				do_action( 'wpf_guest_contact_updated', $contact_id, $order_data['user_email'] );

			} else {

				wp_fusion()->user->push_user_meta( $user_id, $order_data );

			}
		}

		// Save it to the customer

		$order->customer->setAttribute( '_' . WPF_CONTACT_ID_META_KEY, $contact_id );

		// Save it to the order

		update_post_meta( $order_id, '_' . WPF_CONTACT_ID_META_KEY, $contact_id );

		update_post_meta( $order_id, '_wpf_upsell_complete', 1 );

		return $contact_id;

	}

	/**
	 * Add WPF settings to main product edit screen.
	 *
	 * @since  3.37.25
	 *
	 * @param  array   $field_group The ACF field group
	 * @param  integer $id          The product ID.
	 * @return array   The field group.
	 */
	public function add_settings( $field_group, $id ) {
		if ( isset( $field_group['fields'] ) ) {
			$field_group['fields'] = array_merge(
				$field_group['fields'],
				array(
					array(
						'key'       => 'field_5d3842c22e1tetefa_wpf_gr1',
						'label'     => __( 'WP Fusion', 'wp-fusion' ),
						'type'      => 'tab',
						'placement' => 'top',
					),
					array(
						'key'      => 'field_5d3842c22e1tetewpf_f1',
						'label'    => __( 'Apply tags when purchased', 'wp-fusion' ),
						'name'     => 'wpf_settings_upsell_apply_tags',
						'type'     => 'select',
						'choices'  => wp_fusion()->settings->get_available_tags_flat(),
						'multiple' => 1,
						'ui'       => 1,
						'wrapper'  => array(
							'class' => 'wpf-upsell-tags-select select4-wpf-tags',
						),
					),

					array(
						'key'      => 'field_5d3842c22e1tetewpf_f2',
						'label'    => __( 'Apply tags when refunded', 'wp-fusion' ),
						'name'     => 'wpf_settings_upsell_apply_tags_refunded',
						'type'     => 'select',
						'choices'  => wp_fusion()->settings->get_available_tags_flat(),
						'multiple' => 1,
						'ui'       => 1,
						'wrapper'  => array(
							'class' => 'wpf-upsell-tags-select select4-wpf-tags',
						),
					),

					array(
						'key'               => 'field_5d3842c22e1tetewpf_f22',
						'label'             => __( 'Subscription Details', 'wp-fusion' ),
						'type'              => 'message',
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_5cb6c00780eff',
									'operator' => '==',
									'value'    => 'subscription',
								),
							),
						),
					),

					array(
						'key'               => 'field_5d3842c22e1tetewpf_f4',
						'label'             => __( 'Apply tags - Subscription active', 'wp-fusion' ),
						'name'              => 'wpf_settings_upsell_apply_tags_subscription_status_active',
						'type'              => 'select',
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_5cb6c00780eff',
									'operator' => '==',
									'value'    => 'subscription',
								),
							),
						),
						'choices'           => wp_fusion()->settings->get_available_tags_flat(),
						'multiple'          => 1,
						'ui'                => 1,
						'wrapper'           => array(
							'class' => 'wpf-upsell-tags-select select4-wpf-tags',
						),
					),

					array(
						'key'               => 'field_5d3842c22e1tetewpf_f55',
						'label'             => __( 'Remove tags', 'wp-fusion' ),
						'name'              => 'wpf_settings_upsell_subscription_remove_tags',
						'type'              => 'true_false',
						'instructions'      => __( 'Remove the active tags when the subscription is cancelled, failed or expired.', 'wp-fusion' ),
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_5cb6c00780eff',
									'operator' => '==',
									'value'    => 'subscription',
								),
							),
						),
						'ui'                => 1,
						'wrapper'           => array(
							'class' => 'wpf-upsell-tags-select select4-wpf-tags',
						),
					),

					array(
						'key'               => 'field_5d3842c22e1tetewpf_f5',
						'label'             => __( 'Apply tags - Subscription cancelled', 'wp-fusion' ),
						'name'              => 'wpf_settings_upsell_apply_tags_subscription_status_cancelled',
						'type'              => 'select',
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_5cb6c00780eff',
									'operator' => '==',
									'value'    => 'subscription',
								),
							),
						),
						'choices'           => wp_fusion()->settings->get_available_tags_flat(),
						'multiple'          => 1,
						'ui'                => 1,
						'wrapper'           => array(
							'class' => 'wpf-upsell-tags-select select4-wpf-tags',
						),
					),

					array(
						'key'               => 'field_5d3842c22e1tetewpf_f6',
						'label'             => __( 'Apply tags - Subscription payment failed', 'wp-fusion' ),
						'name'              => 'wpf_settings_upsell_apply_tags_subscription_status_failed',
						'type'              => 'select',
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_5cb6c00780eff',
									'operator' => '==',
									'value'    => 'subscription',
								),
							),
						),
						'choices'           => wp_fusion()->settings->get_available_tags_flat(),
						'multiple'          => 1,
						'ui'                => 1,
						'wrapper'           => array(
							'class' => 'wpf-upsell-tags-select select4-wpf-tags',
						),
					),

					array(
						'key'               => 'field_5d3842c22e1tetewpf_f7',
						'label'             => __( 'Apply tags - Subscription expired', 'wp-fusion' ),
						'name'              => 'wpf_settings_upsell_apply_tags_subscription_status_expired',
						'type'              => 'select',
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_5cb6c00780eff',
									'operator' => '==',
									'value'    => 'subscription',
								),
							),
						),
						'choices'           => wp_fusion()->settings->get_available_tags_flat(),
						'multiple'          => 1,
						'ui'                => 1,
						'wrapper'           => array(
							'class' => 'wpf-upsell-tags-select select4-wpf-tags',
						),
					),

				)
			);
		}
		return $field_group;
	}

	/**
	 * Add WPF settings to coupon edit screen.
	 *
	 * @since  3.37.25
	 *
	 * @param  array   $field_group The ACF field group
	 * @param  integer $id          The coupon ID.
	 * @return array   The field group.
	 */
	public function add_coupon_settings( $field_group, $id ) {
		if ( isset( $field_group['fields'] ) ) {
			$field_group['fields'] = array_merge(
				$field_group['fields'],
				array(
					array(
						'key'   => 'field_5d3842c22e1tetefa_wpf_gr2',
						'label' => __( 'WP Fusion', 'wp-fusion' ),
						'type'  => 'tab',
					),
					array(
						'key'      => 'field_5d3842c22e1tetewpf_f3',
						'label'    => __( 'Apply tags when this coupon is used', 'wp-fusion' ),
						'name'     => 'wpf_settings_upsell_apply_tags_coupon',
						'type'     => 'select',
						'choices'  => wp_fusion()->settings->get_available_tags_flat(),
						'multiple' => 1,
						'ui'       => 1,
						'wrapper'  => array(
							'class' => 'wpf-upsell-tags-select select4-wpf-tags',
						),
					),

				)
			);
		}
		return $field_group;
	}



	/**
	 * Adds Upsell field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['upsell'] = array(
			'title'  => 'Upsell',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Add Upsell fields
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function add_meta_fields( $meta_fields ) {

		$meta_fields['customer_id']              = array(
			'label' => 'Customer ID',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_billing_street']  = array(
			'label' => 'Billing Street',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_billing_city']    = array(
			'label' => 'Billing City',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_billing_state']   = array(
			'label' => 'Billing State',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_billing_country'] = array(
			'label' => 'Billing Country',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_billing_zip']     = array(
			'label' => 'Billing Zip',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_billing_phone']   = array(
			'label' => 'Billing Phone',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_billing_vat']     = array(
			'label' => 'Billing VAT',
			'type'  => 'text',
			'group' => 'upsell',
		);

		$meta_fields['customer_shipping_street']  = array(
			'label' => 'Shipping Street',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_shipping_city']    = array(
			'label' => 'Shipping City',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_shipping_state']   = array(
			'label' => 'Shipping State',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_shipping_country'] = array(
			'label' => 'Shipping Country',
			'type'  => 'text',
			'group' => 'upsell',
		);
		$meta_fields['customer_shipping_zip']     = array(
			'label' => 'Shipping Zip',
			'type'  => 'text',
			'group' => 'upsell',
		);

		$meta_fields['sub_id'] = array(
			'label'  => 'Subscription ID',
			'type'   => 'int',
			'group'  => 'upsell',
			'pseudo' => true,
		);

		$meta_fields['sub_status'] = array(
			'label'  => 'Subscription Status',
			'type'   => 'text',
			'group'  => 'upsell',
			'pseudo' => true,
		);

		$meta_fields['sub_product_name'] = array(
			'label'  => 'Subscription Product Name',
			'type'   => 'text',
			'group'  => 'upsell',
			'pseudo' => true,
		);

		$meta_fields['sub_start_date'] = array(
			'label'  => 'Subscription Start Date',
			'type'   => 'date',
			'group'  => 'upsell',
			'pseudo' => true,
		);

		$meta_fields['sub_end_date'] = array(
			'label'  => 'Subscription End Date',
			'type'   => 'date',
			'group'  => 'upsell',
			'pseudo' => true,
		);

		$meta_fields['sub_renewal_date'] = array(
			'label'  => 'Next Payment Date',
			'type'   => 'date',
			'group'  => 'upsell',
			'pseudo' => true,
		);

		return $meta_fields;

	}


	/**
	 * Upsell global settings.
	 *
	 * @param  array $settings The settings.
	 * @param  array $options  The options.
	 * @return array The settings.
	 */
	public function register_settings( $settings, $options ) {

		$settings['upsell_header'] = array(
			'title'   => __( 'Upsell Plugin', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['upsell_main_tags'] = array(
			'title'   => __( 'Apply Tags to Customers', 'wp-fusion' ),
			'desc'    => __( 'These tags will be applied to all Upsell customers.', 'wp-fusion' ),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		return $settings;

	}



	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds Upsell checkbox to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['upsell'] = array(
			'label'   => __( 'Upsell Orders', 'wp-fusion' ),
			'title'   => 'Orders',
			'tooltip' => __( 'Finds Upsell orders that have not been processed by WP Fusion, and adds/updates contacts while applying tags based on the products purchased.', 'wp-fusion' ),
		);

		$options['upsell_sub'] = array(
			'label'   => __( 'Upsell Subscriptions statuses', 'wp-fusion' ),
			'title'   => 'Subscriptions',
			'tooltip' => __( 'Updates user tags for all subscriptions based on current subscription status. Does not sync any custom fields.', 'wp-fusion' ),
		);

		$options['upsell_meta'] = array(
			'label'   => __( 'Upsell Subscriptions meta', 'wp-fusion' ),
			'title'   => 'Subscription Meta',
			'tooltip' => __( 'Syncs the subscription product name, start date, status, and next renewal dates for all subscriptions. Does not modify any tags.', 'wp-fusion' ),
		);

		return $options;

	}

	/**
	 * Get all orders to be processed.
	 *
	 * @since  3.37.25
	 *
	 * @return array Order IDs.
	 */

	public function batch_init() {

		$args      = array(
			'post_type'      => 'upsell_order',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_wpf_upsell_complete',
					'compare' => 'NOT EXISTS',
				),
			),
		);
		$query     = new \WP_Query( $args );
		$order_ids = $query->posts;

		if ( empty( $order_ids ) ) {
			return array();
		}

		return $order_ids;

	}

	/**
	 * Processes orders one at a time.
	 *
	 * @since 3.37.25
	 *
	 * @param int $order_id The order ID.
	 */
	public function batch_step( $order_id ) {

		$order = new \Upsell\Entities\Order( $order_id );
		$this->order_created( $order );

	}


	/**
	 * Get all subscriptions to be processed.
	 *
	 * @since  3.37.25
	 *
	 * @return array Subscription IDs.
	 */

	public function batch_init_sub() {

		$args = array(
			'post_type'      => 'upsell_order',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_subscription_id',
					'compare' => '!=',
					'value'   => '0',
				),
			),
		);

		$query     = new \WP_Query( $args );
		$order_ids = $query->posts;

		if ( empty( $order_ids ) ) {
			return array();
		}

		return $order_ids;
	}

	/**
	 * Processes subscriptions one at a time.
	 *
	 * @since 3.37.25
	 *
	 * @param int $order_id The order ID.
	 */
	public function batch_step_sub( $order_id ) {

		$order        = new \Upsell\Entities\Order( $order_id );
		$subscription = \Upsell\Entities\Subscription::find( $order->subscription_id );

		$this->apply_tags_for_subscription_status( $order );

	}


	/**
	 * Get all subscriptions to be processed.
	 *
	 * @since  3.37.25
	 *
	 * @return array Subscription IDs.
	 */
	public function batch_init_meta() {

		$args = array(
			'post_type'      => 'upsell_order',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_subscription_id',
					'compare' => '!=',
					'value'   => '0',
				),
			),
		);

		$query     = new \WP_Query( $args );
		$order_ids = $query->posts;

		if ( empty( $order_ids ) ) {
			return array();
		}

		return $order_ids;
	}

	/**
	 * Processes subscriptions one at a time.
	 *
	 * @since 3.37.25
	 *
	 * @param int $order_id The order ID.
	 */
	public function batch_step_meta( $order_id ) {

		$order        = new \Upsell\Entities\Order( $order_id );
		$subscription = \Upsell\Entities\Subscription::find( $order->subscription_id );

		// $this->sync_contact( $order, array(), 'data_only' ); TODO
	}


}

new WPF_Upsell();
