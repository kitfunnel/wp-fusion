<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_FacetWP extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'facetwp';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'FacetWP';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/facetwp/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'facetwp_pre_filtered_post_ids', array( $this, 'filter_posts' ), 10, 2 );
		add_filter( 'facetwp_settings_admin', array( $this, 'admin_settings' ), 10, 2 );

		add_action( 'wp_footer', array( $this, 'set_headers' ) );

	}


	/**
	 * Filters restricted posts from results
	 *
	 * @access public
	 * @return array Post IDs
	 */

	public function filter_posts( $post_ids, $class ) {

		if ( 'yes' === FWP()->helper->get_setting( 'wpf_hide_restricted', 'no' ) ) {

			foreach ( $post_ids as $i => $post_id ) {

				if ( ! wp_fusion()->access->user_can_access( $post_id ) ) {
					unset( $post_ids[ $i ] );
				}
			}
		}

		return $post_ids;

	}

	/**
	 * FacetWP uses the REST API, unauthenticated. To personalize results we need to pass a nonce (from https://facetwp.com/pass-authentication-data-through-rest-api-requests/)
	 *
	 * @access public
	 * @return mixed
	 */

	public function set_headers() {

		if ( 'no' === FWP()->helper->get_setting( 'wpf_hide_restricted', 'no' ) ) {
			return;
		}

		?>

		<!-- Added by WP Fusion so that FacetWP results can be personalized based on the current logged in user -->

		<script>

		(function($) {
			$(function() {

				if ( 'object' !== typeof FWP ) {
					return;
				}

				FWP.hooks.addFilter('facetwp/ajax_settings', function(settings) {
					settings.headers = {
						'X-WP-Nonce': FWP_JSON.nonce
					};
					return settings;
				});
			});
		})(jQuery);

		</script>

		<?php

	}

	/**
	 * Add WPF settings to FWP admin
	 *
	 * @access public
	 * @return array Settings
	 */

	public function admin_settings( $settings, $settings_class ) {

		$settings['wp-fusion'] = array(
			'label'  => __( 'WP Fusion', 'wp-fusion' ),
			'fields' => array(
				'wpf_hide_restricted' => array(
					'label' => __( 'Exclude restricted items?', 'wp-fusion' ),
					'notes' => __( 'Any posts that the user doesn\'t have access to will be hidden from the results.', 'fwp' ),
					'html'  => $settings_class->get_setting_html( 'wpf_hide_restricted', 'toggle' ),
				),
			),
		);

		return $settings;

	}


}

new WPF_FacetWP();
