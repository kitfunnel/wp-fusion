<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Breakdance builder integration.
 *
 * @since 3.40.43
 */
class WPF_Breakdance extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.43
	 * @var string $slug
	 */
	public $slug = 'Breakdance';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.43
	 * @var string $name
	 */
	public $name = 'breakdance';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.43
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/page-builders/breakdance/';

	/**
	 * Gets things started.
	 *
	 * @since 3.40.43
	 */
	public function init() {

		if ( ! wpf_get_option( 'restrict_content', true ) ) {
			return;
		}

		add_action( 'breakdance_register_template_types_and_conditions', array( $this, 'add_conditions' ) );

	}

	/**
	 * Add conditions to all fields.
	 *
	 * @since 3.40.43
	 */
	public function add_conditions() {

		$data = wp_fusion()->settings->get_available_tags_flat();

		$items = array();

		foreach ( $data as $key => $value ) {
			$items[] = array(
				'text'  => strval( $value ),
				'value' => strval( $key ),
			);
		}

		\Breakdance\ConditionsAPI\register(
			array(
				'supports'         => array( 'element_display' ),
				'slug'             => 'wpf_tags',
				'label'            => sprintf( __( 'Required %s Tags', 'wp-fusion' ), wp_fusion()->crm->name ),
				'category'         => __( 'WP Fusion', 'wp-fusion' ),
				'operands'         => array( 'has tag', 'does not have tag' ),
				'values'           => function() use ( $items ) {
					return array(
						array(
							'label' => sprintf( __( '%s Tags', 'wp-fusion' ), wp_fusion()->crm->name ),
							'items' => $items,
						),

					); },
				'allowMultiselect' => true,
				'callback'         => function( string $operand, $value ) {

					if ( ! wpf_is_user_logged_in() ) {
						return false;
					}

					$user_tags  = wp_fusion()->user->get_tags();
					$result     = array_intersect( $value, $user_tags );
					$can_access = true;

					if ( empty( $result ) && $operand === 'has tag' ) {
						$can_access = false;
					}

					if ( ! empty( $result ) && $operand === 'does not have tag' ) {
						$can_access = false;
					}

					return $can_access;
				},
			)
		);

	}

}

new WPF_BreakDance();
