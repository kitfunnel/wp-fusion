<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * ONTRApages integration
 *
 * @since 3.37.4
 */

class WPF_Ontrapages extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'ontrapages';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Ontrapages';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Get things started.
	 *
	 * @since 3.37.4
	 */
	public function init() {

		add_action( 'init', array( $this, 'update_hooks' ), 15 );

	}

	/**
	 * Move the ONTRApages redirect hook to priority 20.
	 *
	 * @since 3.37.4
	 */
	public function update_hooks() {

		remove_action( 'template_redirect', array( 'ONTRApage', 'addOPContainerTemplate' ), 10 );
		add_action( 'template_redirect', array( 'ONTRApage', 'addOPContainerTemplate' ), 20 );

	}

}

new WPF_Ontrapages();
