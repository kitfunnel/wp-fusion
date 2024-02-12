<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

class WPF_Woocommerce extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'woocommerce';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WooCommerce';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/woocommerce/';

	/**
	 * Allows passing dynamic tags between create_update_customer() and process_order().
	 *
	 * @var array
	 * @since 3.37.0
	 */
	public $dynamic_tags = array();

	/**
	 * Get things started
	 *
	 * @access public
	 * @return void
	 */
	public function init() {

		// WPF stuff.
		add_filter( 'wpf_user_register', array( $this, 'user_register' ) );
		add_filter( 'wpf_user_update', array( $this, 'user_update' ) );
		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 15 );
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		add_filter( 'wpf_skip_auto_login', array( $this, 'skip_auto_login' ) );
		add_filter( 'wpf_woocommerce_customer_data', array( $this, 'merge_fields_data' ), 10, 2 );

		// Login redirect.
		add_filter( 'woocommerce_login_redirect', array( $this, 'maybe_bypass_login_redirect' ), 10, 2 );

		// Pre-fill checkout values during an auto-login session.
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'pre_fill_checkout_fields' ), 10, 2 );

		// Last updated.
		add_filter( 'woocommerce_user_last_update_fields', array( $this, 'last_update_fields' ) );

		// Account info update.
		add_filter( 'woocommerce_save_account_details', array( $this, 'save_account_details' ) );

		// Taxonomy settings.
		add_action( 'admin_init', array( $this, 'register_taxonomy_form_fields' ) );

		// Async checkout.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_async_checkout_script' ) );
		add_action( 'wp_ajax_wpf_async_woocommerce_checkout', array( $this, 'async_checkout' ) );
		add_action( 'wp_ajax_nopriv_wpf_async_woocommerce_checkout', array( $this, 'async_checkout' ) );
		add_action( 'wpf_handle_async_checkout_fallback', array( $this, 'process_order' ) );

		// Prevent timeouts when bulk editing..
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_actions' ), 5, 3 );

		// Successful orders.
		add_action( 'woocommerce_order_status_processing', array( $this, 'woocommerce_apply_tags_checkout' ), 10 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'woocommerce_apply_tags_checkout' ), 10 );

		// Old async checkout (via WP Background Processing).
		add_action( 'wpf_woocommerce_async_checkout', array( $this, 'process_order' ), 10 );

		// Refunded / other order statuses.
		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ), 10, 4 );
		add_action( 'woocommerce_new_order', array( $this, 'new_order' ) );
		add_action( 'woocommerce_order_partially_refunded', array( $this, 'order_partially_refunded' ), 10, 2 );
		add_action( 'woocommerce_order_fully_refunded', array( $this, 'order_status_refunded' ), 20 ); // 20 so it's after pp_maybe_cancel_subscription_on_full_refund() (Cancel on Refund addon).
		add_action( 'woocommerce_order_status_processing_to_cancelled', array( $this, 'order_status_refunded' ) );
		add_action( 'woocommerce_order_status_completed_to_cancelled', array( $this, 'order_status_refunded' ) );

		// Sync auto generated passwords.
		add_action( 'woocommerce_created_customer', array( $this, 'push_autogen_password' ), 10, 3 );

		// Remove add to cart buttons on Shop pages for restricted products.
		add_action( 'woocommerce_loop_add_to_cart_link', array( $this, 'add_to_cart_buttons' ), 10, 2 );

		// Add meta boxes to Woo product editor.
		add_action( 'woocommerce_product_data_panels', array( $this, 'woocommerce_write_panels' ) );
		add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'woocommerce_write_panel_tabs' ) );
		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ), 5 );

		// Save changes to Woo meta box data.
		add_action( 'save_post', array( $this, 'save_meta_box_data' ) );

		// Variations fields.
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variable_fields' ), 15, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variable_fields' ), 10, 2 );

		// Add order action.
		add_filter( 'woocommerce_order_actions', array( $this, 'order_actions' ) );
		add_action( 'woocommerce_order_action_wpf_process', array( $this, 'process_order_action' ) );

		// Order sync status meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );

		// Order sync status indicator in orders list.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_wp_fusion_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_wp_fusion_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_columns' ), 15, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_columns' ), 15, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'add_status_filter' ) );
		add_filter( 'parse_query', array( $this, 'filter_orders_by_status' ) );

		// Export functions.
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_filter( 'wpf_batch_woocommerce_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_woocommerce', array( $this, 'batch_step' ), 10, 2 );

		// Coupons settings.
		add_filter( 'woocommerce_coupon_data_tabs', array( $this, 'coupon_tabs' ) );
		add_action( 'woocommerce_coupon_data_panels', array( $this, 'coupon_data_panel' ) );

		add_action( 'save_post_shop_coupon', array( $this, 'save_meta_box_data_coupon' ) );
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'coupon_is_valid' ), 50, 3 ); // 50 so it runs after anything else checks the validity (i.e. WooCommerce PDF Vouchers).

		// Apply coupons.
		add_action( 'woocommerce_add_to_cart', array( $this, 'maybe_apply_coupons' ), 30 ); // 30 so the cart totals have time to recalculate.
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'maybe_apply_coupons' ) );
		add_action( 'wpf_tags_modified', array( $this, 'maybe_apply_coupons' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_apply_coupons' ) );

		// Coupon labels.
		add_filter( 'woocommerce_cart_totals_coupon_label', array( $this, 'rename_coupon_label' ), 10, 2 );
		add_filter( 'woocommerce_coupon_message', array( $this, 'coupon_success_message' ), 10, 3 );

		// Optin on checkout.
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'email_optin_checkbox' ) );
		add_filter( 'woocommerce_form_field', array( $this, 'remove_checkout_optional_fields_label' ), 10, 4 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_email_optin_checkbox' ) );

		// Customer reviews on products.
		add_action( 'wp_insert_comment', array( $this, 'insert_comment' ), 10, 2 );
		add_action( 'transition_comment_status', array( $this, 'comment_status_change' ), 10, 3 );

		// Maybe hide coupon field.
		add_filter( 'woocommerce_coupons_enabled', array( $this, 'hide_coupon_field_on_cart' ) );

		// Enable HPOS support.
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_support' ) );

		// Access control. These functions are just used if Restrict Content is
		// enabled in the main WPF settings.

		if ( wpf_get_option( 'restrict_content', true ) ) {

			// Remove restricted products from shop loop & prevent adding to cart.

			add_action( 'the_posts', array( $this, 'exclude_restricted_products' ), 10, 2 );
			add_action( 'woocommerce_add_to_cart_validation', array( $this, 'prevent_restricted_add_to_cart' ), 10, 3 );
			add_filter( 'woocommerce_is_purchasable', array( $this, 'is_purchaseable' ), 10, 2 );
			add_filter( 'woocommerce_related_products', array( $this, 'hide_restricted_related_products' ), 10, 3 );

			// Hide restricted variations.
			add_filter( 'woocommerce_variation_is_purchasable', array( $this, 'variation_is_purchaseable' ), 10, 2 );
			add_filter( 'woocommerce_variation_is_active', array( $this, 'variation_is_purchaseable' ), 10, 2 );
			add_action( 'wp_print_styles', array( $this, 'variation_styles' ) );

			// Restrict access to shop page.
			add_action( 'template_redirect', array( $this, 'restrict_access_to_shop' ) );

			// Coupon usage.
			add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'coupon_usage_restriction' ), 10, 2 );

		}

		// Compatibility with WooCommerce Software Licenses.
		if ( class_exists( 'WOO_SL_functions' ) ) {
			add_filter( 'woo_sl/generate_license_key', array( $this, 'save_license_keys' ), 10, 2 );
			add_filter( 'wpf_woocommerce_customer_data', array( $this, 'merge_license_key_data' ), 10, 2 );
		}

		// Compatibility.

		// Fixes conflct with WooCommerce Anti Fraud syncing customer email to
		// admin's contact record when editing users in the admin.
		remove_action( 'profile_update', 'sync_woocommerce_email', 10, 2 );

	}

	/**
	 * Registers a new contact record for an order, for cases where we need to apply tags to guests before the order was received
	 *
	 * @access public
	 * @return int / false Contact ID
	 */

	public function maybe_create_contact_from_order( $order_id ) {

		$order = wc_get_order( $order_id );

		$contact_id = $order->get_meta( WPF_CONTACT_ID_META_KEY, true );

		if ( ! empty( $contact_id ) ) {
			return $contact_id;
		}

		$customer_data = array(
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'user_email' => $order->get_billing_email(),
		);

		wpf_log(
			'info',
			0,
			'Creating contact record from guest for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>',
			array(
				'source'     => 'woocommerce',
				'meta_array' => $customer_data,
			)
		);

		$contact_id = wp_fusion()->crm->add_contact( $customer_data );

		if ( ! empty( $contact_id ) && ! is_wp_error( $contact_id ) ) {
			$order->update_meta_data( WPF_CONTACT_ID_META_KEY, $contact_id );
			$order->save();
		}

		return $contact_id;

	}

	/**
	 * Formats field data updated via the Update Account form
	 *
	 * @access public
	 * @return array User Meta
	 */

	public function save_account_details( $user_id ) {

		$user_meta = $_POST;

		if ( isset( $user_meta['account_first_name'] ) ) {
			$user_meta['first_name'] = $user_meta['account_first_name'];
		}

		if ( isset( $user_meta['account_email'] ) ) {
			$user_meta['user_email'] = $user_meta['account_email'];
		}

		if ( isset( $user_meta['account_last_name'] ) ) {
			$user_meta['last_name'] = $user_meta['account_last_name'];
		}

		if ( isset( $user_meta['password_1'] ) && ! empty( $user_meta['password_1'] ) ) {
			$user_meta['user_pass'] = $user_meta['password_1'];
		}

		wp_fusion()->user->push_user_meta( $user_id, $user_meta );

	}


	/**
	 * Skips auto login on checkout pages
	 *
	 * @access public
	 * @return bool Skip
	 */

	public function skip_auto_login( $skip ) {

		if ( defined( 'WC_DOING_AJAX' ) ) {
			$skip = true;
		}

		$request_uris = array(
			'checkout',
			wc_get_checkout_url(),
		);

		foreach ( $request_uris as $uri ) {

			if ( strpos( $_SERVER['REQUEST_URI'], $uri ) !== false ) {
				$skip = true;
			}
		}

		if ( isset( $_GET['wc-ajax'] ) ) {
			$skip = true;
		}

		return $skip;

	}

	/**
	 * Stop WooCommerce from redirecting to the My Account page if the wpf_return_to cookie is set
	 *
	 * @access public
	 * @return string Redirect
	 */

	public function maybe_bypass_login_redirect( $redirect, $user ) {

		if ( isset( $_COOKIE['wpf_return_to'] ) ) {

			wp_fusion()->access->return_after_login( $user->user_login, $user );

		}

		return $redirect;

	}

	/**
	 * Pre-fill checkout fields with auto-login data
	 *
	 * @access public
	 * @return string Value
	 */

	public function pre_fill_checkout_fields( $input, $key ) {

		if ( ! doing_wpf_auto_login() ) {
			return $input;
		}

		$user_meta = wp_fusion()->user->get_user_meta( wpf_get_current_user_id() );

		if ( empty( $user_meta['billing_email'] ) ) {
			$user_meta['billing_email'] = $user_meta['user_email'];
		}

		if ( empty( $user_meta['billing_first_name'] ) ) {
			$user_meta['billing_first_name'] = $user_meta['first_name'];
		}

		if ( empty( $user_meta['billing_last_name'] ) ) {
			$user_meta['billing_last_name'] = $user_meta['last_name'];
		}

		if ( isset( $user_meta[ $key ] ) ) {
			return $user_meta[ $key ];
		} else {
			return $input;
		}

	}

	/**
	 * Set the last_updated meta key on the user when tags or contact ID are modified, for Metorik
	 *
	 * @access public
	 * @return array Fields
	 */

	public function last_update_fields( $fields ) {

		$fields[] = WPF_TAGS_META_KEY;
		$fields[] = WPF_CONTACT_ID_META_KEY;

		return $fields;

	}

	/**
	 * Merge WCFF data into the checkout meta data
	 *
	 * @access  public
	 * @return  array Customer data
	 */

	public function merge_fields_data( $customer_data, $order ) {

		foreach ( $order->get_items() as $item ) {

			$item_meta = $item->get_meta_data();

			if ( ! empty( $item_meta ) ) {

				foreach ( $item_meta as $meta ) {

					if ( is_a( $meta, 'WC_Meta_Data' ) ) {

						$data = $meta->get_data();

						$key                   = strtolower( str_replace( ' ', '_', $data['key'] ) );
						$customer_data[ $key ] = $data['value'];

					}
				}
			}
		}

		return $customer_data;

	}

	/**
	 * Registers additional Woocommerce settings
	 *
	 * @access  public
	 * @return  array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['woo_header'] = array(
			'title'   => __( 'WooCommerce Integration', 'wp-fusion' ),
			'url'     => 'https://wpfusion.com/documentation/ecommerce/woocommerce/',
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['woo_tags'] = array(
			'title'   => __( 'Apply Tags to Customers', 'wp-fusion' ),
			'desc'    => __( 'These tags will be applied to all WooCommerce customers.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['woo_hide'] = array(
			'title'   => __( 'Hide Restricted Products', 'wp-fusion' ),
			'desc'    => __( 'If a user can\'t access a product, hide it from the Shop page.', 'wp-fusion' ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		$settings['woo_error_message'] = array(
			'title'   => __( 'Restricted Product Error Message', 'wp-fusion' ),
			'desc'    => __( 'This message will be displayed if a customer attempts to add a restricted product to their cart.', 'wp-fusion' ),
			'std'     => 'You do not have sufficient privileges to purchase this product. Please contact support.',
			'type'    => 'text',
			'format'  => 'html',
			'section' => 'integrations',
		);

		$settings['woo_async'] = array(
			'title'   => __( 'Asynchronous Checkout', 'wp-fusion' ),
			'desc'    => __( 'Runs WP Fusion post-checkout actions asynchronously to speed up load times.', 'wp-fusion' ),
			'tooltip' => __( 'This can improve checkout speed (especially when using abandoned cart tracking and/or enhanced ecommerce) by sending any API calls in a background request rather than as part of the normal checkout process. However, this background request can get cached, or blocked by security plugins, in which case the checkout data simply won\'t be synced (and no error will be recorded). It\'s recommended to only enable this setting if you\'ve noticed your checkout is slower due to WP Fusion.', 'wp-fusion' ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		$settings['woo_hide_coupon_field'] = array(
			'title'   => __( 'Hide Coupon Field', 'wp-fusion' ),
			'desc'    => __( 'Hide the coupon input field on the checkout / cart screen (used with auto-applied coupons).', 'wp-fusion' ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		$settings['woo_review_tags'] = array(
			'title'   => __( 'Apply Tags - Left Review', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags when a user leaves a review on a product.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['woo_header_2'] = array(
			'title'   => __( 'WooCommerce Email Optin', 'wp-fusion' ),
			'url'     => 'https://wpfusion.com/documentation/ecommerce/woocommerce/#email-optins',
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['email_optin'] = array(
			'title'   => __( 'Email Optin', 'wp-fusion' ),
			'desc'    => __( 'Display a checkbox on the checkout page where customers can opt-in to receive email marketing.', 'wp-fusion' ),
			'type'    => 'checkbox',
			'section' => 'integrations',
			'unlock'  => array( 'email_optin_message', 'email_optin_default', 'email_optin_tags', 'hide_email_optin' ),
		);

		$settings['hide_email_optin'] = array(
			'title'   => __( 'Hide If Consented', 'wp-fusion' ),
			'desc'    => __( 'Hide the email optin checkbox if the customer has previously opted in.', 'wp-fusion' ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		$settings['email_optin_message'] = array(
			'title'       => __( 'Email Optin Message', 'wp-fusion' ),
			'placeholder' => __( 'I consent to receive marketing emails', 'wp-fusion' ),
			'type'        => 'text',
			'format'      => 'html',
			'section'     => 'integrations',
		);

		$settings['email_optin_default'] = array(
			'title'   => __( 'Email Optin Default', 'wp-fusion' ),
			'type'    => 'select',
			'std'     => 'checked',
			'choices' => array(
				'checked'   => __( 'Checked', 'wp-fusion' ),
				'unchecked' => __( 'Un-Checked', 'wp-fusion' ),
			),
			'section' => 'integrations',
		);

		$settings['email_optin_tags'] = array(
			'title'   => __( 'Email Optin Tags', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags to the customer when the email optin box is checked.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		if ( in_array( 'add_tags', wp_fusion()->crm->supports ) ) {

			$settings['woo_header_3'] = array(
				'title'   => __( 'WooCommerce Automatic Tagging', 'wp-fusion' ),
				'url'     => 'https://wpfusion.com/documentation/ecommerce/woocommerce/#automatic-tagging',
				'type'    => 'heading',
				'section' => 'integrations',
			);

			$settings['woo_category_tagging'] = array(
				'title'   => __( 'Product Category Tagging', 'wp-fusion' ),
				'desc'    => __( 'Generate and apply tags based on the category of every product purchased.', 'wp-fusion' ),
				'type'    => 'checkbox',
				'section' => 'integrations',
			);

			$settings['woo_name_tagging'] = array(
				'title'   => __( 'Product Name Tagging', 'wp-fusion' ),
				'desc'    => __( 'Generate and apply tags based on the name of every product purchased.', 'wp-fusion' ),
				'type'    => 'checkbox',
				'section' => 'integrations',
			);

			$settings['woo_sku_tagging'] = array(
				'title'   => __( 'Product SKU Tagging', 'wp-fusion' ),
				'desc'    => __( 'Generate and apply tags based on the SKU of every product purchased.', 'wp-fusion' ),
				'type'    => 'checkbox',
				'section' => 'integrations',
			);

			$settings['woo_tagging_prefix'] = array(
				'title'   => __( 'Tag Prefix', 'wp-fusion' ),
				'desc'    => __( 'Enter a prefix (i.e. "Purchased") for any automatically-generated tags. Use shortcode [status] to dynamically insert the order status.', 'wp-fusion' ),
				'type'    => 'text',
				'section' => 'integrations',
			);

		}

		$settings['woo_header_4'] = array(
			'title'   => __( 'WooCommerce Order Statuses', 'wp-fusion' ),
			'url'     => 'https://wpfusion.com/documentation/ecommerce/woocommerce/#order-status-tagging',
			'desc'    => __( '<p>The settings here let you apply tags to a contact when an order status is changed in WooCommerce.</p><p>This is useful if you\'re manually changing order statuses, for example marking an order Completed after it\'s been shipped.</p>', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$statuses = wc_get_order_statuses();

		// Maybe get custom statuses from Woo Order Status Manager
		if ( function_exists( 'wc_order_status_manager_get_order_status_posts' ) ) {

			$statuses = array();

			foreach ( wc_order_status_manager_get_order_status_posts() as $status ) {
				$statuses[ 'wc-' . $status->post_name ] = $status->post_title;
			}
		}

		foreach ( $statuses as $key => $label ) {

			$settings[ 'woo_status_tagging_' . $key ] = array(
				'title'   => $label,
				'type'    => 'assign_tags',
				'section' => 'integrations',
			);

			if ( 'wc-pending' === $key ) {
				$settings[ 'woo_status_tagging_' . $key ]['desc']    = __( '<strong>Caution:</strong> it is recommended not to enable this setting. For more info, see the tooltip.', 'wp-fusion' );
				$settings[ 'woo_status_tagging_' . $key ]['tooltip'] = sprintf( __( 'Syncing pending customers with %1$s will slow down your checkout because a contact record needs to be created and assigned any tags for the pending status, and then immediately updated and assigned additional tags once the payment is received.<br /><br />It is recommended not to sync Pending customers with %2$s unless you have a strong reason to do so and understand the risks.', 'wp-fusion' ), wp_fusion()->crm->name, wp_fusion()->crm->name );
			}
		}

		return $settings;

	}

	/**
	 * Adds WooCommerce field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['woocommerce'] = array(
			'title'  => 'WooCommerce Customer',
			'url'    => 'https://wpfusion.com/documentation/ecommerce/woocommerce/#syncing-customer-data-and-custom-fields',
			'fields' => array(),
		);

		$field_groups['woocommerce_variations'] = array(
			'title'  => 'WooCommerce Attributes',
			'url'    => 'https://wpfusion.com/documentation/ecommerce/woocommerce/#attribute-fields',
			'fields' => array(),
		);

		$field_groups['woocommerce_order'] = array(
			'title'  => 'WooCommerce Order',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Sets field labels and types for WooCommerce custom fields
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		// Several plugins have a fatal error when we try to get the address fields
		// since they try to access the current customer, and there isn't one.

		// So far we know of:
		// 1. WooCommerce Fattureincloud Premium
		// 2. WooCommerce Stripe Gateway
		// 3. Remove WooCommerce Billing Address Fields for Free Checkout.

		remove_all_filters( 'woocommerce_billing_fields' );

		$shipping_fields = WC()->countries->get_address_fields( '', 'shipping_' );
		$billing_fields  = WC()->countries->get_address_fields( '', 'billing_' );

		$woocommerce_fields = array_merge( $shipping_fields, $billing_fields );

		// Support for WooCommerce Checkout Field Editor.
		$additional_fields = array_merge( (array) get_option( 'wc_fields_additional', array() ), (array) get_option( 'wc_fields_billing', array() ), (array) get_option( 'wc_fields_shipping', array() ) );

		if ( ! empty( $additional_fields ) ) {
			$woocommerce_fields = array_merge( $woocommerce_fields, $additional_fields );
		}

		foreach ( $woocommerce_fields as $key => $data ) {

			$meta_fields[ $key ] = array(
				'label' => isset( $data['label'] ) ? $data['label'] : '',
				'type'  => isset( $data['type'] ) ? $data['type'] : 'text',
				'group' => 'woocommerce',
			);

		}

		// Support for WooCommerce Checkout Field Editor Pro
		if ( class_exists( 'WCFE_Checkout_Section' ) ) {

			$additional_fields = get_option( 'thwcfe_sections' );

			if ( ! empty( $additional_fields ) ) {

				foreach ( $additional_fields as $section ) {

					if ( ! empty( $section->fields ) ) {

						foreach ( $section->fields as $field ) {

							if ( ! isset( $meta_fields[ $field->id ] ) ) {

								$meta_fields[ $field->id ] = array(
									'label' => $field->title,
									'type'  => $field->type,
									'group' => 'woocommerce',
								);

							}
						}
					}
				}
			}
		}

		$meta_fields['generated_password'] = array(
			'label' => 'Generated Password',
			'type'  => 'text',
			'group' => 'woocommerce',
		);

		$meta_fields['email_optin'] = array(
			'label' => 'Email Optin',
			'type'  => 'checkbox',
			'group' => 'woocommerce',
		);

		$meta_fields['order_notes'] = array(
			'label' => 'Order Notes',
			'type'  => 'text',
			'group' => 'woocommerce_order',
		);

		$meta_fields['order_date'] = array(
			'label' => 'Last Order Date',
			'type'  => 'date',
			'group' => 'woocommerce_order',
		);

		$meta_fields['coupon_code'] = array(
			'label' => 'Last Coupon Used',
			'type'  => 'text',
			'group' => 'woocommerce_order',
		);

		$meta_fields['order_id'] = array(
			'label' => 'Last Order ID',
			'type'  => 'int',
			'group' => 'woocommerce_order',
		);

		$meta_fields['order_total'] = array(
			'label' => 'Last Order Total',
			'type'  => 'int',
			'group' => 'woocommerce_order',
		);

		$meta_fields['order_payment_method'] = array(
			'label' => 'Last Order Payment Method',
			'type'  => 'text',
			'group' => 'woocommerce_order',
		);

		$meta_fields['order_shipping'] = array(
			'label' => 'Last Order Shipping Method',
			'type'  => 'text',
			'group' => 'woocommerce_order',
		);

		// Get attributes.
		$args = array(
			'posts_per_page' => 100,
			'post_type'      => 'product',
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_product_attributes',
					'compare' => 'EXISTS',
				),
			),
		);

		$products = get_posts( $args );

		if ( ! empty( $products ) ) {

			foreach ( $products as $product_id ) {

				$attributes = get_post_meta( $product_id, '_product_attributes', true );

				if ( ! empty( $attributes ) ) {

					foreach ( $attributes as $key => $attribute ) {

						$meta_fields[ $key ] = array(
							'label' => $attribute['name'],
							'type'  => 'text',
							'group' => 'woocommerce_variations',
						);

					}
				}
			}
		}

		// Support for WooCommerce Software Licenses.
		if ( class_exists( 'WOO_SL_functions' ) ) {

			$meta_fields['license_key'] = array(
				'label' => 'License Key',
				'type'  => 'text',
				'group' => 'woocommerce_order',
			);
		}

		return $meta_fields;

	}


	/**
	 * Removes standard WPF meta boxes from Woo admin pages
	 *
	 * @access  public
	 * @return  array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['shop_order'] );
		unset( $post_types['shop_coupon'] );

		return $post_types;

	}


	/**
	 * Add WPF order status meta box.
	 *
	 * @since 3.37.9
	 */
	public function add_order_meta_box() {

		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {

			$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		} else {
			$screen = 'shop_order';
		}

		add_meta_box( 'wpf-status', __( 'WP Fusion', 'wp-fusion' ), array( $this, 'order_meta_box_callback' ), $screen, 'side', 'core' );

	}

	/**
	 * Display order status meta box.
	 *
	 * @since 3.37.9
	 *
	 * @param WP_Post $post   The post.
	 */
	public function order_meta_box_callback( $post ) {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$order = ( $post instanceof WP_Post ) ? wc_get_order( $post->ID ) : $post;

		if ( isset( $_GET['order_action'] ) && 'wpf_process' === $_GET['order_action'] ) {
			$this->process_order_action( $order );
		}

		?>

		<p class="post-attributes-label-wrapper">
			<strong><?php printf( __( 'Synced to %s:', 'wp-fusion' ), wp_fusion()->crm->name ); ?></strong>&nbsp;

			<?php if ( $order->get_meta( 'wpf_complete', true ) ) : ?>
				<span><?php _e( 'Yes', 'wp-fusion' ); ?></span>
				<span class="dashicons dashicons-yes-alt"></span>
			<?php else : ?>
				<span><?php _e( 'No', 'wp-fusion' ); ?></span>
				<span class="dashicons dashicons-no"></span>
			<?php endif; ?>
		</p>

		<?php $contact_id = $this->get_contact_id_from_order( $post->ID ); ?>

		<?php if ( $contact_id ) : ?>

			<p class="post-attributes-label-wrapper">
				<strong><?php _e( 'Contact ID:', 'wp-fusion' ); ?></strong>&nbsp;

				<?php $url = wp_fusion()->crm->get_contact_edit_url( $contact_id ); ?>
				<?php if ( false !== $url ) : ?>
					<a href="<?php echo $url; ?>" target="_blank">#<?php echo $contact_id; ?><span class="dashicons dashicons-external"></span></a>
				<?php else : ?>
					<span><?php echo $contact_id; ?></span>
				<?php endif; ?>

			</p>

		<?php endif; ?>

		<?php if ( wpf_get_option( 'email_optin' ) ) : ?>

			<p class="post-attributes-label-wrapper">
				<strong><?php _e( 'Opted In:', 'wp-fusion' ); ?></strong>&nbsp;

				<?php if ( $order->get_meta( 'email_optin', true ) ) : ?>
					<span><?php _e( 'Yes', 'wp-fusion' ); ?></span>
					<span class="dashicons dashicons-yes-alt"></span>
				<?php else : ?>
					<span><?php _e( 'No', 'wp-fusion' ); ?></span>
					<span class="dashicons dashicons-no"></span>
				<?php endif; ?>

			</p>

		<?php endif; ?>

		<?php if ( class_exists( 'WP_Fusion_Ecommerce' ) ) : ?>

			<p class="post-attributes-label-wrapper">
				<strong><?php printf( __( 'Enhanced Ecommerce:', 'wp-fusion' ), wp_fusion()->crm->name ); ?></strong>&nbsp;

				<?php if ( $order->get_meta( 'wpf_ec_complete', true ) ) : ?>
					<span><?php _e( 'Yes', 'wp-fusion' ); ?></span>
					<span class="dashicons dashicons-yes-alt"></span>
				<?php else : ?>
					<span><?php _e( 'No', 'wp-fusion' ); ?></span>
					<span class="dashicons dashicons-no"></span>
				<?php endif; ?>
			</p>

			<?php $invoice_id = $order->get_meta( 'wpf_ec_' . wp_fusion()->crm->slug . '_invoice_id', true ); ?>

			<?php if ( $invoice_id ) : ?>

				<p class="post-attributes-label-wrapper">
					<strong><?php _e( 'Invoice ID:', 'wp-fusion' ); ?></strong>&nbsp;
					<span><?php echo $invoice_id; ?></span>
				</p>

			<?php endif; ?>

		<?php endif; ?>

		<p class="post-attributes-label-wrapper">

			<a
			href="<?php echo esc_url( add_query_arg( array( 'order_action' => 'wpf_process' ) ) ); ?>"
			class="wpf-action-button button-secondary wpf-tip wpf-tip-bottom"
			data-tip="<?php printf( esc_html__( 'The order will be processed again as if the customer had just checked out. Any enabled fields will be synced to %s, and any configured tags will be applied.', 'wp-fusion' ), wp_fusion()->crm->name ); ?>">
				<?php _e( 'Process WP Fusion actions again ', 'wp-fusion' ); ?>
			</a>

		</p>

		<?php

	}

	/**
	 * Add a column to the WooCommerce -> Orders admin screen to indicate
	 * whether an order has been successfully processed by WP Fusion.
	 *
	 * @since  3.38.42
	 *
	 * @param  array $columns The current list of columns.
	 * @return array The modified list of columns.
	 */
	public static function add_wp_fusion_column( $columns ) {

		$new_column = '<span class="tips wpf-woo-column-title" data-tip="' . esc_attr__( 'WP Fusion Status', 'wp-fusion' ) . '"><span>' . __( 'WP Fusion Status', 'wp-fusion' ) . '</span>' . wpf_logo_svg( 14 ) . '</span>';

		return wp_fusion()->settings->insert_setting_after( 'shipping_address', $columns, array( 'wp_fusion' => $new_column ) );

	}

	/**
	 * Add WPF order status meta box.
	 *
	 * @since 3.37.9
	 */
	public function render_columns( $column, $post_id ) {

		if ( 'wp_fusion' === $column ) {

			$order = wc_get_order( $post_id );

			$complete = $order->get_meta( 'wpf_complete', true );

			if ( $complete ) {

				$class = 'success';

				// Get the contact edit URL.

				$contact_id = $this->get_contact_id_from_order( $post_id );

				if ( $contact_id ) {

					$url = wp_fusion()->crm->get_contact_edit_url( $contact_id );

					if ( $url ) {
						$id_text = '<a href="' . esc_url_raw( $url ) . '" target="_blank">#' . esc_html( $contact_id ) . '</a>';
					} else {
						$id_text = '#' . esc_html( $contact_id );
					}
				} else {
					$class   = 'partial-success';
					$id_text = '<em>' . __( 'unknown', 'wp-fusion' ) . '</em>';
				}

				$show_date = date_i18n( get_option( 'date_format' ) . ' \a\t ' . get_option( 'time_format' ), strtotime( $complete ) );
				$tooltip   = sprintf(
					__( 'This order was synced to %1$s contact ID %2$s on %3$s.', 'wp-fusion' ),
					esc_html( wp_fusion()->crm->name ),
					$id_text,
					esc_html( $show_date )
				);

				if ( function_exists( 'wp_fusion_ecommerce' ) ) {

					// Enhanced ecommerce.

					if ( $order->get_meta( 'wpf_ec_complete', true ) ) {

						$invoice_id = $order->get_meta( 'wpf_ec_' . wp_fusion()->crm->slug . '_invoice_id', true );

						if ( $invoice_id ) {
							$tooltip .= '<br /><br />' . sprintf( __( 'It was processed by Enhanced Ecommerce with invoice ID #%s.', 'wp-fusion' ), $invoice_id );
						} else {
							$tooltip .= '<br /><br />' . __( 'It was processed by Enhanced Ecommerce.', 'wp-fusion' );
						}
					} else {

						$class    = 'partial-success';
						$tooltip .= '<br /><br />' . __( 'It was not processed by Enhanced Ecommerce.', 'wp-fusion' );

					}
				}
			} else {
				$class   = 'fail';
				$tooltip = sprintf( __( 'This order was not synced to %s.' ), wp_fusion()->crm->name );
			}

			echo '<i class="icon-wp-fusion tips ' . esc_attr( $class ) . '" data-tip="' . esc_attr( $tooltip ) . '"></i>';
		}

	}

	/**
	 * Add a filter to the WooCommerce -> Orders admin screen to filter by
	 * whether an order has been successfully processed by WP Fusion.
	 *
	 * @since  3.40.44
	 */
	public function add_status_filter( $post_type ) {

		if ( 'shop_order' !== $post_type ) {
			return;
		}

		$selected = isset( $_GET['wpf_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['wpf_filter'] ) ) : false;

		$options = array(
			''              => __( 'All statuses', 'wp-fusion' ),
			'processed'     => __( 'Processed by WP Fusion', 'wp-fusion' ),
			'not_processed' => __( 'Not processed by WP Fusion', 'wp-fusion' ),
		);

		echo '<select name="wpf_filter" id="dropdown_wpf_filter">';

		foreach ( $options as $key => $label ) {

			echo '<option value="' . esc_attr( $key ) . '"';

			if ( $selected === $key ) {
				echo ' selected="selected"';
			}

			echo '>' . esc_html( $label ) . '</option>';

		}

		echo '</select>';

	}

	/**
	 * Filter the orders by the selected status.
	 *
	 * @since  3.40.44
	 *
	 * @param  WP_Query $query The query object.
	 */
	public function filter_orders_by_status( $query ) {

		global $typenow;

		if ( 'shop_order' === $typenow ) {

			$selected = isset( $_GET['wpf_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['wpf_filter'] ) ) : false;

			if ( ! empty( $selected ) ) {

				if ( 'processed' === $selected ) {

					$query->query_vars['meta_query'][] = array(
						'key'     => 'wpf_complete',
						'compare' => 'EXISTS',
					);

				} elseif ( 'not_processed' === $selected ) {

					$query->query_vars['meta_query'][] = array(
						'key'     => 'wpf_complete',
						'compare' => 'NOT EXISTS',
					);

				}
			}
		}
	}

	/**
	 * Removes restricted products from shop archives
	 *
	 * @access  public
	 * @return  array Posts
	 */

	public function exclude_restricted_products( $posts, $query ) {

		if ( is_admin() || ! wpf_get_option( 'woo_hide' ) || wpf_get_option( 'hide_archives' ) ) {
			return $posts;
		}

		if ( ! $query->is_archive() ) {
			return $posts;
		}

		if ( $query->query_vars['post_type'] != 'product' && ! isset( $query->query_vars['product_cat'] ) ) {
			return $posts;
		}

		foreach ( $posts as $index => $product ) {

			if ( ! wp_fusion()->access->user_can_access( $product->ID ) ) {
				unset( $posts[ $index ] );
			}
		}

		return array_values( $posts );

	}

	/**
	 * Prevents restricted products from being added to the cart
	 *
	 * @access  public
	 * @return  bool Passed
	 */

	public function prevent_restricted_add_to_cart( $passed, $product_id, $quantity ) {

		if ( $quantity == 0 || wp_fusion()->access->user_can_access( $product_id ) ) {
			return $passed;
		}

		wc_add_notice( wpf_get_option( 'woo_error_message' ), 'error' );

		return false;

	}

	/**
	 * Blocks restricted products from purchase
	 *
	 * @access  public
	 * @return  bool Is purchaseable
	 */

	public function is_purchaseable( $is_purchaseable, $product ) {

		if ( ! wp_fusion()->access->user_can_access( $product->get_id() ) ) {
			return false;
		}

		return $is_purchaseable;

	}

	/**
	 * Blocks restricted variations from purchase
	 *
	 * @access  public
	 * @return  bool Is purchaseable
	 */

	public function variation_is_purchaseable( $is_purchaseable, $variation ) {

		if ( ! wp_fusion()->access->user_can_access( $variation->get_id() ) ) {
			return false;
		}

		return $is_purchaseable;

	}

	/**
	 * Hide restricted variations
	 *
	 * @access  public
	 */

	public function variation_styles() {

		echo '<!-- WP Fusion -->';
		echo '<style type="text/css">.woocommerce .product .variations option:disabled { display: none; } </style>';

	}

	/**
	 * If Filter Queries is on, hide restricted related products
	 *
	 * @access  public
	 * @return  array Products
	 */

	public function hide_restricted_related_products( $product_ids, $related_product_id, $args ) {

		if ( ! wpf_get_option( 'hide_archives' ) ) {
			return $product_ids;
		}

		foreach ( $product_ids as $i => $product_id ) {

			if ( ! wp_fusion()->access->user_can_access( $product_id ) ) {
				unset( $product_ids[ $i ] );
			}
		}

		return array_values( $product_ids );

	}

	/**
	 * Removes Add to Cart buttons in Store page for restricted products
	 *
	 * @access  public
	 * @return  string Link
	 */

	public function add_to_cart_buttons( $link, $product ) {

		if ( ! wp_fusion()->access->user_can_access( $product->get_id() ) ) {
			$link = '';
		}

		return $link;

	}

	/**
	 * Adapt WooCommerce checkout fields to CRM fields for creating customers at checkout
	 *
	 * @access public
	 * @return array Post Data
	 */

	public function user_register( $post_data ) {

		$field_map = array(
			'account_password'   => 'user_pass',
			'password'           => 'user_pass',
			'billing_email'      => 'user_email',
			'account_username'   => 'user_login',
			'billing_first_name' => 'first_name',
			'billing_last_name'  => 'last_name',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		// Get the username if autogenerated by Woo
		if ( empty( $post_data['user_login'] ) ) {
			$user                    = get_user_by( 'email', $post_data['user_email'] );
			$post_data['user_login'] = $user->user_login;
		}

		return $post_data;

	}

	/**
	 * Format WooCommerce account update fields
	 *
	 * @access public
	 * @return array User Meta
	 */

	public function user_update( $user_meta ) {

		if ( ! empty( $user_meta['billing_country'] ) && 'text' == wpf_get_field_type( 'billing_country' ) && isset( WC()->countries->countries[ $user_meta['billing_country'] ] ) ) {

			// Allow sending full country name instead of abbreviation
			$user_meta['billing_country'] = WC()->countries->countries[ $user_meta['billing_country'] ];

		}

		if ( ! empty( $user_meta['shipping_country'] ) && 'text' == wpf_get_field_type( 'shipping_country' ) && isset( WC()->countries->countries[ $user_meta['shipping_country'] ] ) ) {

			// Allow sending full country name instead of abbreviation
			$user_meta['shipping_country'] = WC()->countries->countries[ $user_meta['shipping_country'] ];

		}

		return $user_meta;

	}


	/**
	 * Get contact ID from order.
	 *
	 * Check whether a contact record has been created yet for a given order and
	 * return the ID if applicable.
	 *
	 * @since  3.37.24
	 *
	 * @param  int|WC_Order $order  The order ID or order object.
	 * @return string|bool  The contact ID or false if not found.
	 */
	public function get_contact_id_from_order( $order ) {

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		// If the order is for a registered user we'll check their record first.

		$user_id = $order->get_user_id();

		if ( $user_id ) {

			$contact_id = wpf_get_contact_id( $user_id );

			if ( false !== $contact_id ) {
				return $contact_id;
			}
		}

		// If it's a guest checkout there will be a contact ID saved to the order.

		$contact_id = $order->get_meta( WPF_CONTACT_ID_META_KEY, true );

		if ( $contact_id ) {
			return $contact_id;
		}

		return false;

	}


	/**
	 * Gets customer details from the WooCommerce order when customer isn't a registered user
	 *
	 * @access public
	 * @return array Contact Data
	 */

	public function get_customer_data( $order ) {

		$order_data    = $order->get_data();
		$customer_data = array();

		foreach ( $order_data as $key => $value ) {

			if ( is_array( $value ) ) {

				// Nested params like Billing and Shipping info
				foreach ( $value as $sub_key => $sub_value ) {

					if ( is_object( $sub_value ) ) {
						continue;
					}

					$customer_data[ $key . '_' . $sub_key ] = $sub_value;

				}
			} elseif ( ! is_object( $value ) && ! is_a( $value, 'WC_DateTime' ) ) {

				// Regular params.
				$customer_data[ $key ] = $value;

			}
		}

		// Meta data.
		foreach ( $order_data['meta_data'] as $meta ) {

			if ( is_a( $meta, 'WC_Meta_Data' ) ) {

				$data = $meta->get_data();

				if ( ! is_array( $data['value'] ) ) {

					$customer_data[ $data['key'] ] = $data['value'];

					if ( 0 === strpos( $data['key'], '_' ) ) {
						$data['key']                   = ltrim( $data['key'], '_' ); // works for YITH WooCommerce Checkout Manager custom fields.
						$customer_data[ $data['key'] ] = $data['value'];
					}
				}
			}
		}

		$user_id = $order->get_user_id();
		$user    = get_userdata( $user_id );

		// Map some common additional fields
		foreach ( $customer_data as $key => $value ) {

			if ( 'billing_email' == $key ) {

				if ( ! empty( $user_id ) ) {

					$customer_data['user_email'] = $user->user_email;

				} else {

					$customer_data['user_email'] = $value;

				}
			} elseif ( 'billing_first_name' == $key ) {

				if ( empty( $value ) && ! empty( $user_id ) ) {

					// No billing name provided, use the one from the user record
					$customer_data['billing_first_name'] = $user->first_name;

				} elseif ( ! wpf_is_field_active( 'billing_first_name' ) || wpf_get_crm_field( 'billing_first_name' ) == wpf_get_crm_field( 'first_name' ) || empty( $user_id ) ) {

					// If the billing name is syncing to the same field as the last name, use billing_first_name for first_name
					$customer_data['first_name'] = $value;

				} else {

					// Otherwise keep billing_first_name separate and user the user's first_name for first_name
					$customer_data['first_name'] = $user->first_name;

				}
			} elseif ( 'billing_last_name' == $key ) {

				if ( empty( $value ) && ! empty( $user_id ) ) {

					// No billing name provided, use the one from the user record
					$customer_data['billing_last_name'] = $user->last_name;

				} elseif ( ! wpf_is_field_active( 'billing_last_name' ) || wpf_get_crm_field( 'billing_last_name' ) == wpf_get_crm_field( 'last_name' ) || empty( $user_id ) ) {

					// If the billing name is syncing to the same field as the last name, use billing_last_name for last_name
					$customer_data['last_name'] = $value;

				} else {

					// Otherwise keep billing_last_name separate and user the user's last_name for last_name
					$customer_data['last_name'] = $user->last_name;

				}
			} elseif ( 'billing_state' == $key || 'shipping_state' == $key ) {

				if ( ! empty( $value ) && isset( WC()->countries->states[ $customer_data['billing_country'] ] ) && isset( WC()->countries->states[ $customer_data['billing_country'] ][ $value ] ) ) {

					$customer_data[ $key ] = WC()->countries->states[ $customer_data['billing_country'] ][ $value ];

				}
			} elseif ( 'billing_country' == $key ) {

				if ( ! empty( $value ) && 'text' == wpf_get_field_type( 'billing_country' ) ) {

					// Allow sending full country name instead of abbreviation
					$customer_data[ $key ] = WC()->countries->countries[ $value ];

				}
			} elseif ( 'shipping_country' == $key ) {

				if ( ! empty( $value ) && 'text' == wpf_get_field_type( 'shipping_country' ) ) {

					// Allow sending full country name instead of abbreviation
					$customer_data[ $key ] = WC()->countries->countries[ $value ];

				}
			}
		}

		$order_date = $order->get_date_paid();

		if ( is_object( $order_date ) && isset( $order_date->date ) ) {
			$order_date = $order_date->date;
		} else {
			$order_date = get_the_date( 'c', $order->get_id() );
		}

		$customer_data['order_date']           = $order_date;
		$customer_data['order_total']          = $order->get_total();
		$customer_data['order_id']             = $order->get_order_number();
		$customer_data['order_notes']          = $order->get_customer_note();
		$customer_data['order_payment_method'] = $order->get_payment_method_title();

		// Get shipping method.

		$shipping_methods = $order->get_items( 'shipping' );

		if ( $shipping_methods ) {
			$customer_data['order_shipping_method'] = array_values( $shipping_methods )[0]->get_method_title();
		}

		// Coupons
		if ( method_exists( $order, 'get_coupon_codes' ) ) {

			$coupons = $order->get_coupon_codes();

			if ( ! empty( $coupons ) ) {
				$customer_data['coupon_code'] = $coupons[0];
			}
		}

		// Get any dynamic tags out of the order data

		$this->dynamic_tags = $this->get_dynamic_tags( $customer_data );

		if ( isset( $customer_data['email_optin'] ) && empty( $customer_data['email_optin'] ) ) {
			unset( $customer_data['email_optin'] );
		}

		return apply_filters( 'wpf_woocommerce_customer_data', $customer_data, $order );

	}


	/**
	 * Creates / update customer.
	 *
	 * From a WooCommerce order, create or update a customer in the CRM.
	 *
	 * @since  3.36.1
	 * @since  3.36.2 Now returns the contact ID of the CRM contact record.
	 *
	 * @param  WC_Order $order  The order object.
	 * @return string   The contact ID created / updated.
	 */
	public function create_update_customer( $order ) {

		$email    = apply_filters( 'wpf_woocommerce_billing_email', $order->get_billing_email(), $order );
		$user_id  = apply_filters( 'wpf_woocommerce_user_id', $order->get_user_id(), $order );
		$order_id = $order->get_id();

		if ( empty( $email ) && empty( $user_id ) ) {

			wpf_log( 'error', 0, 'No email address specified for WooCommerce order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>. Aborting' );

			delete_transient( 'wpf_woo_started_' . $order_id );

			// Denotes that the WPF actions have already run for this order.
			$order->update_meta_data( 'wpf_complete', current_time( 'Y-m-d H:i:s' ) );
			$order->save();

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
				delete_transient( 'wpf_woo_started_' . $order_id );
				return false;

			}
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			wpf_log( 'notice', $user_id, 'You\'re currently logged into the site as an administrator. This checkout will update your existing contact #' . $contact_id . ' in ' . wp_fusion()->crm->name . '. If you\'re testing checkouts, it\'s recommended to use an incognito browser window.' );
		}

		// Format order data
		$order_data = $this->get_customer_data( $order );

		if ( is_array( $order_data ) && ( empty( $order_data ) || empty( $order_data['user_email'] ) ) ) {

			// If getting the order data (or the wpf_woocommerce_customer_data filter) messed up somehow

			wpf_log( 'error', $user_id, 'Aborted checkout for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>, no email address found.' );
			delete_transient( 'wpf_woo_started_' . $order_id );
			return false;

		} elseif ( false == $order_data || null == $order_data ) {

			// It was intentionally cancelled so we'll quit silently

			// We can't mark it complete in case it was cancelled because it's not yet at the right status.
			// For example it might become "complete" and need to be synced later.

			wpf_log( 'info', $user_id, 'Order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a> will be ignored (nothing returned from <code>wpf_woocommerce_customer_data</code>).' );

			delete_transient( 'wpf_woo_started_' . $order_id );
			return false;

		}

		/**
		 * In cases like a Woo Subscriptions renewal, we don't need to sync the meta data every time.
		 *
		 * @since  3.36.2
		 *
		 * @param  bool     $sync_data Whether or not to sync the customer data to the CRM.
		 * @param  WC_Order $order     The order object.
		 */

		if ( false === apply_filters( 'wpf_woocommerce_sync_customer_data', true, $order ) ) {
			return $contact_id;
		}

		// If contact doesn't exist in CRM
		if ( false == $contact_id ) {

			// Logger
			wpf_log(
				'info',
				0,
				'Processing guest checkout for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>:',
				array(
					'source'     => 'woocommerce',
					'meta_array' => $order_data,
				)
			);

			$contact_id = wp_fusion()->crm->add_contact( $order_data );

			if ( is_wp_error( $contact_id ) ) {

				wpf_log( 'error', $user_id, 'Error while adding contact: ' . $contact_id->get_error_message(), array( 'source' => wp_fusion()->crm->slug ) );
				delete_transient( 'wpf_woo_started_' . $order_id );
				return false;

			}

			$order->add_order_note( wp_fusion()->crm->name . ' contact ID ' . $contact_id . ' created via guest-checkout.' );

			// Set contact ID locally
			if ( ! empty( $user_id ) ) {
				update_user_meta( $user_id, WPF_CONTACT_ID_META_KEY, $contact_id );
			}

			$order->update_meta_data( WPF_CONTACT_ID_META_KEY, $contact_id );

			do_action( 'wpf_guest_contact_created', $contact_id, $order_data['user_email'] );

		} else {

			if ( empty( $user_id ) ) {

				wpf_log(
					'info',
					0,
					'Processing guest checkout for order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>, for existing contact #' . $contact_id . ':',
					array(
						'source'     => 'woocommerce',
						'meta_array' => $order_data,
					)
				);

				$result = wp_fusion()->crm->update_contact( $contact_id, $order_data );

				if ( is_wp_error( $result ) ) {
					wpf_log( 'error', $user_id, 'Error while updating contact: ' . $result->get_error_message(), array( 'source' => wp_fusion()->crm->slug ) );
					delete_transient( 'wpf_woo_started_' . $order_id );
					return false;
				}

				$order->update_meta_data( WPF_CONTACT_ID_META_KEY, $contact_id );

				do_action( 'wpf_guest_contact_updated', $contact_id, $order_data['user_email'] );

			} else {

				wp_fusion()->user->push_user_meta( $user_id, $order_data );

			}
		}

		$order->save(); // Save the contact ID.

		return $contact_id;

	}


	/**
	 * Get valid order statuses.
	 *
	 * 99% of the time we just want to apply tags for Processing and Completed
	 * orders, but there are some scenarios where a pending order or custom
	 * order status needs to apply tags, so this facilitates that.
	 *
	 * @since  3.36.0
	 * @since  3.36.7 Added $with_prefix parameter.
	 *
	 * @param  bool $with_prefix Whether or not to include the wc- prefix.
	 * @return array The valid order statuses.
	 */
	public function get_valid_order_statuses( $with_prefix = false ) {

		$order_statuses = array_keys( wc_get_order_statuses() );

		// Strip the wc- prefix.

		$order_statuses = array_map(
			function( $s ) {
				return substr( $s, 3 );
			},
			$order_statuses
		);

		// By default we don't want to do anything with these statuses.

		$ignore_statuses = array( 'pending', 'failed', 'on-hold' );
		$order_statuses  = array_diff( $order_statuses, $ignore_statuses );

		$order_statuses = apply_filters( 'wpf_woocommerce_order_statuses', $order_statuses );

		// Maybe add the wc- prefix (for WP_Querys based on post status).

		if ( true === $with_prefix ) {

			foreach ( $order_statuses as $i => $status ) {
				if ( 0 !== strpos( $status, 'wc-' ) ) {
					$order_statuses[ $i ] = 'wc-' . $status;
				}
			}
		}

		return $order_statuses;

	}

	/**
	 * Gets the tags to be applied for an item in an order.
	 *
	 * @since 3.41.29
	 *
	 * @param WC_Order_Item $item  The item.
	 * @param WC_Order      $order The order.
	 * @return array The tags.
	 */
	public function get_apply_tags_for_order_item( $item, $order ) {

		$apply_tags = array();
		$product_id = $item->get_product_id();

		// WooCommerce Global Cart support (for multisite).
		// @see woogc_woocommerce_cart_loop_start().
		// @link https://wpglobalcart.com/documentation/loop-though-the-cart-items/.
		if ( class_exists( 'WOOGC' ) ) {
			do_action( 'woocommerce/cart_loop/start', $item );
		}

		$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

		if ( class_exists( 'WOOGC' ) ) {
			do_action( 'woocommerce/cart_loop/end', $item );
		}

		// Apply tags for products
		if ( ! empty( $settings['apply_tags'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags'] );
		}

		$product = $item->get_product();

		$auto_tagging_prefix = wpf_get_option( 'woo_tagging_prefix', false );

		// Maybe insert the order status
		$auto_tagging_prefix = str_replace( '[status]', wc_get_order_status_name( $order->get_status() ), $auto_tagging_prefix );

		if ( ! empty( $auto_tagging_prefix ) ) {
			$auto_tagging_prefix = trim( $auto_tagging_prefix ) . ' ';
		}

		// Handling for deleted products
		if ( ! empty( $product ) ) {

			// Apply the tags for variations
			if ( $product->is_type( 'variation' ) ) {

				if ( isset( $settings['apply_tags_variation'] ) && ! empty( $settings['apply_tags_variation'][ $item['variation_id'] ] ) ) {

					// Old method where variation settings were stored on the product
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_variation'][ $item['variation_id'] ] );

				} else {

					$variation_settings = get_post_meta( $item['variation_id'], 'wpf-settings-woo', true );

					if ( is_array( $variation_settings ) && isset( $variation_settings['apply_tags_variation'][ $item['variation_id'] ] ) ) {

						$apply_tags = array_merge( $apply_tags, $variation_settings['apply_tags_variation'][ $item['variation_id'] ] );

					}
				}

				// For taxonomy tagging we need to exclude attributes
				$variation_attributes = $product->get_variation_attributes();

			}

			// Auto tagging based on name
			if ( wpf_get_option( 'woo_name_tagging' ) ) {

				if ( ! in_array( $auto_tagging_prefix . $product->get_title(), $apply_tags ) ) {

					$apply_tags[] = $auto_tagging_prefix . $product->get_title();

				}
			}

			// Auto tagging based on SKU
			if ( wpf_get_option( 'woo_sku_tagging' ) && ! empty( $product->get_sku() ) ) {

				if ( ! in_array( $auto_tagging_prefix . $product->get_sku(), $apply_tags ) ) {

					$apply_tags[] = $auto_tagging_prefix . $product->get_sku();

				}
			}
		}

		// Term stuff
		foreach ( get_object_taxonomies( 'product' ) as $product_taxonomy ) {

			$product_terms = get_the_terms( $product_id, $product_taxonomy );

			if ( ! empty( $product_terms ) ) {

				foreach ( $product_terms as $term ) {

					// For taxonomy tagging we need to exclude attributes
					if ( isset( $variation_attributes ) ) {

						foreach ( $variation_attributes as $key => $value ) {

							$key = str_replace( 'attribute_', '', $key );

							if ( $term->taxonomy == $key && $term->slug != $value ) {
								continue 2;
							}
						}
					}

					$term_tags = get_term_meta( $term->term_id, 'wpf-settings-woo', true );

					if ( ! empty( $term_tags ) && ! empty( $term_tags['apply_tags'] ) ) {

						$apply_tags = array_merge( $apply_tags, $term_tags['apply_tags'] );

					}

					if ( 'product_cat' == $product_taxonomy && wpf_get_option( 'woo_category_tagging' ) == true ) {

						if ( ! in_array( $auto_tagging_prefix . $term->name, $apply_tags ) ) {
							$apply_tags[] = $auto_tagging_prefix . $term->name;
						}
					}
				}
			}
		}

		return $apply_tags;

	}

	/**
	 * Async checkout script
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_async_checkout_script() {

		if ( is_checkout() && wpf_get_option( 'woo_async' ) ) {

			wp_enqueue_script( 'wpf-woocommerce-async', WPF_DIR_URL . 'assets/js/wpf-async-checkout.js', array( 'jquery' ), WP_FUSION_VERSION, true );

			$localize_data = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			);

			// Fallback for cases where it got missed during checkout (for example PayPal).
			if ( is_order_received_page() && isset( $_GET['key'] ) ) {

				$key      = wc_clean( wp_unslash( $_GET['key'] ) );
				$order_id = wc_get_order_id_by_order_key( $key );

				$order = wc_get_order( $order_id );

				if ( false === $order ) {
					return;
				}

				$completed = $order->get_meta( 'wpf_complete', true );
				$started   = get_transient( 'wpf_woo_started_' . $order_id );

				if ( empty( $completed ) && empty( $started ) && $order->is_paid() ) {
					$localize_data['pendingOrderID'] = $order_id;
				}
			}

			wp_localize_script( 'wpf-woocommerce-async', 'wpf_async', $localize_data );

		}

	}

	/**
	 * Async checkout callback
	 *
	 * @access public
	 * @return void
	 */

	public function async_checkout() {

		if ( empty( $_POST['order_id'] ) ) {
			wp_send_json_error();
		}

		$order_id = intval( wp_unslash( $_POST['order_id'] ) );

		$this->process_order( $order_id );

		wp_send_json_success();

	}

	/**
	 * Process order.
	 *
	 * The main order handler for WP Fusion. Creates a contact record in the
	 * CRM, applies any configured tags, and then fires an action so that
	 * Enhanced Ecommerce and other addons can run their actions.
	 *
	 * @since  3.36.0
	 * @since  3.38.26 Added second parameter $force.
	 *
	 * @link   https://wpfusion.com/documentation/filters/wpf_woocommerce_apply_tags_checkout/
	 * @link   https://wpfusion.com/documentation/filters/wpf_woocommerce_customer_data/
	 * @link   https://wpfusion.com/documentation/filters/wpf_woocommerce_user_id/
	 * @link   https://wpfusion.com/documentation/actions/wpf_woocommerce_payment_complete/
	 *
	 * @param  int  $order_id The order ID.
	 * @param  bool $force    Whether or not to process the order despite
	 *                        already being exported/locked.
	 * @return bool  Whether or not the order was processed successfully.
	 */
	public function process_order( $order_id, $force = false ) {

		// Clear the async fallback.
		wp_clear_scheduled_hook( 'wpf_handle_async_checkout_fallback', array( $order_id ) );

		// See if checkout process is already running.

		if ( get_transient( 'wpf_woo_started_' . $order_id ) ) {
			return true;
		} else {
			set_transient( 'wpf_woo_started_' . $order_id, true, HOUR_IN_SECONDS );
		}

		$order = wc_get_order( $order_id );

		if ( false === $order ) {

			wpf_log( 'error', 0, 'Unable to find order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>. Aborting.' );

			delete_transient( 'wpf_woo_started_' . $order_id );
			return false;

		}

		// Don't process orders that have already been processed.
		if ( ! $force && $order->get_meta( 'wpf_complete', true ) ) {
			return true;
		}

		$user_id = apply_filters( 'wpf_woocommerce_user_id', $order->get_user_id(), $order );
		$status  = $order->get_status();

		// Sometimes the status may have changed between when the function was called and when get_status() is run during an automated renewal.

		if ( 'woocommerce_order_status_failed' === current_filter() ) {
			$status = 'failed';
		}

		// If it's refunded or cancelled (for example during an export), handle that here.

		if ( in_array( $status, array( 'refunded', 'cancelled' ), true ) ) {

			$this->order_status_refunded( $order_id );

			delete_transient( 'wpf_woo_started_' . $order_id );
			return true;
		}

		// These statuses are eligibible for applying tags.

		$valid_statuses = $this->get_valid_order_statuses();

		if ( has_action( "woocommerce_order_status_{$status}", array( $this, 'process_order' ) ) ) {

			// This is a little bit of magic so that if someone has registered a callback to this function on their custom order status,
			// it will work automatically without also needing to make use of the wpf_woocommerce_order_statuses filter.
			//
			// @link https://wpfusion.com/documentation/ecommerce/woocommerce/#register-additional-statuses-for-sync.

			$valid_statuses[] = $status;
		}

		// Logger.
		wpf_log( 'info', $user_id, 'New ' . $status . ' WooCommerce order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>' );

		// Create / update a contact record for the customer in the CRM.

		$contact_id = $this->create_update_customer( $order );

		if ( false === $contact_id ) {
			return false; // If creating the contact failed for some reason.
		}

		$apply_tags  = array();
		$remove_tags = array();

		// Possibly apply tags for any configured coupons.
		if ( method_exists( $order, 'get_coupon_codes' ) ) {

			$coupons = $order->get_coupon_codes();

			if ( ! empty( $coupons ) ) {

				foreach ( $coupons as $coupon_code ) {

					$coupon_id = wc_get_coupon_id_by_code( $coupon_code );

					$settings = get_post_meta( $coupon_id, 'wpf-settings-woo', true );

					if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {
						$apply_tags = array_merge( $apply_tags, $settings['apply_tags'] );
					}
				}
			}
		}

		if ( in_array( $status, $valid_statuses ) ) {

			// Get global tags
			$global_tags = wpf_get_option( 'woo_tags', array() );

			if ( ! empty( $global_tags ) ) {
				$apply_tags = array_merge( $apply_tags, $global_tags );
			}

			foreach ( $order->get_items() as $item ) {

				$apply_tags = array_merge( $apply_tags, $this->get_apply_tags_for_order_item( $item, $order ) );

				// WooCommerce Global Cart support (for multisite).
				// @see woogc_woocommerce_cart_loop_start().
				// @link https://wpglobalcart.com/documentation/loop-though-the-cart-items/.
				if ( class_exists( 'WOOGC' ) ) {
					do_action( 'woocommerce/cart_loop/start', $item );
				}

				// Get the settings (for transaction failed tags).
				$settings = get_post_meta( $item->get_product_id(), 'wpf-settings-woo', true );

				if ( class_exists( 'WOOGC' ) ) {
					do_action( 'woocommerce/cart_loop/end', $item );
				}

				// Remove transaction failed tags
				if ( ! empty( $settings['apply_tags_failed'] ) ) {
					$remove_tags = array_merge( $remove_tags, $settings['apply_tags_failed'] );
				}
			}
		}

		// Get global status tags
		$status_tags = wpf_get_option( 'woo_status_tagging_wc-' . $status, array() );

		if ( ! empty( $status_tags ) ) {
			$apply_tags = array_merge( $apply_tags, $status_tags );
		}

		// Optin tags
		if ( $order->get_meta( 'email_optin', true ) && ! empty( wpf_get_option( 'email_optin_tags' ) ) ) {
			$apply_tags = array_merge( $apply_tags, wpf_get_option( 'email_optin_tags' ) );
		}

		// "Create Tag(s) from value" tags
		if ( ! empty( $this->dynamic_tags ) ) {
			$apply_tags = array_merge( $apply_tags, $this->dynamic_tags );
		}

		// Remove duplicates and empties
		$apply_tags  = array_filter( array_unique( $apply_tags ) );
		$remove_tags = array_filter( array_unique( $remove_tags ) );

		// Remove transaction failed tags
		if ( ! empty( $remove_tags ) ) {

			if ( empty( $user_id ) ) {

				wpf_log( 'info', 0, 'Removing transaction failed tags, guest checkout for contact #' . $contact_id . ': ', array( 'tag_array' => $remove_tags ) );
				wp_fusion()->crm->remove_tags( $remove_tags, $contact_id );

			} else {

				// Registered users
				wp_fusion()->user->remove_tags( $remove_tags, $user_id );

			}
		}

		$apply_tags = apply_filters( 'wpf_woocommerce_apply_tags_checkout', $apply_tags, $order );

		// Apply the tags
		if ( ! empty( $apply_tags ) ) {

			if ( empty( $user_id ) || empty( wpf_get_contact_id( $user_id ) ) ) {

				// Guest checkout
				wpf_log( 'info', 0, 'Applying tags to guest checkout for contact #' . $contact_id . ': ', array( 'tag_array' => $apply_tags ) );
				$result = wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			} else {

				// Registered users
				$result = wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			}

			if ( is_wp_error( $result ) ) {
				$order->add_order_note( 'Error applying tags for order ID: ' . $order_id . '. ' . $result->get_error_message() );
				wpf_log( 'error', 0, 'Error <strong>' . $result->get_error_message() . '</strong> while applying tags: ', array( 'tag_array' => $apply_tags ) );
			}
		}

		$valid_statuses = apply_filters( 'wpf_woocommerce_order_statuses_for_payment_complete', $valid_statuses, $order );

		if ( in_array( $status, $valid_statuses ) ) {

			// Denotes that the WPF actions have already run for this order.
			$order->update_meta_data( 'wpf_complete', current_time( 'Y-m-d H:i:s' ) );

			// Run payment complete action.
			do_action( 'wpf_woocommerce_payment_complete', $order_id, $contact_id );

			$message = __( 'WP Fusion order actions completed.', 'wp-fusion' );

			if ( isset( $_GET['order_action'] ) && 'wpf_process' === $_GET['order_action'] ) {
				$message .= ' ' . sprintf( __( 'Order was manually processed by %s.', 'wp-fusion' ), wp_get_current_user()->display_name );
			}

			$order->add_order_note( $message );
			$order->save(); // Save the wpf_complete flag.

		}

		// Order is finished, remove locking
		delete_transient( 'wpf_woo_started_' . $order_id );

	}

	/**
	 * Handle bulk actions.
	 *
	 * Unhook the status change watch when bulk-editing more than 20 orders in
	 * the admin, to prevent a timeout.
	 *
	 * @since  3.38.17
	 *
	 * @param  string $redirect_to The redirect URL.
	 * @param  string $action      The action.
	 * @param  array  $ids         The order IDs.
	 * @return string The redirect URL.
	 */
	public function handle_bulk_actions( $redirect_to, $action, $ids ) {

		if ( count( $ids ) > 20 ) {

			remove_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ), 10, 4 );
			remove_action( 'woocommerce_order_status_processing', array( $this, 'woocommerce_apply_tags_checkout' ), 10 );
			remove_action( 'woocommerce_order_status_completed', array( $this, 'woocommerce_apply_tags_checkout' ), 10 );

			wpf_log( 'notice', 0, 'Bulk order status change detected for ' . count( $ids ) . ' orders. To avoid a timeout, no data will be synced to ' . wp_fusion()->crm->name . '.' );
		}

		return $redirect_to;

	}

	/**
	 * Apply tags at checkout.
	 *
	 * This is the OG function for applying tags at checkout, thus the awkward
	 * name.
	 *
	 * This is hooked to woocommerce_order_status_processing and
	 * woocommerce_order_status_completed, and determines whether the order
	 * needs to be pushed to the async queue, or processed immediately.
	 *
	 * @since  1.0.2
	 * @since  3.38.26 Removed second parameter $force.
	 * @see    process_order()
	 *
	 * @param  int $order_id The order ID.
	 * @return bool  Whether or not the order was successfully processed.
	 */
	public function woocommerce_apply_tags_checkout( $order_id ) {

		$order = wc_get_order( $order_id );
		// Prevents the API calls being sent multiple times for the same order.

		if ( $order->get_meta( 'wpf_complete', true ) ) {
			return true;
		}

		// Handle async request if async enabled and we're currently in an AJAX
		// checkout. Don't run on AJAX order status changes in the admin, or
		// during a REST request (like a PayPal IPN).

		if ( wpf_get_option( 'woo_async' ) && wp_doing_ajax() && ! defined( 'REST_REQUEST' ) && ! doing_action( 'woocommerce_scheduled_subscription_payment' ) && ( ! isset( $_REQUEST['action'] ) || 'woocommerce_mark_order_status' !== $_REQUEST['action'] ) ) {

			// New method. Do nothing, it will come later via the AJAX request.
			wpf_log( 'info', $order->get_user_id(), 'New WooCommerce order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>, will be processed via Asynchronous Checkout.' );

			// Add a cron task just in case it gets missed somehow.
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wpf_handle_async_checkout_fallback', array( $order_id ) );

			return true;

		}

		// Regular checkout.

		return $this->process_order( $order_id );

	}

	/**
	 * Triggered when an order is refunded / cancelled.
	 *
	 * @since unknown
	 * @since 3.41.38 Moved from order_status_refunded to order_fully_refunded.
	 *
	 * @param WC_Order|int $order The WooCommerce order or order ID.
	 */
	public function order_status_refunded( $order ) {

		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		$order_id   = $order->get_id();
		$user_id    = $order->get_user_id();
		$contact_id = $this->get_contact_id_from_order( $order );

		if ( ! $contact_id ) {
			$contact_id = $this->create_update_customer( $order ); // create the contact if necessary.
		}

		if ( empty( $user_id ) && empty( $contact_id ) ) {
			wpf_log( 'error', 0, 'Unable to process refund actions for order #' . $order_id . '. No user or contact record found.' );
			return;
		}

		// Check to see if the order's parent subscription is still active.

		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {

			foreach ( wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) ) as $subscription ) {

				if ( $subscription->has_status( 'active' ) ) {
					wpf_log( 'notice', $user_id, 'WooCommerce order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a> was refunded, but the parent subscription <a href="' . admin_url( 'post.php?post=' . $subscription->get_id() . '&action=edit' ) . '" target="_blank">#' . $subscription->get_id() . '</a> is still active, so no tags will be modified.' );
					return;
				}
			}
		}

		wpf_log( 'info', $user_id, 'Processing refund actions for WooCommerce order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a>.' );

		$items = $order->get_items();

		$auto_tagging_prefix = wpf_get_option( 'woo_tagging_prefix', false );

		if ( ! empty( $auto_tagging_prefix ) ) {
			$auto_tagging_prefix = trim( $auto_tagging_prefix ) . ' ';
		}

		$remove_tags = array();

		foreach ( $items as $item ) {

			$product_id = $item->get_product_id();
			$settings   = get_post_meta( $product_id, 'wpf-settings-woo', true );

			if ( ! empty( $settings['apply_tags_refunded'] ) && ( $order->has_status( 'refunded' ) || doing_action( 'woocommerce_order_fully_refunded' ) ) ) {

				if ( ! empty( $user_id ) ) {
					wp_fusion()->user->apply_tags( $settings['apply_tags_refunded'], $user_id );
				} else {
					wpf_log( 'info', $user_id, 'Applying refund tags to contact #' . $contact_id, array( 'tag_array' => $settings['apply_tags_refunded'] ) );
					wp_fusion()->crm->apply_tags( $settings['apply_tags_refunded'], $contact_id );
				}
			}

			// This is to prevent situations where the customer may have accidentally checked out twice. The duplicate order is refunded, but they
			// still have an active subscription. So we don't want the tags to be removed.

			if ( function_exists( 'wcs_user_has_subscription' ) && wcs_user_has_subscription( $user_id, $product_id, 'active' ) ) {
				wpf_log( 'notice', $user_id, 'WooCommerce order <a href="' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ) . '" target="_blank">#' . $order_id . '</a> was refunded, but user still has an active subscription to product <strong>' . get_the_title( $product_id ) . '</strong>, so no tags will be removed.' );
				continue;
			}


			if ( ! empty( $settings['apply_tags'] ) ) {
				$remove_tags = array_merge( $remove_tags, $settings['apply_tags'] );
			}

			// Variations.
			if ( isset( $item['variation_id'] ) && $item['variation_id'] != 0 ) {

				if ( isset( $settings['apply_tags_variation'] ) && ! empty( $settings['apply_tags_variation'][ $item['variation_id'] ] ) ) {

					$variation_tags = $settings['apply_tags_variation'][ $item['variation_id'] ];

				} else {

					$variation_settings = get_post_meta( $item['variation_id'], 'wpf-settings-woo', true );

					if ( is_array( $variation_settings ) && isset( $variation_settings['apply_tags_variation'][ $item['variation_id'] ] ) ) {

						$variation_tags = $variation_settings['apply_tags_variation'][ $item['variation_id'] ];

					}
				}

				if ( ! empty( $variation_tags ) ) {

					$remove_tags = array_merge( $remove_tags, $variation_tags );
				}
			}
		}

		if ( ! empty( $remove_tags ) ) {

			if ( ! empty( $user_id ) ) {
				wp_fusion()->user->remove_tags( $remove_tags, $user_id );
			} else {

				wpf_log( 'info', $user_id, 'Removing tags from contact #' . $contact_id, array( 'tag_array' => $remove_tags ) );

				wp_fusion()->crm->remove_tags( $remove_tags, $contact_id );
			}
		}

	}



	/**
	 * Get product quantity from order.
	 *
	 * @since 3.40.23
	 *
	 * @param WC_Order $order      WC_Order.
	 * @param int      $product_id Product id.
	 *
	 * @return int The quantity refunded.
	 */
	private function get_order_product_qty( $order, $product_id ) {
		$items    = $order->get_items();
		$quantity = 0;
		foreach ( $order->get_items() as $item ) {
			if ( intval( $product_id ) === intval( $item->get_product_id() ) ) {
				$quantity = $item->get_quantity();
			}
		}
		return $quantity;
	}

	/**
	 * Runs when a product is partially refunded.
	 *
	 * @since 3.40.23
	 *
	 * @param int $order_id  The order id.
	 * @param int $refund_id The refund id.
	 */
	public function order_partially_refunded( $order_id, $refund_id ) {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Get refunded products.
		$refund         = new WC_Order_Refund( $refund_id );
		$refunded_items = $refund->get_items( 'line_item' );

		// No product was refunded, it was just a partial refund.
		if ( empty( $refunded_items ) ) {
			return false;
		}

		// Get user ID.
		$user_id = apply_filters( 'wpf_woocommerce_user_id', $order->get_user_id(), $order );
		if ( empty( $user_id ) ) {
			$email = apply_filters( 'wpf_woocommerce_billing_email', $order->get_billing_email(), $order );

			// Try seeing if an existing contact ID exists
			$contact_id = wp_fusion()->crm->get_contact_id( $email );

			if ( is_wp_error( $contact_id ) ) {

				wpf_log( $contact_id->get_error_code(), $user_id, 'Error getting contact ID for <strong>' . $email . '</strong>: ' . $contact_id->get_error_message() );
				return false;

			}
		}

		$remove_tags         = array();
		$apply_refunded_tags = array();

		foreach ( $refund->get_items() as $refunded_item ) {

			$product_id = intval( $refunded_item->get_meta( '_product_id' ) );
			$item_id    = intval( $refunded_item->get_meta( '_refunded_item_id' ) );

			foreach ( $order->get_items() as $order_item ) {

				if ( $order_item->get_product_id() === $product_id ) {

					// Let's make sure the item was fully refunded.
					if ( abs( $order->get_total_refunded_for_item( $item_id ) ) < $order_item->get_total() ) {
						continue 2;
					}
				}
			}

			$settings = get_post_meta( $product_id, 'wpf-settings-woo', true );

			if ( ! empty( $settings ) ) {
				if ( ! empty( $settings['apply_tags'] ) ) {
					$remove_tags = array_merge( $remove_tags, $settings['apply_tags'] );
				}

				if ( ! empty( $settings['apply_tags_refunded'] ) ) {
					$apply_refunded_tags = array_merge( $apply_refunded_tags, $settings['apply_tags_refunded'] );
				}
			}
		}

		// Remove applied tags from refunded products.
		if ( ! empty( $remove_tags ) ) {

			if ( empty( $user_id ) ) {

				wpf_log( 'info', 0, 'Removing applied tags for a partially refunded order for contact #' . $contact_id . ': ', array( 'tag_array' => $remove_tags ) );
				wp_fusion()->crm->remove_tags( $remove_tags, $contact_id );

			} else {

				wp_fusion()->user->remove_tags( $remove_tags, $user_id );

			}
		}

		if ( ! empty( $settings['apply_tags_refunded'] ) && ! empty( $apply_refunded_tags ) ) {

			if ( ! empty( $user_id ) ) {
				wp_fusion()->user->apply_tags( $settings['apply_tags_refunded'], $user_id );
			} else {
				wp_fusion()->crm->apply_tags( $settings['apply_tags_refunded'], $contact_id );
			}
		}

	}


	/**
	 * Order status changed.
	 *
	 * Runs when an order status changes and applies tags specific to that order
	 * status, creating a contact record in the CRM first if necessary.
	 *
	 * @since 3.36.1
	 *
	 * @param int      $order_id   The order ID.
	 * @param string   $old_status The old order status.
	 * @param string   $new_status The new order status.
	 * @param WC_Order $order      The order object.
	 */

	public function order_status_changed( $order_id, $old_status, $new_status, $order ) {

		if ( 'processing' == $new_status && in_array( 'processing', $this->get_valid_order_statuses() ) ) {
			// This is going to be processed by the main process_order() function so no need to duplicate it here.
			return;
		}

		// Generic tags for the status.

		$apply_tags = wpf_get_option( 'woo_status_tagging_wc-' . $new_status, array() );

		if ( 'failed' === $new_status ) {

			// Possibly get failed transaction tags from the order products. We won't do
			// this in the case of a failed renewal payment on a subscription since that's
			// already handled by the Woo Subscriptions integration.

			if ( ! function_exists( 'wcs_order_contains_renewal' ) || ! wcs_order_contains_renewal( $order ) ) {

				foreach ( $order->get_items() as $item ) {

					$product_id = $item->get_product_id();
					$settings   = get_post_meta( $product_id, 'wpf-settings-woo', true );

					// Apply tags for products.
					if ( ! empty( $settings ) && ! empty( $settings['apply_tags_failed'] ) ) {
						$apply_tags = array_merge( $apply_tags, $settings['apply_tags_failed'] );
					}
				}
			}
		}

		// If no tags to apply, there's nothing to do

		if ( empty( $apply_tags ) ) {
			return;
		}

		// If this is an initial failed or pending transaction we may need to create a contact before tags can be applied.

		$contact_id = $this->get_contact_id_from_order( $order );

		if ( ! $contact_id ) {
			$contact_id = $this->create_update_customer( $order );
		}

		$user_id = $order->get_user_id();

		if ( $user_id ) {

			// Registered users
			wp_fusion()->user->apply_tags( $apply_tags, $user_id );

		} else {

			wpf_log(
				'info',
				0,
				'Order status changed to <strong>' . $new_status . '</strong>. Applying tags to contact #' . $contact_id . ' (' . $order->get_billing_email() . '):',
				array(
					'tag_array' => $apply_tags,
					'source'    => 'woocommerce',
				)
			);

			wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );
		}

		$order->add_order_note( 'WP Fusion order actions completed for status ' . $new_status . '.' );

	}

	/**
	 * Insert new order.
	 *
	 * An order status transition does not always happen when a new pending
	 * order is inserted, so this ensures that any tags set for the Pending
	 * status get applied.
	 *
	 * @since 3.36.1
	 * @since 3.37.3 Updated to watch for on-hold orders as well.
	 *
	 * @param int $order_id The order ID.
	 */

	public function new_order( $order_id ) {

		$order  = wc_get_order( $order_id );
		$status = $order->get_status();

		if ( has_action( 'woocommerce_order_status_' . $status, array( $this, 'process_order' ) ) ) {
			// For Pending or On Hold orders added via the admin, there's no status change so the
			// normal transition hooks aren't triggered.
			$this->process_order( $order_id );
		}

		// Maybe process a status transition to pending.
		if ( 'pending' === $status && ! did_action( 'woocommerce_order_status_changed' ) ) {
			$this->order_status_changed( $order_id, '', 'pending', $order );
		}

	}

	/**
	 * Pushes password fields for when Woo is set to auto generate password
	 *
	 * @access public
	 * @return void
	 */

	public function push_autogen_password( $customer_id, $new_customer_data, $password_generated ) {

		if ( false == $password_generated ) {
			return;
		}

		$update_data = array(
			'user_pass'          => $new_customer_data['user_pass'],
			'generated_password' => $new_customer_data['user_pass'],
		);

		wp_fusion()->user->push_user_meta( $customer_id, $update_data );

	}

	/**
	 * Outputs custom panels to WooCommerce product config screen
	 *
	 * @access public
	 * @return mixed
	 */

	public function woocommerce_write_panels() {

		if ( ! is_admin() ) {
			return; // YITH WooCommerce Frontend Manager adds these panels to the frontend, which crashes WPF
		}

		if ( wpf_get_option( 'admin_permissions' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wpf_meta_box_woo', 'wpf_meta_box_woo_nonce' );

		echo '<div id="wp_fusion_tab" class="panel woocommerce_options_panel wpf-meta">';

		global $post;

		// Writes the panel content
		do_action( 'wpf_woocommerce_panel', $post->ID );

		echo '</div>';

	}

	/**
	 * Displays "apply tags" field on the WPF product configuration panel
	 *
	 * @access public
	 * @return mixed
	 */

	public function panel_content( $post_id ) {

		$settings = array(
			'apply_tags'          => array(),
			'apply_tags_refunded' => array(),
			'apply_tags_failed'   => array(),
		);

		if ( get_post_meta( $post_id, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group wpf-product">';

		echo '<p class="form-field"><label><strong>' . esc_html__( 'Product', 'wp-fusion' ) . '</strong></label></p>';

		echo '<p>' . sprintf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/ecommerce/woocommerce/" target="_blank">', '</a>' ) . '</p>';

		echo '<p class="form-field"><label for="wpf-apply-tags-woo">' . __( 'Apply tags when<br />purchased', 'wp-fusion' ) . '</label>';
		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags',
			)
		);

		echo '<br /><span style="margin-left: 0px;" class="description show_if_variable">' . __( 'Tags for product variations can be configured within the variations tab.', 'wp-fusion' ) . '</span>';

		echo '</p>';

		echo '<p class="form-field"><label for="wpf-apply-tags-woo">' . __( 'Apply tags when<br />refunded', 'wp-fusion' );
		echo ' <span class="dashicons dashicons-editor-help wpf-tip wpf-tip-bottom" data-tip="' . __( 'The tags specified above for \'Apply tags when purchased\' will automatically be removed if an order is refunded.', 'wp-fusion' ) . '"></span>';
		echo '</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_refunded'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_refunded',
			)
		);

		echo '</p>';

		echo '<p class="form-field"><label for="wpf-apply-tags-woo">' . __( 'Apply tags when transaction failed', 'wp-fusion' );
		echo ' <span class="dashicons dashicons-editor-help wpf-tip wpf-tip-bottom" data-tip="' . __( 'A contact record will be created and these tags will be applied when an initial transaction on an order fails.<br /><br />Note that this may create problems since WP Fusion normally doesn\'t create a contact record until a successful payment is received.<br /><br />In almost all cases it\'s preferable to use abandoned cart tracking instead of failed transaction tagging.', 'wp-fusion' ) . '"></span>';
		echo '</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_failed'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_failed',
			)
		);

		echo '</p>';

		echo '</div>';

	}


	/**
	 * Adds tabs to left side of Woo product editor panel
	 *
	 * @access public
	 * @return mixed
	 */
	public function woocommerce_write_panel_tabs() {

		if ( ! is_admin() ) {
			return; // YITH WooCommerce Frontend Manager adds these panels to the frontend, which crashes WPF.
		}

		if ( wpf_get_option( 'admin_permissions' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<li class="custom_tab wp-fusion-settings-tab hide_if_grouped">';
		echo '<a href="#wp_fusion_tab">';
		echo wpf_logo_svg( '14px' ); // phpcs:ignore
		echo '<span> ' . esc_html_e( 'WP Fusion', 'wp-fusion' ) . '</span>';
		echo '</a>';
		echo '</li>';

	}


	/**
	 * Adds tag multiselect to variation fields
	 *
	 * @access public
	 * @return mixed
	 */

	public function variable_fields( $loop, $variation_data, $variation ) {

		$defaults = array(
			'apply_tags_variation'     => array( $variation->ID => array() ),
			'allow_tags_variation'     => array( $variation->ID => array() ),
			'allow_tags_not_variation' => array( $variation->ID => array() ),
		);

		if ( ! isset( $variation_data['wpf-settings-woo'] ) ) {
			$settings = array();
		} else {
			$settings = maybe_unserialize( $variation_data['wpf-settings-woo'][0] );
		}

		$settings = wp_parse_args( $settings, $defaults );

		echo '<div><p class="form-row form-row-full"><label for="wpf-apply-tags-woo">' . sprintf( __( 'Apply these tags in %s when purchased', 'wp-fusion' ), wp_fusion()->crm->name ) . ':</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_variation'][ $variation->ID ],
				'meta_name' => "wpf-settings-woo-variation[apply_tags_variation][$variation->ID]",
				'read_only' => true,
			)
		);

		echo '</p></div>';

		if ( wpf_get_option( 'restrict_content', true ) ) {

			// Restrict access to variations.
			echo '<div><p class="form-row form-row-full"><label for="wpf-allow-tags-woo"><strong>' . __( 'Required tags (any)', 'wp-fusion' ) . '. </strong>' . __( 'If the user doesn\'t have <em>any</em> of these tags, the variation will not show as an option for purchase:', 'wp-fusion' ) . '</label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['allow_tags_variation'][ $variation->ID ],
					'meta_name' => "wpf-settings-woo-variation[allow_tags_variation][$variation->ID]",
					'read_only' => true,
				)
			);

			echo '</p></div>';

			echo '<div><p class="form-row form-row-full"><label for="wpf-allow-tags-woo"><strong>' . __( 'Required tags (not)', 'wp-fusion' ) . '. </strong>' . __( 'If the user <em>has</em> any of these tags, the variation will not show as an option for purchase:', 'wp-fusion' ) . '</label>';

			wpf_render_tag_multiselect(
				array(
					'setting'   => $settings['allow_tags_not_variation'][ $variation->ID ],
					'meta_name' => "wpf-settings-woo-variation[allow_tags_not_variation][$variation->ID]",
					'read_only' => true,
				)
			);

			echo '</p></div>';

		}

		do_action( 'wpf_woocommerce_variation_panel', $variation->ID, $settings );

	}

	/**
	 * Saves variable field data to product
	 *
	 * @access public
	 * @return mixed
	 */

	public function save_variable_fields( $variation_id, $i ) {

		if ( isset( $_POST['wpf-settings-woo-variation'] ) ) {
			$data = $_POST['wpf-settings-woo-variation'];
		} else {
			$data = array();
		}

		// Clean up settings from other variations getting stored with this one
		foreach ( $data as $setting_type => $setting ) {

			if ( ! empty( $setting ) ) {

				foreach ( $setting as $posted_variation_id => $tags ) {

					if ( $posted_variation_id != $variation_id ) {

						unset( $data[ $setting_type ][ $posted_variation_id ] );

					}
				}
			}
		}

		// Variation restriction tags (saved as postmeta to the variation ID now that WooCommerce isn't as shitty as it used to be)
		update_post_meta( $variation_id, 'wpf-settings-woo', $data );

		// Save the normal access restrictions as well so WPF_Access_Control can do its thing
		if ( isset( $data['allow_tags_variation'] ) || isset( $data['allow_tags_not_variation'] ) ) {

			$settings = array();

			if ( isset( $data['allow_tags_variation'] ) && ! empty( array_filter( (array) $data['allow_tags_variation'][ $variation_id ] ) ) ) {
				$settings['lock_content'] = true;
				$settings['allow_tags']   = $data['allow_tags_variation'][ $variation_id ];
			}

			if ( isset( $data['allow_tags_not_variation'] ) && ! empty( array_filter( (array) $data['allow_tags_not_variation'][ $variation_id ] ) ) ) {
				$settings['allow_tags_not'] = $data['allow_tags_not_variation'][ $variation_id ];
			}

			update_post_meta( $variation_id, 'wpf-settings', $settings );

		} else {

			delete_post_meta( $variation_id, 'wpf-settings' );

		}

		// Clean up old data storage
		$post_id = $_POST['product_id'];

		$post_meta = get_post_meta( $post_id, 'wpf-settings-woo', true );

		if ( isset( $post_meta['apply_tags_variation'] ) ) {

			unset( $post_meta['apply_tags_variation'] );
			update_post_meta( $post_id, 'wpf-settings-woo', $post_meta );

		}

	}


	/**
	 * Saves WPF configuration to product
	 *
	 * @access public
	 * @return mixed
	 */

	public function save_meta_box_data( $post_id ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_woo_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['wpf_meta_box_woo_nonce'], 'wpf_meta_box_woo' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Don't update on revisions
		if ( $_POST['post_type'] != 'product' ) {
			return;
		}

		if ( isset( $_POST['wpf-settings-woo'] ) ) {
			$data = $_POST['wpf-settings-woo'];
		} else {
			$data = array();
		}

		// Update the meta field in the database.
		update_post_meta( $post_id, 'wpf-settings-woo', $data );

	}

	/**
	 * //
	 * // Order Actions
	 * //
	 **/

	/**
	 * Adds WP Fusion option to Order Actions dropdown
	 *
	 * @access public
	 * @return array Actions
	 */

	public function order_actions( $actions ) {

		global $post; // $post will be null when using HPOS.

		if ( is_null( $post ) || 'shop_order' === $post->post_type ) {
			$actions['wpf_process'] = __( 'Process WP Fusion actions again', 'wp-fusion' );
		}

		return $actions;

	}

	/**
	 * Processes order action
	 *
	 * @access public
	 * @return void
	 */

	public function process_order_action( $order ) {

		delete_post_meta( $order->get_id(), 'wpf_ec_complete' ); // This allows Enhanced Ecommerce to run a second time.

		delete_transient( 'wpf_woo_started_' . $order->get_id() ); // unlock the order in case it crashed last time.

		add_filter( 'wpf_prevent_reapply_tags', '__return_false' ); // allow tags to be sent again despite the cache.

		// Force lookup the user ID.

		wpf_get_contact_id( $order->get_user_id(), true );

		wp_fusion()->logger->add_source( 'order-actions' );

		$this->process_order( $order->get_id(), true );

		do_action( 'wpf_woocommerce_process_order_actions_again', $order );

		wp_safe_redirect( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) );
		exit;

	}

	/**
	 * //
	 * // Coupons
	 * //
	 **/

	/**
	 * Adds WP Fusion settings tab to coupon config
	 *
	 * @access public
	 * @return array Tabs
	 */

	public function coupon_tabs( $tabs ) {

		$tabs['wp_fusion'] = array(
			'label'  => __( 'WP Fusion', 'wp-fusion' ),
			'target' => 'wp_fusion_tab',
			'class'  => '',
		);

		return $tabs;

	}

	/**
	 * Output for coupon data panel
	 *
	 * @access public
	 * @return mixed
	 */

	public function coupon_data_panel() {

		echo '<div id="wp_fusion_tab" class="panel woocommerce_options_panel">';

		echo '<div class="options_group">';

		global $post;

		$settings = array(
			'apply_tags'         => array(),
			'auto_apply_tags'    => array(),
			'coupon_label'       => false,
			'coupon_applied_msg' => false,
		);

		if ( get_post_meta( $post->ID, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings-woo', true ) );
		}

		echo '<p class="form-field"><label><strong>' . __( 'Coupon Settings', 'wp-fusion' ) . '</strong></label></p>';

		echo '<p class="form-field"><label for="wpf-apply-tags-woo">' . __( 'Apply tags when used', 'wp-fusion' ) . '</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags',
			)
		);

		echo '</p>';

		echo '</div>';
		echo '<div class="options_group">';

		echo '<p class="form-field"><label><strong>Auto-apply Discounts</strong></label></p>';

		echo '<p class="form-field"><label>' . __( 'Auto-apply tags', 'wp-fusion' ) . '</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['auto_apply_tags'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'auto_apply_tags',
				'read_only' => true,
			)
		);

		echo '<span class="description"><small>' . __( 'If the user has any of the tags specified here, the coupon will automatically be applied to their cart.', 'wp-fusion' ) . '</small></span>';

		echo '</p>';

		echo '<p class="form-field"><label>' . __( 'Discount label', 'wp-fusion' ) . '</label>';

		echo '<input type="text" class="short" style="" name="wpf-settings-woo[coupon_label]" value="' . $settings['coupon_label'] . '" placeholder="Coupon">';

		echo '<span class="description" style="display: block; clear: both; margin-left: 0px;"><small>' . __( 'Use this setting to override the coupon label at checkout when a coupon has been auto-applied.<br />For example "Discount" or "Promo". (Leave blank for default)', 'wp-fusion' ) . '</small></span>';

		echo '</p>';

		echo '<p class="form-field"><label>' . __( 'Discount message', 'wp-fusion' ) . '</label>';

		echo '<input type="text" class="short" style="" name="wpf-settings-woo[coupon_applied_msg]" value="' . $settings['coupon_applied_msg'] . '" placeholder="Coupon code applied successfully.">';

		echo '<span class="description" style="display: block; clear: both; margin-left: 0px;"><small>' . __( 'Use this setting to override the message at checkout when a coupon has been auto-applied.<br />For example "You received a discount!". (Leave blank for default)', 'wp-fusion' ) . '</small></span>';

		echo '</p>';

		echo '<br />';

		do_action( 'wpf_woocommerce_coupon_panel', $post->ID, $settings );

		echo '</div>';

		echo '</div>';

	}

	/**
	 * Output for coupon usage restriction settings
	 *
	 * @access public
	 * @return mixed
	 */

	public function coupon_usage_restriction( $coupon_id, $coupon ) {

		$settings = array(
			'allow_tags' => array(),
		);

		if ( get_post_meta( $coupon_id, 'wpf-settings', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $coupon_id, 'wpf-settings', true ) );
		}

		echo '<div class="options_group">';

		echo '<p class="form-field"><label>' . __( 'Required tags', 'wp-fusion' ) . '</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['allow_tags'],
				'meta_name' => 'wpf-settings',
				'field_id'  => 'allow_tags',
				'read_only' => true,
			)
		);

		echo '<span class="description"><small>' . __( 'The user must be logged in and have one of the specified tags to use the coupon.', 'wp-fusion' ) . '</small></span>';

		echo '</p>';

		echo '</div>';

	}

	/**
	 * Saves WPF configuration to product
	 *
	 * @access public
	 * @return mixed
	 */

	public function save_meta_box_data_coupon( $post_id ) {

		if ( isset( $_POST['wpf-settings-woo'] ) ) {

			$data = array_filter( $_POST['wpf-settings-woo'] );

		} else {
			$data = array();
		}

		if ( ! empty( $data ) ) {
			// Update coupon-specific stuff.
			update_post_meta( $post_id, 'wpf-settings-woo', $data );
		} else {
			delete_post_meta( $post_id, 'wpf-settings-woo' );
		}

		// Update coupon restrictions
		if ( isset( $_POST['wpf-settings'] ) ) {

			$_POST['wpf-settings']['lock_content'] = true;

			$data = $_POST['wpf-settings'];

			update_post_meta( $post_id, 'wpf-settings', $data );

		} else {
			delete_post_meta( $post_id, 'wpf-settings' );
		}

	}

	/**
	 * Check if coupon is valid before applying
	 *
	 * @access public
	 * @return bool Valid
	 */

	public function coupon_is_valid( $valid, $coupon, $discount ) {

		if ( ! wp_fusion()->access->user_can_access( $coupon->get_id() ) ) {
			$valid = false;
		}

		return $valid;

	}

	/**
	 * Applies any linked coupons when tags are modified or when an item is added to the cart.
	 *
	 * @access public
	 * @return void
	 */

	public function maybe_apply_coupons() {

		if ( ! wpf_is_user_logged_in() || empty( WC()->cart ) || ! did_action( 'wp_loaded' ) ) {
			return;
		}

		remove_action( 'woocommerce_add_to_cart', array( $this, 'maybe_apply_coupons' ), 30 ); // don't need to do this twice.
		remove_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'maybe_apply_coupons' ) ); // fixes an infinite loop with SUMO Subscriptions.

		WC()->cart->calculate_totals();

		if ( 0 === intval( WC()->cart->get_cart_contents_total() ) ) {
			return; // if the cart is already 0, don't bother.
		}

		// When called from woocommerce_add_to_cart.
		$user_id   = wpf_get_current_user_id();
		$user_tags = wpf_get_tags( $user_id );

		if ( empty( $user_tags ) ) {
			return;
		}

		$args = array(
			'numberposts' => 200,
			'post_type'   => 'shop_coupon',
			'fields'      => 'ids',
			'orderby'     => 'meta_value_num',
			'meta_key'    => 'coupon_amount',
			'meta_query'  => array(
				array(
					'key'     => 'wpf-settings-woo',
					'compare' => 'EXISTS',
				),
			),
		);

		$coupons = get_posts( $args );

		if ( empty( $coupons ) ) {
			return;
		}

		foreach ( $coupons as $coupon_id ) {

			$settings = get_post_meta( $coupon_id, 'wpf-settings-woo', true );

			if ( empty( $settings['auto_apply_tags'] ) ) {
				continue;
			}

			if ( ! empty( array_intersect( $settings['auto_apply_tags'], $user_tags ) ) ) {
				$should_apply = true;
			} else {
				$should_apply = false;
			}

			$coupon    = new WC_Coupon( $coupon_id );
			$discounts = new WC_Discounts( WC()->cart );

			if ( is_wp_error( $discounts->is_coupon_valid( $coupon ) ) ) {
				// Checks if its valid for the cart contents.
				continue;
			}

			// Don't apply if the user is logged in and fails the email restrictions.
			$restrictions = $coupon->get_email_restrictions();

			if ( $should_apply && wpf_is_user_logged_in() && ! empty( $restrictions ) ) {

				$email = wpf_get_current_user_email();

				if ( ! wc()->cart->is_coupon_emails_allowed( array( $email ), $restrictions ) ) {
					$should_apply = false;
				}
			}

			// Check the "Usage limit per user" setting.

			$coupon_usage_limit = $coupon->get_usage_limit_per_user();

			if ( 0 < $coupon_usage_limit ) {

				$email      = wpf_get_current_user_email();
				$data_store = $coupon->get_data_store();
				$email      = strtolower( sanitize_email( $email ) );

				if ( $data_store && $data_store->get_usage_by_email( $coupon, $email ) >= $coupon_usage_limit ) {
					$should_apply = false;
				}
			}

			$should_apply = apply_filters( 'wpf_auto_apply_coupon_for_user', $should_apply, $coupon_id, $user_id );

			if ( $should_apply ) {

				if ( ! WC()->cart->has_discount( $coupon->get_code() ) ) {

					// Remove filter so the check to wc_coupons_enabled() passes.
					remove_filter( 'woocommerce_coupons_enabled', array( $this, 'hide_coupon_field_on_cart' ) );

					$result = WC()->cart->apply_coupon( $coupon->get_code() );

					WC()->cart->calculate_totals();

					add_filter( 'woocommerce_coupons_enabled', array( $this, 'hide_coupon_field_on_cart' ) );

					if ( 0 === intval( WC()->cart->get_cart_contents_total() ) ) {
						return; // if the coupon has aready made the cart subtotal 0.00, don't bother with any other linked coupons.
					}
				}
			}
		}

	}

	/**
	 * Renames coupon fields for auto-applied coupons
	 *
	 * @access public
	 * @return void
	 */

	public function rename_coupon_label( $label, $coupon ) {

		$settings = get_post_meta( $coupon->get_id(), 'wpf-settings-woo', true );

		if ( ! empty( $settings ) && ! empty( $settings['coupon_label'] ) ) {

			return $settings['coupon_label'];

		}

		return $label;

	}

	/**
	 * Allows overriding the coupon success message
	 *
	 * @access public
	 * @return string Coupon success message
	 */

	public function coupon_success_message( $msg, $msg_code, $coupon ) {

		$settings = get_post_meta( $coupon->get_id(), 'wpf-settings-woo', true );

		if ( ! empty( $settings ) && ! empty( $settings['coupon_applied_msg'] ) ) {

			return $settings['coupon_applied_msg'];

		}

		return $msg;

	}

	/**
	 * Output an email optin checkbox on the checkout, if enabled
	 *
	 * @access public
	 * @return mixed Form field
	 */

	public function email_optin_checkbox() {

		if ( wpf_get_option( 'email_optin' ) ) {

			if ( wpf_get_option( 'hide_email_optin' ) && is_user_logged_in() ) {
				$orders = wc_get_orders(
					array(
						'limit'    => -1,
						'type'     => 'shop_order',
						'status'   => $this->get_valid_order_statuses( true ),
						'customer' => array( get_current_user_id() ),
					)
				);

				if ( ! empty( $orders ) ) {
					foreach ( $orders as $order ) {
						if ( $order->get_meta( 'email_optin', true ) ) {
							return;
						}
					}
				}
			}

			if ( 'unchecked' === wpf_get_option( 'email_optin_default' ) ) {
				$default = false;
			} else {
				$default = true;
			}

			$message = wpf_get_option( 'email_optin_message', __( 'I consent to receive marketing emails', 'wp-fusion' ) );

			woocommerce_form_field(
				'email_optin',
				array(
					'type'        => 'checkbox',
					'class'       => array( 'form-row privacy' ),
					'label_class' => array( 'woocommerce-form__label woocommerce-form__label-for-checkbox checkbox' ),
					'input_class' => array( 'woocommerce-form__input woocommerce-form__input-checkbox input-checkbox' ),
					'label'       => '<span>' . $message . '</span>', // span to fix alignment of checkbox on some themes.
				),
				$default
			);

		}

	}

	/**
	 * Remove the "(optional)" label from our checkbox
	 *
	 * @access public
	 * @return html Form field
	 */

	public function remove_checkout_optional_fields_label( $field, $key, $args, $value ) {

		if ( 'email_optin' == $key ) {
			$field = str_replace( '(optional)', '', $field );
		}

		return $field;

	}

	/**
	 * Save the checkbox to order meta so it's synced and we have a record of it
	 *
	 * @access public
	 * @return mixed Form field
	 */

	public function save_email_optin_checkbox( $order_id ) {

		if ( wpf_get_option( 'email_optin' ) ) {

			$order = wc_get_order( $order_id );

			if ( ! empty( $_POST['email_optin'] ) ) {
				$order->update_meta_data( 'email_optin', date( 'Y-m-d h:i:s' ) );
			} else {
				$order->update_meta_data( 'email_optin', false );
			}

			$order->save();

		}

	}


	/**
	 * Apply tags when a product review is approved.
	 *
	 * @since 3.38.17
	 *
	 * @param string $new_status The comment status.
	 * @param string $old_status The old comment status.
	 * @param object $comment    The comment.
	 */
	public function comment_status_change( $new_status, $old_status, $comment ) {
		if ( 'approved' === $new_status ) {
			$this->apply_tags_leaves_review( $comment );
		}
	}

	/**
	 * Apply tags when a product review is inserted.
	 *
	 * @since 3.38.17
	 *
	 * @param int    $id      Comment ID.
	 * @param object $comment The comment.
	 */
	public function insert_comment( $id, $comment ) {
		$this->apply_tags_leaves_review( $comment );
	}

	/**
	 * Apply tags to a user who reviews a product.
	 *
	 * @since 3.38.17
	 *
	 * @param object $comment The comment.
	 */
	public function apply_tags_leaves_review( $comment ) {

		$review_tags = wpf_get_option( 'woo_review_tags' );

		if ( empty( $review_tags ) ) {
			return;
		}

		if ( 'review' !== $comment->comment_type ) {
			return;
		}

		$product_id = $comment->comment_post_ID;
		if ( get_post_type( $product_id ) !== 'product' ) {
			return;
		}

		if ( intval( $comment->comment_approved ) !== 1 ) {
			return;
		}

		$user_id = intval( $comment->user_id );

		if ( empty( $user_id ) ) {

			$contact_id = wp_fusion()->crm->get_contact_id( $comment->comment_author_email );

			if ( ! empty( $contact_id ) ) {

				wpf_log( 'info', 0, 'Applying tags for WooCommerce review to contact #' . $contact_id . ': ', array( 'tag_array' => $review_tags ) );
				wp_fusion()->crm->apply_tags( $review_tags, $contact_id );

			}
		} else {
			wp_fusion()->user->apply_tags( $review_tags, $user_id );
		}

	}


	/**
	 * Allow access control rules to do redirects on the Shop page
	 *
	 * @access public
	 * @return void
	 */

	public function restrict_access_to_shop() {

		if ( is_shop() ) {

			$post_id = get_option( 'woocommerce_shop_page_id' );

			// If user can access, return without doing anything
			if ( wp_fusion()->access->user_can_access( $post_id ) == true ) {
				return;
			}

			// Get redirect URL for the post
			$redirect = wp_fusion()->access->get_redirect( $post_id );

			if ( ! empty( $redirect ) ) {

				wp_redirect( $redirect, 302, 'WP Fusion; Post ID ' . $post_id );
				exit();

			}
		}

	}


	/**
	 * Hides the coupon fields on cart / checkout if enabled
	 *
	 * @access public
	 * @return bool
	 */

	public function hide_coupon_field_on_cart( $enabled ) {

		if ( wpf_get_option( 'woo_hide_coupon_field' ) == true ) {

			if ( is_cart() || is_checkout() ) {
				$enabled = false;
			}
		}

		return $enabled;

	}

	// Software Licenses integration.

	/**
	 * Save License Keys
	 *
	 * Syncs license keys to the CRM when a key is generated.
	 * Keys are generated at checkout, so this happens when an order is placed.
	 *
	 * @since 3.42.0
	 *
	 * @param string $license_key The license key.
	 * @param int    $order_id The order ID.
	 * @return string $license_key
	 */
	public function save_license_keys( $license_key, $order_id ) {

		wp_fusion()->user->push_user_meta( wc_get_order( $order_id )->get_customer_id(), array( 'license_key' => $license_key ) );

		return $license_key;
	}

	/**
	 * Merge license key data into the customer data array.
	 *
	 * @since 3.42.0
	 *
	 * @param array    $customer_data The customer data array.
	 * @param WC_Order $order         The order object.
	 * @return array $customer_data The customer data
	 */
	public function merge_license_key_data( $customer_data, $order ) {

		$order_id     = $order->get_id();
		$license_data = WOO_SL_functions::get_order_licence_details( $order_id );

		// Get all the license keys in the order.
		foreach ( $license_data as $id => $data ) {
			$keys = WOO_SL_functions::get_order_product_generated_keys( $order_id, $data[0]->order_item_id, $data[0]->group_id );
		}

		if ( ! empty( $keys ) ) {
			$customer_data['license_key'] = $keys[0]->licence;
		}

		return $customer_data;

	}

	/**
	 * Declare support for High Performance Order Storage (HPOS).
	 *
	 * @since 3.40.45
	 *
	 * @link https://woocommerce.wordpress.com/2021/03/02/high-performance-order-storage-in-woocommerce-5-5/
	 */
	public function declare_hpos_support() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'wp-fusion/wp-fusion.php', true );
		}
	}

	/**
	 * //
	 * // TAXONOMIES
	 * //
	 **/

	/**
	 * Add settings to taxonomies
	 *
	 * @access public
	 * @return void
	 */

	public function register_taxonomy_form_fields() {

		$product_taxonomies = get_object_taxonomies( 'product' );

		foreach ( $product_taxonomies as $slug ) {
			add_action( $slug . '_edit_form_fields', array( $this, 'taxonomy_form_fields' ) );
			add_action( 'edited_' . $slug, array( $this, 'save_taxonomy_form_fields' ) );
		}

	}

	/**
	 * Output settings to taxonomies
	 *
	 * @access public
	 * @return mixed HTML Output
	 */

	public function taxonomy_form_fields( $term ) {

		?>

		<tr class="form-field">
			<th style="padding-bottom: 0px;" colspan="2"><h3>WP Fusion - WooCommerce Settings</h3></th>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label for="wpf-apply-tag-product"><?php _e( 'Apply tags when a product with this term is purchased', 'wp-fusion' ); ?></label></th>
			<td style="max-width: 400px;">
				<?php

				// retrieve values for tags to be applied.
				$settings = get_term_meta( $term->term_id, 'wpf-settings-woo', true );

				if ( empty( $settings ) ) {
					$settings = array( 'apply_tags' => array() );
				}

				$args = array(
					'setting'   => $settings['apply_tags'],
					'meta_name' => 'wpf-settings-woo',
					'field_id'  => 'apply_tags',
				);

				wpf_render_tag_multiselect( $args );
				?>

			</td>
		</tr>

			<?php

	}

	/**
	 * Save taxonomy settings
	 *
	 * @access public
	 * @return void
	 */

	public function save_taxonomy_form_fields( $term_id ) {

		if ( ! empty( $_POST['wpf-settings-woo'] ) ) {
			update_term_meta( $term_id, 'wpf-settings-woo', wpf_clean( $_POST['wpf-settings-woo'] ) );
		} else {
			delete_term_meta( $term_id, 'wpf-settings-woo' );
		}

	}


	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds WooCommerce checkbox to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['woocommerce'] = array(
			'label'         => 'WooCommerce orders',
			'title'         => 'Orders',
			'process_again' => true,
			'tooltip'       => sprintf(
				// translators: %1$s: Valid order statuses for export, %2$s CRM name.
				__( 'For each WooCommerce order (with status %1$s), adds/updates contact records in %2$s and applies any tags configured to be applied at checkout.', 'wp-fusion' ),
				implode( ', ', $this->get_valid_order_statuses() ),
				wp_fusion()->crm->name
			),
		);

		return $options;

	}

	/**
	 * Counts total number of orders to be processed
	 *
	 * @access public
	 * @return int Count
	 */

	public function batch_init( $args ) {

		$query_args = array(
			'limit'  => -1,
			'type'   => 'shop_order',
			'status' => $this->get_valid_order_statuses( true ),
			'return' => 'ids',
			'order'  => 'ASC',
		);

		if ( ! empty( $args['skip_processed'] ) ) {

			$query_args['meta_key']     = 'wpf_complete';
			$query_args['meta_compare'] = 'NOT EXISTS';

		}

		$orders = wc_get_orders( $query_args );

		return $orders;

	}

	/**
	 * Processes order actions in batches
	 *
	 * @access public
	 * @return void
	 */
	public function batch_step( $order_id ) {

		// Any already processed orders that make it to this point did so
		// because "skip processed" was disabled in batch_init (above), so it's
		// safe to force-process all orders.

		$this->process_order( $order_id, true );

	}

	/**
	 * //
	 * // DEPRECATED
	 * //
	 **/

	/**
	 * Gets customer details from the WooCommerce order when customer isn't a registered user (deprecated)
	 *
	 * @access public
	 * @return array Contact Data
	 */

	public function woocommerce_get_customer_data( $order ) {

		return $this->get_customer_data( $order );

	}

}

new WPF_Woocommerce();
