<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Memberium extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'memberium';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Memberium';

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
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_bypass_profile_update', array( $this, 'bypass_update' ), 10, 2 );

	}


	/**
	 * We don't want to send data back to the CRM after Memberium has just received an API call
	 *
	 * @access public
	 * @return bool Bypass
	 */

	public function bypass_update( $bypass, $request ) {

		if ( defined( 'MEMBERIUM_SKU' ) && MEMBERIUM_SKU == 'm4ac' && memberium()->getDoingWebHook( $deprecated = null ) ) {
			$bypass = true;
		}

		return $bypass;

	}


}

new WPF_Memberium();
