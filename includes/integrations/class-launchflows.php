<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_LaunchFlows extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'launchflows';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Launch Flows';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/launchflows/';


	/**
	 * Gets things started
	 *
	 * @since 3.35.20
	 *
	 * @return void
	 */

	public function init() {

		if ( ! shortcode_exists( 'lf-apply-tags' ) ) {
			add_shortcode( 'lf-apply-tags', array( $this, 'layout_apply_tags' ) );
		}

		if ( ! shortcode_exists( 'lf-remove-tags' ) ) {
			add_shortcode( 'lf-remove-tags', array( $this, 'layout_remove_tags' ) );
		}

	}


	/**
	 * Apply tags when viewing widget
	 *
	 * @since 3.35.20
	 *
	 * @param array $atts Shortcode attributes.
	 * @return mixed HTML output.
	 */

	public function lf_layout_apply_tags( $atts ) {

		if ( ! is_admin() ) {

			$a = shortcode_atts(
				array(

					'tags'  => false,
					'debug' => false,

				),
				$atts,
				'lf-apply-tags'
			);

			if ( $a['tags'] ) {

					// parse type into an array, whitespace will be stripped
					$tags = array_map( 'trim', str_getcsv( $a['tags'], ',' ) );

			}

			// apply tags
			wp_fusion()->user->apply_tags( $tags );

			// optional for debug: show on front end
			if ( $a['debug'] == 'yes' ) {

				echo '<div id="lf-apply-tags" class="tags-wrapper">';

				// confirm
				if ( wp_fusion()->user->apply_tags( $tags ) ) {

					echo '<img class="wpf-logo" src="' . LF_DIR_URL . '/elementor/images/wpfusion.png"></img><br/>';

					echo '<strong>' . __( 'Applied: ', 'lf' ) . '</strong><br/><span class="wpf-tags">' . $a['tags'] . '</span>';

				} else {

					echo '<strong>' . __( 'Not Applied: ', 'lf' ) . '</strong><br/><span class="wpf-tags">' . $a['tags'] . '</span>';

				}

				echo '<br/>';
				echo '<strong>' . __( 'User Has: ', 'lf' ) . '</strong><br/>';
				$tags = wp_fusion()->user->get_tags();
				foreach ( $tags as $tag ) {
					echo $tag;
					echo ',';
				}

				echo '</div>';

			} // end debug
		}//end admin

	}

	/**
	 * Remove tags when viewing widget
	 *
	 * @since 3.35.20
	 *
	 * @param array $atts Shortcode attributes.
	 * @return mixed HTML output.
	 */

	public function lf_layout_remove_tags( $atts ) {

		if ( ! is_admin() ) {

			$a = shortcode_atts(
				array(

					'tags'  => false,
					'debug' => false,

				),
				$atts,
				'lf-remove-tags'
			);

			if ( $a['tags'] ) {

					// parse type into an array, whitespace will be stripped
					$tags = array_map( 'trim', str_getcsv( $a['tags'], ',' ) );

			}

			// apply tags
			wp_fusion()->user->remove_tags( $tags );

			// optional for debug: show on front end
			if ( $a['debug'] == 'yes' ) {

				echo '<div id="lf-remove-tags" class="tags-wrapper">';

				// confirm
				if ( wp_fusion()->user->remove_tags( $tags ) ) {

					echo '<img class="wpf-logo" src="' . LF_DIR_URL . '/elementor/images/wpfusion.png"></img><br/>';

					echo '<strong>' . __( 'Removed: ', 'lf' ) . '</strong><br/><span class="wpf-tags">' . $a['tags'] . '</span>';

				} else {

					echo '<strong>' . __( 'Not Removed: ', 'lf' ) . '</strong><br/><span class="wpf-tags">' . $a['tags'] . '</span>';

				}

				echo '<br/>';
				echo '<strong>' . __( 'User Has: ', 'lf' ) . '</strong><br/>';
				$tags = wp_fusion()->user->get_tags();
				foreach ( $tags as $tag ) {
					echo $tag;
					echo ',';
				}

				echo '</div>';

			} // end debug
		}//end admin

	}

}

new WPF_LaunchFlows();
