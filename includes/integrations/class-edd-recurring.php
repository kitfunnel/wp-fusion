<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_EDD_Recurring extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'edd-recurring';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'EDD Recurring Payments';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/edd-recurring-payments/';

	/**
	 * Get things started
	 *
	 * @access public
	 * @return void
	 */

	public function init() {

		// Add additional meta fields.
		add_action( 'wpf_edd_meta_box', array( $this, 'meta_box_content' ), 10, 2 );
		add_action( 'edd_download_price_table_row', array( $this, 'variable_meta_box_content' ), 10, 3 );
		add_action( 'save_post', array( $this, 'save_crm_fields_data' ) );

		add_filter( 'wpf_edd_apply_tags_checkout', array( $this, 'apply_trial_tags' ), 10, 2 );

		// Subscription status triggers.
		add_action( 'edd_subscription_status_change', array( $this, 'subscription_status_change' ), 10, 3 );
		add_action( 'edd_subscription_post_create', array( $this, 'sync_subscription_fields' ) );

		// Remove tags from cancelled + expired subs. 40 so it runs after EDD_Recurring_Cron::check_for_expired_subscriptions at 20.
		add_action( 'edd_recurring_daily_scheduled_events', array( $this, 'check_for_cancelled_expired_subscriptions' ), 40 );

		// Upgrades. 5 so it runs before "handle_subscription_upgrade" in EDD_Recurring_Software_Licensing.
		add_action( 'edd_recurring_post_create_payment_profiles', array( $this, 'maybe_doing_upgrade' ), 5 );

		// Cancellation survey.
		add_filter( 'edd_update_payment_meta__edd_cancellation_reason', array( $this, 'sync_cancellation_reason' ), 10, 2 );

		// Meta fields.
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 50 );

		// Export functions.
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_edd_recurring_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_edd_recurring_meta_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_edd_recurring', array( $this, 'batch_step' ) );
		add_action( 'wpf_batch_edd_recurring_meta', array( $this, 'batch_step_meta' ) );

	}

	/**
	 * Sync subscription fields.
	 *
	 * @since 3.41.8
	 * @param int $subscription_id The subscription ID.
	 */
	public function sync_subscription_fields( $subscription_id ) {

		if ( intval( $subscription_id ) === 0 ) {
			return;
		}

		$subscription = new EDD_Subscription( $subscription_id );

		if ( ! $subscription ) {
			return;
		}

		$download_id = $subscription->product_id;
		$update_data = array(
			'edd_sub_id'             => $subscription->id,
			'edd_sub_status'         => $subscription->status,
			'edd_sub_download_name'  => get_the_title( $download_id ),
			'edd_sub_start_date'     => $subscription->created,
			'edd_sub_end_date'       => $subscription->expiration,
			'edd_sub_trial_end_date' => $subscription->trial_period,
			'edd_sub_renewal_date'   => $subscription->expiration,
		);

		// Specific download fields.
		$download_data = array(
			'edd_sub_id_' . $download_id             => $subscription->id,
			'edd_sub_status_' . $download_id         => $subscription->status,
			'edd_sub_download_name_' . $download_id  => get_the_title( $download_id ),
			'edd_sub_start_date_' . $download_id     => $subscription->created,
			'edd_sub_end_date_' . $download_id       => $subscription->expiration,
			'edd_sub_trial_end_date_' . $download_id => $subscription->trial_period,
			'edd_sub_renewal_date_' . $download_id   => $subscription->expiration,
		);

		$update_data = array_merge( $update_data, $download_data );

		wp_fusion()->user->push_user_meta( $subscription->customer->user_id, $update_data );

	}

	/**
	 * Determines if a product or variable price option is a recurring charge
	 *
	 * @access  public
	 * @return  bool
	 */

	private function is_recurring( $download_id ) {

		if ( EDD_Recurring()->is_recurring( $download_id ) ) {
			return true;
		}

		if ( edd_has_variable_prices( $download_id ) ) {

			$prices = edd_get_variable_prices( $download_id );

			foreach ( $prices as $price_id => $price ) {
				if ( EDD_Recurring()->is_price_recurring( $download_id, $price_id ) ) {
					return true;
				}
			}
		}

		return false;

	}

	/**
	 * Applies tags for free trials when a new EDD payment is created with a
	 * product that has a trial.
	 *
	 * @since  3.38.46
	 *
	 * @param  array       $apply_tags The tags to apply.
	 * @param  EDD_Payment $payment    The payment.
	 * @return array       The tags to apply.
	 */
	public function apply_trial_tags( $apply_tags, $payment ) {

		if ( doing_action( 'edd_complete_purchase' ) || doing_action( 'wpf_edd_async_checkout' ) ) {

			// Subsequent changes to the subscription status are handled by
			// subscription_status_change() so we only need to do this at
			// checkout.

			foreach ( $payment->downloads as $download ) {

				if ( edd_recurring()->has_free_trial( $download['id'] ) ) {

					$settings = get_post_meta( $download['id'], 'wpf-settings-edd', true );

					if ( ! empty( $settings ) && ! empty( $settings['apply_tags_trialling'] ) ) {

						$apply_tags = array_merge( $apply_tags, $settings['apply_tags_trialling'] );

					}
				}
			}
		}

		return $apply_tags;

	}

	/**
	 * Triggered when a subscription status changes
	 *
	 * @access  public
	 * @return  void
	 */

	public function subscription_status_change( $old_status, $status, $subscription ) {

		if ( ! $this->is_recurring( $subscription->product_id ) ) {
			return;
		}

		if ( $old_status === $status ) {
			return; // No change, nothing to do.
		}

		if ( doing_action( 'edd_subscription_status_change' ) ) {
			// only sync the fields during an actual status change, not when running the exporter.
			$this->sync_subscription_fields( $subscription->id );
		}

		if ( 'cancelled' === $status && defined( 'WPF_EDD_DOING_UPGRADE' ) ) {
			return;
		}

		wpf_log( 'info', $subscription->customer->user_id, 'EDD subscription <a href="' . admin_url( 'edit.php?post_type=download&page=edd-subscriptions&id=' . $subscription->id ) . '" target="_blank">#' . $subscription->id . '</a> status changed from <strong>' . ucwords( $old_status ) . '</strong> to <strong>' . ucwords( $status ) . '</strong>.' );

		$defaults = array(
			'remove_tags'          => false,
			'apply_tags_completed' => array(),
			'apply_tags_trialling' => array(),
			'apply_tags_converted' => array(),
			'apply_tags_failing'   => array(),
			'apply_tags_expired'   => array(),
			'apply_tags_cancelled' => array(),
		);

		$settings = wp_parse_args( get_post_meta( $subscription->product_id, 'wpf-settings-edd', true ), $defaults );

		if ( empty( $settings ) ) {

			// No settings, nothing to do.
			return;
		}

		$remove_tags = array();
		$apply_tags  = array();

		// Remove tags if option is selected.

		if ( ! $subscription->is_active() && $settings['remove_tags'] ) {
			$remove_tags = array_merge( $remove_tags, $settings['apply_tags'] );
		}

		// Apply the tags for the new status.

		if ( ! empty( $settings[ 'apply_tags_' . $status ] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings[ "apply_tags_{$status}" ] );
		}

		// Maybe get tags from price ID.

		if ( ! empty( $subscription->price_id ) ) {

			// Remove price ID tags if applicable.

			if ( $settings['remove_tags'] && 'active' !== $status ) {

				if ( ! empty( $settings['apply_tags_price'][ $subscription->price_id ] ) ) {
					$remove_tags = array_merge( $remove_tags, $settings['apply_tags_price'][ $subscription->price_id ] );
				}
			}

			// If we're applying tags for the status set on the price ID.

			if ( ! empty( $settings[ "apply_tags_{$status}_price" ] ) && ! empty( $settings[ "apply_tags_{$status}_price" ][ $subscription->price_id ] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings[ "apply_tags_{$status}_price" ][ $subscription->price_id ] );
			}
		}

		// Converted to paid.

		if ( 'active' === $status && ! empty( $subscription->trial_period ) && ! empty( $settings['apply_tags_converted'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_converted'] );
		}

		// Possibly remove any of the other status tags if a subscription has come back to active.

		if ( 'active' === $status && 'pending' !== $old_status ) {

			$remove_tags_keys = array( 'completed', 'expired', 'failing', 'cancelled' );

			foreach ( $remove_tags_keys as $key ) {

				if ( ! empty( $settings[ "apply_tags_{$key}" ] ) ) {
					$remove_tags = array_merge( $remove_tags, $settings[ "apply_tags_{$key}" ] );
				}

				// And maybe from the price ID as well.

				if ( ! empty( $settings[ "apply_tags_{$key}_price" ] ) && ! empty( $settings[ "apply_tags_{$key}_price" ][ $subscription->price_id ] ) ) {
					$remove_tags = array_merge( $remove_tags, $settings[ "apply_tags_{$key}_price" ][ $subscription->price_id ] );
				}
			}

			// Re-apply active tags.

			if ( ! empty( $settings['apply_tags'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags'] );
			}

			// Re-apply tags for variations.

			if ( ! empty( $settings['apply_tags_price'] ) && ! empty( $settings['apply_tags_price'][ $subscription->price_id ] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_price'][ $subscription->price_id ] );
			}
		}

		// If there's nothing to be done, don't bother logging it.

		if ( empty( $apply_tags ) && empty( $remove_tags ) ) {
			return true;
		}

		$apply_tags  = array_unique( $apply_tags );
		$remove_tags = array_unique( $remove_tags );

		if ( ! empty( $remove_tags ) ) {
			wp_fusion()->user->remove_tags( $remove_tags, $subscription->customer->user_id );
		}

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $subscription->customer->user_id );
		}

	}

	/**
	 * Runs on the edd_recurring_daily_scheduled_events cron and checks for cancelled +
	 * expired subscriptions and removes tags where applicable.
	 *
	 * @since 3.41.6
	 */
	public function check_for_cancelled_expired_subscriptions() {

		$args = array(
			'status'     => 'cancelled',
			'number'     => 999999,
			'expiration' => array(
				'start' => date( 'Y-n-d 00:00:00', strtotime( '-1 day', current_time( 'timestamp' ) ) ),
				'end'   => date( 'Y-n-d 23:59:59', strtotime( '-1 day', current_time( 'timestamp' ) ) ),
			),
		);

		$db   = new EDD_Subscriptions_DB();
		$subs = $db->get_subscriptions( $args );

		if ( empty( $subs ) ) {
			return;
		}

		foreach ( $subs as $sub ) {

			$settings = wp_parse_args( get_post_meta( $sub->product_id, 'wpf-settings-edd', true ), array() );

			if ( ! empty( $settings['remove_tags'] ) || ! empty( $settings['apply_tags_expired'] ) ) {

				wpf_log( 'info', $sub->customer->user_id, 'Cancelled subscription <a href="' . admin_url( 'edit.php?post_type=download&page=edd-subscriptions&id=' . $sub->id ) . '" target="_blank">#' . $sub->id . '</a> is now expired.' );

				if ( ! empty( $settings['remove_tags'] ) ) {
					wp_fusion()->user->remove_tags( $settings['apply_tags'], $sub->customer->user_id );
				}

				if ( ! empty( $settings['apply_tags_expired'] ) ) {
					wp_fusion()->user->apply_tags( $settings['apply_tags_expired'], $sub->customer->user_id );
				}
			}
		}

	}

	/**
	 * We don't want to apply cancelled tags if the subscription is being cancelled due to an upgrade, so this will check that
	 *
	 * @access  public
	 * @return  void
	 */

	public function maybe_doing_upgrade( EDD_Recurring_Gateway $gateway_data ) {

		foreach ( $gateway_data->subscriptions as $subscription ) {

			if ( ! empty( $subscription['is_upgrade'] ) && ! empty( $subscription['old_subscription_id'] ) ) {
				define( 'WPF_EDD_DOING_UPGRADE', true );
				return;
			}
		}

	}

	/**
	 * Syncs the EDD cancellation reason.
	 *
	 * @since 3.42.1
	 *
	 * @param string $meta_value The cancellation reason.
	 * @param int    $payment_id The payment ID.
	 */
	public function sync_cancellation_reason( $meta_value, $payment_id ) {

		$payment = new EDD_Payment( $payment_id );

		wp_fusion()->user->push_user_meta( $payment->user_id, array( '_edd_cancellation_reason' => $meta_value ) );

		return $meta_value;

	}

	/**
	 * Outputs fields to EDD meta box
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_content( $post, $settings ) {

		$defaults = array(
			'remove_tags'          => false,
			'apply_tags_completed' => array(),
			'apply_tags_trialling' => array(),
			'apply_tags_converted' => array(),
			'apply_tags_failing'   => array(),
			'apply_tags_expired'   => array(),
			'apply_tags_cancelled' => array(),
		);

		$settings = wp_parse_args( $settings, $defaults );

		echo '<hr />';

		echo '<table class="form-table wpf-edd-recurring-options' . ( $this->is_recurring( $post->ID ) == true ? '' : ' hidden' ) . '"><tbody>';

		// Remove tags.

		echo '<tr>';

		echo '<th scope="row"><label for="remove_tags">' . __( 'Remove Tags', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';
		echo '<input class="checkbox" type="checkbox" id="remove_tags" name="wpf-settings-edd[remove_tags]" value="1" ' . checked( $settings['remove_tags'], 1, false ) . ' />';
		echo '<span class="description">' . __( 'Remove tags when the subscription is completed, is cancelled (and is past the next scheduled renewal date), or expires.', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		// Trials.

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_trialling">' . __( 'Subscription in Trial', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_trialling'],
				'meta_name' => 'wpf-settings-edd',
				'field_id'  => 'apply_tags_trialling',
			)
		);
		echo '<span class="description">' . __( 'Apply these tags when a subscription is created in trial status.', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		// Trials.

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_trialling">' . __( 'Trial Converted', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_converted'],
				'meta_name' => 'wpf-settings-edd',
				'field_id'  => 'apply_tags_converted',
			)
		);
		echo '<span class="description">' . __( 'Apply these tags when a subscription in trial status converts to a paid subscription.', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		// Completed.

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_completed">' . __( 'Subscription Completed', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_completed'],
				'meta_name' => 'wpf-settings-edd',
				'field_id'  => 'apply_tags_completed',
			)
		);
		echo '<span class="description">' . __( 'Apply these tags when a subscription is complete (number of payments matches the Times field).', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		// Failing.

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_expired">' . __( 'Subscription Failing', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_failing'],
				'meta_name' => 'wpf-settings-edd',
				'field_id'  => 'apply_tags_failing',
			)
		);
		echo '<span class="description">' . __( 'Apply these tags when a subscription has a failed payment.', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		// Expired.

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_expired">' . __( 'Subscription Expired', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_expired'],
				'meta_name' => 'wpf-settings-edd',
				'field_id'  => 'apply_tags_expired',
			)
		);
		echo '<span class="description">' . __( 'Apply these tags when a subscription has multiple failed payments, is marked Expired, or when a cancelled subscription reaches its expiration date.', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		// Cancelled.

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_cancelled">' . __( 'Subscription Cancelled', 'wp-fusion' ) . ':</label></th>';
		echo '<td>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_cancelled'],
				'meta_name' => 'wpf-settings-edd',
				'field_id'  => 'apply_tags_cancelled',
			)
		);
		echo '<span class="description">' . __( 'Apply these tags when a subscription is cancelled (immediately).', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

		echo '<hr>';

		// CRM Fields.
		echo '<h3><strong>' . esc_html__( 'Recurring Payments', 'wp-fusion' ) . '</strong></h3>';

		echo '<table class="form-table wpf-edd-recurring-options"><tbody>';

		$crm_fields = $this->get_subscription_meta_fields();

		$fields = wp_fusion()->settings->get( 'contact_fields' );

		foreach ( $crm_fields as $key => $value ) {

			$id = $key . '_' . $post->ID;

			echo '<tr>';

			echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $value['name'] ) . ':</label></th>';
			echo '<td>';
			wpf_render_crm_field_select(
				isset( $fields[ $id ] ) ? $fields[ $id ]['crm_field'] : false,
				'wpf_settings_edd_crm_fields',
				$id
			);
			echo '<input type="hidden" name="wpf_settings_edd_crm_fields[' . esc_attr( $id ) . '][type]" value="' . esc_attr( $value['type'] ) . '" />';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<hr>';

	}

	/**
	 * Saves CRM fields data in single subscription download.
	 *
	 * @since 3.41.8
	 */
	public function save_crm_fields_data() {

		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_edd_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_edd_nonce'], 'wpf_meta_box_edd' ) ) {
			return;
		}

		$data = wpf_clean( wp_unslash( $_POST['wpf_settings_edd_crm_fields'] ) );

		if ( empty( $data ) ) {
			return;
		}

		// Save any CRM fields to the field mapping.
		$contact_fields = wpf_get_option( 'contact_fields', array() );

		foreach ( $data as $key => $value ) {

			if ( ! empty( $value['crm_field'] ) ) {

				$contact_fields[ $key ]['crm_field'] = $value['crm_field'];
				$contact_fields[ $key ]['type']      = $value['type'];
				$contact_fields[ $key ]['active']    = true;

			} elseif ( isset( $contact_fields[ $key ] ) ) {

				// If the setting has been removed we can un-list it from the main Contact Fields list.
				unset( $contact_fields[ $key ] );
			}
		}

		wp_fusion()->settings->set( 'contact_fields', $contact_fields );

	}


	/**
	 * //
	 * // OUTPUTS EDD METABOXES
	 * //
	 *
	 *  @access public
	 *  @return mixed
	 **/

	public function variable_meta_box_content( $post_id, $key, $args ) {

		$settings = get_post_meta( $post_id, 'wpf-settings-edd', true );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		$defaults = array(
			'apply_tags_completed_price' => array(),
			'apply_tags_trialling_price' => array(),
			'apply_tags_failing_price'   => array(),
			'apply_tags_expired_price'   => array(),
			'apply_tags_cancelled_price' => array(),
		);

		$settings = array_merge( $defaults, $settings );

		if ( empty( $settings['apply_tags_completed_price'][ $key ] ) ) {
			$settings['apply_tags_completed_price'][ $key ] = array();
		}
		if ( empty( $settings['apply_tags_trialling_price'][ $key ] ) ) {
			$settings['apply_tags_trialling_price'][ $key ] = array();
		}
		if ( empty( $settings['apply_tags_failing_price'][ $key ] ) ) {
			$settings['apply_tags_failing_price'][ $key ] = array();
		}
		if ( empty( $settings['apply_tags_expired_price'][ $key ] ) ) {
			$settings['apply_tags_expired_price'][ $key ] = array();
		}
		if ( empty( $settings['apply_tags_cancelled_price'][ $key ] ) ) {
			$settings['apply_tags_cancelled_price'][ $key ] = array();
		}

		$variable_price = edd_get_variable_prices( $post_id );

		$recurring = false;

		if ( ! empty( $variable_price[ $key ]['recurring'] ) && $variable_price[ $key ]['recurring'] == 'yes' ) {
			$recurring = true;
		}

		echo '<div class="wpf-edd-recurring-options' . ( $recurring == true ? '' : ' hidden' ) . '" style="' . ( $recurring == true ? '' : 'display: none;' ) . '">';

		// trialling

		echo '<div style="display:inline-block; width:50%;margin-bottom:20px;">';
		echo '<label for="apply_tags_cancelled_price">' . __( 'Subscription In Trial', 'wp-fusion' ) . ':</label>';

		$args = array(
			'setting'   => $settings['apply_tags_trialling_price'][ $key ],
			'meta_name' => "wpf-settings-edd[apply_tags_trialling_price][{$key}]",
		);

		wpf_render_tag_multiselect( $args );

		echo '</div>';

		// Completed

		echo '<div style="display:inline-block; width:50%;margin-bottom:20px;">';
		echo '<label>' . __( 'Subscription Completed', 'wp-fusion' ) . ':</label>';

		$args = array(
			'setting'   => $settings['apply_tags_completed_price'][ $key ],
			'meta_name' => "wpf-settings-edd[apply_tags_completed_price][{$key}]",
		);

		wpf_render_tag_multiselect( $args );

		echo '</div>';

		// Failing

		echo '<div style="display:inline-block; width:50%;margin-bottom:20px;">';
		echo '<label for="apply_tags_expired_price">' . __( 'Subscription Failing', 'wp-fusion' ) . ':</label>';

		$args = array(
			'setting'   => $settings['apply_tags_failing_price'][ $key ],
			'meta_name' => "wpf-settings-edd[apply_tags_failing_price][{$key}]",
		);

		wpf_render_tag_multiselect( $args );

		echo '</div>';

		// Expired

		echo '<div style="display:inline-block; width:50%;margin-bottom:20px;">';
		echo '<label for="apply_tags_expired_price">' . __( 'Subscription Expired', 'wp-fusion' ) . ':</label>';

		$args = array(
			'setting'   => $settings['apply_tags_expired_price'][ $key ],
			'meta_name' => "wpf-settings-edd[apply_tags_expired_price][{$key}]",
		);

		wpf_render_tag_multiselect( $args );

		echo '</div>';

		// Cancelled

		echo '<div style="display:inline-block; width:50%;margin-bottom:20px;">';
		echo '<label for="apply_tags_cancelled_price">' . __( 'Subscription Cancelled', 'wp-fusion' ) . ':</label>';

		$args = array(
			'setting'   => $settings['apply_tags_cancelled_price'][ $key ],
			'meta_name' => "wpf-settings-edd[apply_tags_cancelled_price][{$key}]",
		);

		wpf_render_tag_multiselect( $args );

		echo '</div>';

		echo '</div>';

	}


	/**
	 * Adds EDD Recurring field group to meta fields list
	 *
	 * @since  3.41.8
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['edd_recurring'] = array(
			'title'  => 'Easy Digital Downloads Recurring Payments',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Sets field labels and types for EDD Recurring custom fields.
	 *
	 * @since  3.41.8
	 * @param  array $meta_fields The meta fields.
	 * @return array Meta fields.
	 */
	public function prepare_meta_fields( $meta_fields ) {

		$crm_fields = $this->get_subscription_meta_fields();

		// Global fields.
		foreach ( $crm_fields as $key => $value ) {
			$meta_fields[ $key ] = array(
				'label'  => $value['name'],
				'type'   => $value['type'],
				'pseudo' => true,
				'group'  => 'edd_recurring',
			);
		}

		// Fill in download-specific fields.
		$contact_fields = wpf_get_option( 'contact_fields', array() );

		foreach ( $contact_fields as $key => $value ) {

			foreach ( $crm_fields as $crm_key => $crm_value ) {

				if ( 0 === strpos( $key, $crm_key . '_' ) ) {

					$post_id             = str_replace( $crm_key . '_', '', $key );
					$meta_fields[ $key ] = array(
						'label'  => get_the_title( $post_id ) . ' - ' . $crm_value['name'],
						'type'   => $crm_value['type'],
						'pseudo' => true,
						'group'  => 'edd_recurring',
					);
				}
			}
		}

		if ( class_exists( 'EDD_Cancellation_Survey' ) ) {

			$meta_fields['_edd_cancellation_reason'] = array(
				'label'  => 'Cancellation Reason',
				'type'   => 'text',
				'group'  => 'edd_recurring',
				'pseudo' => true,
			);

		}

		return $meta_fields;

	}



	/**
	 * Get download subscription CRM fields.
	 *
	 * @since  3.41.8
	 * @return array
	 */
	private function get_subscription_meta_fields() {
		return array(
			'edd_sub_id'             => array(
				'name' => __( 'Subscription ID', 'wp-fusion' ),
				'type' => 'int',
			),
			'edd_sub_status'         => array(
				'name' => __( 'Subscription Status', 'wp-fusion' ),
				'type' => 'text',
			),
			'edd_sub_download_name'  => array(
				'name' => __( 'Download Name', 'wp-fusion' ),
				'type' => 'text',
			),
			'edd_sub_start_date'     => array(
				'name' => __( 'Subscription Start Date', 'wp-fusion' ),
				'type' => 'date',
			),
			'edd_sub_end_date'       => array(
				'name' => __( 'Subscription End Date', 'wp-fusion' ),
				'type' => 'date',
			),
			'edd_sub_trial_end_date' => array(
				'name' => __( 'Trial End Date', 'wp-fusion' ),
				'type' => 'date',
			),
			'edd_sub_renewal_date'   => array(
				'name' => __( 'Next Payment Date', 'wp-fusion' ),
				'type' => 'date',
			),
		);
	}


	/**
	 * //
	 * // EXPORT TOOLS
	 * //
	 **/

	/**
	 * Adds EDD Recurring checkbox to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['edd_recurring'] = array(
			'label'   => __( 'EDD Recurring Payments statuses', 'wp-fusion' ),
			'title'   => __( 'Orders', 'wp-fusion' ),
			'tooltip' => __( 'Updates user tags for all subscriptions based on current subscription status. Does not sync any fields.', 'wp-fusion' ),
		);

		$options['edd_recurring_meta'] = array(
			'label'   => __( 'EDD Recurring Payments meta', 'wp-fusion' ),
			'title'   => __( 'Subscriptions', 'wp-fusion' ),
			'tooltip' => __( 'Syncs the subscription product name, start date, status, and next renewal dates for all subscriptions (if enabled). Does not modify any tags.', 'wp-fusion' ),
		);

		return $options;

	}

	/**
	 * Gets array of all subscriptions to be processed
	 *
	 * @access public
	 * @return array Subscriptions
	 */

	public function batch_init() {

		$edd_db           = new EDD_Subscriptions_DB();
		$db_subscriptions = $edd_db->get_subscriptions(
			array(
				'number' => 0,
				'order'  => 'ASC',
			)
		);

		$subscriptions = array();
		foreach ( $db_subscriptions as $subscription_object ) {
			$subscriptions[] = $subscription_object->id;
		}

		return $subscriptions;

	}

	/**
	 * Update subscription statuses in batches
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step( $subscription_id ) {

		$subscription = new EDD_Subscription( $subscription_id );
		$this->subscription_status_change( false, $subscription->status, $subscription );

	}

	/**
	 * Syncs subscription fields..
	 *
	 * @since 3.41.8
	 *
	 * @param int $subscription_id The subscription ID.
	 */
	public function batch_step_meta( $subscription_id ) {

		$this->sync_subscription_fields( $subscription_id );

	}


}

new WPF_EDD_Recurring();
