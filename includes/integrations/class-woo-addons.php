<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Woo_Addons extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'woo-addons';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WooCommerce Product Addons';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/woocommerce-addons/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// Admin settings
		add_action( 'woocommerce_product_addons_panel_option_heading', array( $this, 'panel_option_heading' ), 10, 3 );
		add_action( 'woocommerce_product_addons_panel_option_row', array( $this, 'panel_option_row' ), 10, 4 );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ) );

		// Handle tags and fields at checkout
		add_action( 'wpf_woocommerce_payment_complete', array( $this, 'payment_complete' ), 10, 2 );

		// Add text-type addons to meta fields list
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

	}

	/**
	 * Check to see if it's v3+ of the plugin
	 *
	 * @access  public
	 * @return  bool
	 */

	public function is_v3() {

		if ( defined( 'WC_PRODUCT_ADDONS_VERSION' ) && version_compare( WC_PRODUCT_ADDONS_VERSION, '3.0', '>' ) ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Get WPF field type from addon field type
	 *
	 * @access public
	 * @return string Field type
	 */

	public function convert_field_type( $type ) {

		switch ( $type ) {

			case 'custom':
			case 'custom_textarea':
			case 'custom_text':
				return 'text';
				break;

			case 'checkbox':
				return 'multiselect';
				break;

			case 'input_multiplier':
				return 'int';
				break;

			case 'multiple_choice':
				return 'select';
				break;

			default:
				return false;
				break;
		}

	}


	/**
	 * Addon option column heading in admin
	 *
	 * @access public
	 * @return mixed HTML output
	 */

	public function panel_option_heading( $post, $addon, $loop ) {

		if ( empty( $post ) ) {
			return;
		}

		if ( $this->is_v3() ) {

			echo '<div class="wc-pao-addon-content-tags-header">Apply tags if selected</div>';

		} else {
			echo '<th style="width:250px;">Apply tags if selected</th>';
		}

	}

	/**
	 * Addon option column in admin
	 *
	 * @access public
	 * @return mixed HTML output
	 */

	public function panel_option_row( $post, $product_addons, $loop, $option ) {

		if ( empty( $post ) ) {
			return;
		}

		$sub_field_id = false;

		foreach ( $product_addons as $addon ) {

			foreach ( $addon['options'] as $i => $addon_options ) {

				if ( $addon_options == $option ) {
					$sub_field_id = $i;
				}
			}
		}

		if ( ! $this->is_v3() ) {
			echo '<td class="wpf_column">';
		}

		if ( is_numeric( $loop ) ) {

			$setting = get_post_meta( $post->ID, 'wpf_woo_addons', true );

			// Set defaults
			if ( empty( $setting ) ) {
				$setting = array();
			}

			if ( empty( $setting[ $loop ] ) ) {
				$setting[ $loop ] = array();
			}

			if ( empty( $setting[ $loop ][ $sub_field_id ] ) ) {
				$setting[ $loop ][ $sub_field_id ] = array();
			}

			$args = array(
				'setting'   => $setting[ $loop ][ $sub_field_id ],
				'meta_name' => "wpf_woo_addons[{$loop}][{$sub_field_id}]",
			);

			wpf_render_tag_multiselect( $args );

		}

		if ( ! $this->is_v3() ) {
			echo '</td>';
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
		if ( $_POST['post_type'] == 'revision' ) {
			return;
		}

		if ( isset( $_POST['wpf_woo_addons'] ) ) {

			$data = $_POST['wpf_woo_addons'];
			update_post_meta( $post_id, 'wpf_woo_addons', $data );

		}

	}

	/**
	 * Apply addon tags and sync meta data
	 *
	 * @access public
	 * @return void
	 */

	public function payment_complete( $order_id, $contact_id ) {

		$order = wc_get_order( $order_id );

		$apply_tags  = array();
		$update_data = array();

		foreach ( $order->get_items() as $item_id => $item ) {

			$product_id = $item->get_product_id();

			$item_meta = $item->get_meta_data();

			if ( empty( $item_meta ) ) {
				continue;
			}

			$wpf_settings = get_post_meta( $product_id, 'wpf_woo_addons', true );

			if ( empty( $wpf_settings ) ) {
				$wpf_settings = array();
			}

			$addon_settings = get_post_meta( $product_id, '_product_addons', true );

			foreach ( $item_meta as $meta ) {

				$data = $meta->get_data();

				// Product specific add-ons:

				if ( ! empty( $addon_settings ) ) {

					foreach ( $addon_settings as $i => $addon_setting ) {

						if ( strpos( $data['key'], $addon_setting['name'] ) !== false ) {

							if ( in_array( $addon_setting['type'], array( 'custom', 'custom_textarea', 'custom_text', 'input_multiplier' ) ) ) {

								// Text types.

								$update_data[ $product_id . '_' . $i ] = $data['value'];

							} else {

								// Array types.

								foreach ( $addon_setting['options'] as $ii => $addon_setting_option ) {

									// Others

									if ( $data['value'] == $addon_setting_option['label'] ) {

										// Tags

										if ( isset( $wpf_settings[ $i ] ) && isset( $wpf_settings[ $i ][ $ii ] ) ) {
											$apply_tags = array_merge( $apply_tags, $wpf_settings[ $i ][ $ii ] );
										}

										// Data

										if ( $addon_setting['type'] == 'checkbox' ) {

											// Combine them

											if ( ! isset( $update_data[ $product_id . '_' . $i ] ) ) {
												$update_data[ $product_id . '_' . $i ] = array();
											}

											$update_data[ $product_id . '_' . $i ][] = $data['value'];

										} elseif ( $addon_setting['type'] == 'multiple_choice' ) {

											$update_data[ $product_id . '_' . $i ] = $data['value'];

										}
									}
								}
							}
						}
					}
				}

				// Global addons

				$key = str_replace( '-', '_', sanitize_title( $data['key'] ) );

				if ( ! isset( $update_data[ $key ] ) ) {

					// Text and select fields

					$update_data[ $key ] = $data['value'];

				} else {

					// Multiselect fields

					if ( ! is_array( $update_data[ $key ] ) ) {

						// Convert to array format
						$update_data[ $key . '_0' ] = $update_data[ $key ];
						$update_data[ $key ]        = array( $update_data[ $key ] );

					}

					$index                              = count( $update_data[ $key ] );
					$update_data[ $key ][]              = $data['value'];
					$update_data[ $key . '_' . $index ] = $data['value'];

				}
			}
		}

		if ( ! empty( $apply_tags ) ) {

			$user_id = wp_fusion()->user->get_user_id( $contact_id );

			if ( ! empty( $user_id ) ) {

				wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			} else {

				wpf_log( 'info', $order->get_user_id(), 'Applying tags for add-ons:', array( 'tag_array' => $apply_tags ) );
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}
		}

		if ( ! empty( $update_data ) ) {

			$user_id = wp_fusion()->user->get_user_id( $contact_id );

			if ( ! empty( $user_id ) ) {

				wp_fusion()->user->push_user_meta( $user_id, $update_data );

			} else {

				wpf_log( 'info', $order->get_user_id(), 'Syncing add-ons fields:', array( 'meta_array' => $update_data ) );
				wp_fusion()->crm->update_contact( $contact_id, $update_data );

			}
		}

	}


	/**
	 * Adds WooCommerce field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['wc_addons'] ) ) {
			$field_groups['wc_addons'] = array(
				'title'  => 'WooCommerce Addons',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Add input type addons to meta fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		// Get global addons

		$args = array(
			'nopaging'  => true,
			'post_type' => 'global_product_addon',
			'fields'    => 'ids',
		);

		$addons = get_posts( $args );

		if ( empty( $addons ) ) {
			return $meta_fields;
		}

		foreach ( $addons as $addon_id ) {

			$addon_settings = get_post_meta( $addon_id, '_product_addons', true );

			if ( ! empty( $addon_settings ) ) {

				foreach ( $addon_settings as $setting ) {

					$key  = str_replace( '-', '_', sanitize_title( $setting['name'] ) );
					$type = $this->convert_field_type( $setting['type'] );

					if ( ! $type ) {
						continue;
					}

					$meta_fields[ $key ] = array(
						'label' => $setting['name'],
						'type'  => $type,
						'group' => 'wc_addons',
					);

					if ( $type == 'multiselect' && ! empty( $setting['options'] ) ) {

						foreach ( $setting['options'] as $i => $option ) {

							if ( ! empty( $option['label'] ) ) {
								$option['label'] = ' - ' . $option['label'];
							}

							$meta_fields[ $key . '_' . $i ] = array(
								'label' => $setting['name'] . $option['label'],
								'type'  => 'checkbox',
								'group' => 'wc_addons',
							);

						}
					}
				}
			}
		}

		// Get product specific options

		$args = array(
			'nopaging'   => true,
			'post_type'  => 'product',
			'fields'     => 'ids',
			'meta_query' => array(
				array(
					'key'     => '_product_addons',
					'compare' => 'EXISTS',
				),
			),
		);

		$products = get_posts( $args );

		if ( empty( $products ) ) {
			return $meta_fields;
		}

		foreach ( $products as $product_id ) {

			$addon_settings = get_post_meta( $product_id, '_product_addons', true );

			foreach ( $addon_settings as $i => $setting ) {

				$type = $this->convert_field_type( $setting['type'] );

				if ( ! $type ) {
					continue;
				}

				$meta_fields[ $product_id . '_' . $i ] = array(
					'label' => $setting['name'],
					'type'  => $type,
					'group' => 'wc_addons',
				);

				if ( $type == 'multiselect' && ! empty( $setting['options'] ) ) {

					foreach ( $setting['options'] as $ii => $option ) {

						if ( ! empty( $option['label'] ) ) {
							$option['label'] = ' - ' . $option['label'];
						}

						$meta_fields[ $product_id . '_' . $i . '_' . $ii ] = array(
							'label' => $setting['name'] . $option['label'],
							'type'  => 'checkbox',
							'group' => 'wc_addons',
						);

					}
				}
			}
		}

		return $meta_fields;

	}

}

new WPF_Woo_Addons();
