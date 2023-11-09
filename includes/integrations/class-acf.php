<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_ACF extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'acf';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Advanced Custom Fields';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/advanced-custom-fields/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 15 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 10 );
		add_action( 'wpf_user_meta_updated', array( $this, 'user_meta_updated' ), 10, 3 );
		add_filter( 'wpf_user_update', array( $this, 'user_update' ), 10, 2 );
		add_filter( 'wpf_user_register', array( $this, 'user_update' ), 10, 2 );

		add_filter( 'wpf_meta_box_post_types', array( $this, 'unset_wpf_meta_boxes' ) );

		add_action( 'af/form/submission', array( $this, 'save_user_form' ), 10, 3 ); // Advanced Forms Pro

	}


	/**
	 * Adds ACF field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['acf'] = array(
			'title'  => 'Advanced Custom Fields',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Set field labels from ACF field labels
	 *
	 * @access public
	 * @return array Settings
	 */

	public function prepare_meta_fields( $meta_fields = array() ) {

		// Only works with ACF pro
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return $meta_fields;
		}

		// Query ACF for field groups registered on the user edit page
		$field_groups = acf_get_field_groups();

		if ( empty( $field_groups ) ) {
			return $meta_fields;
		}

		// Limit it to just user field groups.
		foreach ( $field_groups as $i => $group ) {

			if ( empty( $group ) ) {
				continue;
			}

			foreach ( $group['location'] as $location ) {

				if ( 'user_form' === $location[0]['param'] || 'user_role' === $location[0]['param'] ) {
					continue 2;
				}
			}

			unset( $field_groups[ $i ] );

		}

		foreach ( $field_groups as $field_group ) {

			$fields = acf_get_fields( $field_group );

			foreach ( (array) $fields as $field => $data ) {

				if ( 'group' === $data['type'] || 'repeater' === $data['type'] || 'flexible_content' === $data['type'] ) {

					// Flexible content.
					if ( 'flexible_content' === $data['type'] ) {
						$data['sub_fields'] = array();

						foreach ( $data['layouts'] as $layout ) {
							$data['sub_fields'] = array_merge( $data['sub_fields'], $layout['sub_fields'] );
						}
					}

					if ( ! empty( $data['sub_fields'] ) ) {

						foreach ( $data['sub_fields'] as $sub_field ) {

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

							$meta_fields[ $data['name'] . '_' . $sub_field['name'] ] = array(
								'label' => $sub_field['label'],
								'type'  => $sub_field['type'],
								'group' => 'acf',
							);

						}
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

					$meta_fields[ $data['name'] ] = array(
						'label' => $data['label'],
						'type'  => $data['type'],
						'group' => 'acf',
					);

				}
			}
		}

		return $meta_fields;

	}

	/**
	 * Removes standard WPF meta boxes from ACF related post types
	 *
	 * @access  public
	 * @return  array Post Types
	 */

	public function unset_wpf_meta_boxes( $post_types ) {

		unset( $post_types['acf-field'] );
		unset( $post_types['acf'] );
		unset( $post_types['acf-field-group'] );

		return $post_types;

	}


	/**
	 * Formats ACF fields from internal forms before sending update to CRM
	 *
	 * @access  public
	 * @return  void
	 */

	public function user_update( $post_data, $user_id ) {

		if ( ! empty( $post_data['acf'] ) && is_array( $post_data['acf'] ) ) {
			$post_data = array_merge( $post_data, $post_data['acf'] ); // From profile edits.
			unset( $post_data['acf'] );
		}

		foreach ( (array) $post_data as $field_id => $value ) {

			$field_object = get_field_object( $field_id, 'user_' . $user_id );

			if ( false === $field_object ) {
				continue; // not an ACF field.
			}

			// Don't erase a value with an empty one.
			if ( ! empty( $post_data[ $field_object['name'] ] ) && empty( $value ) ) {
				continue;
			}

			// Move it up into the main array.

			if ( 'group' === $field_object['type'] && is_array( $value ) ) {

				// Groups.

				foreach ( $value as $sub_field_id => $sub_value ) {

					$sub_field_object  = get_field_object( $sub_field_id, 'user_' . $user_id );
					$key               = $field_object['name'] . '_' . $sub_field_object['name'];
					$post_data[ $key ] = maybe_unserialize( $sub_value );
				}
			} elseif ( ( 'repeater' === $field_object['type'] || 'flexible_content' === $field_object['type'] ) && is_array( $value ) ) {

				// Repeaters & flexible content.

				foreach ( $value as $row ) {

					if ( is_array( $row ) ) {

						foreach ( $row as $sub_field_id => $sub_value ) {

							$sub_field_object = get_field_object( $sub_field_id, 'user_' . $user_id );

							if ( false === $sub_field_object ) {
								continue;
							}

							$key = $field_object['name'] . '_' . $sub_field_object['name'];

							if ( ! isset( $post_data[ $key ] ) ) {
								$post_data[ $key ] = array();
							}

							$post_data[ $key ][] = maybe_unserialize( $sub_value );
						}
					}
				}
			} else {

				// Regular fields.

				$key               = $field_object['name'];
				$post_data[ $key ] = maybe_unserialize( $value );

			}

			// Do some basic formatting.

			if ( 'date' === wpf_get_field_type( $key ) || 'image' === wpf_get_field_type( $key ) ) {

				$value = acf_format_date( $value, $field_object['return_format'] );

				// Convert / to - with European date formats, so strtotime() can understand it.
				if ( 0 === strpos( $field_object['return_format'], 'd/' ) ) {
					$value = str_replace( '/', '-', $value );
				}

				$post_data[ $key ] = $value;

			} elseif ( 'relationship' === wpf_get_field_type( $key ) && is_array( $value ) && wpf_is_field_active( $key ) ) {

				// Relationship fields.

				if ( 'user' === $field_object['type'] ) {

					// Multi-users.

					foreach ( $post_data[ $key ] as $i => $user_id ) {
						$post_data[ $key ][ $i ] = get_user_meta( $user_id, 'first_name', true ) . ' ' . get_user_meta( $user_id, 'first_name', true );
					}
				} else {

					// Posts.

					$post_data[ $key ] = array_map( 'get_the_title', $post_data[ $key ] );

				}
			}
		}

		return $post_data;

	}


	/**
	 * Updates ACF fields when user meta is loaded from the CRM
	 *
	 * @access  public
	 * @return  void
	 */

	public function user_meta_updated( $user_id, $key, $value ) {

		if ( get_field_object( $key, 'user_' . $user_id ) ) {

			$field_object = get_field_object( $key, 'user_' . $user_id );
			update_field( $field_object['key'], $value, 'user_' . $user_id );

		}

	}

	/**
	 * Syncs ACF form data when user data is saved via a frontend form
	 *
	 * @access  public
	 * @return  void
	 */

	public function save_user_form( $form, $fields, $args ) {

		if ( ! empty( $args['user'] ) ) {
			wp_fusion()->user->push_user_meta( $args['user'], $_POST );
		}

	}

}

new WPF_ACF();
