<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_EDD_Custom_Prices extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'edd-custom-prices';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Edd custom prices';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_edd_apply_tags_checkout', array( $this, 'apply_tags' ), 10, 2 );
		add_action( 'wpf_edd_meta_box_inner', array( $this, 'meta_box' ), 10, 2 );

	}

	/**
	 * Maybe apply bonus tags
	 *
	 * @access public
	 * @return array Tags to Apply
	 */

	public function apply_tags( $apply_tags, $payment ) {

		$payment_meta = $payment->get_meta();

		foreach ( $payment_meta['cart_details'] as $item ) {

			if ( isset( $item['item_number']['options']['cp_bonus_parent'] ) ) {

				$settings = get_post_meta( $item['item_number']['options']['cp_bonus_parent'], 'wpf-settings-edd', true );

				if ( ! empty( $settings ) && ! empty( $settings['apply_tags_bonus_item'] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_bonus_item'] );
					$apply_tags = array_unique( $apply_tags );
				}
			}
		}

		return $apply_tags;

	}


	/**
	 * Add settings to EDD meta box
	 *
	 * @access public
	 * @return mixed HTML Output
	 */

	public function meta_box( $post, $settings ) {

		if ( empty( $settings['apply_tags_bonus_item'] ) ) {
			$settings['apply_tags_bonus_item'] = array();
		}

		echo '<tr>';

			echo '<th scope="row"><label for="apply_tags_bonus_item">' . __( 'Apply Tags - Bonus Item', 'wp-fusion' ) . ':</label></th>';
			echo '<td>';
				wpf_render_tag_multiselect(
					array(
						'setting'   => $settings['apply_tags_bonus_item'],
						'meta_name' => 'wpf-settings-edd',
						'field_id'  => 'apply_tags_bonus_item',
					)
				);
				echo '<span class="description">' . __( 'Apply these tags when the bonus item is earned', 'wp-fusion' ) . '</span>';
			echo '</td>';

		echo '</tr>';

	}


}

new WPF_EDD_Custom_Prices();
