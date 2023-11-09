<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use BuddyBossApp\UserSegment\SegmentsAbstract;

/**
 * App notification segments integration for BuddyBoss.
 *
 * @since 3.37.0
 */
class WPF_BuddyBoss_App_Segment extends SegmentsAbstract {

	/**
	 * Constructor.
	 *
	 * @since 3.37.0
	 */
	public function __construct() {

		$this->add_group( 'wp_fusion', __( 'WP Fusion', 'wp-fusion' ) );

		$this->add_filter(
			'wp_fusion',
			'user_tags',
			array( 'wp_fusion_tag_select' ),
			array(
				'label' => sprintf( __( '%s Tags', 'wp-fusion' ), wp_fusion()->crm->name ),
			)
		);

		$this->add_field(
			'wp_fusion_tag_select',
			'Checkbox',
			array(
				'options'       => wp_fusion()->settings->get_available_tags_flat(),
				'multiple'      => true,
				'title'         => sprintf( __( 'If the user has any of these %s tags', 'wp-fusion' ), wp_fusion()->crm->name ),
				'empty_message' => __( 'No tags found.', 'wp-fusion' ),
			)
		);

		$this->load();
	}

	/**
	 * Filter the users based on their tags.
	 *
	 * @since  3.37.0
	 *
	 * @param  array $user_ids The user IDs.
	 * @return array The user IDs
	 */
	public function filter_users( $user_ids ) {

		$filter  = $this->get_filter_data_value( 'filter' );
		$tag_ids = (array) $this->get_filter_data_value( 'wp_fusion_tag_select' );

		if ( 'wp_fusion_user_tags' == $filter ) {

			$args = array(
				'fields'     => 'ids',
				'meta_query' => array(
					'relation' => 'OR',
				),
			);

			foreach ( $tag_ids as $tag ) {

				$args['meta_query'][] = array(
					'key'     => WPF_TAGS_META_KEY,
					'value'   => '"' . $tag . '"',
					'compare' => 'LIKE',
				);

			}

			$_user_ids = get_users( $args );

		}

		if ( ! empty( $_user_ids ) ) {
			return array_merge( $user_ids, $_user_ids );
		}

		return $user_ids;
	}

	/**
	 * Render script.
	 *
	 * @since 3.37.0
	 */
	function render_script() {
		// TODO: Implement render_script() method.
	}
}

new WPF_BuddyBoss_App_Segment();
