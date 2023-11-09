<?php
use BuddyBossApp\AccessControls\Integration_Abstract;

/**
 * BuddyBoss App Access Controls integration.
 * 
 * @since 3.40.17
 *
 * @link https://wpfusion.com/documentation/membership/buddyboss/
 */
class WPF_BuddyBoss_App_Access_Group extends Integration_Abstract {

	/**
	 * @var string $_condition_name condition name.
	 */
	private $_condition_name = 'wp-fusion';

	/**
	 * Function to set up the conditions.
	 *
	 * @since 3.40.17
	 *
	 * @return mixed|void
	 */
	public function setup() {

		$this->register_condition(
			array(
				'condition'              => $this->_condition_name,
				'items_callback'         => array( $this, 'items_callback' ),
				'item_callback'          => array( $this, 'item_callback' ),
				'users_callback'         => array( $this, 'tag_users_callback' ),
				'labels'                 => array(
					'condition_name'          => sprintf( __( '%s Tag', 'wp-fusion' ), wp_fusion()->crm->name ),
					'item_singular'           => sprintf( __( '%s Tag', 'wp-fusion' ), wp_fusion()->crm->name ),
					'member_of_specific_item' => __( 'Has specific tag', 'wp-fusion' ),
				),
				'support_any_items'      => false,
			)
		);

		$this->load_hooks();
	}

	/**
	 * Function to load all hooks of this condition.
	 *
	 * @since 3.40.17
	 */
	public function load_hooks() {

		add_filter( 'wpf_tags_removed', array( $this, 'tags_removed' ), 10, 2 );
		add_filter( 'wpf_tags_applied', array( $this, 'tags_added' ), 10, 2 );

	}

	/**
	 * Items callback method.
	 *
	 * @param string $search Search the condition.
	 * @param int    $page   Page number
	 * @param int    $limit  Limit the items to be fetched.
	 *
	 * @since 3.40.17
	 *
	 * @return array
	 */
	public function items_callback( $search = '', $page = 1, $limit = 20 ) {

		$tags  = wp_fusion()->settings->get_available_tags_flat( true, false );
		$items = array();
		foreach ( $tags as $key => $value ) {
			$items[ $key ] = array(
				'id'   => $key,
				'name' => $value,
			);
		}

		return $this->paginate_items_list( $items, $page, $limit, $search );
	}

	/**
	 * Item callback method.
	 *
	 * @param int $item_value Item value of condition.
	 *
	 * @since 3.40.17
	 *
	 * @return array|false
	 */
	public function item_callback( $item_value ) {

		foreach ( wp_fusion()->settings->get_available_tags_flat() as $key => $value ) {
			if ( strval( $key ) === $item_value ) {
				return array(
					'name' => $value,
					'id'   => $key,
					'link' => admin_url( 'users.php?wpf_filter_tag=' . $key ),
				);
			}
		}
	}

	/**
	 * Users callback method.
	 *
	 * @param array $data     condition data.
	 * @param int   $page     current page number.
	 * @param int   $per_page limit.
	 *
	 * @since 3.40.17
	 * @return array
	 */
	public function tag_users_callback( $data, $page = 1, $per_page = 10 ) {

		/**
		 * I know extract() is bad form but BuddyBoss uses it BuddyBossApp\AccessControls\Core
		 * and for some reason if we don't use it the initial query spins forever.
		 * 
		 * @type string $sub_condition
		 * @type string $item_value
		 * @type string $group_id
		 * @type int    $rounds_count
		 */
		extract( $data );

		if ( empty( $item_value ) ) {
			return array();
		}

		$args = array(
			'meta_key'     => WPF_TAGS_META_KEY,
			'meta_value'   => '"' . $item_value . '"',
			'meta_compare' => 'LIKE',
			'number'       => ( ! empty( $per_page ) ) ? $per_page : 10,
			'paged'        => ( ! empty( $page ) ) ? $page : 1,
			'fields'       => 'ID',
		);

		$users = new \WP_User_Query( $args );

		$result = $this->return_users( $users->get_results() );

		return $result;
	}


	/**
	 * Remove condition if tags is removed from user.
	 *
	 * @param int   $user_id
	 * @param array $tags
	 */
	public function tags_removed( $user_id, $tags ) {

		foreach ( $tags as $tag ) {
			$this->condition_remove_user( $user_id, $this->_condition_name, $tag );
		}
	}

	/**
	 * Add condition if tags is added to user.
	 *
	 * @param int   $user_id
	 * @param array $tags
	 */
	public function tags_added( $user_id, $tags ) {

		foreach ( $tags as $tag ) {
			$this->condition_add_user( $user_id, $this->_condition_name, $tag );
		}
	}


}

WPF_BuddyBoss_App_Access_Group::instance();
