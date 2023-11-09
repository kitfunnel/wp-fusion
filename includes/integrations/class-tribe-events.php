<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Tribe Events integration.
 *
 * Handles Filter Queries and the calendar view / events list.
 *
 * @since 3.37.24
 */
class WPF_Tribe_Events extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'tribe-events';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'The Events Calendar';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/events/the-events-calendar-event-tickets/';

	/**
	 * Gets things started
	 *
	 * @since 3.37.24
	 */
	public function init() {

		add_filter( 'wpf_compatibility_notices', array( $this, 'compatibility_notices' ) );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 15 );

		// Virtual event registeration.
		add_filter( 'wpf_event_tickets_attendee_data', array( $this, 'add_virtual_data' ), 10, 4 );

		// Query filtering
		add_filter( 'tribe_query_can_inject_date_field', array( $this, 'can_inject_date_field' ), 10, 2 );
		add_filter( 'wpf_should_filter_query', array( $this, 'should_filter_query' ), 10, 2 );
		add_action( 'tribe_repository_events_pre_get_posts', array( $this, 'filter_event_queries' ) );
	}

	/**
	 * Show warning if Query Filtering is being used with cached calendar view.
	 *
	 * @since  3.37.0
	 *
	 * @param  array $notices The notices.
	 * @return array The notices.
	 */

	public function compatibility_notices( $notices ) {

		if ( wpf_get_option( 'hide_archives' ) ) {

			$types = wpf_get_option( 'query_filter_post_types', array() );

			if ( empty( $types ) || in_array( 'tribe_events', $types ) ) {

				if ( tribe_get_option( 'enable_month_view_cache', false ) ) {

					$notices['tribe-events-cache'] = sprintf(
						__( '<strong>Note:</strong> You have Filter Queries enabled on the <code>tribe_events</code> post type, but the <strong>Month View Cache</strong> is enabled in your <a href="%s">Event Display Settings</a>. To use Query Filtering with events in month view, you must turn off the month view cache.', 'wp-fusion' ),
						admin_url( 'edit.php?post_type=tribe_events&page=tribe-common&tab=display' )
					);

				}
			}
		}

		return $notices;

	}

	/**
	 * Disable date field injection on filtered queries.
	 *
	 * Filter Queries - Advanced creates a database error when searching for
	 * events and Tribe is injecting date meta parameters into the query. To get
	 * around that we'll disable date injection if the query is being filtered.
	 *
	 * The error is:
	 *
	 * WordPress database error Unknown column 'wp_postmeta.meta_value' in
	 * 'field list' for query SELECT SQL_CALC_FOUND_ROWS DISTINCT wp_posts.*,
	 * MIN(wp_postmeta.meta_value) as EventStartDate,
	 * MIN(tribe_event_end_date.meta_value) as EventEndDate FROM wp_posts  LEFT
	 * JOIN wp_postmeta as tribe_event_end_date ON ( wp_posts.ID =
	 * tribe_event_end_date.post_id AND tribe_event_end_date.meta_key =
	 * '_EventEndDate' )  WHERE 1=1  AND wp_posts.ID NOT IN (8197) AND
	 * wp_posts.post_type = 'tribe_events' AND ((wp_posts.post_status =
	 * 'publish'))  ORDER BY wp_posts.post_date DESC LIMIT 0
	 *
	 * @since  3.37.3
	 *
	 * @param  boolean  $can_inject Whether the date field can be injected.
	 * @param  WP_Query $query      Query object.
	 * @return bool     Whether the date field can be injected.
	 */
	public function can_inject_date_field( $can_inject, $query ) {

		if ( $query->get( 'wpf_filtering_query' ) ) {
			$can_inject = false;
		}

		return $can_inject;
	}


	/**
	 * Bypass Filter Queries on Events in calendar view (handled by the next function).
	 *
	 * @since  3.37.6
	 *
	 * @param  bool     $filter Whether or not to filter the query.
	 * @param  WP_Query $query  The query.
	 * @return bool     Whether or not to filter the query.
	 */
	public function should_filter_query( $filter, $query ) {

		if ( did_action( 'tribe_repository_events_pre_get_posts' ) && 'tribe_events' == $query->get( 'post_type' ) ) {
			return false;
		}

		return $filter;

	}



	/**
	 * Makes Filter Queries work with events.
	 *
	 * @since  3.37.6
	 * @since  3.37.16 $query is now returned.
	 *
	 * @param  WP_Query $query  The query.
	 * @return WP_Query The query.
	 */
	public function filter_event_queries( $query ) {

		if ( wpf_get_option( 'hide_archives' ) && wp_fusion()->access->is_post_type_eligible_for_query_filtering( 'tribe_events' ) ) {

			$not_in = wp_fusion()->access->get_restricted_posts( 'tribe_events' );

			if ( ! empty( $not_in ) ) {

				// Maybe merge existing

				if ( ! empty( $query->get( 'post__not_in' ) ) ) {
					$not_in = array_merge( $query->get( 'post__not_in' ), $not_in );
				}

				$query->set( 'post__not_in', $not_in );

				$query->set( 'wpf_filtering_query', true );

				// If the query has a post__in, that will take priority, so we'll adjust for that here

				if ( ! empty( $query->get( 'post__in' ) ) ) {
					$in = array_diff( $query->get( 'post__in' ), $not_in );
					$query->set( 'post__in', $in );
				}
			}
		}

		return $query;

	}

	/**
	 * Adds Events field group to meta fields list
	 *
	 * @since 3.40.50
	 *
	 * @param array $field_groups The field groups.
	 * @return array Field groups
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['tribe_events'] = array(
			'title'  => 'Tribe Events',
			'fields' => array(),
		);

		return $field_groups;
	}


	/**
	 * Sets field labels and types for Tribe Events custom fields.
	 *
	 * @since 3.40.50
	 *
	 * @param array $meta_fields Array of meta fields.
	 * @return array Meta fields
	 */
	public function prepare_meta_fields( $meta_fields ) {

		if ( class_exists( 'Tribe\Events\Virtual\Utils' ) ) {

			$meta_fields['tribe_events_zoom_meeting_id'] = array(
				'label'  => 'Zoom Meeting ID',
				'type'   => 'text',
				'group'  => 'tribe_events',
				'pseudo' => true,
			);

			$meta_fields['tribe_events_zoom_join_url'] = array(
				'label'  => 'Zoom Meeting URL',
				'type'   => 'url',
				'group'  => 'tribe_events',
				'pseudo' => true,
			);

			$meta_fields['tribe_events_zoom_password'] = array(
				'label'  => 'Zoom Meeting Password',
				'type'   => 'text',
				'group'  => 'tribe_events',
				'pseudo' => true,
			);

		}

		return $meta_fields;
	}

	/**
	 * Add virtual event data to users when registering.
	 *
	 * @since 3.40.50
	 * @param array $update_data The update data.
	 * @param int   $attendee_id The attendee ID.
	 * @param int   $event_id    The event ID.
	 * @param int   $ticket_id   The ticket ID.
	 * @return array The update data.
	 */
	public function add_virtual_data( $update_data, $attendee_id, $event_id, $ticket_id ) {

		if ( ! class_exists( 'Tribe\Events\Virtual\Utils' ) ) {
			return $update_data;
		}

		$update_data['tribe_events_zoom_meeting_id'] = get_post_meta( $event_id, '_tribe_events_zoom_meeting_id', true );
		$update_data['tribe_events_zoom_join_url']   = get_post_meta( $event_id, '_tribe_events_zoom_join_url', true );
		$update_data['tribe_events_zoom_password']   = get_post_meta( $event_id, '_tribe_events_zoom_password', true );

		return $update_data;

	}


}

new WPF_Tribe_Events();
