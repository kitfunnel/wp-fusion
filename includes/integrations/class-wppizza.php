<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_WPPizza extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wppizza';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Wppizza';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/wppizza/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'wppizza_on_order_execute', array( $this, 'order_execute' ), 10, 4 );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

		add_filter( 'wppizza_filter_admin_metaboxes', array( $this, 'admin_metabox' ), 80, 4 );
		add_filter( 'wppizza_filter_admin_save_metaboxes', array( $this, 'admin_save_metabox' ), 10, 3 );

	}


	/**
	 * Handle customer data when an order is placed
	 *
	 * @access public
	 * @return void
	 */

	public function order_execute( $order_id, $deprecated, $print_templates, $order_details ) {

		// Create / update contact

		$update_data = array();

		foreach ( $order_details['sections']['customer'] as $field => $data ) {

			$update_data[ $field ] = $data['value'];

		}

		$field_map = array(
			'cname'  => 'first_name',
			'cemail' => 'user_email',
		);

		$update_data = $this->map_meta_fields( $update_data, $field_map );

		if ( wpf_is_user_logged_in() ) {

			// Registered user

			wp_fusion()->user->push_user_meta( wpf_get_current_user_id(), $update_data );

		} else {

			// Guest checkout

			wpf_log( 'info', 0, 'WPPizza guest checkout:', array( 'meta_array' => $update_data ) );

			$contact_id = wp_fusion()->crm->get_contact_id( $update_data['user_email'] );

			if ( empty( $contact_id ) ) {
				$contact_id = wp_fusion()->crm->add_contact( $update_data );
			} else {
				wp_fusion()->crm->update_contact( $contact_id, $update_data );
			}

			if ( is_wp_error( $contact_id ) ) {

				wpf_log( $contact_id->get_error_code(), 0, 'Error adding contact: ' . $contact_id->get_error_message() );
				return;

			}

			update_post_meta( $order_id, WPF_CONTACT_ID_META_KEY, $contact_id );

		}

		// Apply tags
		$apply_tags = array();

		foreach ( $order_details['sections']['order']['items'] as $item ) {

			$settings = get_post_meta( $item['post_id'], 'wppizza', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags'] );
			}
		}

		if ( ! empty( $apply_tags ) ) {

			if ( wpf_is_user_logged_in() ) {

				wp_fusion()->user->apply_tags( $apply_tags );

			} else {

				wpf_log( 'info', 0, 'WPPizza guest checkout applying tag(s): ', array( 'tag_array' => $apply_tags ) );

				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

			}
		}

	}

	/**
	 * Adds WPPizza field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['wppizza'] = array(
			'title'  => 'WPPizza',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Add WPpizza fields
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function add_meta_fields( $meta_fields ) {

		$options = get_option( 'wppizza', array() );

		if ( ! empty( $options['order_form'] ) ) {

			foreach ( $options['order_form'] as $id => $field ) {

				$meta_fields[ $id ] = array(
					'label' => str_replace( ' :', '', $field['lbl'] ),
					'type'  => $field['type'],
					'group' => 'wppizza',
				);

			}
		}

		return $meta_fields;

	}

	/**
	 * Register settings on menu item
	 *
	 * @access  public
	 * @return  array Meta Box
	 */

	function admin_metabox( $wppizza_meta_box, $meta_values, $meal_sizes, $wppizza_options ) {

		$wppizza_meta_box['wpfusion'] = '<div class="wppizza_option_meta"><label class="wppizza-meta-label">' . __( 'Apply Tags', 'wp-fusion' ) . ':</label>';

		ob_start();

		$args = array(
			'setting'   => $meta_values['apply_tags'],
			'meta_name' => 'wppizza',
			'field_id'  => 'apply_tags',
		);

		wpf_render_tag_multiselect( $args );

		$wppizza_meta_box['wpfusion'] .= ob_get_clean();

		$wppizza_meta_box['wpfusion'] .= '</div>';

		return $wppizza_meta_box;

	}

	/**
	 * Save admin metabox
	 *
	 * @access  public
	 * @return  array Item Meta
	 */

	function admin_save_metabox( $item_meta, $item_id, $wppizza_options ) {

		$item_meta['apply_tags'] = $_POST['wppizza']['apply_tags'];

		return $item_meta;

	}


}

new WPF_WPPizza();
