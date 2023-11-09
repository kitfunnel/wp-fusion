<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_Pods extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'pods';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Pods';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = false;

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.33.5
	 * @return  void
	 */

	public function init() {

		$this->name = 'Pods';

		add_action( 'pods_api_post_save_pod_item_user', array( $this, 'post_save_user' ) );

		add_filter( 'wpf_user_register', array( $this, 'filter_form_fields' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'filter_form_fields' ), 10, 2 );

		// WPF stuff
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

	}

	/**
	 * Sync PODs data for users submitted on the frontend
	 *
	 * @access public
	 * @return void
	 */

	public function post_save_user( $data ) {

		if ( is_admin() ) {
			return;
		}

		wp_fusion()->user->push_user_meta( $data['params']->id, $_POST );

	}

	/**
	 * Filter form fields during profile updates
	 *
	 * @access public
	 * @return void
	 */

	public function filter_form_fields( $user_meta, $user_id ) {

		foreach ( $user_meta as $key => $value ) {

			if ( strpos( $key, 'pods_meta_' ) === 0 ) {

				$key = str_replace( 'pods_meta_', '', $key );

				$user_meta[ $key ] = $value;

			} elseif ( strpos( $key, 'pods_field_' ) === 0 ) {

				$key = str_replace( 'pods_field_', '', $key );

				$user_meta[ $key ] = $value;

			}
		}

		return $user_meta;

	}

	/**
	 * Adds Pods field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['pods'] = array(
			'title'  => 'Pods',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Adds Pods meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$user_fields = PodsInit::$meta->groups_get( 'user', 'user' );

		if ( ! empty( $user_fields ) ) {

			foreach ( $user_fields as $pod ) {

				foreach ( $pod['fields'] as $key => $field ) {

					$meta_fields[ $key ] = array(
						'label' => $field['label'],
						'type'  => $field['type'],
						'group' => 'pods',
					);

				}
			}
		}

		return $meta_fields;

	}

}

new WPF_Pods();
