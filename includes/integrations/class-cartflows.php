<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_CartFlows extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'cartflows';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'CartFlows';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/cartflows/';

	/**
	 * Gets things started.
	 *
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'init', array( $this, 'add_action' ) );

		add_action( 'wpf_woocommerce_payment_complete', array( $this, 'maybe_block_ecom_addon' ), 5, 2 );
		add_action( 'wpf_woocommerce_payment_complete', array( $this, 'maybe_sync_upsells' ), 10, 2 );

		// Offer stuff
		add_action( 'cartflows_offer_accepted', array( $this, 'offer_accepted' ), 10, 2 );
		add_action( 'cartflows_offer_rejected', array( $this, 'offer_rejected' ), 10, 2 );

		// Admin settings
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );

		// Cartflows admin settings (new UI)
		add_filter( 'cartflows_admin_upsell_step_meta_settings', array( $this, 'get_settings' ), 15, 2 );
		add_filter( 'cartflows_admin_downsell_step_meta_settings', array( $this, 'get_settings' ), 15, 2 );
		add_filter( 'cartflows_offer_meta_options', array( $this, 'offer_meta_options' ) );
	}


	/**
	 * Adds CartFlows order status trigger if enabled
	 *
	 * @access public
	 * @return void
	 */

	public function add_action() {

		if ( wpf_get_option( 'cartflows_main_order' ) ) {

			add_action( 'woocommerce_order_status_wcf-main-order', array( wp_fusion()->integrations->woocommerce, 'woocommerce_apply_tags_checkout' ) );

			add_action( 'woocommerce_order_status_processing', array( $this, 'clear_wpf_complete' ), 5 );
			add_action( 'woocommerce_order_status_completed', array( $this, 'clear_wpf_complete' ), 5 );

		}

	}

	/**
	 * Don't run the ecommerce addon when the main order is complete
	 *
	 * @access public
	 * @return void
	 */

	public function maybe_block_ecom_addon( $order_id, $contact_id ) {

		$order  = wc_get_order( $order_id );
		$status = $order->get_status();

		// Ecom addon
		if ( function_exists( 'wp_fusion_ecommerce' ) && 'wcf-main-order' == $status && wpf_get_option( 'cartflows_main_order' ) == true ) {

			remove_action( 'wpf_woocommerce_payment_complete', array( wp_fusion_ecommerce()->integrations->woocommerce, 'send_order_data' ), 10, 2 );

		}

	}

	/**
	 * If Asynchronous Checkout is enabled, this will trigger any upsell orders after the main order has been processed.
	 *
	 * @since 3.40.8
	 *
	 * @param int    $order_id   The order ID.
	 * @param string $contact_id The contact ID.
	 */
	public function maybe_sync_upsells( $order_id, $contact_id ) {

		if ( wpf_get_option( 'woo_async' ) ) {

			$child_orders = get_post_meta( $order_id, '_cartflows_offer_child_orders', true );

			if ( ! empty( $child_orders ) ) {

				foreach ( $child_orders as $child_order_id => $data ) {

					wp_fusion()->integrations->woocommerce->process_order( $child_order_id );

					if ( 'upsell' === $data['type'] ) {

						$step_id = get_post_meta( $child_order_id, '_cartflows_offer_step_id', true );

						$order = wc_get_order( $child_order_id );

						$this->offer_accepted( $order, array(), $step_id );
					}

				}
			}
		}

	}

	/**
	 * Clear the wpf_complete flag so the order can be processed again after the main checkout is complete
	 *
	 * @access public
	 * @return void
	 */

	public function clear_wpf_complete( $order_id ) {

		delete_post_meta( $order_id, 'wpf_complete' );

	}

	/**
	 * Offer accepted
	 *
	 * @access public
	 * @return void
	 */

	public function offer_accepted( $order, $offer_product, $step_id = false ) {

		if ( false === $step_id ) { // $step_id is false when triggered from the hook.
			$step_id = $offer_product['step_id'];
		}

		$setting = get_post_meta( $step_id, 'wpf-offer-accepted', true );

		if ( ! empty( $setting ) ) {

			if ( ! is_array( $setting ) ) { // the new CF UI doesn't save the tags as an array
				$setting = array( $setting );
			}

			$user_id = $order->get_user_id();

			if ( ! empty( $user_id ) ) {

				wp_fusion()->user->apply_tags( $setting, $user_id );

			} else {

				$contact_id = get_post_meta( $order->get_id(), WPF_CONTACT_ID_META_KEY, true );

				if ( ! empty( $contact_id ) ) {

					wpf_log( 'info', 0, 'Applying offer accepted tags to contact #' . $contact_id . ': ', array( 'tag_array' => $setting ) );

					wp_fusion()->crm->apply_tags( $setting, $contact_id );

				}
			}
		}

	}

	/**
	 * Offer rejected
	 *
	 * @access public
	 * @return void
	 */

	public function offer_rejected( $order, $offer_product ) {

		$setting = get_post_meta( $offer_product['step_id'], 'wpf-offer-rejected', true );

		if ( ! empty( $setting ) ) {

			if ( ! is_array( $setting ) ) { // the new CF UI doesn't save the tags as an array
				$setting = array( $setting );
			}

			$user_id = $order->get_user_id();

			if ( ! empty( $user_id ) ) {

				wp_fusion()->user->apply_tags( $setting, $user_id );

			} else {

				$contact_id = get_post_meta( $order->get_id(), WPF_CONTACT_ID_META_KEY, true );

				if ( ! empty( $contact_id ) ) {

					wpf_log( 'info', 0, 'Applying offer rejected tags to contact #' . $contact_id . ': ', array( 'tag_array' => $setting ) );

					wp_fusion()->crm->apply_tags( $setting, $contact_id );

				}
			}
		}

	}


	/**
	 * Registers CartFlows settings
	 *
	 * @access  public
	 * @return  array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['cartflows_header'] = array(
			'title'   => __( 'CartFlows Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['cartflows_main_order'] = array(
			'title'   => __( 'Run on Main Order Accepted', 'wp-fusion' ),
			'desc'    => __( 'Runs WP Fusion post-checkout actions when the order status is Main Order Accepted instead of waiting for Completed.', 'wp-fusion' ),
			'type'    => 'checkbox',
			'section' => 'integrations',
		);

		return $settings;

	}

	/**
	 * Adds CartFlows custom fields to Contact Fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$args = array(
			'post_type' => 'cartflows_step',
			'fields'    => 'ids',
			'nopaging'  => true,
		);

		$steps = get_posts( $args );

		if ( ! empty( $steps ) ) {

			foreach ( $steps as $step_id ) {

				$fields = get_post_meta( $step_id, 'wcf_field_order_billing', true );

				if ( ! empty( $fields ) ) {

					$shipping_fields = get_post_meta( $step_id, 'wcf_field_order_shipping', true );

					if ( empty( $shipping_fields ) ) {
						$shipping_fields = array();
					}

					$fields = array_merge( $fields, $shipping_fields );

					foreach ( $fields as $key => $field ) {

						if ( ! isset( $meta_fields[ $key ] ) ) {

							if ( ! isset( $field['type'] ) ) {
								$field['type'] = 'text';
							}

							$meta_fields[ $key ] = array(
								'label' => $field['label'],
								'type'  => $field['type'],
								'group' => 'woocommerce',
							);

						}
					}
				}

				// Optin fields are prefixed with an underscore.

				$optin_fields = get_post_meta( $step_id, 'wcf-optin-fields-billing', true );

				if ( ! empty( $optin_fields ) ) {

					foreach ( $optin_fields as $key => $field ) {

						if ( ! isset( $meta_fields[ '_' . $key ] ) ) {

							$meta_fields[ '_' . $key ] = array(
								'label' => $field['label'],
								'type'  => isset( $field['type'] ) ? $field['type'] : 'text',
								'group' => 'woocommerce',
							);
						}
					}
				}
			}
		}

		return $meta_fields;

	}


	/**
	 * Register WPF settings (new UI)
	 *
	 * @since  3.37.0
	 *
	 * @param  array $settings settings.
	 * @param  int   $step_id  Post meta.
	 * @return array The settings.
	 */
	public function get_settings( $settings, $step_id ) {

		$tags    = wp_fusion()->settings->get_available_tags_flat();
		$options = array(
			array(
				'value' => '',
				'label' => __( 'Select a tag', 'wp-fusion' ),
			),
		);

		foreach ( $tags as $id => $label ) {

			$options[] = array(
				'value' => $id,
				'label' => $label,
			);

		}

		$accepted = get_post_meta( $step_id, 'wpf-offer-accepted', true );

		$rejected = get_post_meta( $step_id, 'wpf-offer-rejected', true );

		$settings['settings']['settings']['wp_fusion'] = array(
			'title'    => __( 'WP Fusion', 'wp-fusion' ),
			'slug'     => 'wp-fusion',
			'priority' => 20,
			'fields'   => array(
				'wpf-offer-accepted' => array(
					'type'    => 'select',
					'label'   => __( 'Apply Tag', 'wp-fusion' ) . ' - ' . __( 'Offer Accepted', 'wp-fusion' ),
					'name'    => 'wpf-offer-accepted',
					'options' => $options,
					'value'   => $accepted,
				),
				'wpf-offer-rejected' => array(
					'type'    => 'select',
					'label'   => __( 'Apply Tag', 'wp-fusion' ) . ' - ' . __( 'Offer Rejected', 'wp-fusion' ),
					'name'    => 'wpf-offer-rejected',
					'options' => $options,
					'value'   => $rejected,
				),
			),
		);

		return $settings;

	}

	/**
	 * Register WPF options
	 *
	 * @access  public
	 * @return  array Options
	 */

	public function offer_meta_options( $options ) {

		$options['wpf-offer-accepted'] = array(
			'default'  => array(),
			'sanitize' => 'FILTER_DEFAULT',
		);

		$options['wpf-offer-rejected'] = array(
			'default'  => array(),
			'sanitize' => 'FILTER_DEFAULT',
		);

		return $options;

	}

}

new WPF_CartFlows();
