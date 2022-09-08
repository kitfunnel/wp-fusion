<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * BuddyBoss WP Fusion CRM tags Membership Class.
 *
 * @since   3.36.10
 */
class WPF_BuddyBoss_Access_Control extends BB_Access_Control_Abstract {

	/**
	 * The single instance of the class.
	 *
	 * @since 3.36.10
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * WPF_BuddyBoss_Access_Control constructor.
	 *
	 * @since 3.36.10
	 */
	public function __construct() {

	}

	/**
	 * Get the instance of this class.
	 *
	 * @since 3.36.10
	 *
	 * @return Controller|null
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			$class_name           = __CLASS__;
			self::$instance       = new $class_name();
			self::$instance->slug = 'wp_fusion';
		}

		return self::$instance;
	}


	/**
	 * Function will return all the available CRM tags.
	 *
	 * @since 3.36.10
	 *
	 * @return array list of available CRM tags.
	 */
	public function get_level_lists() {

		$available_tags = wp_fusion()->settings->get_available_tags_flat();
		$results        = array();

		foreach ( $available_tags as $id => $label ) {
			$results[] = array(
				'id'   => $id,
				'text' => $label,
			);
		}

		return apply_filters( 'bb_wp_fusion_get_level_lists', $results );

	}

	/**
	 * Function will check whether user has access or not.
	 *
	 * @param int     $user_id       user id.
	 * @param array   $settings_data DB settings.
	 * @param boolean $threaded      threaded check.
	 *
	 * @since 3.36.10
	 *
	 * @return boolean whether user has access to do a particular given action.
	 */
	public function has_access( $user_id = 0, $settings_data = array(), $threaded = false ) {

		$has_access = parent::has_access( $user_id, $settings_data, $threaded );

		if ( ! is_null( $has_access ) ) {
			return $has_access;
		}

		if ( true == wpf_get_option( 'exclude_admins' ) && current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( empty( $settings_data['access-control-options'] ) ) {
			// If no tags are specified
			return true;
		}

		$has_access = false;
		$user_tags  = wp_fusion()->user->get_tags( bp_loggedin_user_id() );

		if ( $threaded ) {

			$recipient_tags = wp_fusion()->user->get_tags( $user_id );

			// Threaded, like messaging

			foreach ( $settings_data['access-control-options'] as $tag_id ) {

				if ( in_array( $tag_id, $user_tags ) ) {

					// Meets the condition for sending

					if ( ! empty( $settings_data[ 'access-control-' . $tag_id . '-options' ] ) ) {

						if ( ! empty( array_intersect( $recipient_tags, $settings_data[ 'access-control-' . $tag_id . '-options' ] ) ) ) {

							// Recipient matches the condition for receiving
							$has_access = true;
							break;

						}
					} else {

						// No recipient condition
						$has_access = true;
						break;

					}
				}
			}
		} else {

			if ( ! empty( array_intersect( $user_tags, $settings_data['access-control-options'] ) ) ) {
				$has_access = true;
			}
		}

		return apply_filters( 'bb_access_control_' . $this->slug . '_has_access', $has_access, $user_id );

	}
}
