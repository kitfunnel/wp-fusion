<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use BuddyBossApp\InAppPurchases\Controller;
use BuddyBossApp\InAppPurchases\IntegrationAbstract;
use BuddyBossApp\InAppPurchases\Orders;


/**
 * In-App-Purchases integration for BuddyBoss.
 *
 * @since 3.37.0
 */
class WPF_BuddyBoss_IAP extends IntegrationAbstract {

	/**
	 * Instance.
	 *
	 * @var WPF_BuddyBoss_IAP
	 * @since 3.37.0
	 */

	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		// ... leave empty, see Singleton below
	}

	/**
	 * Get the instance of this class.
	 *
	 * @since  3.37.0
	 *
	 * @return Controller|null
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			$classname      = __CLASS__;
			self::$instance = new $classname();
		}

		return self::$instance;
	}

	/**
	 * Overriding the parent(from base-class) function.
	 *
	 * @since 3.37.0
	 *
	 * @param string $integration_type  The integration type.
	 * @param string $integration_label The integration label.
	 */
	public function set_up( $integration_type, $integration_label ) {

		$this->integration_slug = 'wp_fusion';

		parent::set_up( $integration_type, $integration_label );

		$this->item_label = sprintf( __( '%s Tag', 'wp-fusion' ), wp_fusion()->crm->name );

		// Register Instance
		bbapp_iap()->integration[ $integration_type ] = $this::instance();

	}

	/**
	 * Below function get triggers when(hook) order is completed.
	 *
	 * @since 3.37.0
	 *
	 * @param array  $item_ids The item IDs.
	 * @param object $order    The order.
	 */
	public function on_order_completed( $item_ids, $order ) {

		wpf_log( 'info', $order->user_id, 'New in-app order #' . $order->id . '.', array( 'source' => 'buddyboss-iap' ) );

		// Apply the tag(s).
		wp_fusion()->user->apply_tags( $item_ids, $order->user_id );

		$readable_item_ids = implode( ', ', array_map( 'wpf_get_tag_label', $item_ids ) );

		Orders::instance()->add_history( $order->id, 'info', sprintf( __( 'User granted %1$s tags: %2$s', 'wp-fusion' ), wp_fusion()->crm->name, $readable_item_ids ) );

		Orders::instance()->update_meta( $order->id, '_wp_fusion_tags', serialize( $item_ids ) );

	}

	/**
	 * Below function get triggers when(hook) order is activated.
	 *
	 * @since  3.37.0
	 *
	 * @param  array  $item_ids The item IDs
	 * @param  object $order    The order.
	 * @return void
	 */
	public function on_order_activate( $item_ids, $order ) {
		// NOTE : Similar to onOrderCompleted($order) until something needs to be changed?
		return $this->on_order_completed( $item_ids, $order );
	}

	/**
	 * Below function get triggers when(hook) order is expired.
	 *
	 * @since  3.37.0
	 *
	 * @param  array  $item_ids The item IDs
	 * @param  object $order    The order.
	 * @return void
	 */
	public function on_order_expired( $item_ids, $order ) {
		// NOTE : Similar to onOrderCancelled($order) until something needs to be changed?
		$this->on_order_cancelled( $item_ids, $order );
	}

	/**
	 * Remove the tags when the order is cancelled.
	 *
	 * @since  3.37.0
	 *
	 * @param  array  $item_ids The item IDs
	 * @param  object $order    The order.
	 */
	public function on_order_cancelled( $item_ids, $order ) {

		wpf_log( 'info', $order->user_id, 'In-app order #' . $order->id . ' cancelled.', array( 'source' => 'buddyboss-iap' ) );

		// Remove the tag(s).
		wp_fusion()->user->remove_tags( $item_ids, $order->user_id );

		$readable_item_ids = implode( ', ', array_map( 'wpf_get_tag_label', $item_ids ) );

		Orders::instance()->add_history( $order->id, 'info', sprintf( __( 'User removed from %1$s tags: %2$s', 'wp-fusion' ), wp_fusion()->crm->name, $readable_item_ids ) );
	}

	/**
	 * Register available tags for linking.
	 *
	 * @since  3.37.0
	 *
	 * @param  array $results The results.
	 * @return array The results.
	 */
	public function iap_linking_options( $results ) {

		$available_tags = wp_fusion()->settings->get_available_tags_flat();
		$results        = array();

		foreach ( $available_tags as $id => $label ) {
			$results[] = array(
				'id'   => $id,
				'text' => $label,
			);
		}

		return $results;

	}

	/**
	 * Integration IDs.
	 *
	 * @since  3.37.0
	 *
	 * @param  array $results         The results.
	 * @param  array $integration_ids The integration IDs.
	 * @return array The results.
	 */
	public function iap_integration_ids( $results, $integration_ids ) {
		return $results;
	}

	/**
	 * Item ID permalink.
	 *
	 * This would normally be the course or membership level edit page in the
	 * admin but since WPF tags don't have permalinks we'll return false.
	 *
	 * @since  3.6.17
	 *
	 * @param  string $link    The edit link.
	 * @param  string $item_id The item ID.
	 * @return bool   False.
	 */
	public function item_id_permalink( $link, $item_id ) {
		return false;
	}

	/**
	 * Is purchase available. Not sure what this does.
	 *
	 * @since  3.37.0
	 *
	 * @param  bool   $is_available        Indicates if available.
	 * @param  string $item_id             The item ID.
	 * @param  string $integration_item_id The integration item ID.
	 * @return bool   True if purchase available, False otherwise.
	 */
	public function is_purchase_available( $is_available, $item_id, $integration_item_id ) {
		return $item_id == $integration_item_id;
	}

	/**
	 * Check if the user has any of the specified tags.
	 *
	 * @since  3.37.0
	 * @since  3.38.27 Updated second parameter to $user_id.
	 *
	 * @param  array  $item_ids The item IDs.
	 * @param  object $user_id  The user ID.
	 * @return bool   Has access.
	 */
	public function has_access( $item_ids, $user_id ) {

		return wpf_has_tag( $item_ids, $user_id );

	}
}
