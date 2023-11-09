<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * RestroPress Integration
 *
 * @since 3.37.31
 */

class WPF_RestroPress extends WPF_Integrations_Base {

	/**
	 * This identifies the integration internally and makes it available at
	 * wp_fusion()->integrations->{'my-plugin-slug'}
	 *
	 * @var  string
	 * @since 3.37.31
	 */

	public $slug = 'restropress';

	/**
	 * The human-readable name of the integration.
	 *
	 * @var  string
	 * @since 3.37.31
	 */

	public $name = 'RestroPress';

	/**
	 * Get things started.
	 *
	 * @since 3.37.31
	 */

	public function init() {
		// Metabox options
		add_filter( 'rpress_fooditem_data_tabs', array( $this, 'add_metabox_tab' ) );
		add_action( 'rpress_fooditem_data_panels', array( $this, 'metabox_output' ) );
		add_action( 'rpress_save_fooditem', array( $this, 'save_metabox' ) );

		// Handle checkouts
		add_action( 'rpress_complete_purchase', array( $this, 'process_order' ) );
		add_action( 'rpress_post_refund_payment', array( $this, 'order_refunded' ) );

		// Meta fields

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		// Global settings

		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

		// Export options
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_filter( 'wpf_batch_restropress_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_restropress', array( $this, 'batch_step' ) );

	}

	/**
	 * Save metabox fields.
	 *
	 * @since 3.37.31
	 *
	 * @param int $post_id
	 */
	public function save_metabox( $post_id ) {

		if ( ! empty( $_POST['wpf_settings_restropress'] ) ) {

			$settings               = $_POST['wpf_settings_restropress'];
			$settings['apply_tags'] = array_map( 'sanitize_text_field', $settings['apply_tags'] );

			update_post_meta( $post_id, 'wpf_settings_restropress', $settings );

		} else {
			delete_post_meta( $post_id, 'wpf_settings_restropress' );
		}
	}

	/**
	 * Add metabox content to products.
	 *
	 * @since 3.37.31
	 *
	 * @return mixed Metabox content.
	 */
	public function metabox_output() {
		$settings = get_post_meta( get_the_ID(), 'wpf_settings_restropress', true );
		if ( empty( $settings ) ) {
			$settings = array(
				'apply_tags' => array(),
			);
		}
		?>
		<div id="wpfusion_fooditem_data" class="panel restropress_options_panel hidden">
			<div class="rp-metabox-container">
				<div class="toolbar toolbar-top">
					<span class="rp-toolbar-title">
						<?php _e( 'WP Fusion', 'wp-fusion' ); ?>
					</span>
				</div>
				<div class="options_group" style="padding: 0 15px;">
					<div class="rp-metabox">
						<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<label><?php _e( 'Apply Tags', 'wp-fusion' ); ?></label>
									</th>
									<td class="rp-select-category">
									<?php
									$args = array(
										'setting'   => $settings['apply_tags'],
										'meta_name' => 'wpf_settings_restropress',
										'field_id'  => 'apply_tags',
									);

									wpf_render_tag_multiselect( $args )
									?>
									<span class="description"><?php printf( __( 'Apply these tags in %s when purchased.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></span>
									</td>
								</tr>

							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add metabox tab in single item.
	 *
	 * @since  3.37.31
	 *
	 * @param  array $tabs   The tabs.
	 * @return array The tabs.
	 */
	public function add_metabox_tab( $tabs ) {
		$tabs['wpfusion'] = array(
			'label'    => __( 'WP Fusion', 'restropress' ),
			'target'   => 'wpfusion_fooditem_data',
			'class'    => array(),
			'icon'     => 'icon-wp-fusion',
			'priority' => 50,
		);

		return $tabs;
	}

	/**
	 * Runs when a checkout has processed in your plugin. Syncs customer data to
	 * the CRM and applies configured tags.
	 *
	 * @since  3.37.31
	 *
	 * @see    get_contact_edit_url()
	 *
	 * @param  int $order_id The order ID
	 * @return bool|string The contact ID or false.
	 */

	public function process_order( $order_id ) {
		$order = new RPRESS_Payment( $order_id );

		// Create customer

		$contact_id = $this->create_update_customer( $order );

		if ( empty( $contact_id ) ) {
			return false; // If creating the contact failed for some reason.
		}

		// Apply tags for the order

		$apply_tags = wpf_get_option( 'restropress_apply_tags', array() ); // global tags

		// Maybe apply tags for the products
		$items = rpress_get_payment_meta_cart_details( $order_id );

		foreach ( $items as $product ) {

			$settings = get_post_meta( $product['id'], 'wpf_settings_restropress', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags'] );
			}
		}

		$apply_tags = array_filter( array_unique( $apply_tags ) );

		/**
		 * Modify the tags to be applied to the customer before they're sent to
		 * the CRM.
		 *
		 * @since 3.37.31
		 *
		 * @link  https://wpfusion.com/documentation/filters/wpf_woocommerce_apply_tags_checkout/
		 *
		 * @param array  $apply_tags An array of tag IDs to be applied to the customer.
		 * @param object $order      The order object.
		 */

		$apply_tags = apply_filters( "wpf_{$this->slug}_apply_tags_checkout", $apply_tags, $order );

		$user_id = rpress_get_payment_user_id( $order_id );

		// Apply the tags.
		if ( ! empty( $apply_tags ) ) {

			if ( 0 >= $user_id ) {

				// Guest checkout.
				wpf_log( 'info', 0, 'Applying tags to guest checkout for contact #' . $contact_id . ': ', array( 'tag_array' => $apply_tags ) );
				$result = wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			} else {

				// Registered users.
				$result = wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			}

			if ( is_wp_error( $result ) ) {

				// Handle errors

				$order->add_note( 'Error applying tags for order ID: ' . $order_id . '. ' . $result->get_error_message() );
				wpf_log( 'error', $user_id, 'Error <strong>' . $result->get_error_message() . '</strong> while applying tags: ', array( 'tag_array' => $apply_tags ) );
				return false;

			}
		}

		update_post_meta( $order_id, '_wpf_complete', current_time( 'Y-m-d H:i:s' ) );

		/**
		 * Payment complete.
		 *
		 * Indicates that WP Fusion has finished processing the order. Could be
		 * used to bind additional functionality to the new contact record (for
		 * example marking an abandoned cart as recovered, or creating an
		 * invoice in the CRM).
		 *
		 * @since 3.37.31
		 *
		 * @link  https://wpfusion.com/documentation/actions/wpf_woocommerce_payment_complete/
		 *
		 * @param int    $order_id   The order ID.
		 * @param string $contact_id The contact ID in the CRM.
		 */

		do_action( "wpf_{$this->slug}_payment_complete", $order_id, $contact_id );

		// Get the link to edit the contact in the CRM

		$edit_url = wp_fusion()->crm->get_contact_edit_url( $contact_id );
		$note     = sprintf( __( 'WP Fusion order actions completed (contact ID <a href="%1$s" target="blank">#%2$s</a>).', 'wp-fusion' ), $edit_url, $contact_id );

		$order->add_note( $note );

		return $contact_id;

	}


	/**
	 * Creates or updates a customer in the CRM for an order.
	 *
	 * @since  3.37.31
	 *
	 * @param  object $order  The order.
	 * @return bool|string The contact ID created in the CRM.
	 */

	public function create_update_customer( $order ) {

		$order_id = $order->ID;

		/**
		 * Allows for overriding the email address used for duplicate checking
		 * when creating/updating the CRM contact record.
		 *
		 * @since 3.37.31
		 *
		 * @param string $email_address The customer's email address.
		 * @param object $order         The order.
		 */
		$email = apply_filters( "wpf_{$this->slug}_billing_email", $order->email, $order );

		/**
		 * Allows for overriding the user ID for the order.
		 *
		 * @since 3.37.31
		 *
		 * @link  https://wpfusion.com/documentation/filters/wpf_woocommerce_user_id/
		 *
		 * @param int    $user_id The customer's user ID.
		 * @param object $order   The order.
		 */
		$user_id = apply_filters( "wpf_{$this->slug}_user_id", $order->user_id, $order );

		// $user_id is -1 for guest checkouts.

		if ( empty( $email ) && 0 >= $user_id ) {

			wpf_log( 'error', 0, 'No email address specified for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>. Aborting' );

			// Denotes that the WPF actions have already run for this order.
			update_post_meta( $order_id, '_wpf_complete', current_time( 'Y-m-d H:i:s' ) );

			return false;

		}

		if ( user_can( $user_id, 'manage_options' ) ) { // debug notice
			wpf_log( 'notice', $user_id, 'You\'re currently logged into the site as an administrator. This checkout will update your existing contact ID #' . wpf_get_contact_id() . ' in ' . wp_fusion()->crm->name . '. If you\'re testing checkouts, it\'s recommended to use an incognito browser window.' );
		}

		// Get the customer data.
		$customer_data = $this->get_customer_data( $order_id );

		// Sync it to the CRM.

		if ( 0 < $user_id ) {

			// Registered users.

			wp_fusion()->user->push_user_meta( $user_id, $customer_data );
			$contact_id = wpf_get_contact_id( $user_id ); // we'll use this in the next step.

		} else {

			// Helper function for creating/updating contact in the CRM from a guest checkout.

			$contact_id = $this->guest_registration( $email, $customer_data );

		}

		if ( false !== $contact_id ) {

			$order->add_note( 'Customer synced to contact ID ' . $contact_id . ' in ' . wp_fusion()->crm->name );

			update_post_meta( $order_id, WPF_CONTACT_ID_META_KEY, $contact_id ); // save it to the order meta in case we need it later

		}

		return $contact_id;

	}

	/**
	 * Gets an array of customer data from an order.
	 *
	 * @since  3.37.31
	 *
	 * @param  int $order_id The order ID.
	 * @return array The customer data.
	 */
	public function get_customer_data( $order_id ) {

		$customer_data = rpress_get_payment_meta_user_info( $order_id );
		$customer_data = array_merge( $customer_data, get_post_meta( $order_id, '_rpress_payment_meta', true ) ); // the phone is only stored here for some reason.
		$customer_data = array_merge( $customer_data, get_post_meta( $order_id, '_rpress_delivery_address', true ) );

		$field_map = array(
			'address'  => 'line1',
			'flat'     => 'line2',
			'postcode' => 'zip',
			'email'    => 'user_email',
		);

		$customer_data = $this->map_meta_fields( $customer_data, $field_map );

		// Special fields.
		$customer_data['order_date']  = get_the_date( 'Y-m-d H:i:s', $order_id );
		$customer_data['order_total'] = rpress_get_payment_amount( $order_id );
		$customer_data['order_id']    = $order_id;

		$notes = rpress_get_payment_notes( $order_id );

		if ( ! empty( $notes ) ) {
			$customer_data['order_notes'] = implode( PHP_EOL . PHP_EOL, wp_list_pluck( $notes, 'comment_content' ) );
		}

		// Coupons.
		$discount_total = rpress_get_discount_price_by_payment_id( $order_id );

		if ( $discount_total > 0 ) {
			$customer_data['discount_value'] = $$discount_total;
		}

		/**
		 * Allows for modifying the customer data before it's synced to the CRM.
		 *
		 * @since 3.37.31
		 *
		 * @link  https://wpfusion.com/documentation/filters/wpf_woocommerce_customer_data/
		 *
		 * @param array $customer_data The customer data.
		 * @param int   $order_id      The order ID.
		 */

		return apply_filters( "wpf_{$this->slug}_customer_data", $customer_data, $order_id );

	}

	/**
	 * Runs when an order is refunded and removes the tags from the customer.
	 *
	 * @since 3.37.31
	 *
	 * @param object $order  The order object.
	 */

	public function order_refunded( $order ) {
		$order_id = $order->ID;

		$remove_tags = array();

		// Get tags to remove
		$items = rpress_get_payment_meta_cart_details( $order_id );
		foreach ( $items as $product ) {

			$settings = get_post_meta( $product['id'], 'wpf_settings_restropress', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {
				$remove_tags = array_merge( $remove_tags, $settings['apply_tags'] );
			}
		}

		$remove_tags = array_filter( array_unique( $remove_tags ) );

		if ( ! empty( $remove_tags ) ) {

			$user_id = rpress_get_payment_user_id( $order_id );

			if ( 0 < $user_id ) {

				// Registered users

				wp_fusion()->user->remove_tags( $remove_tags, $user_id );

			} else {

				// Guests

				$contact_id = get_post_meta( $order_id, WPF_CONTACT_ID_META_KEY, true );

				if ( $contact_id ) {
					wpf_log( 'info', 0, 'Removing tags from guest customer ' . $contact_id . ': ', array( 'tag_array' => $remove_tags ) );
					$result = wp_fusion()->crm->remove_tags( $remove_tags, $contact_id );
				}
			}
		}

	}

	/**
	 * Registers the meta field group on the Contact Fields tab in the WP Fusion
	 * settings.
	 *
	 * @since  3.37.31
	 *
	 * @param  array $field_groups The field groups.
	 * @return array The field groups.
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups[ $this->slug ] = array(
			'title'  => 'RestroPress',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Register the custom meta fields for sync.
	 *
	 * @since  3.37.31
	 *
	 * @link   https://wpfusion.com/documentation/filters/wpf_meta_fields/
	 *
	 * @param  array $meta_fields The meta fields.
	 * @return array The meta fields.
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['phone'] = array(
			'label' => 'Customer Phone',
			'type'  => 'text',
			'group' => $this->slug,
		);

		$meta_fields['line1'] = array(
			'label' => 'Customer Address Line 1',
			'type'  => 'text',
			'group' => $this->slug,
		);

		$meta_fields['line2'] = array(
			'label' => 'Customer Address Line 2',
			'type'  => 'text',
			'group' => $this->slug,
		);

		$meta_fields['city'] = array(
			'label' => 'Customer City',
			'type'  => 'text',
			'group' => $this->slug,
		);

		$meta_fields['state'] = array(
			'label' => 'Customer State',
			'type'  => 'state',
			'group' => $this->slug,
		);

		$meta_fields['country'] = array(
			'label' => 'Customer Country',
			'type'  => 'country',
			'group' => $this->slug,
		);

		$meta_fields['zip'] = array(
			'label' => 'Customer Zip Code',
			'type'  => 'text',
			'group' => $this->slug,
		);

		$meta_fields['order_date'] = array(
			'label'  => 'Order Date',
			'type'   => 'date',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['order_total'] = array(
			'label'  => 'Order Total',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['order_id'] = array(
			'label'  => 'Order ID',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['order_notes'] = array(
			'label'  => 'Order Notes',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		$meta_fields['discount_value'] = array(
			'label'  => 'Order Discount Value',
			'type'   => 'text',
			'group'  => $this->slug,
			'pseudo' => true,
		);

		return $meta_fields;

	}


	/**
	 * Add a custom field to the Integrations tab in the WP Fusion settings.
	 *
	 * @since  3.37.31
	 *
	 * @param  array $settings The registered settings.
	 * @param  array $options  The options in the database.
	 * @return array The registered settings.
	 */

	public function register_settings( $settings, $options ) {

		$settings['restropress_header'] = array(
			'title'   => __( 'RestroPress', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['restropress_apply_tags'] = array(
			'title'   => __( 'Apply Tags to Customers', 'wp-fusion' ),
			'desc'    => __( 'These tags will be applied to all RestroPress customers.', 'wp-fusion' ),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		return $settings;

	}


	/**
	 * Registers an orders export operation.
	 *
	 * @since  3.37.31
	 *
	 * @link https://wpfusion.com/documentation/advanced-developer-tutorials/registering-custom-batch-operations/
	 *
	 * @param  array $options The options.
	 * @return array The options.
	 */

	public function export_options( $options ) {

		$options['restropress'] = array(
			'label'   => __( 'RestroPress Orders', 'wp-fusion' ),
			'title'   => 'Orders',
			'tooltip' => __( 'Finds Restropress orders that have not been processed by WP Fusion, and adds/updates contacts while applying tags based on the products purchased.', 'wp-fusion' ),
		);

		return $options;

	}

	/**
	 * Get the list of order IDs to be exported.
	 *
	 * @since  3.37.31
	 *
	 * @return array Order IDs.
	 */

	public function batch_init() {

		$args = array(
			'post_type'      => 'rpress_payment',
			'posts_per_page' => 2000,
			'fields'         => 'ids',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => '_wpf_complete',
					'compare' => 'NOT EXISTS',
				),
			),

		);
		return get_posts( $args );

	}

	/**
	 * Processes orders one at a time.
	 *
	 * @since 3.37.31
	 *
	 * @param int $order_id The order ID.
	 */

	public function batch_step( $order_id ) {

		$this->process_order( $order_id );

	}

}

new WPF_RestroPress();
