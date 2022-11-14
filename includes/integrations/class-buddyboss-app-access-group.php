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
				'users_callback'         => array( $this, 'users_callback' ),
				'labels'                 => array(
					'condition_name'          => sprintf( __( '%s Tags', 'wp-fusion' ), wp_fusion()->crm->name ),
					'item_singular'           => sprintf( __( '%s Tag', 'wp-fusion' ), wp_fusion()->crm->name ),
					'member_of_specific_item' => __( 'Has specific tag', 'wp-fusion' ),
					//'member_of_any_items'     => __( 'Has any tag', 'wp-fusion' ),
				),
				'support_any_items'      => true,
				'has_any_items_callback' => array( $this, 'has_any_items_callback' ),
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
			$items[ $key ] = array( 'name' => $value );
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
			if ( $value == $item_value ) {
				return array(
					'name' => $value,
					'id'   => $key,
					'link' => '',
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
	public function users_callback( $data, $page = 1, $per_page = 10 ) {

		if ( empty( $data['item_value'] ) ) {
			return array();
		}

		$args = array(
			'meta_key'     => WPF_TAGS_META_KEY,
			'meta_value'   => '"' . $data['item_value'] . '"',
			'meta_compare' => 'LIKE',
			'number'       => ( ! empty( $data['per_page'] ) ) ? $data['per_page'] : 10,
			'paged'        => ( ! empty( $data['page'] ) ) ? $data['page'] : 1,
			'fields'       => 'ID',
		);

		$users = new \WP_User_Query( $args );

		return $this->return_users( $users->get_results() );
	}

	/**
	 * Function to check if user has any condition.
	 *
	 * @param int $user_id User id to check.
	 *
	 * @since 3.40.17
	 *
	 * @return bool
	 */
	public function has_any_items_callback( $user_id ) {

		if ( empty( wpf_get_tags( $user_id ) ) ) {
			return false;
		}

		return true;
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
