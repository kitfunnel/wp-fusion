<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ACF Frontend integration.
 *
 * Integration with https://wordpress.org/plugins/acf-frontend-form-element/.
 *
 * @since 3.39.2
 */
class WPF_ACF_Frontend extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.39.2
	 * @var string $slug
	 */

	public $slug = 'acf-frontend';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.39.2
	 * @var string $name
	 */
	public $name = 'ACF Frontend';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.39.2
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/advanced-custom-fields/';

	/**
	 * Gets things started.
	 *
	 * @since 3.39.2
	 */
	public function init() {

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 10 );

		add_filter( 'wpf_user_update', array( $this, 'user_update' ), 5, 2 ); // 5 so it runs before WPF_ACF::user_update().
		add_filter( 'wpf_user_register', array( $this, 'user_update' ), 5, 2 ); // 5 so it runs before WPF_ACF::user_update().

	}

	/**
	 * Adds ACF frontend field group to meta fields list.
	 *
	 * @since  3.39.2
	 *
	 * @param  array $field_groups The field groups.
	 * @return array  Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['acf-frontend'] = array(
			'title'  => 'ACF Frontend',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Get all user fields from all ACF frontend forms.
	 *
	 * @since  3.39.2
	 *
	 * @return array The fields.
	 */
	private function get_user_fields() {

		$args = array(
			'post_type'           => 'admin_form',
			'meta_key'            => 'admin_form_type',
			'meta_value'          => array( 'edit_user', 'new_user' ),
			'posts_per_page'      => 100,
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		$forms = get_posts( $args );

		if ( empty( $forms ) ) {
			return array();
		}

		$custom_fields = array();

		foreach ( $forms as $form_id ) {

			$args = array(
				'post_type'           => 'acf-field',
				'posts_per_page'      => 100,
				'post_parent'         => $form_id,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			);

			$custom_fields = array_merge( $custom_fields, get_posts( $args ) );
		}

		return $custom_fields;
	}

	/**
	 * Get sub fields of an ACF field like 'repeater'.
	 *
	 * @since  3.39.2
	 *
	 * @param  WP_Post $post   The post.
	 * @return array   The fields.
	 */
	private function get_sub_fields( $post ) {
		$args = array(
			'post_type'           => 'acf-field',
			'posts_per_page'      => 100,
			'post_parent'         => $post->ID,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		return get_posts( $args );
	}

	/**
	 * Set field labels from ACF frontend field labels.
	 *
	 * @since  3.39.2
	 *
	 * @param  array $meta_fields The meta fields.
	 * @return array Meta fields.
	 */
	public function prepare_meta_fields( $meta_fields = array() ) {

		$non_custom = array( 'username', 'user_email', 'user_password', 'first_name', 'last_name', 'role', 'submit_button' );

		$user_fields = $this->get_user_fields();

		if ( empty( $user_fields ) ) {
			return $meta_fields;
		}

		foreach ( $user_fields as $post ) {

			if ( in_array( $post->post_excerpt, $non_custom ) ) {
				continue;
			}

			$data = unserialize( $post->post_content );

			if ( 'group' === $data['type'] || 'repeater' === $data['type'] || 'flexible_content' === $data['type'] ) {

				$sub_fields         = $this->get_sub_fields( $post );
				$data['sub_fields'] = $sub_fields;

				// Flexible content.
				if ( 'flexible_content' === $data['type'] ) {
					$data['sub_fields'] = array();

					foreach ( $data['layouts'] as $layout ) {
						$data['sub_fields'] = array_merge( $data['sub_fields'], $layout['sub_fields'] );
					}
				}

				foreach ( $data['sub_fields'] as $child_post ) {
					$sub_field = unserialize( $child_post->post_content );

					// Fix formats.
					if ( $sub_field['type'] == 'date_picker' || $sub_field['type'] == 'date_time_picker' ) {
						$sub_field['type'] = 'date';
					} elseif ( $sub_field['type'] == 'checkbox' || $data['type'] == 'repeater' || $data['type'] == 'flexible_content' ) {
						$sub_field['type'] = 'multiselect';
					} elseif ( $sub_field['type'] == 'true_false' ) {
						$sub_field['type'] = 'checkbox';
					} elseif ( 'user' === $data['type'] && 1 === $data['multiple'] ) {
						$data['type'] = 'relationship';
					}

					$meta_fields[ $post->post_excerpt . '_' . $child_post->post_excerpt ] = array(
						'label' => $child_post->post_title,
						'type'  => $sub_field['type'],
						'group' => 'acf-frontend',
					);

				}
			} else {

				// Fix formats.
				if ( $data['type'] == 'date_picker' || $data['type'] == 'date_time_picker' ) {
					$data['type'] = 'date';
				} elseif ( $data['type'] == 'checkbox' ) {
					$data['type'] = 'multiselect';
				} elseif ( $data['type'] == 'true_false' ) {
					$data['type'] = 'checkbox';
				} elseif ( 'user' === $data['type'] && 1 === $data['multiple'] ) {
					$data['type'] = 'relationship';
				}

				$meta_fields[ $post->post_excerpt ] = array(
					'label' => $post->post_title,
					'type'  => $data['type'],
					'group' => 'acf-frontend',
				);

			}
		}

		return $meta_fields;
	}


	/**
	 * Sync user fields when updated on frontend.
	 *
	 * @since  3.39.2
	 *
	 * @param  array $post_data The submitted form data.
	 * @param  int   $user_id   The user ID.
	 * @return array The submitted form data.
	 */
	public function user_update( $post_data, $user_id ) {

		if ( ! empty( $post_data['acff'] ) && ! empty( $post_data['acff']['user'] ) ) {
			$post_data = array_merge( $post_data, $post_data['acff']['user'] );
		}

		return $post_data;

	}

}

new WPF_ACF_Frontend();
