<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_WP_Job_Manager extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wp-job-manager';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Wp job manager';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/wp-job-manager/';

	/**
	 * Get things started
	 *
	 * @access public
	 * @return void
	 */

	public function init() {

		// Job manager
		add_filter( 'job_manager_settings', array( $this, 'add_settings' ) );
		add_action( 'wp_job_manager_admin_field_wpf_select_tags', array( $this, 'render_tags_select' ), 10, 4 );
		add_action( 'job_manager_job_submitted', array( $this, 'apply_job_submitted_tags' ) );

		// WPF
		add_action( 'wpf_user_created', array( $this, 'apply_registration_tags' ), 10, 3 );

		// Job Alerts
		add_action( 'admin_init', array( $this, 'register_taxonomy_form_fields' ) );
		add_action( 'added_post_meta', array( $this, 'new_alert' ), 5, 4 );

	}

	/**
	 * Add settings to WP Job Manager settings page
	 *
	 * @access public
	 * @return array Settings
	 */

	public function add_settings( $settings ) {

		$settings['job_submission'][1][] = array(
			'name'  => 'job_manager_wpf_settings',
			'std'   => array(),
			'label' => __( 'Apply Tags', 'wp-fusion' ),
			'desc'  => __( 'These tags will be applied in ' . wp_fusion()->crm->name . ' when a user submits a job or creates a WP Job Manager profile.', 'wp-fusion' ),
			'type'  => 'wpf_select_tags',
		);

		return $settings;

	}

	/**
	 * Output render tags multiselect
	 *
	 * @access public
	 * @return mixed HTML Output
	 */

	public function render_tags_select( $option, $attributes, $value, $placeholder ) {

		wpf_render_tag_multiselect(
			array(
				'setting'   => $value['apply_tags'],
				'meta_name' => 'job_manager_wpf_settings',
				'field_id'  => 'apply_tags',
			)
		);
		echo '<p class="description">' . $option['desc'] . '</p>';

	}

	/**
	 * Apply job manager tags when job submitted
	 *
	 * @access public
	 * @return void
	 */

	public function apply_job_submitted_tags( $job_id ) {

		$wpf_settings = get_option( 'job_manager_wpf_settings' );

		if ( empty( $wpf_settings ) || empty( $wpf_settings['apply_tags'] ) ) {
			return;
		}

		wp_fusion()->user->apply_tags( $wpf_settings['apply_tags'] );

	}


	/**
	 * Apply job manager registration tags
	 *
	 * @access public
	 * @return void
	 */

	public function apply_registration_tags( $user_id, $contact_id, $post_data ) {

		if ( ! isset( $post_data['job_manager_form'] ) ) {
			return;
		}

		$wpf_settings = get_option( 'job_manager_wpf_settings' );

		if ( ! empty( $wpf_settings ) && ! empty( $wpf_settings['apply_tags'] ) ) {
			wp_fusion()->user->apply_tags( $wpf_settings['apply_tags'], $user_id );
		}

	}

	//
	// JOB ALERTS
	//

	/**
	 * Add settings to Job Type taxonomy
	 *
	 * @access public
	 * @return void
	 */

	public function register_taxonomy_form_fields() {

		if ( class_exists( 'WP_Job_Manager_Alerts' ) ) {

			add_action( 'job_listing_type_edit_form_fields', array( $this, 'taxonomy_form_fields' ), 5, 2 );
			add_action( 'edited_job_listing_type', array( $this, 'save_taxonomy_form_fields' ), 10, 2 );

		}

	}


	/**
	 * Output settings to taxonomies
	 *
	 * @access public
	 * @return mixed HTML Output
	 */

	public function taxonomy_form_fields( $term ) {

		$t_id = $term->term_id;

		// retrieve the existing value(s) for this meta field. This returns an array
		$taxonomy_rules = get_option( 'wpf_job_alerts_taxonomy_rules', array() );

		if ( isset( $taxonomy_rules[ $t_id ] ) ) {

			$settings = $taxonomy_rules[ $t_id ];

		} else {

			$settings = array();

		}

		?>

		</table>

		<table id="wpf-meta" class="form-table" style="max-width: 800px;">

			<tbody>

				<tr class="form-field">
					<th style="padding-bottom: 0px;" colspan="2"><h3 style="margin: 0px;">WP Fusion - Job Alerts Settings</h3></th>
				</tr>

				<tr class="form-field">
					<th scope="row" valign="top"><label for="wpf-apply-tags"><?php _e( 'Apply Tags', 'wp-fusion' ); ?></label></th>
					<td style="max-width: 400px;">

						<?php

						$args = array(
							'setting'   => $settings['apply_tags'],
							'meta_name' => 'wpf-job-alerts-settings',
							'field_id'  => 'apply_tags',
						);

						wpf_render_tag_multiselect( $args );
						?>

						<p class="description">Apply these tags to a user when they sign up for a job alert of this job type</p>

					</td>
				</tr>

		<?php
	}

	/**
	 * Save taxonomy settings
	 *
	 * @access public
	 * @return void
	 */

	public function save_taxonomy_form_fields( $term_id ) {

		if ( isset( $_POST['wpf-job-alerts-settings'] ) ) {

			$wpf_settings = $_POST['wpf-job-alerts-settings'];

			$taxonomy_rules             = get_option( 'wpf_job_alerts_taxonomy_rules', array() );
			$taxonomy_rules[ $term_id ] = $wpf_settings;

			// Save the option array.
			update_option( 'wpf_job_alerts_taxonomy_rules', $taxonomy_rules );

		}

	}

	/**
	 * Apply tags on new Job Alert
	 *
	 * @access public
	 * @return void
	 */

	public function new_alert( $meta_id, $post_id, $meta_key, $_meta_value ) {

		if ( $meta_key != 'alert_search_terms' || empty( $_meta_value['types'] ) ) {
			return;
		}

		$taxonomy_rules = get_option( 'wpf_job_alerts_taxonomy_rules', array() );

		if ( empty( $taxonomy_rules ) ) {
			return;
		}

		foreach ( $_meta_value['types'] as $term_id ) {

			if ( ! empty( $taxonomy_rules[ $term_id ] ) && ! empty( $taxonomy_rules[ $term_id ]['apply_tags'] ) ) {

				wp_fusion()->user->apply_tags( $taxonomy_rules[ $term_id ]['apply_tags'] );

			}
		}

	}


}

new WPF_WP_Job_Manager();
