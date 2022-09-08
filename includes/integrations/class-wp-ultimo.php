<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_WP_Ultimo extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wp-ultimo';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Wp ultimo';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/wp-ultimo/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_action( 'wp_ultimo_registration', array( $this, 'registration' ), 10, 4 );
		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );

		add_action( 'wpf_tags_modified', array( $this, 'update_plans' ), 10, 2 );

		// Custom fields
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		// Plan settings
		add_filter( 'wu_plans_advanced_options_tabs', array( $this, 'add_options_tab' ) );
		add_action( 'wu_plans_advanced_options_after_panels', array( $this, 'options_tab' ) );
		add_action( 'save_post_wpultimo_plan', array( $this, 'save_post' ) );

	}

	/**
	 * Apply tags on registration.
	 *
	 * @access public
	 * @return void
	 */

	public function registration( $site_id, $user_id, $transient, $plan ) {

		$settings = get_post_meta( $plan->id, 'wpf_settings_wu', true );

		if ( empty( $settings ) ) {
			return;
		}

		if ( ! empty( $settings['apply_tags'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags'], $user_id );
		}

	}

	/**
	 * Merge the Ultimo transient data into the data being synced to the CRM for
	 * the new user registration.
	 *
	 * @since  3.37.29
	 *
	 * @param  array $post_data The POSTed data from the registration form.
	 * @param  int   $user_id   The user ID.
	 * @return array The registration data.
	 */
	public function user_register( $post_data, $user_id ) {

		$transient = WU_Signup::get_transient( false ); // don't die

		/*
		 $transient contains

		(
			[honeypot_id] => 60fedfb2b21fa
			[plan_freq] => 1
			[plan_id] => 50
			[blog_title] => TestTwo
			[blogname] => testtwo
			[signup_form_id] => 1
			[save_step] => 1
			[first_name] => J
			[last_name] => A
			[user_name] => testtwo
			[user_email] => testtwo@example.com
			[user_pass] => testtwo
			[user_pass_conf] => testtwo
			[site_url] =>
			[coupon] =>
			[site_id] => 4
			[user_id] => 3
		) */

		$post_data = array_merge( $post_data, $transient );

		return $post_data;

	}

	/**
	 * Update user plans when tags are modified (unfinished)
	 *
	 * @access public
	 * @return void
	 */

	public function update_plans( $user_id, $user_tags ) {

		$linked_plans = get_posts(
			array(
				'post_type'  => 'wpultimo_plan',
				'nopaging'   => true,
				'meta_query' => array(
					array(
						'key'     => 'wpf_settings_wu',
						'compare' => 'EXISTS',
					),
				),
				'fields'     => 'ids',
			)
		);

		if ( empty( $linked_plans ) ) {
			return;
		}

	}


	/**
	 * Adds WP Ultimo field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['wp_ultimo'] = array(
			'title'  => 'WP Ultimo',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Adds WP Ultimo meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['blog_title'] = array(
			'label' => 'Site Title',
			'type'  => 'text',
			'group' => 'wp_ultimo',
		);
		$meta_fields['blogname']   = array(
			'label' => 'Site URL',
			'type'  => 'text',
			'group' => 'wp_ultimo',
		);

		return $meta_fields;

	}

	/**
	 * Register options tab
	 *
	 * @access public
	 * @return array Options tabs
	 */

	public function add_options_tab( $tabs ) {

		$tabs['wp_fusion'] = 'WP Fusion';

		return $tabs;

	}

	/**
	 * Output options tab
	 *
	 * @access public
	 * @return mixed Settings output
	 */

	public function options_tab( $plan ) {

		$settings = array(
			'apply_tags' => array(),
			'tag_link'   => array(),
		);

		if ( get_post_meta( $plan->id, 'wpf_settings_wu', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $plan->id, 'wpf_settings_wu', true ) );
		}

		?>

		<div id="wu_wp_fusion" class="panel wu_options_panel" style="display: none;">

		<div class="options_group">
			<p class="form-field">
				<label><?php _e( 'Apply Tags', 'wp-fusion' ); ?></label>

				<?php

				$args = array(
					'setting'   => $settings['apply_tags'],
					'meta_name' => 'wpf_settings_wu',
					'field_id'  => 'apply_tags',
				);

				wpf_render_tag_multiselect( $args );

				?>

				<span class="description"><?php echo sprintf( __( 'The selected tags will be applied in %s when someone registers with this plan.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></span>

			</p>

			<?php
			/*
			<p class="form-field">
				<label><?php _e( 'Link with Tag', 'wp-fusion' ); ?></label>

				<?php

				$args = array(
					'setting'     => $settings['tag_link'],
					'meta_name'   => 'wpf_settings_wu',
					'field_id'    => 'tag_link',
					'limit'       => 1,
					'placeholder' => __( 'Select tag' ),
				);

				wpf_render_tag_multiselect( $args );

				?>

				<span class="description"><?php echo sprintf( __( 'This tag will be applied in %s when someone registers with this plan. Likewise, if this tag is applied in %s, the user will automatically be enrolled in this plan. If the tag is removed the user will be unenrolled.', 'wp-fusion' ), wp_fusion()->crm->name, wp_fusion()->crm->name ); ?></span>

			</p>

			*/
			?>

		</div>

		<?php

	}

	/**
	 * Save WPF settings
	 *
	 * @access public
	 * @return void
	 */

	public function save_post( $post_id ) {

		if ( ! empty( $_POST['wpf_settings_wu'] ) ) {
			update_post_meta( $post_id, 'wpf_settings_wu', $_POST['wpf_settings_wu'] );
		} else {
			delete_post_meta( $post_id, 'wpf_settings_wu' );
		}

	}


}

new WPF_WP_Ultimo();
