<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * SureCart integration class.
 *
 * @since 3.40.48
 */

class WPF_SureCart extends WPF_Integrations_Base {

	/**
	 * This identifies the integration internally and makes it available at
	 * wp_fusion()->integrations->{'my-plugin-slug'}
	 *
	 * @var  string
	 * @since 3.40.48
	 */
	public $slug = 'surecart';

	/**
	 * The human-readable name of the integration.
	 *
	 * @var  string
	 * @since 3.40.48
	 */
	public $name = 'SureCart';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.48
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/surecart/';

	/**
	 * Get things started.
	 *
	 * @since 3.40.48
	 */
	public function init() {

		$this->includes();

		( new \WPFusion\Integrations\Apply_Tags() )->bootstrap();
		( new \WPFusion\Integrations\Remove_Tags() )->bootstrap();
	}

	/**
	 * Includes.
	 *
	 * @since 3.41.46
	 */
	public function includes() {

		require_once __DIR__ . '/class-apply-tags.php';
		require_once __DIR__ . '/class-remove-tags.php';
	}

}

new WPF_SureCart();
