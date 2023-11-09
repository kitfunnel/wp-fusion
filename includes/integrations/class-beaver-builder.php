<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Gets labels for selected tags in settings
 *
 * @return array Tags
 */

function fl_wpf_tags_value( $value, $data ) {

	return WPF_BeaverBuilder::get_tags_values( $value, $data );

}


class WPF_BeaverBuilder extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'beaver-builder';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Beaver Builder';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/page-builders/beaver-builder/';


	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		if ( ! wpf_get_option( 'restrict_content', true ) ) {
			return;
		}

		add_action( 'fl_ajax_before_fl_builder_autosuggest', array( $this, 'get_autosuggest_terms' ), 10, 1 );
		add_filter( 'fl_builder_register_settings_form', array( $this, 'add_visibility_settings_value' ), 999, 2 );
		add_filter( 'fl_builder_is_node_visible', array( $this, 'is_node_visible' ), 10, 2 );

		add_action( 'fl_builder_loop_settings_after_form', array( $this, 'add_filter_queries_settings' ) );
		add_filter( 'fl_builder_loop_query_args', array( $this, 'loop_query_args' ) );

	}

	/**
	 * Get autosuggest terms for WPF tags
	 *
	 * @access public
	 * @return void
	 */

	public function get_autosuggest_terms( $keys_args ) {

		if ( $_REQUEST['fl_as_action'] == 'fl_wpf_tags' ) {

			$data = array();

			$available_tags = wpf_get_option( 'available_tags', array() );

			foreach ( $available_tags as $id => $label ) {

				if ( is_array( $label ) ) {
					$label = $label['label'];
				}

				$data[] = array(
					'name'  => $label,
					'value' => $id,
				);
			}

			wp_send_json( $data );
			wp_die();

		}

	}

	/**
	 * Gets tag labels for display
	 *
	 * @access public
	 * @return void
	 */

	public static function get_tags_values( $value, $data ) {

		$values = explode( ',', $value );
		$data   = array();

		foreach ( $values as $tag_id ) {
			$data[] = array(
				'name'  => wp_fusion()->user->get_tag_label( $tag_id ),
				'value' => $tag_id,
			);
		}

		return $data;

	}


	/**
	 * Determine if a node is visible based on WPF settings.
	 *
	 * @since   3.12.7
	 *
	 * @param   bool $visible default visibility
	 * @param   obj  $node    BB node object
	 * @return  boolean
	 */
	public function is_node_visible( $visible, $node ) {

		/**
		 * Allows filering the node access meta (for example for inheriting
		 * rules from another node or post).
		 *
		 * @since 3.37.29
		 *
		 * @param object $settings The node access settings.
		 * @param object $node     The node.
		 */
		$settings = apply_filters( 'wpf_beaver_builder_access_meta', $node->settings, $node );

		if ( $settings->visibility_display != 'wpf_tag' && $settings->visibility_display != 'wpf_tag_not' ) {

			$visible = apply_filters( 'wpf_beaver_builder_can_access', $visible, $node );

			return apply_filters( 'wpf_user_can_access', $visible, wpf_get_current_user_id(), false );
		}

		if ( wpf_admin_override() ) {

			$visible = apply_filters( 'wpf_user_can_access', true, wpf_get_current_user_id(), false );

			return $visible;
		}

		$can_access = true;

		if ( $settings->visibility_display == 'wpf_tag' ) {

			$user_tags    = wp_fusion()->user->get_tags();
			$setting_tags = explode( ',', $settings->wpf_tags_type );

			if ( empty( array_intersect( $user_tags, $setting_tags ) ) ) {
				$can_access = false;
			}
		} elseif ( $settings->visibility_display == 'wpf_tag_not' ) {

			$user_tags = wp_fusion()->user->get_tags();

			if ( ! wpf_is_user_logged_in() && $settings->wpf_loggedout == 'default' ) {
				$can_access = false;
			}

			$setting_tags = explode( ',', $settings->wpf_tags_not );

			if ( ! empty( array_intersect( $user_tags, $setting_tags ) ) ) {
				$can_access = false;
			}
		}

		$can_access = apply_filters( 'wpf_beaver_builder_can_access', $can_access, $node );

		$can_access = apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), false );

		if ( $can_access ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Remove restricted posts from query results if enabled
	 *
	 * @access public
	 * @return array Query Args
	 */

	public function loop_query_args( $query_args ) {

		if ( ! isset( $query_args['settings']->wpf_filter_queries ) || 'no' == $query_args['settings']->wpf_filter_queries ) {
			return $query_args;
		}

		// If query filtering is on in the WPF settings there's no need to do it twice

		if ( 'off' != wpf_get_option( 'hide_archives' ) ) {
			return $query_args;
		}

		$args = array(
			'post_type'  => $query_args['post_type'],
			'nopaging'   => true,
			'fields'     => 'ids',
			'meta_query' => array(
				array(
					'key'     => 'wpf-settings',
					'compare' => 'EXISTS',
				),
			),
		);

		$post_ids = get_posts( $args );

		if ( ! empty( $post_ids ) ) {

			if ( ! isset( $query_args['post__not_in'] ) ) {
				$query_args['post__not_in'] = array();
			}

			foreach ( $post_ids as $post_id ) {

				if ( ! wp_fusion()->access->user_can_access( $post_id ) ) {

					$query_args['post__not_in'][] = $post_id;

				}
			}
		}

		return $query_args;

	}


	/**
	 * Adds WPF tag select to bb modulules.
	 *
	 * @access public
	 * @return array
	 */

	public function add_visibility_settings_value( $form, $id ) {

		$options = array(
			'wpf_tag'     => __( 'User Tags (any)', 'wp-fusion' ),
			'wpf_tag_not' => __( 'User Tags (not)', 'wp-fusion' ),
		);

		$toggle = array(
			'wpf_tag'     => array(
				'fields' => array( 'wpf_tags_type' ),
			),
			'wpf_tag_not' => array(
				'fields' => array( 'wpf_tags_not', 'wpf_loggedout' ),
			),
		);

		$fields = array(
			'wpf_tags_type' => array(
				'type'    => 'suggest',
				'action'  => 'fl_wpf_tags',
				'label'   => wp_fusion()->crm->name . ' Tags',
				'help'    => __( 'This module will be hidden from users without any of the specified tags.', 'wp-fusion' ),
				'preview' => array(
					'type' => 'none',
				),
			),
			'wpf_tags_not'  => array(
				'type'    => 'suggest',
				'action'  => 'fl_wpf_tags',
				'label'   => wp_fusion()->crm->name . ' Tags',
				'help'    => __( 'This module will be hidden from users who <i>have</i> any of the specified tags.', 'wp-fusion' ),
				'preview' => array(
					'type' => 'none',
				),
			),
			'wpf_loggedout' => array(
				'type'    => 'select',
				'label'   => 'Logged Out Behavior',
				'help'    => __( 'By default content will only be shown to registered users. Set this setting to "Display" to show content to guests as well.', 'wp-fusion' ),
				'default' => 'default',
				'options' => array(
					'default' => __( 'Default (hidden)', 'wp-fusion' ),
					'display' => __( 'Display', 'wp-fusion' ),
				),
			),
		);

		// rows
		if (
			isset( $form['tabs'] ) &&
			isset( $form['tabs']['advanced'] ) &&
			isset( $form['tabs']['advanced']['sections'] ) &&
			isset( $form['tabs']['advanced']['sections']['visibility'] )
		) {

			$form['tabs']['advanced']['sections']['visibility']['fields']['visibility_display']['options'] = array_merge( $form['tabs']['advanced']['sections']['visibility']['fields']['visibility_display']['options'], $options );
			$form['tabs']['advanced']['sections']['visibility']['fields']['visibility_display']['toggle']  = array_merge( $form['tabs']['advanced']['sections']['visibility']['fields']['visibility_display']['toggle'], $toggle );
			$form['tabs']['advanced']['sections']['visibility']['fields']                                  = array_merge( $form['tabs']['advanced']['sections']['visibility']['fields'], $fields );

			// modules
		} elseif (
			isset( $form['sections'] ) &&
			isset( $form['sections']['visibility'] )
		) {

			$form['sections']['visibility']['fields']['visibility_display']['options'] = array_merge( $form['sections']['visibility']['fields']['visibility_display']['options'], $options );
			$form['sections']['visibility']['fields']['visibility_display']['toggle']  = array_merge( $form['sections']['visibility']['fields']['visibility_display']['toggle'], $toggle );
			$form['sections']['visibility']['fields']                                  = array_merge( $form['sections']['visibility']['fields'], $fields );

		}

		return $form;

	}

	/**
	 * Adds filter queries options to Posts widgets
	 *
	 * @access public
	 * @return mixed HTML Output
	 */

	public function add_filter_queries_settings( $settings ) {

		?>

		<div class="fl-custom-query fl-loop-data-source" data-source="custom_query">
			<div id="fl-builder-settings-section-general" class="fl-builder-settings-section">
				<h3 class="fl-builder-settings-title">
					<span class="fl-builder-settings-title-text-wrap">WP Fusion</span>
				</h3>
				<table class="fl-form-table">

				<?php

				FLBuilder::render_settings_field(
					'wpf_filter_queries',
					array(
						'type'    => 'select',
						'label'   => __( 'Filter Queries', 'wp-fusion' ),
						'help'    => __( 'Filter results based on WP Fusion access rules', 'wp-fusion' ),
						'default' => 'no',
						'options' => array(
							'yes' => __( 'Yes', 'fl-builder' ),
							'no'  => __( 'No', 'fl-builder' ),
						),
						'preview' => array(
							'type' => 'none',
						),
					),
					$settings
				);

				?>

			</table>
		</div>
	</div>

		<?php

	}

}

new WPF_BeaverBuilder();
