<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_WPEP extends WPEP_Content_Library_Integration {

	public $service_name   = 'WP Fusion';
	public $options_prefix = 'wpep_wpf_';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function __construct() {

		wp_fusion()->integrations->wpep = $this;

		$this->init();

		// Add meta field group
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );
		add_filter( 'wpf_watched_meta_fields', array( $this, 'watch_meta_fields' ) );

		add_filter( 'wpf_user_update', array( $this, 'user_update' ), 10, 2 );
		add_filter( 'wpf_user_register', array( $this, 'user_update' ), 10, 2 );

		// Apply tags on course completion
		add_action( 'wpep_user_set_course_data', array( $this, 'apply_tags_wpep_complete' ), 10, 7 );

		// Settings
		add_action( 'wpf_meta_box_content', array( $this, 'meta_box_content' ), 40, 2 );

	}


	/**
	 * Adds field group for WPEP to contact fields list
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['wpep'] ) ) {
			$field_groups['wpep'] = array(
				'title'  => 'eLearnCommerce',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Sets field labels and types for WPEP custom fields
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields[ WPEP_USER_META_AUTO_LOGIN_TOKEN ] = array(
			'label' => 'Auto Login Token',
			'type'  => 'text',
			'group' => 'wpep',
		);

		return $meta_fields;

	}

	/**
	 * Sets up token to automatically sync
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function watch_meta_fields( $meta_fields ) {

		$meta_fields[] = WPEP_USER_META_AUTO_LOGIN_TOKEN;

		return $meta_fields;

	}

	/**
	 * Generate autologin token on push if needed
	 *
	 * @access  public
	 * @return  array Update Data
	 */

	public function user_update( $update_data, $user_id ) {

		if ( empty( $update_data[ WPEP_USER_META_AUTO_LOGIN_TOKEN ] ) ) {

			$user_token = get_user_meta( $user_id, WPEP_USER_META_AUTO_LOGIN_TOKEN, true );

			if ( $user_token === '' ) {

				$salt       = wpep_get_setting( 'auto-login-link-salt' );
				$user_token = sha1( $salt . wpep_generate_random_token( 32 ) );
				update_user_meta( $user_id, WPEP_USER_META_AUTO_LOGIN_TOKEN, $user_token );

				$update_data[ WPEP_USER_META_AUTO_LOGIN_TOKEN ] = $user_token;

			} else {

				$update_data[ WPEP_USER_META_AUTO_LOGIN_TOKEN ] = $user_token;

			}
		}

		return $update_data;

	}


	/**
	 * Determine whether or not a user has access to content
	 *
	 * @access public
	 * @return bool
	 */

	public function has_access( $post_id ) {

		if ( ! wp_fusion()->access->user_can_access( $post_id ) ) {
			return false;
		} else {
			return true;
		}

	}


	/**
	 * Displays restricted item button text in course grid
	 *
	 * @access public
	 * @return string Text
	 */

	public function get_sell_text( $post_id = 0 ) {

		if ( ! $this->has_access( $post_id ) ) {

			$settings = get_post_meta( $post_id, 'wpf-settings', true );

			if ( ! empty( $settings['restricted_button_text'] ) ) {
				return $settings['restricted_button_text'];
			}
		}

		return $this->sell_button_text;

	}


	/**
	 * Applies tags when a WPEP course is completed
	 *
	 * @access public
	 * @return void
	 */

	public function apply_tags_wpep_complete( $key, $progress, $course_id, $section_id, $lesson_id, $user_id, $updated ) {

		if ( $key == 'course_completed' && $progress == 1 ) {

			$wpf_settings = get_post_meta( $course_id, 'wpf-settings', true );

			if ( ! empty( $wpf_settings['apply_tags_wpep'] ) ) {
				wp_fusion()->user->apply_tags( $wpf_settings['apply_tags_wpep'], $user_id );
			}
		}

	}


	/**
	 * Adds WPEP fields to WPF meta box
	 *
	 * @access public
	 * @return void
	 */

	public function meta_box_content( $post, $settings ) {

		if ( $post->post_type != WPEP_POST_TYPE_COURSE ) {
			return;
		}

		echo '<hr/>';

		echo '<strong style="margin-top: 5px; display: inline-block;">eLearnCommerce Course:</strong>';

		echo '<p><label for="wpf-apply-tags-wpep"><small>Apply these tags when marked complete:</small></label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_wpep'],
				'meta_name' => 'wpf-settings',
				'field_id'  => 'apply_tags_wpep',
			)
		);

		echo '</p>';

		echo '<p><label for="wpf-restricted-course-message"><small>Button text to display when course is restricted:</small></label>';

		if ( ! isset( $settings['restricted_button_text'] ) ) {
			$settings['restricted_button_text'] = '';
		}

		echo '<input type="text" id="wpf-restricted-course-message" placeholder="' . $this->sell_button_text . '" name="wpf-settings[restricted_button_text]" value="' . $settings['restricted_button_text'] . '">';

		echo '</p>';

	}

}

new WPF_WPEP();
