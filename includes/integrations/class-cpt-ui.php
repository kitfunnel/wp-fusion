<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_CPT_UI extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'cpt-ui';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'CPT-UI';

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
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		if ( ! wpf_get_option( 'restrict_content', true ) ) {
			return;
		}

		add_action( 'cptui_post_type_after_fieldsets', array( $this, 'admin_settings' ) );
		add_action( 'cptui_after_update_post_type', array( $this, 'save_post_type' ) );

	}

	/**
	 * Display admin settings
	 *
	 * @access public
	 * @return mixed Settings output
	 */

	public function admin_settings( $ui ) {

		$post_types = cptui_get_post_type_data();

		$post_type_deleted = apply_filters( 'cptui_post_type_deleted', false );

		$selected_post_type = cptui_get_current_post_type( $post_type_deleted );

		if ( $selected_post_type ) {
			if ( isset( $post_types[ $selected_post_type ] ) ) {
				$current = $post_types[ $selected_post_type ];
			}
		}

		$defaults = array(
			'lock_content' => false,
			'allow_tags'   => array(),
			'redirect'     => false,
		);

		$settings = wpf_get_option( 'post_type_rules', array() );

		if ( ! isset( $settings[ $current['name'] ] ) ) {
			$settings[ $current['name'] ] = array();
		}

		$settings = array_merge( $defaults, $settings[ $current['name'] ] );

		echo '<div id="wpf-center-meta" class="wpf-meta cptui-section postbox">';
		echo '<h2 class="handle">WP Fusion</h2>';
		echo '<div class="inside">';
		echo '<div class="main">';
		echo '<table class="form-table cptui-table">';
		echo '<tbody>';

		// Restrict access
		echo '<tr valign="top">';
		echo '<th scope="row">';
		echo '<label for="wpf-lock-content">' . __( 'Restrict Access', 'wp-fusion' ) . '</label>';
		echo '</th>';

		echo '<td>';
		echo '<input class="checkbox wpf-restrict-access-checkbox" type="checkbox" data-unlock="wpf-settings-allow_tags wpf-settings-allow_tags_all wpf-redirect wpf-redirect-url" id="wpf-lock-content" name="wpf-settings[lock_content]" value="1" ' . checked( $settings['lock_content'], 1, false ) . ' /> <label for="wpf-lock-content" class="wpf-restrict-access">Users must be logged in to view any posts of this type</label>';
		echo '</td>';
		echo '</tr>';

		// Required tags
		echo '<tr valign="top">';
		echo '<th scope="row">';
		echo '<label for="wpf-lock-content">' . __( 'Required Tags (any)', 'wp-fusion' ) . '</label>';
		echo '</th>';

		echo '<td>';

		$args = array(
			'setting'   => $settings['allow_tags'],
			'meta_name' => 'wpf-settings',
			'field_id'  => 'allow_tags',
			'disabled'  => ! $settings['lock_content'],
			'read_only' => true,
		);

		wpf_render_tag_multiselect( $args );

		echo '</td>';
		echo '</tr>';

		// Redirect to page
		echo '<tr valign="top">';
		echo '<th scope="row">';
		echo '<label for="wpf-lock-content">' . __( 'Redirect To', 'wp-fusion' ) . '</label>';
		echo '</th>';

		echo '<td>';

		$post_types      = get_post_types( array( 'public' => true ) );
		$available_posts = array();

		unset( $post_types['attachment'] );
		$post_types = apply_filters( 'wpf_redirect_post_types', $post_types );

		foreach ( $post_types as $post_type ) {

			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => 200,
					'orderby'        => 'post_title',
					'order'          => 'ASC',
				)
			);

			foreach ( $posts as $post ) {
				$available_posts[ $post_type ][ $post->ID ] = $post->post_title;
			}
		}

		echo '<select' . ( $settings['lock_content'] == 1 ? '' : ' disabled' ) . ' id="wpf-redirect" class="select4-search" style="width: 100%;" data-placeholder="None" name="wpf-settings[redirect]">';

		echo '<option></option>';

		foreach ( $available_posts as $post_type => $data ) {

			echo '<optgroup label="' . $post_type . '">';

			foreach ( $available_posts[ $post_type ] as $id => $post_name ) {
				echo '<option value="' . $id . '"' . selected( $id, $settings['redirect'], false ) . '>' . $post_name . '</option>';
			}

			echo '</optgroup>';
		}

		echo '</select>';

		echo '</td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';
		echo '</div>'; // end main
		echo '</div>'; // end inside
		echo '</div>'; // end postbox

	}

	/**
	 * Save settings
	 *
	 * @access public
	 * @return void
	 */

	public function save_post_type( $data ) {

		if ( isset( $_POST['wpf-settings'] ) ) {

			$settings = wpf_get_option( 'post_type_rules', array() );
			$settings[ $data['cpt_custom_post_type']['name'] ] = $_POST['wpf-settings'];

			wp_fusion()->settings->set( 'post_type_rules', $settings );

		} else {

			$settings = wpf_get_option( 'post_type_rules', array() );

			if ( isset( $settings[ $data['cpt_custom_post_type']['name'] ] ) ) {

				unset( $settings[ $data['cpt_custom_post_type']['name'] ] );

				wp_fusion()->settings->set( 'post_type_rules', $settings );

			}
		}

	}


}

new WPF_CPT_UI();
