<?php

/**
 * Handles the WP Fusion settings on a single LearnDash course.
 *
 * @since 3.38.28
 */
if ( ( class_exists( 'LearnDash_Settings_Metabox' ) ) && ( ! class_exists( 'WPF_LearnDash_Metabox_Course_Settings' ) ) ) {
	/**
	 * Class to create the settings section.
	 */
	class WPF_LearnDash_Metabox_Course_Settings extends LearnDash_Settings_Metabox {

		/**
		 * Public constructor for class
		 */
		public function __construct() {
			// What screen ID are we showing on.
			$this->settings_screen_id = 'sfwd-courses';

			// Used within the Settings API to uniquely identify this section.
			$this->settings_metabox_key = 'learndash-course-wpf';

			// Section label/header.
			$this->settings_section_label = sprintf( esc_html__( 'WP Fusion - %s Settings.', 'wp-fusion' ), learndash_get_custom_label( 'course' ) );

			$this->settings_section_description = sprintf( esc_html__( 'Controls the WP Fusion settings for this %s.', 'wp-fusion' ), strtolower( learndash_get_custom_label( 'course' ) ) );

			$this->settings_section_description .= ' ' . sprintf( '<a href="https://wpfusion.com/documentation/learning-management/learndash/#course-settings-and-auto-enrollment" target="_blank" rel="noopener">%s &rarr;</a>', esc_html__( 'View documentation', 'wp-fusion' ) );

			parent::__construct();
		}



		/**
		 * Initialize the metabox settings fields.
		 */
		public function load_settings_fields() {

			$this->setting_option_fields = apply_filters( 'learndash_settings_fields_wpf', $this->setting_option_fields, $this->settings_metabox_key );

			parent::load_settings_fields();
		}
	}


	add_filter(
		'learndash_post_settings_metaboxes_init_' . learndash_get_post_type_slug( 'course' ),
		function( $metaboxes = array() ) {
			if ( ( ! isset( $metaboxes['WPF_LearnDash_Metabox_Course_Settings'] ) ) && ( class_exists( 'WPF_LearnDash_Metabox_Course_Settings' ) ) ) {
				$metaboxes['WPF_LearnDash_Metabox_Course_Settings'] = WPF_LearnDash_Metabox_Course_Settings::add_metabox_instance();
			}

			return $metaboxes;
		}
	);
}
