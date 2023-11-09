<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Uncanny_Groups extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'uncanny-groups';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Uncanny LearnDash Groups';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/uncanny-learndash-groups/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'ulgm_group_user_invited', array( $this, 'group_user_added' ), 10, 2 );
		add_action( 'ulgm_existing_group_user_added', array( $this, 'group_user_added' ), 10, 2 );
		add_action( 'ulgm_user_registered', array( $this, 'user_registered' ), 10, 2 );

		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );
		add_action( 'wpf_woocommerce_panel', array( $this, 'panel_content' ), 5 );

	}

	/**
	 * Apply tags when a user is added to a group.
	 *
	 * @since 3.25.7
	 *
	 * @param int|array $user_id  The user ID or array of user data.
	 * @param int       $group_id The group ID.
	 */
	public function group_user_added( $user_id, $group_id ) {

		if ( is_array( $user_id ) ) {
			// The ulgm_group_user_invited and ulgm_existing_group_user_added hooks pass
			// an array of user data.
			$user    = get_user_by( 'email', $user_id['user_email'] );
			$user_id = $user->ID;
		}

		$product_id = uncanny_learndash_groups\SharedFunctions::get_product_id_from_group_id( $group_id );

		if ( ! empty( $product_id ) ) {

			// Subscription and group license products.

			$settings = get_post_meta( $product_id['product_id'], 'wpf-settings-woo', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_group_user_added'] ) ) {

				wp_fusion()->user->apply_tags( $settings['apply_tags_group_user_added'], $user_id );
				return;

			}

			// Group course products, need to read the group course product IDs.

			$license = get_post_meta( $product_id['product_id'], '_ulgm_license', true );

			if ( ! empty( $license ) ) {

				foreach ( $license as $license_id ) {

					$settings = get_post_meta( $license_id, 'wpf-settings-woo', true );

					if ( ! empty( $settings ) && ! empty( $settings['apply_tags_group_user_added'] ) ) {

						wp_fusion()->user->apply_tags( $settings['apply_tags_group_user_added'], $user_id );

					}
				}
			}
		}

	}

	/**
	 * Fires after a user is registered in the frontend, just before redirection.
	 *
	 * @since 3.40.35
	 *
	 * @param int        $user_id      Newly registered user ID.
	 * @param array|null $code_details Redemption code details, if available.
	 */
	public function user_registered( $user_id, $code_details ) {

		if ( ! $code_details ) {
			return;
		}

		$this->group_user_added( $user_id, $code_details['ld_group_id'] );

	}

	/**
	 * Fix names getting added as arrays with Uncanny Groups
	 *
	 * @access  public
	 * @return  array Post data
	 */

	public function user_register( $post_data, $user_id ) {

		if ( isset( $post_data['first_name'] ) && is_array( $post_data['first_name'] ) ) {
			$post_data['first_name'] = get_user_meta( $user_id, 'first_name', true );
		}

		if ( isset( $post_data['last_name'] ) && is_array( $post_data['last_name'] ) ) {
			$post_data['last_name'] = get_user_meta( $user_id, 'last_name', true );
		}

		if ( isset( $post_data['user_email'] ) && is_array( $post_data['user_email'] ) ) {

			$user                    = get_userdata( $user_id );
			$post_data['user_email'] = $user->user_email;

		}

		return $post_data;

	}

	/**
	 * Output Woo settings
	 *
	 * @access  public
	 * @return  mixed Panel Content
	 */

	public function panel_content( $post_id ) {

		$settings = array(
			'apply_tags_group_user_added' => array(),
		);

		if ( get_post_meta( $post_id, 'wpf-settings-woo', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post_id, 'wpf-settings-woo', true ) );
		}

		echo '<div class="options_group wpf-product show_if_courses show_if_subscription show_if_variable">';

		echo '<p><strong>' . __( 'Group License', 'wp-fusion' ) . '</strong> - ' . sprintf( __( '%1$sSee documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/learning-management/uncanny-learndash-groups/" target="_blank">', '</a>' ) . '</p>';

		echo '<p class="form-field"><label for="wpf-apply-tags-woo" class="show_if_courses">' . __( 'Apply tags when a user is added to this group course', 'wp-fusion' ) . ':</label>';

		echo '<label for="wpf-apply-tags-woo" class="show_if_subscription show_if_license">' . __( 'Apply tags when a user is added to this group license', 'wp-fusion' ) . ':</label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_group_user_added'],
				'meta_name' => 'wpf-settings-woo',
				'field_id'  => 'apply_tags_group_user_added',
			)
		);

		echo '</p>';

		echo '</div>';

	}


}

new WPF_Uncanny_Groups();
