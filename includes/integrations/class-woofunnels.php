<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooFunnels integration class.
 *
 * @since 3.37.14
 */
class WPF_WooFunnels extends WPF_Integrations_Base {

	/**
	 * The slug name for WP Fusion's module tracking.
	 *
	 * @since 3.37.14
	 * @var  slug
	 */

	public $slug = 'woofunnels';

	/**
	 * The integration name.
	 *
	 * @since 3.37.14
	 * @var  name
	 */

	public $name = 'FunnelKit';


	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/woofunnels/';


	/**
	 * Get things started.
	 *
	 * @since 3.37.14
	 */
	public function init() {

		// Handle the Primary Order Accepted order status.
		add_action( 'woocommerce_order_status_wfocu-pri-order', array( $this, 'primary_order_accepted' ) );
		add_filter( 'wpf_woocommerce_order_statuses', array( $this, 'order_statuses' ) );

		// Async checkout.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_async_checkout_script' ) );

		// Admin settings.
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );

		// WooFunnels settings.
		add_action( 'wffn_optin_action_tabs', array( $this, 'optin_tab' ) );
		add_action( 'wffn_optin_action_tabs_content', array( $this, 'optin_tab_content' ) );
		add_action( 'wfopp_default_actions_settings', array( $this, 'optin_default' ) );
		add_action( 'wfopp_localized_data', array( $this, 'optin_localize' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'optin_settings_js' ), 9999 );

		/**
		 * Process optin hook
		 */
		add_action( 'wffn_optin_form_submit', array( $this, 'handle_optin_submission' ), 10, 2 );

		/**
		 * Settings related hooks
		 */
		add_action( 'admin_enqueue_scripts', array( $this, 'upsell_localize' ), 100 );
		add_filter( 'wfocu_offer_settings_default', array( $this, 'upsell_defaults' ) );
		add_action( 'admin_footer', array( $this, 'upsells_settings_js' ) );

		/**
		 * Process Upsell Hook
		 */
		add_action( 'wfocu_offer_accepted_and_processed', array( $this, 'handle_upsell_accept' ) );
		add_action( 'wfocu_offer_rejected_event', array( $this, 'handle_upsell_reject' ) );

	}

	/**
	 * Syncs the order on the Primary Order Accepted status, if enabled.
	 *
	 * @since 3.41.1
	 *
	 * @param int $order_id The order ID.
	 */
	public function primary_order_accepted( $order_id ) {

		if ( wpf_get_option( 'woofunnels_primary_order' ) ) {

			wp_fusion()->integrations->woocommerce->woocommerce_apply_tags_checkout( $order_id );

		}

	}

	/**
	 * Don't sync Primary Order Accepted orders unless the option is enabled.
	 *
	 * @since 3.41.1
	 *
	 * @param array $statuses The valid statuses.
	 * @return array The valid statuses.
	 */
	public function order_statuses( $statuses ) {

		if ( ! wpf_get_option( 'woofunnels_primary_order' ) ) {

			$key = array_search( 'wfocu-pri-order', $statuses, true );

			if ( $key ) {
				unset( $statuses[ $key ] );
			}
		}

		return $statuses;

	}


	/**
	 * This is the equivalent of WPF_WooCommerce::enqueue_async_checkout_script() but
	 * for WooFunnels.
	 *
	 * @since 3.40.28
	 */
	public function enqueue_async_checkout_script() {

		if ( wpf_get_option( 'woo_async' ) && WFOCU_Core()->data->get_current_order() ) {

			$order           = WFOCU_Core()->data->get( 'porder', false, '_orders' );
			$upsell_order_id = $order->get_meta( '_wfocu_sibling_order' );

			if ( ! empty( $upsell_order_id ) ) {
				$order = wc_get_order( $upsell_order_id );
			}

			if ( ! $order->get_meta( 'wpf_complete', true ) && ! get_transient( 'wpf_woo_started_' . $order->get_id() ) ) {

				if ( $order->is_paid() || ( 'wfocu-pri-order' === $order->get_status() && wpf_get_option( 'woofunnels_primary_order' ) ) ) {

					$localize_data = array(
						'ajaxurl'        => admin_url( 'admin-ajax.php' ),
						'pendingOrderID' => $order->get_id(),
					);

					wp_enqueue_script( 'wpf-woocommerce-async', WPF_DIR_URL . 'assets/js/wpf-async-checkout.js', array( 'jquery' ), WP_FUSION_VERSION, true );
					wp_localize_script( 'wpf-woocommerce-async', 'wpf_async', $localize_data );

				}

			}
		}

	}


	/**
	 * Registers WooFunnels settings.
	 *
	 * @since  3.37.19
	 *
	 * @param  array $settings The settings.
	 * @param  array $options  The saved options.
	 * @return array The settings.
	 */

	public function register_settings( $settings, $options ) {

		$settings['woofunnels_header'] = array(
			'title'   => __( 'WooFunnels Integration', 'wp-fusion' ),
			'url'     => 'https://wpfusion.com/documentation/ecommerce/woofunnels/#general-settings',
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['woofunnels_primary_order'] = array(
			'title'   => __( 'Run on Primary Order Accepted', 'wp-fusion' ),
			'desc'    => __( 'Runs WP Fusion post-checkout actions when the order status is Primary Order Accepted instead of waiting for Completed.', 'wp-fusion' ),
			'tooltip' => __( 'This is useful if you have a funnel with multiple upsells and you want to apply tags as soon as the initial order is submitted, rather than waiting for all upsells to either be accepted or rejected.', 'wp-fusion' ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;

	}


	/**
	 * Enqueue multiselect scripts.
	 *
	 * @since 3.37.14
	 */
	public function scripts() {

		if ( function_exists( 'WFFN_Core' ) && function_exists( 'WFOCU_Core' ) && ! empty( WFOCU_Core()->admin ) && WFOCU_Core()->admin->is_upstroke_page( 'offers' ) ) {

			wp_enqueue_style( 'wffn-vue-multiselect', WFFN_Core()->get_plugin_url() . '/admin/assets/vuejs/vue-multiselect.min.css', array(), WFFN_VERSION_DEV );

			wp_enqueue_script( 'wffn-vuejs', WFFN_Core()->get_plugin_url() . '/admin/assets/vuejs/vue.min.js', array(), '2.6.10' );
			wp_enqueue_script( 'wffn-vue-vfg', WFFN_Core()->get_plugin_url() . '/admin/assets/vuejs/vfg.min.js', array(), '2.3.4' );
			wp_enqueue_script( 'wffn-vue-multiselect', WFFN_Core()->get_plugin_url() . '/admin/assets/vuejs/vue-multiselect.min.js', array(), WFFN_VERSION_DEV );

		}

	}

	/**
	 * Adds WooFunnels field group to meta fields list.
	 *
	 * @since  3.37.19
	 *
	 * @param  array $field_groups The field groups.
	 * @return array  Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['woofunnels'] = array(
			'title'  => 'WooFunnels',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Sets field labels and types for WooFunnels custom fields.
	 *
	 * @since  3.37.19
	 *
	 * @param  array $meta_fields The meta fields.
	 * @return array  Meta fields
	 */
	public function prepare_meta_fields( $meta_fields ) {

		if ( ! class_exists( 'WFACP_Common' ) ) {
			return $meta_fields;
		}

		$args = array(
			'post_type'      => 'wfacp_checkout',
			'posts_per_page' => 200,
			'fields'         => 'ids',
		);

		$checkouts = get_posts( $args );

		if ( ! empty( $checkouts ) ) {

			foreach ( $checkouts as $checkout_id ) {

				$field_groups = WFACP_Common::get_page_custom_fields( $checkout_id );

				foreach ( $field_groups as $fields ) {

					foreach ( $fields as $key => $field ) {

						if ( 'wfacp_html' == $field['type'] ) {
							continue;
						}

						if ( ! isset( $meta_fields[ $key ] ) ) {
							$meta_fields[ $key ] = array(
								'label' => $field['label'],
								'type'  => $field['type'],
								'group' => 'woofunnels',
							);
						}
					}
				}
			}
		}

		return $meta_fields;

	}

	/**
	 * Render optin settings tab.
	 *
	 * @since 3.37.14
	 */
	public function optin_tab() {
		?>
		<div class="wffn-tab-title wffn-tab-desktop-title" data-tab="4" role="tab"><?php esc_html_e( 'WP Fusion', 'wp-fusion' ); ?></div>
		<?php
	}

	/**
	 * Render optin tab content.
	 *
	 * @since 3.37.14
	 */
	public function optin_tab_content() {
		?>
		<vue-form-generator ref="modelfusionref" :schema="schemaFusion" :model="modelFusion" :options="formOptions"></vue-form-generator>
		<?php
	}

	/**
	 * Register optin defaults.
	 *
	 * @since  3.37.14
	 *
	 * @param  array $actions_defaults The actions defaults.
	 * @return array The actions defaults.
	 */
	public function optin_default( $actions_defaults ) {
		$actions_defaults['op_wpfusion_optin_tags'] = array();
		$actions_defaults['op_wpfusion_enable']     = 'false';

		return $actions_defaults;
	}


	/**
	 * Prepare the available tags (optins).
	 *
	 * @since  3.37.14
	 *
	 * @param  array $data   The localize data.
	 * @return array The localize data.
	 */
	public function optin_localize( $data ) {
		$all_available_tags = wp_fusion()->settings->get_available_tags_flat();
		$options = [];
		foreach ( $all_available_tags as $id => $label ) {

			$options[] = array(
				'id'   => $id,
				'name' => $label,
			);

		}
		$data['op_wpfusion_optin_tags_vals'] = $options;

		$data['op_wpfusion_optin_radio_vals'] = array(
			array(
				'value' => 'true',
				'name'  => __( 'Yes', 'wp-fusion' ),
			),
			array(
				'value' => 'false',
				'name'  => __( 'No', 'wp-fusion' ),
			),
		);

		return $data;
	}

	/**
	 * Prepare the available tags (upsell).
	 *
	 * @since 3.37.14
	 */
	public function upsell_localize() {
		if ( ! empty( WFOCU_Core()->admin ) && WFOCU_Core()->admin->is_upstroke_page( 'offers' ) ) {

			$data               = array();
			$all_available_tags = wp_fusion()->settings->get_available_tags_flat();

			foreach ( $all_available_tags as $id => $label ) {

				$options[] = array(
					'id'   => $id,
					'name' => $label,
				);

			}
			$data['wpfusion_tags'] = $options;

			wp_localize_script( 'wfocu-admin', 'wfocuWPF', $data );
		}

	}

	/**
	 * JS for the optin settings.
	 *
	 * @since 3.37.14
	 */
	public function optin_settings_js() {

		?>
		<script>
            (function ($, doc, win) {

					if (typeof window.wffnBuilderCommons !== "undefined") {

						window.wffnBuilderCommons.addFilter('wffn_js_optin_vue_data', function (e) {
							let custom_settings_valid_fields = [
								{
									type: "radios",
									label: "<?php _e( 'Enable Integration', 'wp-fusion' ); ?>",
									model: "op_wpfusion_enable",
									values: () => {
										return wfop_action.op_wpfusion_optin_radio_vals
									},
									hint: "<?php printf( __( 'Select Yes to sync optins with %s.', 'wp-fusion' ), wp_fusion()->crm->name ); ?>",
								},
								{
									type: "vueMultiSelect",
									label: "<?php _e( 'Apply Tags - Optin Submitted', 'wp-fusion' ); ?>",
									placeholder: "<?php _e( 'Select tags', 'wp-fusion' ); ?>",
									model: "op_wpfusion_optin_tags",
									selectOptions: {hideNoneSelectedText: true},
									hint: "<?php printf( __( 'Select tags to be applied in %s when this form is submitted.', 'wp-fusion' ), wp_fusion()->crm->name ); ?>",
									values: () => {
										return wfop_action.op_wpfusion_optin_tags_vals
									},
									selectOptions: {
										multiple: true,
										key: "id",
										label: "name",
									},
									visible: function (model) {
										return (model.op_wpfusion_enable === 'true');
									},
								},


							];
							e.schemaFusion = {
								groups: [{
									legend: '<?php _e( 'WP Fusion', 'wp-fusion' ); ?>',
									fields: custom_settings_valid_fields
								}]
							};
							e.modelFusion = wfop_action.action_options;
							return e;
						});
					}
            })(jQuery, document, window);

		</script>
		<?php
	}

	/**
	 * Upsell defaults.
	 *
	 * @since  3.37.14
	 *
	 * @param  object $object The object.
	 * @return object The object.
	 */
	public function upsell_defaults( $object ) {
		$object->wfocu_wpfusion_offer_accept_tags = array();
		$object->wfocu_wpfusion_offer_reject_tags = array();

		return $object;
	}

	/**
	 * JS for the upsell settings.
	 *
	 * @since 3.37.14
	 */
	public function upsells_settings_js() {
		?>
		<script>

			(function ($, doc, win) {
				'use strict';

				if (typeof window.wfocuBuilderCommons !== "undefined") {
					Vue.component('multiselect', window.VueMultiselect.default);
					window.wfocuBuilderCommons.addFilter('wfocu_offer_settings', function (e) {
						e.unshift(
							{
								type: "label",
								label: "<?php _e( 'Apply Tags - Offer Accepted', 'wp-fusion' ); ?>",
								model: "wfocu_wp_fusion_label_1",

							},
							{
								type: "vueMultiSelect",
								label: "",
								model: "wfocu_wpfusion_offer_accept_tags",
								placeholder: "<?php _e( 'Select tags', 'wp-fusion' ); ?>",
								selectOptions: {hideNoneSelectedText: true},
								values: () => {
									return wfocuWPF.wpfusion_tags
								},
								selectOptions: {
									multiple: true,
									key: "id",
									label: "name",
								}
							},

							{
								type: "label",
								label: "<?php _e( 'Apply Tags - Offer Rejected', 'wp-fusion' ); ?>",
								model: "wfocu_wp_fusion_label_2",

							},
							{
								type: "vueMultiSelect",
								label: "",
								model: "wfocu_wpfusion_offer_reject_tags",
								placeholder: "<?php _e( 'Select tags', 'wp-fusion' ); ?>",
								selectOptions: {hideNoneSelectedText: true},
								values: () => {
									return wfocuWPF.wpfusion_tags
								},
								selectOptions: {
									multiple: true,
									key: "id",
									label: "name",
								}
							},
						);


						return e;
					});
				}

			})(jQuery, document, window);
		</script>
		<?php
	}

	/**
	 * Handles an optin submission.
	 *
	 * @since 3.37.14
	 *
	 * @param int   $optin_id    The optin ID.
	 * @param array $posted_data The posted data.
	 */
	public function handle_optin_submission( $optin_id, $posted_data ) {

		$settings = WFOPP_Core()->optin_actions->get_optin_action_settings( $optin_id );

		if ( ! empty( $settings ) && isset ( $settings['op_wpfusion_enable'] ) && 'true' == $settings['op_wpfusion_enable'] ) {

			// Map data

			$field_map = array(
				'optin_first_name' => 'first_name',
				'optin_last_name'  => 'last_name',
				'optin_email'      => 'user_email',
			);

			$update_data = $this->map_meta_fields( $posted_data, $field_map );
			$update_data = wp_fusion()->crm->map_meta_fields( $update_data );

			// Prep tags

			$apply_tags = array();

			if ( ! empty( $settings['op_wpfusion_optin_tags'] ) ) {

				foreach ( $settings['op_wpfusion_optin_tags'] as $tag ) {
					$apply_tags[] = $tag['id'];
				}
			}

			$args = array(
				'email_address'    => $posted_data['optin_email'],
				'update_data'      => $update_data,
				'apply_tags'       => $apply_tags,
				'integration_slug' => 'woofunnels_optin',
				'integration_name' => 'WooFunnels Optin',
				'form_id'          => $optin_id,
				'form_title'       => get_the_title( $optin_id ),
				'form_edit_link'   => admin_url( 'admin.php?page=wf-op&section=action&edit=' . $optin_id ),
			);

			$contact_id = WPF_Forms_Helper::process_form_data( $args );

		}

	}

	/**
	 * Handle an upsell accept.
	 *
	 * @since 3.37.14
	 *
	 * @param int $offer_id The offer ID.
	 */
	public function handle_upsell_accept( $offer_id ) {

		$order = WFOCU_Core()->data->get( 'porder', false, '_orders' );

		$get_offer_data = WFOCU_Core()->data->get( '_current_offer' );

		if ( ! empty( $get_offer_data->settings->wfocu_wpfusion_offer_accept_tags ) ) {

			$offer_tags = array();

			foreach ( $get_offer_data->settings->wfocu_wpfusion_offer_accept_tags as $tag ) {
				$offer_tags[] = $tag['id'];
			}

			$user_id    = $order->get_user_id();
			$contact_id = wp_fusion()->integrations->woocommerce->get_contact_id_from_order( $order );

			if ( ! empty( $user_id ) ) {

				wp_fusion()->user->apply_tags( $offer_tags, $user_id );

			} elseif ( ! empty( $contact_id ) ) {

				wpf_log( 'info', 0, 'Applying Offer Accepted tags to guest contact #' . $contact_id . ': ', array( 'tag_array' => $offer_tags ) );
				wp_fusion()->crm->apply_tags( $offer_tags, $contact_id );

			}
		}

		// This is for cases where the main order has already been synced by the
		// Ecommerce Addon due to the "Forcefully Switch Order Status" setting in
		// WooFunnels (default 15 mins). We need to update the invoice in the CRM
		// with the upsell details.

		if ( 'processing' === $order->get_status() && function_exists( 'wp_fusion_ecommerce' ) && ( 'drip' === wp_fusion()->crm->slug || 'activecampaign' === wp_fusion()->crm->slug ) ) {

			delete_post_meta( $order->get_id(), 'wpf_ec_complete' ); // unlock it.
			wp_fusion_ecommerce()->integrations->woocommerce->send_order_data( $order->get_id() );

		}

	}

	/**
	 * Handle an upsell reject.
	 *
	 * @since 3.37.14
	 *
	 * @param array $args   The arguments.
	 */
	public function handle_upsell_reject( $args ) {

		$get_offer_data = WFOCU_Core()->data->get( '_current_offer' );

		if ( empty( $get_offer_data->settings->wfocu_wpfusion_offer_reject_tags ) ) {
			return;
		}

		$offer_tags = array();

		foreach ( $get_offer_data->settings->wfocu_wpfusion_offer_reject_tags as $tag ) {
			$offer_tags[] = $tag['id'];
		}

		$order = WFOCU_Core()->data->get( 'porder', false, '_orders' );

		$user_id = $order->get_user_id();

		if ( ! empty( $user_id ) ) {

			wp_fusion()->user->apply_tags( $offer_tags, $user_id );

		} else {

			$contact_id = $order->get_meta( WPF_CONTACT_ID_META_KEY );

			if ( ! empty( $contact_id ) ) {

				wpf_log( 'info', 0, 'Applying Offer Rejected tags to guest contact #' . $contact_id . ': ', array( 'tag_array' => $offer_tags ) );
				wp_fusion()->crm->apply_tags( $offer_tags, $contact_id );
			}
		}
	}

}

new WPF_WooFunnels();
