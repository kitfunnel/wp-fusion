<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Users_Insights extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'users-insights';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Users insights';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/users-insights/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'get_user_meta', array( $this, 'merge_geo_data' ), 10, 2 );

		add_action( 'admin_init', array( $this, 'module_init' ) );
		add_filter( 'usin_fields', array( $this, 'add_module_fields' ) );
		add_filter( 'usin_users_raw_data', array( $this, 'users_raw_data' ) );

		// WPF stuff.
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		add_filter( 'wpf_watched_meta_fields', array( $this, 'watch_meta_fields' ) );

	}


	/**
	 * Merge Geo data into update data
	 *
	 * @access public
	 * @return array User Meta
	 */

	public function merge_geo_data( $user_meta, $user_id ) {

		$user = new USIN_User_Data( $user_id );

		$data = $user->get_all();

		if ( empty( $data ) ) {
			return $user_meta;
		}

		$user_meta = array_merge( $user_meta, (array) $data );

		return $user_meta;

	}

	/**
	 * Initialize the usermeta query class.
	 *
	 * @since 3.38.5
	 */
	public function module_init() {

		$meta_query = new USIN_Meta_Query( WPF_TAGS_META_KEY, 'serialized_multioption' );
		$meta_query->init();

	}

	/**
	 * Adds CRM tags field to filters
	 *
	 * @access public
	 * @return array Fields
	 */

	public function add_module_fields( $fields ) {

		$data = array();

		foreach ( wp_fusion()->settings->get_available_tags_flat() as $id => $label ) {

			$data[] = array(
				'key' => $id,
				'val' => $label,
			);

		}

		$fields[] = array(
			'name'      => sprintf( __( '%s tags', 'wp-fusion' ), wp_fusion()->crm->name ),
			'id'        => WPF_TAGS_META_KEY,
			'order'     => false,
			'show'      => true,
			'fieldType' => 'general',
			'filter'    => array(
				'type'    => 'serialized_multioption',
				'options' => $data,
			),
		);

		return $fields;

	}

	/**
	 * Convert tag IDs to labels for display in the table.
	 *
	 * @access public
	 * @return array Data
	 */

	public function users_raw_data( $data ) {

		// Now add the tag labels to the column for anyone left.

		foreach ( $data as $i => $user ) {

			if ( ! isset( $user->{ WPF_TAGS_META_KEY } ) ) {
				continue;
			}

			$tags = maybe_unserialize( $user->{ WPF_TAGS_META_KEY } );

			if ( ! empty( $tags ) ) {
				$tags = array_map( array( wp_fusion()->user, 'get_tag_label' ), $tags );
				$data[ $i ]->{ WPF_TAGS_META_KEY } = implode( ', ', $tags );
			} else {
				$data[ $i ]->{ WPF_TAGS_META_KEY } = '';
			}
		}

		return $data;

	}


	/**
	 * Adds Users Insights field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['users_insights'] = array(
			'title'  => 'Users Insights',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Adds Users Insights meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields = array() ) {

		if ( usin_modules()->is_module_active( 'activity' ) ) {

			// Disabled for now, hooks don't support it

			// $meta_fields['last_seen'] = array(
			// 'label' => 'Last Seen',
			// 'type'  => 'date',
			// 'group' => 'users_insights',
			// );

			// $meta_fields['sessions'] = array(
			// 'label' => 'Sessions',
			// 'type'  => 'int',
			// 'group' => 'users_insights',
			// );

		}

		if ( usin_modules()->is_module_active( 'devices' ) ) {

			// Disabled for now, hooks don't support it

			// $meta_fields['browser'] = array(
			// 'label' => 'Browser',
			// 'type'  => 'text',
			// 'group' => 'users_insights',
			// );

		}

		if ( usin_modules()->is_module_active( 'geolocation' ) ) {

			// Disabled for now, hooks don't support it

			// $meta_fields['country'] = array(
			// 'label' => 'Country',
			// 'type'  => 'country',
			// 'group' => 'users_insights',
			// );

			// $meta_fields['region'] = array(
			// 'label' => 'Region',
			// 'type'  => 'state',
			// 'group' => 'users_insights',
			// );

			// $meta_fields['city'] = array(
			// 'label' => 'City',
			// 'type'  => 'text',
			// 'group' => 'users_insights',
			// );

			// $meta_fields['coordinates'] = array(
			// 'label' => 'Coordinates',
			// 'type'  => 'text',
			// 'group' => 'users_insights',
			// );

		}

		$custom_fields = USIN_Custom_Fields_Options::get_saved_fields();

		if ( ! empty( $custom_fields ) ) {

			foreach ( $custom_fields as $field ) {

				$meta_fields[ $field['key'] ] = array(
					'label' => $field['name'],
					'type'  => $field['type'],
					'group' => 'users_insights',
				);

			}
		}

		return $meta_fields;

	}



	/**
	 * Watch Users Insights fields for changes.
	 *
	 * @since  3.36.10
	 *
	 * @param  array $meta_fields The meta fields to watch.
	 * @return array The meta fields to watch.
	 */
	public function watch_meta_fields( $meta_fields ) {

		$meta_fields = array_merge( $meta_fields, array_keys( $this->prepare_meta_fields() ) );

		return $meta_fields;

	}

}

new WPF_Users_Insights();
