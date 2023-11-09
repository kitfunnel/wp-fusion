<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Studiocart integration.
 *
 * @since 3.40.45
 */
class WPF_StudioCart extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.45
	 * @var string $slug
	 */

	public $slug = 'studiocart';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.45
	 * @var string $name
	 */
	public $name = 'Studiocart';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.45
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/studiocart/';

	/**
	 * Gets things started.
	 *
	 * @since 3.40.45
	 */
	public function init() {

		add_filter( 'sc_integrations', array( $this, 'add_integration' ) );
		add_filter( 'sc_integration_fields', array( $this, 'add_integration_fields' ) );

		add_filter( 'studiocart_wpf_apply_tags_integrations', array( $this, 'apply_tags' ), 10, 3 );
		add_filter( 'studiocart_wpf_remove_tags_integrations', array( $this, 'remove_tags' ), 10, 3 );

	}


	/**
	 * Apply tags.
	 *
	 * @since 3.40.45
	 *
	 * @param array $integration The integration.
	 * @param int   $product_id  The product ID.
	 * @param array $order       The order.
	 */
	public function apply_tags( $integration, $product_id, $order ) {

		if ( empty( $integration['wpf_apply_tags'] ) ) {
			return;
		}

		$apply_tags = $integration['wpf_apply_tags'];

		if ( intval( $order['user_account'] ) !== 0 ) {

			wp_fusion()->user->apply_tags( $apply_tags, $order['user_account'] );

		} else {

			$contact_id = get_post_meta( $order['id'], WPF_CONTACT_ID_META_KEY, true );

			if ( empty( $contact_id ) ) {
				$order['user_email'] = $order['email'];
				$contact_id          = $this->guest_registration( $order['email'], $order );
			}

			if ( ! is_wp_error( $contact_id ) ) {

				wpf_log( 'info', false, 'Applying tags for Studiocart guest checkout:', array( 'tag_array' => $apply_tags ) );
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

				update_post_meta( $order['id'], WPF_CONTACT_ID_META_KEY, $contact_id );

			}

		}
	}

	/**
	 * Remove tags.
	 *
	 * @since 3.40.45
	 *
	 * @param array $integration The integration.
	 * @param int   $product_id  The product ID.
	 * @param array $order       The order.
	 */
	public function remove_tags( $integration, $product_id, $order ) {

		if ( empty( $integration['wpf_remove_tags'] ) ) {
			return;
		}

		$remove_tags = $integration['wpf_remove_tags'];

		if ( intval( $order['user_account'] ) !== 0 ) {

			wp_fusion()->user->remove_tags( $remove_tags, $order['user_account'] );

		} else {

			$contact_id = get_post_meta( $order['id'], WPF_CONTACT_ID_META_KEY, true );

			if ( empty( $contact_id ) ) {
				$order['user_email'] = $order['email'];
				$contact_id          = $this->guest_registration( $order['email'], $order );
			}

			if ( ! is_wp_error( $contact_id ) ) {

				wpf_log( 'info', false, 'Removing tags for Studiocart guest checkout:', array( 'tag_array' => $remove_tags ) );
				wp_fusion()->crm->apply_tags( $remove_tags, $contact_id );

				update_post_meta( $order['id'], WPF_CONTACT_ID_META_KEY, $contact_id );

			}

		}
	}


	/**
	 * Add WPF into integration list.
	 *
	 * @since 3.40.45
	 *
	 * @param array $integrations The integrations.
	 * @return array The integrations.
	 */
	public function add_integration( $integrations ) {
		$integrations['wpf_apply_tags']  = __( 'WP Fusion - Apply tags', 'wp-fusion' );
		$integrations['wpf_remove_tags'] = __( 'WP Fusion - Remove tags', 'wp-fusion' );
		return $integrations;
	}

	/**
	 * Add WPF fields to the integrations settings.
	 *
	 * @since 3.40.45
	 *
	 * @param array $fields The fields.
	 * @return array The fields.
	 */
	public function add_integration_fields( $fields ) {
		$tags       = wp_fusion()->settings->get_available_tags_flat();
		$apply_tags = array(
			'select' => array(
				'class'             => 'select2 multiple',
				'id'                => 'wpf_apply_tags',
				'label'             => __( 'Apply Tags', 'wp-fusion' ),
				'placeholder'       => __( 'Select Tag(s)', 'wp-fusion' ),
				'type'              => 'select',
				'value'             => '',
				'selections'        => $tags,
				'conditional_logic' => array(
					array(
						'field'   => 'services',
						'value'   => 'wpf_apply_tags',
						'compare' => '=',
					),
				),
			),
		);

		$remove_tags = array(
			'select' => array(
				'class'             => 'select2 multiple',
				'id'                => 'wpf_remove_tags',
				'label'             => __( 'Remove Tags', 'wp-fusion' ),
				'placeholder'       => __( 'Select Tag(s)', 'wp-fusion' ),
				'type'              => 'select',
				'value'             => '',
				'selections'        => $tags,
				'conditional_logic' => array(
					array(
						'field'   => 'services',
						'value'   => 'wpf_remove_tags',
						'compare' => '=',
					),
				),
			),
		);

		foreach ( $fields as $key => $field ) {
			if ( 'repeater' === $field['type'] ) {
				$fields[ $key ]['fields'][] = $apply_tags;
				$fields[ $key ]['fields'][] = $remove_tags;
			}
		}

		return $fields;

	}


	/**
	 * Add WPF tab in product page.
	 *
	 * @since 3.40.45
	 *
	 * @param array $tabs The tabs.
	 * @return array Tabs
	 */
	public function add_product_tab( $tabs ) {
		$tabs['wpfusion'] = __( 'WP Fusion', 'wp-fusion' );
		return $tabs;
	}

}


new WPF_StudioCart();
