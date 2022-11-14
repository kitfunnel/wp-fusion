<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Give extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'give';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Give';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/give/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.19
	 * @return  void
	 */

	public function init() {

		add_action( 'give_insert_payment', array( $this, 'insert_payment' ), 10, 2 );
		add_action( 'give_update_payment_status', array( $this, 'update_status' ), 105, 3 ); // 105 so it's after give_complete_purchase.

		// Payment status
		add_action( 'give_view_donation_details_update_after', array( $this, 'order_details_sidebar' ), 20 );
		add_action( 'give_wpf_process', array( $this, 'process_order_again' ) );

		// Recurring
		add_action( 'give_subscription_cancelled', array( $this, 'subscription_cancelled' ), 10, 2 );
		add_action( 'give_subscription_failing', array( $this, 'subscription_failed' ), 10, 2 );

		// Custom Fields
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

		// Settings
		add_filter( 'give_metabox_form_data_settings', array( $this, 'add_settings' ), 20 );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ) );

		// Settings
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

		// Batch operations
		add_filter( 'wpf_export_options', array( $this, 'export_options' ), 12 ); // 12 so we can match it with the ones added by the Ecom addon

		// Donors
		add_action( 'wpf_batch_give_donors_init', array( $this, 'batch_init_donors' ) );
		add_action( 'wpf_batch_give_donors', array( $this, 'batch_step_donors' ) );

		// Donations
		add_action( 'wpf_batch_give_donations_init', array( $this, 'batch_init_donations' ) );
		add_action( 'wpf_batch_give_donations', array( $this, 'batch_step_donations' ), 10, 2 );

		// Optin field
		add_action( 'give_donation_form_after_email', array( $this, 'add_optin_field' ), 10, 1 );
	}

	/**
	 * Send data to CRM and apply tags on payment insert
	 *
	 * @access  public
	 * @return  void
	 */

	public function insert_payment( $payment_id, $payment_data = array(), $status = false ) {

		// Save the email optin, if present. This happens regardless of if the payment is being synced to the CRM now, so we can use it later if needed.

		if ( isset( $_POST['give_email_optin'] ) ) {
			$give_email_optin = filter_var( $_POST['give_email_optin'], FILTER_VALIDATE_BOOLEAN );
			give_update_payment_meta( $payment_id, 'give_email_optin', $give_email_optin );
		}

		// Get the payment data from the payment ID if empty (for example during a batch export)

		if ( empty( $payment_data ) ) {
			$payment_data = $this->get_payment_data( $payment_id );
		}

		if ( false !== $status ) { // if we're overriding the status.
			$payment_data['status'] = $status;
		}

		$settings = get_post_meta( $payment_data['give_form_id'], 'wpf_settings_give', true );

		if ( empty( $settings ) || 'enabled' != $settings['enabled'] ) {
			return;
		}

		// Only run on successful payments

		if ( 'publish' != $payment_data['status'] && 'give_subscription' != $payment_data['status'] ) {
			return;
		}

		wpf_log( 'info', $payment_data['user_info']['id'], 'New Give donation <a href="' . admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-payment-details&id=' . $payment_id ) . '">#' . $payment_id . '</a>' );

		// Create / update the contact

		$contact_id = $this->create_update_donor( $payment_data['user_info']['donor_id'] );

		if ( is_wp_error( $contact_id ) ) {

			wpf_log( 'error', $payment_data['user_info']['id'], 'Error syncing Give donor: ' . $contact_id->get_error_message() );
			return;
		}

		// Apply the tags

		$this->apply_payment_tags( $payment_id, $contact_id, $payment_data['status'] );

		// Save the contact ID for future operations

		give_update_meta( $payment_id, '_' . WPF_CONTACT_ID_META_KEY, $contact_id );

		// Trigger the ecommerce addon

		give_update_meta( $payment_id, '_wpf_complete', current_time( 'Y-m-d H:i:s' ) );

		do_action( 'wpf_give_payment_complete', $payment_id, $contact_id, $payment_data );

	}

	/**
	 * Maybe trigger payment complete actions when status updated
	 *
	 * @access  public
	 * @return  void
	 */

	public function update_status( $payment_id, $status, $old_status = false ) {

		if ( 'publish' == $status || 'give_subscription' == $status ) {

			$this->insert_payment( $payment_id, array(), $status );

		}

	}

	/**
	 * Gets payment data array from a payment ID
	 *
	 * @access  public
	 * @return  void
	 */

	public function get_payment_data( $payment_id ) {

		$payment = new Give_Payment( $payment_id );

		$payment_data = array(
			'give_form_id'  => $payment->form_id,
			'give_price_id' => $payment->price_id,
			'status'        => $payment->status,
			'user_email'    => $payment->email,
			'user_info'     => array(
				'id'         => $payment->user_id,
				'first_name' => $payment->first_name,
				'last_name'  => $payment->last_name,
				'email'      => $payment->email,
				'donor_id'   => $payment->customer_id,
			),
			'price'         => $payment->subtotal,
			'currency'      => $payment->currency,
			'date'          => $payment->date,
		);

		return $payment_data;

	}

	/**
	 * Syncs a donor to the CRM
	 *
	 * @access  public
	 * @return  bool / int Contact ID
	 */

	public function create_update_donor( $donor_id ) {

		$donor = new Give_Donor( $donor_id );

		$update_data = array(
			'user_email'      => $donor->email,
			'first_name'      => $donor->get_first_name(),
			'last_name'       => $donor->get_last_name(),
			'donations_count' => $donor->purchase_count,
			'total_donated'   => round( $donor->purchase_value, 2 ),
		);

		$last_donation = $donor->get_last_donation();

		$payment = new Give_Payment( $last_donation );

		// Add address
		$update_data = array_merge( $update_data, $payment->address );

		// Get custom fields from last donation (includes give_email_optin)

		$payment_meta = give_get_meta( $last_donation );

		if ( ! empty( $payment_meta ) ) {

			foreach ( $payment_meta as $key => $value ) {

				// Skip internal fields

				if ( strpos( $key, '_' ) !== 0 ) {
					$update_data[ $key ] = $value[0];
				}
			}
		}

		// Funds

		if ( class_exists( 'GiveFunds\Repositories\Funds' ) ) {

			$revenue = new GiveFunds\Repositories\Revenue();
			$fund_id = $revenue->getDonationFundId( $last_donation );

			if ( $fund_id ) {

				$fund_repository = give( GiveFunds\Repositories\Funds::class );
				$fund            = $fund_repository->getFund( $fund_id );

				if ( $fund ) {
					$update_data['fund'] = $fund->get( 'title' );
				}
			}
		}

		// Give gift addon.
		if ( class_exists( 'Give_Gift_Aid' ) ) {

			$country_name = '';
			$country_id   = $donor->get_meta( '_give_gift_aid_country', true );
			if ( ! empty( $country_id ) ) {
				$country_name = give_get_country_name_by_key( $country_id );
			}

			$give_gift_data = array(
				'give_gift_aid_country'        => $country_name,
				'give_gift_aid_address_line_1' => $donor->get_meta( '_give_gift_aid_card_address', true ),
				'give_gift_aid_address_line_2' => $donor->get_meta( '_give_gift_aid_card_address_2', true ),
				'give_gift_aid_city'           => $donor->get_meta( '_give_gift_aid_card_city', true ),
				'give_gift_aid_state'          => $donor->get_meta( '_give_gift_aid_card_state', true ),
				'give_gift_aid_postal_code'    => $donor->get_meta( '_give_gift_aid_card_zip', true ),

			);
			$update_data = array_merge( $update_data, $give_gift_data );
		}

		$dynamic_tags = $this->get_dynamic_tags( $update_data );

		if ( ! empty( $dynamic_tags ) ) {
			// Add it to the filter so it's included by apply_payment_tags()

			add_filter(
				'wpf_give_apply_tags',
				function( $apply_tags ) use ( &$dynamic_tags ) {
					return array_merge( $apply_tags, $dynamic_tags );
				}
			);

		}

		if ( ! empty( $donor->user_id ) ) {

			// Registered users

			wp_fusion()->user->push_user_meta( $donor->user_id, $update_data );

			$contact_id = wp_fusion()->user->get_contact_id( $donor->user_id );

			return $contact_id;

		} else {

			$contact_id = wp_fusion()->crm->get_contact_id( $donor->email );

			// Guests

			wpf_log(
				'info',
				0,
				'Syncing Give guest donor:',
				array(
					'meta_array_nofilter' => $update_data,
					'source'              => 'give',
				)
			);

			if ( ! is_wp_error( $contact_id ) && empty( $contact_id ) ) {

				// Add new contact
				$contact_id = wp_fusion()->crm->add_contact( $update_data );

				if ( is_wp_error( $contact_id ) ) {

					wpf_log( $contact_id->get_error_code(), 0, 'Error adding contact to ' . wp_fusion()->crm->name . ': ' . $contact_id->get_error_message() );
					return false;

				}
			} elseif ( ! is_wp_error( $contact_id ) && ! empty( $contact_id ) ) {

				wp_fusion()->crm->update_contact( $contact_id, $update_data );

			}

			return $contact_id;

		}

	}

	/**
	 * Applies tags in the CRM based on a payment and/or subscription status
	 *
	 * @access  public
	 * @return  bool / int Contact ID
	 */

	public function apply_payment_tags( $payment_id, $contact_id, $status ) {

		$payment = new Give_Payment( $payment_id );

		$settings = get_post_meta( $payment->form_id, 'wpf_settings_give', true );

		if ( empty( $settings ) ) {
			return;
		}

		$apply_tags = array();

		if ( ! empty( $settings['apply_tags'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags'] );
		}

		if ( ! empty( $settings['apply_tags_level'][ $payment->price_id ] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_level'][ $payment->price_id ] );
		}

		// Maybe get recurring tags

		if ( class_exists( 'Give_Recurring' ) ) {

			$subscriber = new Give_Recurring_Subscriber( $payment->email );

			if ( $subscriber->has_subscription( $payment->form_id ) ) {

				if ( ! empty( $settings['apply_tags_recurring'] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_recurring'] );
				}

				// Maybe also apply cancelled tags if the most recent subscription to this form is cancelled

				$subscriptions = $subscriber->get_subscriptions( $payment->form_id );

				if ( 'cancelled' == $subscriptions[0]->status && ! empty( $settings['apply_tags_cancelled'] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_cancelled'] );
				}
			}
		}

		// Funds

		if ( class_exists( 'GiveFunds\Repositories\Funds' ) ) {

			$revenue = new GiveFunds\Repositories\Revenue();
			$fund_id = $revenue->getDonationFundId( $payment_id );

			if ( $fund_id ) {

				$fund_tags = wpf_get_option( 'give_fund_tags_' . $fund_id );

				if ( $fund_tags ) {
					$apply_tags = array_merge( $apply_tags, $fund_tags );
				}
			}
		}

		// Email optin
		if ( wpf_get_option( 'give_email_optin' ) ) {
			$apply_tags = array_merge( $apply_tags, wpf_get_option( 'give_email_optin_tags', array() ) );
		}

		// Remove duplicates and empties
		$apply_tags = array_filter( array_unique( $apply_tags ) );
		$apply_tags = apply_filters( 'wpf_give_apply_tags', $apply_tags, $payment_id );

		// Apply the tags. We don't need to re-apply the same tags if it's a renewal payment, so this will just run on Publish

		if ( ! empty( $payment->user_id ) && 'publish' === $status ) {

			// Registered users

			wp_fusion()->user->apply_tags( $apply_tags, $payment->user_id );

		} elseif ( ! empty( $contact_id ) && 'publish' === $status ) {

			// Guests

			wpf_log(
				'info',
				0,
				'Applying tags to guest donor: ',
				array(
					'tag_array' => $apply_tags,
					'source'    => 'give',
				)
			);

			wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

		}

	}

	/**
	 * Apply tags when a payment/subscription has failed.
	 *
	 * @param integer $subscription_id
	 * @param object  $subscription
	 */
	public function subscription_failed( $subscription_id, $subscription ) {

		$settings = get_post_meta( $subscription->form_id, 'wpf_settings_give', true );

		if ( empty( $settings ) || empty( $settings['apply_tags_failed'] ) ) {
			return;
		}

		if ( ! empty( $subscription->donor->user_id ) ) {

			wpf_log( 'info', $subscription->donor->user_id, 'Give subscription <a href="' . admin_url( "edit.php?post_type=give_forms&page=give-subscriptions&id={$subscription_id}" ) . '">#' . $subscription_id . '</a> failing.' );

			wp_fusion()->user->apply_tags( $settings['apply_tags_failed'], $subscription->donor->user_id );

		} else {

			$contact_id = wp_fusion()->crm->get_contact_id( $subscription->donor->email );

			if ( ! empty( $contact_id ) && ! is_wp_error( $contact_id ) ) {

				wpf_log( 'info', 0, 'Give subscription <a href="' . admin_url( "edit.php?post_type=give_forms&page=give-subscriptions&id={$subscription_id}" ) . '">#' . $subscription_id . '</a> failing. Applying tags: ', array( 'tag_array' => $settings['apply_tags_failed'] ) );

				wp_fusion()->crm->apply_tags( $settings['apply_tags_failed'], $contact_id );

			}
		}

	}


	/**
	 * Maybe trigger payment complete actions when status updated
	 *
	 * @access  public
	 * @return  void
	 */

	public function subscription_cancelled( $subscription_id, $subscription ) {

		$settings = get_post_meta( $subscription->form_id, 'wpf_settings_give', true );

		if ( empty( $settings ) || empty( $settings['apply_tags_cancelled'] ) ) {
			return;
		}

		if ( ! empty( $subscription->donor->user_id ) ) {

			wpf_log( 'info', $subscription->donor->user_id, 'Give subscription <a href="' . admin_url( "edit.php?post_type=give_forms&page=give-subscriptions&id={$subscription_id}" ) . '">#' . $subscription_id . '</a> cancelled.' );

			wp_fusion()->user->apply_tags( $settings['apply_tags_cancelled'], $subscription->donor->user_id );

		} else {

			$contact_id = wp_fusion()->crm->get_contact_id( $subscription->donor->email );

			if ( ! empty( $contact_id ) && ! is_wp_error( $contact_id ) ) {

				wpf_log( 'info', 0, 'Give subscription <a href="' . admin_url( "edit.php?post_type=give_forms&page=give-subscriptions&id={$subscription_id}" ) . '">#' . $subscription_id . '</a> cancelled. Applying tags: ', array( 'tag_array' => $settings['apply_tags_cancelled'] ) );

				wp_fusion()->crm->apply_tags( $settings['apply_tags_cancelled'], $contact_id );

			}
		}

	}


	/**
	 * Outputs WP Fusion details to the payment meta box.
	 *
	 * @since 3.36.8
	 *
	 * @param int $payment_id  The payment identifier.
	 */
	public function order_details_sidebar( $payment_id ) {

		$payment = new Give_Payment( $payment_id );

		?>

		</div></div> <?php // Close out the Upate Payment box ?>

		<div id="give-wpf-status" class="postbox give-wpf-status">

			<h3 class="hndle">
				<span><?php _e( 'WP Fusion', 'wp-fusion' ); ?></span>
			</h3>
			<div class="give-admin-box">

				<div class="give-order-wpf-status give-admin-box-inside">

					<p>
						<span class="label"><?php printf( __( 'Synced to %s:', 'wp-fusion' ), wp_fusion()->crm->name ); ?></span>&nbsp;

						<?php if ( give_get_meta( $payment_id, '_wpf_complete', true ) ) : ?>
							<span><?php _e( 'Yes', 'wp-fusion' ); ?></span>
							<span class="dashicons dashicons-yes-alt"></span>
						<?php else : ?>
							<span><?php _e( 'No', 'wp-fusion' ); ?></span>
							<span class="dashicons dashicons-no"></span>
						<?php endif; ?>
					</p>

				</div>

				<?php $contact_id = give_get_meta( $payment_id, '_' . WPF_CONTACT_ID_META_KEY, true ); ?>

				<?php if ( $contact_id ) : ?>

					<div class="give-order-wpf-status give-admin-box-inside">

						<p>

							<span class="label"><?php _e( 'Contact ID:', 'wp-fusion' ); ?></span>&nbsp;
							<span><?php echo $contact_id; ?></span>

							<?php $url = wp_fusion()->crm->get_contact_edit_url( $contact_id ); ?>
							<?php if ( false !== $url ) : ?>
								- <a href="<?php echo $url; ?>" target="_blank"><?php _e( 'View', 'wp-fusion' ); ?> &rarr;</a>
							<?php endif; ?>
						</p>

					</div>

				<?php endif; ?>

				<?php if ( class_exists( 'WP_Fusion_Ecommerce' ) ) : ?>

					<div class="give-order-wpf-status give-admin-box-inside">

						<p>
							<span class="label"><?php printf( __( 'Enhanced Ecommerce:', 'wp-fusion' ), wp_fusion()->crm->name ); ?></span>&nbsp;

							<?php if ( give_get_meta( $payment_id, '_wpf_ec_complete', true ) ) : ?>
								<span><?php _e( 'Yes', 'wp-fusion' ); ?></span>
								<span class="dashicons dashicons-yes-alt"></span>
							<?php else : ?>
								<span><?php _e( 'No', 'wp-fusion' ); ?></span>
								<span class="dashicons dashicons-no"></span>
							<?php endif; ?>
						</p>

					</div>

					<?php $invoice_id = give_get_meta( $payment_id, '_wpf_ec_' . wp_fusion()->crm->slug . '_invoice_id', true ); ?>

					<?php if ( $invoice_id ) : ?>

						<div class="give-order-wpf-status give-admin-box-inside">

							<p>
								<span class="label"><?php _e( 'Invoice ID:', 'wp-fusion' ); ?></span>&nbsp;
								<span><?php echo $invoice_id; ?></span>
							</p>

						</div>


					<?php endif; ?>

				<?php endif; ?>

				<div class="give-order-wpf-status give-admin-box-inside">

					<p>
						<a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'give-action' => 'wpf_process',
									'purchase_id' => $payment_id,
								)
							)
						);
						?>
									" class="button-secondary"><?php _e( 'Process WP Fusion actions again ', 'wp-fusion' ); ?> &raquo;</a>
					</p>

				</div>


		<?php

	}

	/**
	 * Re-processes a single payment.
	 *
	 * @since 3.36.8
	 *
	 * @param array $data   The data
	 */
	public function process_order_again( $data ) {

		$payment_id = absint( $data['purchase_id'] );

		if ( empty( $payment_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_give_payments' ) ) {
			wp_die( __( 'You do not have permission to edit payments.', 'give' ), __( 'Error', 'give' ), array( 'response' => 403 ) );
		}

		$payment = new Give_Payment( $payment_id );

		give_delete_meta( $payment_id, '_wpf_complete' );
		give_delete_meta( $payment_id, '_wpf_ec_complete' );

		add_filter( 'wpf_prevent_reapply_tags', '__return_false' ); // allow tags to be sent again despite the cache.

		$this->insert_payment( $payment_id );

	}


	/**
	 * Adds Give field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['give'] = array(
			'title'  => 'GiveWP',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Add Give fields
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function add_meta_fields( $meta_fields ) {

		$meta_fields['line1'] = array(
			'label' => 'Billing Address 1',
			'type'  => 'text',
			'group' => 'give',
		);

		$meta_fields['line2'] = array(
			'label' => 'Billing Address 2',
			'type'  => 'text',
			'group' => 'give',
		);

		$meta_fields['city'] = array(
			'label' => 'Billing City',
			'type'  => 'text',
			'group' => 'give',
		);

		$meta_fields['state'] = array(
			'label' => 'Billing State',
			'type'  => 'state',
			'group' => 'give',
		);

		$meta_fields['country'] = array(
			'label' => 'Billing Country',
			'type'  => 'country',
			'group' => 'give',
		);

		$meta_fields['zip'] = array(
			'label' => 'Billing Postcode',
			'type'  => 'text',
			'group' => 'give',
		);

		$meta_fields['donations_count'] = array(
			'label'  => 'Donations Count',
			'type'   => 'int',
			'group'  => 'give',
			'pseudo' => true,
		);

		$meta_fields['total_donated'] = array(
			'label'  => 'Total Donated',
			'type'   => 'text',
			'group'  => 'give',
			'pseudo' => true,
		);

		$meta_fields['give_email_optin'] = array(
			'label'  => 'Email Optin',
			'type'   => 'checkbox',
			'group'  => 'give',
			'pseudo' => true,
		);

		if ( class_exists( 'GiveFunds\Repositories\Funds' ) ) {

			// Funds

			$meta_fields['fund'] = array(
				'label'  => 'Selected Fund',
				'type'   => 'text',
				'group'  => 'give',
				'pseudo' => true,
			);

		}

		$forms_query = new Give_Forms_Query(
			array(
				'number'      => 30,
				'post_status' => 'publish',
			)
		);

		// Fetch the donation forms.
		$forms = $forms_query->get_forms();

		if ( ! empty( $forms ) ) {

			foreach ( $forms as $form ) {

				$fields = give_get_meta( $form->ID, 'give-form-fields', true, false, 'form' );

				if ( ! empty( $fields ) ) {

					foreach ( $fields as $field ) {

						$meta_fields[ $field['name'] ] = array(
							'label' => $field['label'],
							'type'  => $field['input_type'],
							'group' => 'give',
						);

					}
				}
			}
		}

		// Give gift addon.
		if ( class_exists( 'Give_Gift_Aid' ) ) {

			$meta_fields['give_gift_aid_country'] = array(
				'label'  => 'Gift Aid Country',
				'type'   => 'text',
				'group'  => 'give',
				'pseudo' => true,
			);

			$meta_fields['give_gift_aid_address_line_1'] = array(
				'label'  => 'Gift Aid Address Line 1',
				'type'   => 'text',
				'group'  => 'give',
				'pseudo' => true,
			);

			$meta_fields['give_gift_aid_address_line_2'] = array(
				'label'  => 'Gift Aid Address Line 2',
				'type'   => 'text',
				'group'  => 'give',
				'pseudo' => true,
			);

			$meta_fields['give_gift_aid_city'] = array(
				'label'  => 'Gift Aid City',
				'type'   => 'text',
				'group'  => 'give',
				'pseudo' => true,
			);

			$meta_fields['give_gift_aid_state'] = array(
				'label'  => 'Gift Aid State',
				'type'   => 'text',
				'group'  => 'give',
				'pseudo' => true,
			);

			$meta_fields['give_gift_aid_postal_code'] = array(
				'label'  => 'Gift Aid Postal Code',
				'type'   => 'text',
				'group'  => 'give',
				'pseudo' => true,
			);

		}

		return $meta_fields;

	}


	/**
	 * Add settings to admin form editor
	 *
	 * @access  public
	 * @return  array Settings
	 */

	public function add_settings( $settings ) {

		$fields = array(
			array(
				'name'    => __( 'Create Contacts', 'wp-fusion' ),
				'desc'    => sprintf( __( 'Create contacts in %s when donations are given?', 'give' ), wp_fusion()->crm->name ),
				'id'      => 'wpf_settings_give_enabled',
				'type'    => 'radio_inline',
				'default' => 'enabled',
				'options' => array(
					'enabled'  => __( 'Enabled', 'wp-fusion' ),
					'disabled' => __( 'Disabled', 'wp-fusion' ),
				),
			),
			array(
				'name'     => __( 'Apply Tags', 'wp-fusion' ),
				'desc'     => sprintf( __( 'Apply these tags in %s when a donation is given.', 'wp-fusion' ), wp_fusion()->crm->name ),
				'id'       => 'apply_tags',
				'type'     => 'select4',
				'callback' => array( $this, 'select_callback' ),
			),
		);

		if ( class_exists( 'Give_Recurring' ) ) {

			$fields[] = array(
				'name'        => __( 'Apply Tags - Recurring', 'wp-fusion' ),
				'desc'        => __( 'Apply these tags when a recurring donation is given (in addition to Apply Tags).', 'wp-fusion' ),
				'id'          => 'apply_tags_recurring',
				'type'        => 'select4',
				'callback'    => array( $this, 'select_callback' ),
				'row_classes' => 'give-recurring-row',
			);

			$fields[] = array(
				'name'        => __( 'Apply Tags - Cancelled', 'wp-fusion' ),
				'desc'        => __( 'Apply these tags when a recurring donation is cancelled.', 'wp-fusion' ),
				'id'          => 'apply_tags_cancelled',
				'type'        => 'select4',
				'callback'    => array( $this, 'select_callback' ),
				'row_classes' => 'give-recurring-row',
			);

			$fields[] = array(
				'name'        => __( 'Apply Tags - Failed', 'wp-fusion' ),
				'desc'        => __( 'Apply these tags when a recurring donation payment has failed.', 'wp-fusion' ),
				'id'          => 'apply_tags_failed',
				'type'        => 'select4',
				'callback'    => array( $this, 'select_callback' ),
				'row_classes' => 'give-recurring-row',
			);

		}

		$settings['wp_fusion'] = array(
			'id'        => 'wp_fusion',
			'title'     => 'WP Fusion',
			'icon-html' => '<span style="vertical-align: text-top;">' . wpf_logo_svg( 14 ) . '</span>',
			'fields'    => $fields,
		);

		// Add donation options settings
		foreach ( $settings['form_field_options']['fields'] as $i => $field ) {

			if ( isset( $field['id'] ) && $field['id'] == '_give_donation_levels' ) {

				$settings['form_field_options']['fields'][ $i ]['fields'][] = array(
					'name'     => __( 'Apply Tags', 'wp-fusion' ),
					'desc'     => sprintf( __( 'Apply these tags in %s when a donation is given at this level.', 'wp-fusion' ), wp_fusion()->crm->name ),
					'id'       => 'apply_tags',
					'type'     => 'select4',
					'callback' => array( $this, 'select_callback' ),
				);

			}
		}

		return $settings;

	}

	/**
	 * Render WPF select box
	 *
	 * @access  public
	 * @return  mixed HTML Output
	 */

	public function select_callback( $field ) {

		// Don't do it on placeholders
		if ( false !== strpos( $field['id'], '{{row-count-placeholder}}' ) ) {
			return;
		}

		global $post;

		$settings = get_post_meta( $post->ID, 'wpf_settings_give', true );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		$defaults = array(
			'apply_tags'           => array(),
			'apply_tags_recurring' => array(),
			'apply_tags_cancelled' => array(),
			'apply_tags_failed'    => array(),
			'apply_tags_level'     => array(),
		);

		$settings = array_merge( $defaults, $settings );

		$field['name'] = isset( $field['name'] ) ? $field['name'] : $field['id'];

		wp_nonce_field( 'wpf_meta_box_give', 'wpf_meta_box_give_nonce' );

		echo '<fieldset class="give-field-wrap ' . esc_attr( $field['id'] ) . '_field"><span class="give-field-label">' . wp_kses_post( $field['name'] ) . '</span><legend class="screen-reader-text">' . wp_kses_post( $field['name'] ) . '</legend>';

		if ( isset( $field['repeat'] ) ) {

			$field_sub_id = str_replace( '_give_donation_levels_', '', $field['id'] );
			$field_sub_id = str_replace( '_apply_tags', '', $field_sub_id );

			if ( ! isset( $settings['apply_tags_level'][ $field_sub_id ] ) ) {
				$settings['apply_tags_level'][ $field_sub_id ] = array();
			}

			$args = array(
				'setting'   => $settings['apply_tags_level'][ $field_sub_id ],
				'meta_name' => "wpf_settings_give[apply_tags_level][{$field_sub_id}]",
			);

			if ( ! isset( $args['setting'][ $field_sub_id ] ) ) {
				$args['setting'][ $field_sub_id ] = array();
			}
		} else {

			$args = array(
				'setting'   => $settings[ $field['id'] ],
				'meta_name' => 'wpf_settings_give',
				'field_id'  => $field['id'],
			);

		}

		wpf_render_tag_multiselect( $args );

		echo give_get_field_description( $field );
		echo '</fieldset>';

	}


	/**
	 * Saves WPF configuration to product
	 *
	 * @access public
	 * @return mixed
	 */

	public function save_meta_box_data( $post_id ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_give_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_give_nonce'], 'wpf_meta_box_give' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Don't update on revisions
		if ( $_POST['post_type'] == 'revision' ) {
			return;
		}

		if ( isset( $_POST['wpf_settings_give'] ) ) {
			$data = $_POST['wpf_settings_give'];
		} else {
			$data = array();
		}

		if ( isset( $_POST['wpf_settings_give_enabled'] ) ) {
			$data['enabled'] = $_POST['wpf_settings_give_enabled'];
		}

		// Update the meta field in the database.
		update_post_meta( $post_id, 'wpf_settings_give', $data );

	}

	/**
	 * Give global settings.
	 *
	 * @since  3.36.10
	 * @since  3.37.30 Added email option settings.
	 *
	 * @param  array $settings The settings.
	 * @param  array $options  The options.
	 * @return array The settings.
	 */
	public function register_settings( $settings, $options ) {

		$settings['give_header'] = array(
			'title'   => __( 'GiveWP Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['give_email_optin'] = array(
			'title'   => __( 'Email Optin', 'wp-fusion' ),
			'desc'    => __( 'Display a checkbox on the donation form where customers can opt-in to receive email marketing.', 'wp-fusion' ),
			'type'    => 'checkbox',
			'section' => 'integrations',
			'unlock'  => array( 'give_email_optin_message', 'give_email_optin_default', 'give_email_optin_tags' ),
		);

		$settings['give_email_optin_message'] = array(
			'title'       => __( 'Email Optin Message', 'wp-fusion' ),
			'placeholder' => __( 'I consent to receive marketing emails', 'wp-fusion' ),
			'type'        => 'text',
			'format'      => 'html',
			'section'     => 'integrations',
		);

		$settings['give_email_optin_default'] = array(
			'title'   => __( 'Email Optin Default', 'wp-fusion' ),
			'type'    => 'select',
			'std'     => 'checked',
			'choices' => array(
				'checked'   => __( 'Checked', 'wp-fusion' ),
				'unchecked' => __( 'Un-Checked', 'wp-fusion' ),
			),
			'section' => 'integrations',
		);

		$settings['give_email_optin_tags'] = array(
			'title'   => __( 'Email Optin Tags', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags to the donor when the email optin box is checked.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		if ( class_exists( 'GiveFunds\Repositories\Funds' ) ) {

			$settings['give_funds_header'] = array(
				'title'   => __( 'Give Funds Integration', 'wp-fusion' ),
				'type'    => 'heading',
				'section' => 'integrations',
				'desc'    => sprintf( __( 'Select tags to be applied in %s when a donation is given to each fund.', 'wp-fusion' ), wp_fusion()->crm->name ),
			);

			$fund_repository = give( GiveFunds\Repositories\Funds::class );
			$funds           = $fund_repository->getFunds();

			if ( $funds ) {

				foreach ( $funds as $fund ) {

					$settings[ 'give_fund_tags_' . $fund->get( 'id' ) ] = array(
						'title'   => $fund->get( 'title' ),
						'type'    => 'assign_tags',
						'section' => 'integrations',
					);

				}
			}
		}

		return $settings;

	}


	/**
	 * Add optin field in checkout.
	 *
	 * @since 3.37.30
	 *
	 * @param int $form_id The donation form ID.
	 * @return mixed HTML Output.
	 */
	function add_optin_field( $form_id ) {

		if ( ! wpf_get_option( 'give_email_optin' ) ) {
			return;
		}

		$settings = get_post_meta( $form_id, 'wpf_settings_give', true );

		if ( empty( $settings['enabled'] ) ) {
			return; // WPF isn't enabled on the form.
		}

		if ( 'unchecked' === wpf_get_option( 'give_email_optin_default' ) ) {
			$default = false;
		} else {
			$default = true;
		}

		$message = wpf_get_option( 'give_email_optin_message', __( 'I consent to receive marketing emails', 'wp-fusion' ) );

		if ( ! give_is_anonymous_donation_field_enabled( $form_id ) ) {

			// If the anonymous donation field isn't being displayed we can hijack its ID to get a nicer UI. Ugly but not sure what else to do.

			echo '
				<p id="give-anonymous-donation-wrap" class="form-row form-row-wide">
					<label class="give-label" for="give-email-optin">
						<input type="checkbox" class="give-input" name="give_email_optin" value="1" id="give-email-optin" ' . checked( 1, $default, false ) . '>
						' . esc_html( $message ) . '
					</label>
				</p>
			';

		} else {

			// If anonymous donation is enabled then we can't use the same ID or both checkboxes will become linked, so we'll use an ugly checkbox.

			echo '
				<p id="give-email-optin-wrap" class="form-row form-row-wide">
					<label for="give-email-optin">
						<input type="checkbox" style="opacity: 1 !important;position: relative !important;display: inline-block; left: 0px;" class="give-input" name="give_email_optin" value="1" id="give-email-optin" ' . checked( 1, $default, false ) . '>
						' . esc_html( $message ) . '
					</label>
				</p>
			';

		}

	}





	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds Woo Memberships option to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['give_donors'] = array(
			'label'   => __( 'Give donors', 'wp-fusion' ),
			'title'   => __( 'Donors', 'wp-fusion' ),
			'tooltip' => __( 'Creates / updates contact records for all Give donors, including the donor address, Donations Count and Total Donated fields. Does not modify any tags.', 'wp-fusion' ),
		);

		$options['give_donations'] = array(
			'label'         => __( 'Give donations', 'wp-fusion' ),
			'process_again' => true,
			'title'         => __( 'Donations', 'wp-fusion' ),
			'tooltip'       => __( 'Processes all Give donations with a status of Complete that haven\'t yet been processed by WP Fusion. Creates / updates contact records for donors, and applies tags based on the payment form used and subscription status.', 'wp-fusion' ),
		);

		return $options;

	}

	/**
	 * Gets all the donors to be processed
	 *
	 * @access public
	 * @return array Donor IDs
	 */

	public function batch_init_donors() {

		$donors = Give()->donors->get_donors(
			array(
				'number' => - 1,
				'fields' => array( 'id' ),
			)
		);

		$donor_ids = array();

		if ( ! empty( $donors ) ) {

			foreach ( $donors as $donor ) {
				$donor_ids[] = $donor->id;
			}
		}

		return $donor_ids;

	}

	/**
	 * Processes donor actions in batches
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step_donors( $donor_id ) {

		$this->create_update_donor( $donor_id );

	}

	/**
	 * Gets all the donations to be processed
	 *
	 * @access public
	 * @return array Payment IDs
	 */

	public function batch_init_donations( $args ) {

		$query_args = array(
			'number' => -1,
			'fields' => 'ids',
			'status' => array( 'publish', 'give_subscription' ),
			'order'  => 'ASC',
		);

		if ( ! empty( $args['skip_processed'] ) ) {
			$query_args['meta_query'] = array(
				array(
					'key'     => '_wpf_complete',
					'compare' => 'NOT EXISTS',
				),
			);
		}

		$donation_ids = give_get_payments( $query_args );

		return $donation_ids;

	}

	/**
	 * Processes donor actions in batches
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step_donations( $payment_id ) {

		$this->insert_payment( $payment_id );

	}

}

new WPF_Give();
