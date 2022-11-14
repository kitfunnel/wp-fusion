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

		add_filter( 'wpf_edd_apply_tags_checkout', array( $this, 'apply_trial_tags' ), 10, 2 );

		// Subscription status triggers.
		add_action( 'edd_subscription_status_change', array( $this, 'subscription_status_change' ), 10, 3 );

		// Upgrades.
		add_action( 'edd_recurring_post_create_payment_profiles', array( $this, 'maybe_doing_upgrade' ), 5 ); // 5 so it runs before "handle_subscription_upgrade" in EDD_Recurring_Software_Licensing

		// Export functions.
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_edd_recurring_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_edd_recurring', array( $this, 'batch_step' ) );

	}

	/**
	 * Determines if a product or variable price option is a recurring charge
	 *
	 * @access  public
	 * @return  bool
	 */

	private function is_recurring( $download_id ) {

		if ( EDD_Recurring()->is_recurring( $download_id ) == true ) {
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

	public function subscription_status_change( $old_status, $status, $subscription = false ) {

		if ( ! $this->is_recurring( $subscription->product_id ) ) {
			return;
		}

		if ( $old_status === $status ) {
			return; // No change, nothing to do.
		}

		if ( 'cancelled' === $status && defined( 'WPF_EDD_DOING_UPGRADE' ) ) {
			return;
		}

		wpf_log( 'info', $subscription->customer->user_id, 'EDD subscription <a href="' . admin_url( 'edit.php?post_type=download&page=edd-subscriptions&id=' . $subscription->id ) . '" target="_blank">#' . $subscription->id . '</a> status changed from <strong>' . ucwords( $old_status ) . '</strong> to <strong>' . ucwords( $status ) . '</strong>.' );

		$settings = get_post_meta( $subscription->product_id, 'wpf-settings-edd', true );

		if ( empty( $settings ) ) {

			// No settings, nothing to do.
			return;
		}

		$remove_tags = array();
		$apply_tags  = array();

		// Remove tags if option is selected.

		if ( isset( $settings['remove_tags'] ) && 'active' !== $status ) {
			$remove_tags = array_merge( $remove_tags, $settings['apply_tags'] );
		}

		// Apply the tags for the new status.

		if ( ! empty( $settings[ 'apply_tags_' . $status ] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings[ "apply_tags_{$status}" ] );
		}

		// Maybe get tags from price ID.

		if ( ! empty( $subscription->price_id ) ) {

			// Remove price ID tags if applicable.

			if ( isset( $settings['remove_tags'] ) && 'active' !== $status ) {

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

		if ( 'active' === $status && 'trialling' === $old_status && ! empty( $settings['apply_tags_converted'] ) ) {
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
		echo '<span class="description">' . __( 'Remove tags when the subscription is completed, fails to charge, is cancelled, or expires.', 'wp-fusion' ) . '</span>';
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
		echo '<span class="description">' . __( 'Apply these when a subscription is created in trial status.', 'wp-fusion' ) . '</span>';
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
		echo '<span class="description">' . __( 'Apply these when a subscription in trial status converts to a paid subscription.', 'wp-fusion' ) . '</span>';
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
		echo '<span class="description">' . __( 'Apply these when a subscription is complete (number of payments matches the Times field).', 'wp-fusion' ) . '</span>';
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
		echo '<span class="description">' . __( 'Apply these when a subscription has a failed payment.', 'wp-fusion' ) . '</span>';
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
		echo '<span class="description">' . __( 'Apply these when a subscription has multiple failed payments or is marked Expired.', 'wp-fusion' ) . '</span>';
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
		echo '<span class="description">' . __( 'Apply these when a subscription is cancelled.', 'wp-fusion' ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

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
			'tooltip' => __( 'Updates user tags for all subscriptions based on current subscription status', 'wp-fusion' ),
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
		$db_subscriptions = $edd_db->get_subscriptions( array( 'number' => 0 ) );

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
		$this->subscription_status_change( $subscription_id, $subscription );

	}


}

new WPF_EDD_Recurring();
