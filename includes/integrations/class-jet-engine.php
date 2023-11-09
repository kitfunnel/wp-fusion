<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * JetEngine integration.
 *
 * Control access to JetEngine-registered post types based on a user's CRM tags.
 *
 * @since 3.38.16
 */
class WPF_JetEngine extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.16
	 * @var string $slug
	 */

	public $slug = 'jet-engine';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.16
	 * @var string $name
	 */
	public $name = 'JetEngine';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.16
	 * @var string $docs_url
	 */
	public $docs_url = false;


	/**
	 * Gets things started.
	 *
	 * @since 3.38.16
	 */

	public function init() {

		if ( ! wpf_get_option( 'restrict_content', true ) ) {
			return;
		}

		add_filter( 'wpf_post_type_rules', array( $this, 'add_rules' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 40 );

		// Filter queries
		add_action( 'elementor/element/before_section_end', array( $this, 'add_filter_queries_control' ), 10, 3 );
		add_filter( 'jet-engine/listing/grid/posts-query-args', array( $this, 'query_args' ), 10, 3 );

	}

	/**
	 * Filter query args.
	 * 
	 * @since 3.40.17
	 *
	 * @param array  $query_args
	 * @param object $listing_grid
	 * @param array  $settings
	 * @return array
	 */
	public function query_args( $query_args, $listing_grid, $settings ) {

		if ( isset( wp_fusion()->integrations->elementor ) ) {
			return wp_fusion()->integrations->elementor->query_args( $query_args, $settings );
		} else {
			return $query_args;
		}
	}

	/**
	 * Add filter query control.
	 * 
	 * @since 3.40.17
	 *
	 * @access public
	 */

	public function add_filter_queries_control( $element, $section_id, $args ) {

		if ( $section_id !== 'section_posts_query' ) {
			return;
		}

		$element->add_control(
			'wpf_filter_queries',
			array(
				'label'       => __( 'Filter Queries', 'wp-fusion' ),
				'description' => __( 'Filter results based on WP Fusion access rules', 'wp-fusion' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'label_block' => false,
				'show_label'  => true,
				'separator'   => 'before',
			)
		);

	}


	/**
	 * Creates WPF submenu item.
	 *
	 * @since 3.38.16
	 */

	public function admin_menu() {

		$id = add_submenu_page(
			'jet-engine',
			'JetEngine Integration',
			'WP Fusion',
			'manage_options',
			'jetengine-wpf-settings',
			array( $this, 'render_admin_menu' )
		);

		add_action( 'load-' . $id, array( $this, 'enqueue_scripts' ) );

	}

	/**
	 * Enqueues WPF scripts and styles on Jet Engine options page.
	 *
	 * @since 3.38.16
	 */
	public function enqueue_scripts() {

		wp_enqueue_style( 'options-css', WPF_DIR_URL . 'includes/admin/options/css/options.css', array(), WP_FUSION_VERSION );
		wp_enqueue_style( 'wpf-options', WPF_DIR_URL . 'assets/css/wpf-options.css', array(), WP_FUSION_VERSION );

	}

	/**
	 * Renders JetEngine submenu item.
	 *
	 * @since 3.38.16
	 *
	 * @return mixed The settings page content.
	 */
	public function render_admin_menu() {

		?>

		<div class="wrap">

			<h1>JetEngine Integration</h1>

			<?php

			// Save settings.
			if ( isset( $_POST['wpf_jetengine_settings_nonce'] ) && wp_verify_nonce( $_POST['wpf_jetengine_settings_nonce'], 'wpf_jetengine_settings' ) ) {

				update_option( 'wpf_jetengine_settings', $_POST['wpf_settings'], false );
				echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
			}

			// Get settings.
			$settings = get_option( 'wpf_jetengine_settings', array() );

			// Get pages for dropdown.
			$post_types = jet_engine()->cpt->get_items();

			if ( empty( $post_types ) ) {
				return;
			}

			?>

			<form id="wpf-jetengine-settings" action="" method="post" style="width: 100%; max-width: 1200px;">

				<?php wp_nonce_field( 'wpf_jetengine_settings', 'wpf_jetengine_settings_nonce' ); ?>

				<h4>JetEngine Post Types</h4>
				<p class="description">You can restrict access to JetEngine post types by a logged in user's tags. If they don't have the required tags, they'll be redirected to the page you choose in the dropdown.</p>
				<p class="description">For more advanced functionality, use the <a href="https://wpfusion.com/documentation/filters/wpf_post_type_rules/" target="blank">wpf_post_type_rules filter</a>.</p>
				<br/>

				<input type="hidden" name="action" value="update">

					<table class="table table-hover wpf-settings-table">
						<thead>
							<tr>

								<th scope="row"><?php _e( 'Post Type', 'wp-fusion' ); ?></th>

								<th scope="row"><?php _e( 'Required tags (any)', 'wp-fusion' ); ?></th>

								<th scope="row"><?php _e( 'Redirect if access is denied', 'wp-fusion' ); ?></th>

							</tr>
						</thead>
						<tbody>

						<?php
						$available_posts = $this->get_redirect_pages();
						foreach ( $post_types as $post_type ) {
							$id   = $post_type['id'];
							$slug = $post_type['slug'];
							$name = ( $post_type['labels']['name'] ? $post_type['labels']['name'] : $slug );

							$defaults = array(
								'required_tags' => array(),
								'redirect'      => false,
							);

							if ( ! isset( $settings[ $slug ] ) ) {
								$settings[ $slug ] = array();
							}

							$settings[ $slug ] = array_merge( $defaults, $settings[ $slug ] );
							?>

							<tr>
								<td><?php echo ucfirst( $name ); ?></td>
								<td>
								<?php

									$args = array(
										'setting'   => $settings[ $slug ]['required_tags'],
										'meta_name' => "wpf_settings[{$slug}][required_tags]",
										'read_only' => true,
									);

									wpf_render_tag_multiselect( $args );

									?>
								</td>

								<td>

									<select id="wpf-redirect-<?php echo $slug; ?>" class="select4-search" style="width: 100%;" data-placeholder="None" name="wpf_settings[<?php echo $slug; ?>][redirect]">

										<option></option>

										<?php foreach ( $available_posts as $p_type => $data ) : ?>

											<optgroup label="<?php echo $p_type; ?>">

											<?php foreach ( $available_posts[ $p_type ] as $id => $post_name ) : ?>
												<option value="<?php echo $id; ?>" <?php selected( $id, $settings[ $slug ]['redirect'] ); ?> ><?php echo $post_name; ?></option>
											<?php endforeach; ?>

											</optgroup>

										<?php endforeach; ?>

									</select>

								</td>

							</tr>

						<?php } ?>

					</tbody>
				</table>

				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes"/></p>

			</form>
		</div>
		<?php
	}



	/**
	 * Add jet engine rules to post type rules.
	 *
	 * @since 3.38.16
	 *
	 * @param array $settings The settings.
	 * @return array The settings.
	 */
	public function add_rules( $settings ) {
		$post_types = jet_engine()->cpt->get_items();

		if ( empty( $post_types ) ) {
			return $settings;
		}

		$wpf_settings = get_option( 'wpf_jetengine_settings' );
		if ( empty( $wpf_settings ) ) {
			return $settings;
		}

		foreach ( $post_types as $post_type ) {
			$id            = $post_type['id'];
			$slug          = $post_type['slug'];
			$post_settings = $wpf_settings[ $slug ];
			if ( empty( $post_settings ) ) {
				continue;
			}

			$lock = ( isset( $post_settings['hide'] ) ? $post_settings['hide'] : 0 );
			if ( intval( $lock ) === 0 ) {
				continue;
			}

			$settings[ $slug ] = array(
				'lock_content' => true,
				'allow_tags'   => $post_settings['required_tags'],
				'redirect'     => intval( $post_settings['redirect'] ),
			);
		}

		return $settings;

	}

	/**
	 * Get redirect pages.
	 *
	 * @since 3.38.16
	 *
	 * @return array The redirect page options.
	 */
	private function get_redirect_pages() {
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
		return $available_posts;
	}


}

new WPF_JetEngine();
