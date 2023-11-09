<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_BP_Account_Deactivator extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'bp-account-deactivator';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Bp account deactivator';

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

		add_action( 'wpf_tags_modified', array( $this, 'tags_modified' ), 10, 2 );

		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

	}


	/**
	 * Updates account status if linked tag is applied
	 *
	 * @access public
	 * @return void
	 */

	public function tags_modified( $user_id, $user_tags ) {

		$setting = wpf_get_option( 'bpad_deactivation_tag' );

		if ( ! empty( $setting ) ) {

			$is_active = bp_account_deactivator()->is_active( $user_id );

			$match = array_intersect( $setting, $user_tags );

			if ( ! empty( $match ) && true == $is_active ) {

				// Deactivate account
				bp_account_deactivator()->set_inactive( $user_id );

			} elseif ( empty( $match ) && false == $is_active ) {

				// Reactivate account
				bp_account_deactivator()->set_active( $user_id );

			}
		}

	}



	/**
	 * Add fields to settings page
	 *
	 * @access public
	 * @return array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['bpad_header'] = array(
			'title'   => __( 'BuddyPress Account Deactivator Integration', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['bpad_deactivation_tag'] = array(
			'title'       => __( 'Deactivation Tag', 'wp-fusion' ),
			'desc'        => __( 'You can specify a tag here to be used as an account deactivation trigger.<br />When the tag is applied the account will be set to deactivated. When the tag is removed the account will be reactivated.', 'wp-fusion' ),
			'std'         => array(),
			'type'        => 'assign_tags',
			'section'     => 'integrations',
			'limit'       => 1,
			'placeholder' => __( 'Select tag', 'wp-fusion' ),
		);

		return $settings;

	}


}

new WPF_BP_Account_Deactivator();
