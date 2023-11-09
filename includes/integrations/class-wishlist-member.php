<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_WishListMember extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'wishlist-member';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Wishlist member';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/wishlist-member/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// WPF filters
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 10 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 20 );
		add_filter( 'wpf_get_contact_id_email', array( $this, 'get_contact_id_email' ), 10, 2 );
		add_filter( 'wpf_user_register', array( $this, 'user_register' ) );
		add_filter( 'wpf_get_user_meta', array( $this, 'get_user_meta' ), 10, 2 );
		add_filter( 'wpf_pulled_user_meta', array( $this, 'pulled_user_meta' ), 10, 2 );

		// WPF actions
		add_action( 'wpf_tags_modified', array( $this, 'update_levels' ), 10, 2 );
		add_action( 'wpf_admin_profile_tags_edited', array( $this, 'admin_profile_tags_edited' ) );

		// Create Sub-Menu
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Send additional info after WLM registration
		add_action( 'wishlistmember_user_registered', array( $this, 'user_registered' ), 10, 2 );

		// Update tags when levels are changed
		add_action( 'wishlistmember_add_user_levels', array( $this, 'payperpost_tags' ), 10, 3 );
		add_action( 'wishlistmember_add_user_levels', array( $this, 'add_user_levels' ), 10, 3 );
		add_action( 'wishlistmember_pre_remove_user_levels', array( $this, 'remove_user_levels' ), 99, 2 );

		// Cancelled levels
		add_action( 'wishlistmember_cancel_user_levels', array( $this, 'cancel_user_levels' ), 10, 2 );

		// Batch operations
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_wishlist_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_wishlist', array( $this, 'batch_step' ) );

		// Add submenu tab.
		add_action( 'wishlistmember_post_page_options_menu', array( $this, 'add_tab' ) );
		add_action( 'wishlistmember_post_page_options_content', array( $this, 'tab_content' ) );

	}

	/**
	 * Add meta field group
	 *
	 * @access  public
	 * @return  array Field Groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['wishlist_member'] = array(
			'title'  => 'Wishlist Member',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Sets field labels and types for EDD custom fields
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['company']  = array(
			'label' => 'Company',
			'type'  => 'text',
			'group' => 'wishlist_member',
		);
		$meta_fields['address1'] = array(
			'label' => 'Address (First Line)',
			'type'  => 'text',
			'group' => 'wishlist_member',
		);
		$meta_fields['address2'] = array(
			'label' => 'Address (Second Line)',
			'type'  => 'text',
			'group' => 'wishlist_member',
		);
		$meta_fields['city']     = array(
			'label' => 'City',
			'type'  => 'text',
			'group' => 'wishlist_member',
		);
		$meta_fields['state']    = array(
			'label' => 'State',
			'type'  => 'text',
			'group' => 'wishlist_member',
		);
		$meta_fields['zip']      = array(
			'label' => 'Zip Code',
			'type'  => 'text',
			'group' => 'wishlist_member',
		);
		$meta_fields['country']  = array(
			'label' => 'Country',
			'type'  => 'text',
			'group' => 'wishlist_member',
		);

		// WishList v2

		global $WishListMemberInstance;

		$registration_forms = $WishListMemberInstance->get_option( 'regpage_form' );

		if ( ! empty( $registration_forms ) ) {

			foreach ( $registration_forms as $form_key ) {

				$form_data = $WishListMemberInstance->get_option( $form_key );

				if ( empty( $form_data ) ) {
					continue;
				}

				foreach ( $form_data['form_dissected']['fields'] as $data ) {

					if ( isset( $data['system_field'] ) || isset( $data['wp_field'] ) ) {
						continue;
					}

					$meta_fields[ $data['attributes']['name'] ] = array(
						'label' => $data['label'],
						'type'  => $data['type'],
						'group' => 'wishlist_member',
					);

				}
			}
		}

		// WishList v3

		if ( method_exists( $WishListMemberInstance, 'GetCustomRegForms' ) ) {

			$registration_forms = $WishListMemberInstance->GetCustomRegForms();

			foreach ( $registration_forms as $form_data ) {

				foreach ( $form_data->option_value['form_dissected']['fields'] as $data ) {

					if ( isset( $data['system_field'] ) || isset( $data['wp_field'] ) ) {
						continue;
					}

					if ( 'input' == $data['type'] ) {
						$data['type'] = 'text';
					}

					$meta_fields[ $data['attributes']['name'] ] = array(
						'label' => $data['label'],
						'type'  => $data['type'],
						'group' => 'wishlist_member',
					);

				}
			}
		};

		$meta_fields['wlm_level'] = array(
			'label' => 'Membership Level Name',
			'type'  => 'text',
			'group' => 'wishlist_member',
		);

		return $meta_fields;

	}

	/**
	 * Triggered when a user is registered by WLM, or when a temp user is converted to a real user
	 *
	 * @access public
	 * @return void
	 */

	function user_registered( $user_id, $data ) {
		$user = get_userdata( $user_id );

		if ( strpos( $user->user_login, 'temp_' ) !== 0 ) {
			wp_fusion()->user->push_user_meta( $user_id, array( 'user_login' => $user->user_login ) );
		}

	}

	/**
	 * Apply tags when user register for pay per post.
	 *
	 * @since 3.38.5
	 *
	 * @param int   $user_id   The user ID who purchased the post.
	 * @param array $addlevels The levels purchased.
	 */
	public function payperpost_tags( $user_id, $addlevels ) {

		// Prevent looping.
		remove_action( 'wpf_tags_modified', array( $this, 'update_levels' ), 10, 2 );

		foreach ( $add_levels as $add_level_id ) {

			// Check if the level is pay per post.
			$payperpost = wishlistmember_instance()->is_ppp_level( $add_level_id );

			if ( $payperpost ) {

				$pay_settings = get_post_meta( $payperpost->ID, 'wpf-settings', true );

				if ( ! empty( $pay_settings['apply_tags_pay_per_post'] ) ) {
					wp_fusion()->user->apply_tags( $pay_settings['apply_tags_pay_per_post'], $user_id );
				}
			}
		}

		add_action( 'wpf_tags_modified', array( $this, 'update_levels' ), 10, 2 );

	}

	/**
	 * Triggered when level changes
	 *
	 * @access public
	 * @return void
	 */

	function add_user_levels( $user_id, $addlevels ) {

		// Sync level name.
		$this->sync_level_name( $user_id, end( $addlevels ) );

		$settings = get_option( 'wpf_wlm_settings' );

		if ( empty( $settings ) ) {
			return;
		}

		// Prevent looping

		remove_action( 'wpf_tags_modified', array( $this, 'update_levels' ), 10, 2 );

		foreach ( $addlevels as $add_level_id ) {

			if ( ! empty( $settings[ $add_level_id ] ) ) {

				if ( ! empty( $settings[ $add_level_id ]['apply_tags'] ) ) {
					wp_fusion()->user->apply_tags( $settings[ $add_level_id ]['apply_tags'], $user_id );
				}

				if ( ! empty( $settings[ $add_level_id ]['tag_link'] ) ) {
					wp_fusion()->user->apply_tags( $settings[ $add_level_id ]['tag_link'], $user_id );
				}
			}
		}

		add_action( 'wpf_tags_modified', array( $this, 'update_levels' ), 10, 2 );

	}

	/**
	 * Remove linked tags when level removed
	 *
	 * @access public
	 * @return void
	 */

	function remove_user_levels( $user_id, $removed_levels ) {

		$settings = get_option( 'wpf_wlm_settings' );

		if ( empty( $settings ) ) {
			return;
		}

		// Prevent looping

		remove_action( 'wpf_tags_modified', array( $this, 'update_levels' ), 10, 2 );

		foreach ( $settings as $level_id => $level_settings ) {

			foreach ( $removed_levels as $removed_levels_id ) {

				if ( $removed_levels_id == $level_id ) {

					wp_fusion()->user->remove_tags( $level_settings['apply_tags'], $user_id );
					wp_fusion()->user->remove_tags( $level_settings['tag_link'], $user_id );

				}
			}
		}

		add_action( 'wpf_tags_modified', array( $this, 'update_levels' ), 10, 2 );

	}

	/**
	 * Apply tags when level cancelled
	 *
	 * @access public
	 * @return void
	 */

	function cancel_user_levels( $user_id, $cancelled_levels ) {

		$settings = get_option( 'wpf_wlm_settings' );

		if ( empty( $settings ) ) {
			return;
		}

		// Prevent looping

		remove_action( 'wpf_tags_modified', array( $this, 'update_levels' ), 10, 2 );

		foreach ( $settings as $level_id => $level_settings ) {

			foreach ( $cancelled_levels as $cancelled_level_id ) {

				if ( $cancelled_level_id == $level_id ) {

					wp_fusion()->user->apply_tags( $level_settings['apply_tags_cancelled'], $user_id );

				}
			}
		}

		add_action( 'wpf_tags_modified', array( $this, 'update_levels' ), 10, 2 );

	}

	/**
	 * Use the orig_email for email lookups during a WLM checkout
	 *
	 * @access public
	 * @return string Email Address
	 */

	public function get_contact_id_email( $email, $user_id ) {

		if ( ! is_email( $email ) && ! empty( $_POST ) && ! empty( $_POST['orig_email'] ) ) {
			$email = $_POST['orig_email'];
		}

		return $email;

	}

	/**
	 * Map fields on register
	 *
	 * @access public
	 * @return array Post Data
	 */

	public function user_register( $post_data ) {

		$field_map = array(
			'password1' => 'user_pass',
			'email'     => 'user_email',
			'firstname' => 'first_name',
			'lastname'  => 'last_name',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		// Fix random emails at signup

		if ( ! empty( $post_data['email'] ) ) {
			$post_data['user_email'] = $post_data['email'];
		}

		// Stripe registration

		if ( ! empty( $post_data['orig_email'] ) ) {
			$post_data['user_email'] = $post_data['orig_email'];
		}

		return $post_data;

	}

	/**
	 * Syncs the membership level name to the CRM when a user is added to a level
	 *
	 * @access public
	 * @return array User Meta
	 */

	public function sync_level_name( $user_id, $level_id ) {

		global $WishListMemberInstance;
		$wpm_levels = $WishListMemberInstance->get_option( 'wpm_levels' );

		if ( isset( $wpm_levels[ $level_id ] ) && ! empty( $wpm_levels[ $level_id ]['name'] ) ) {

			wp_fusion()->user->push_user_meta( $user_id, array( 'wlm_level' => $wpm_levels[ $level_id ]['name'] ) );

		}

	}

	/**
	 * Get WLM custom fields from the wlm_user_options table for export
	 *
	 * @access public
	 * @return array User Meta
	 */

	public function get_user_meta( $user_meta, $user_id ) {

		global $WishListMemberInstance, $wpdb;

		// Get the address

		$address = $WishListMemberInstance->Get_UserMeta( $user_id, 'wpm_useraddress' );

		if ( is_array( $address ) ) {
			$user_meta = array_merge( $user_meta, $address );
		}

		// Get any custom fields

		$wlm_meta = $WishListMemberInstance->get_user_custom_fields( $user_id );

		if ( ! empty( $wlm_meta ) ) {

			foreach ( $wlm_meta as $key => $meta ) {

				if ( ! empty( $meta['attributes']['value'] ) ) {

					$user_meta[ $key ] = $meta['attributes']['value'];

				}
			}
		}

		// Membership level name

		if ( function_exists( 'wlmapi_get_member_levels' ) ) {

			$levels = wlmapi_get_member_levels( $user_id );

			if ( ! empty( $levels ) ) {
				$last_level             = end( $levels );
				$user_meta['wlm_level'] = $last_level->Name;
			}
		}

		return $user_meta;

	}

	/**
	 * Save WLM custom fields to the wlm_user_options table instead of wp_usermeta
	 *
	 * @access public
	 * @return array User Meta
	 */

	public function pulled_user_meta( $user_meta, $user_id ) {

		global $WishListMemberInstance;

		$fields = $WishListMemberInstance->get_custom_fields();

		foreach ( $fields as $key => $data ) {

			if ( isset( $user_meta[ $key ] ) ) {

				$value = $user_meta[ $key ];
				unset( $user_meta[ $key ] );

				// WLM prefixes custom fields with custom_ for no reason
				if ( 'lastname' !== $key && 'firstname' !== $key ) {
					$key = 'custom_' . $key;
				}

				$WishListMemberInstance->Update_UserMeta( $user_id, $key, $value );

			}
		}

		return $user_meta;

	}

	/**
	 * Update user levels when linked tags are modified
	 *
	 * @access public
	 * @return void
	 */

	public function update_levels( $user_id, $user_tags ) {

		$settings = get_option( 'wpf_wlm_settings' );

		if ( ! empty( $settings ) ) {

			$levels_to_add    = array();
			$levels_to_remove = array();

			if ( function_exists( 'wlmapi_get_member_levels' ) ) {

				// Wishlist v3

				$levels = wlmapi_get_member_levels( $user_id );

				$user_levels = array();

				foreach ( $levels as $level ) {
					$user_levels[] = $level->Level_ID;
				}
			} else {

				// Wishlist v2x

				global $WishListMemberInstance;
				$user_levels = $WishListMemberInstance->GetMembershipLevels( $user_id );

			}

			foreach ( $settings as $level_id => $tags ) {

				if ( empty( $tags['tag_link'] ) ) {
					continue;
				}

				$tag_id = $tags['tag_link'][0];

				if ( in_array( $tag_id, $user_tags ) && ! in_array( $level_id, $user_levels ) ) {

					$level = wlmapi_get_level( $level_id );

					wpf_log( 'info', $user_id, 'User granted WishList membership level <strong>' . $level['level']['name'] . '</strong> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

					$levels_to_add[] = $level_id;

				}

				if ( in_array( $level_id, $user_levels ) && ! in_array( $tag_id, $user_tags ) ) {

					$level = wlmapi_get_level( $level_id );

					wpf_log( 'info', $user_id, 'User removed from WishList membership level <strong>' . $level['level']['name'] . '</strong> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

					$levels_to_remove[] = $level_id;

				}
			}

			remove_action( 'wishlistmember_add_user_levels', array( $this, 'add_user_levels' ), 10, 3 );
			remove_action( 'wishlistmember_pre_remove_user_levels', array( $this, 'remove_user_levels' ), 99, 2 );

			if ( function_exists( 'wlmapi_update_member' ) ) {

				// Wishlist v3

				if ( ! empty( $levels_to_add ) ) {

					wlmapi_update_member( $user_id, array( 'Levels' => $levels_to_add ) );

					global $WishListMemberInstance;
					$WishListMemberInstance->is_sequential( array( $user_id ), true ); // Enables sequential upgrade.

				}

				if ( ! empty( $levels_to_remove ) ) {
					wlmapi_update_member( $user_id, array( 'RemoveLevels' => $levels_to_remove ) );
				}
			} else {

				// Wishlist v2x

				if ( ! empty( $levels_to_add ) ) {
					$wlmapi = new WLMAPI();
					$wlmapi->AddUserLevels( $user_id, $levels_to_add );
					$wlmapi->MakeSequential( $user_id ); // Enables sequential upgrade
				}

				if ( ! empty( $levels_to_remove ) ) {
					$wlmapi = new WLMAPI();
					$wlmapi->DeleteUserLevels( $user_id, $levels_to_remove );
				}
			}

			add_action( 'wishlistmember_add_user_levels', array( $this, 'add_user_levels' ), 10, 3 );
			add_action( 'wishlistmember_pre_remove_user_levels', array( $this, 'remove_user_levels' ), 99, 2 );

			// Sync name

			if ( ! empty( $levels_to_add ) ) {

				$this->sync_level_name( $user_id, end( $levels_to_add ) );

			}
		}

	}

	/**
	 * Unset the WLM post data when tags have been edited in case linked tags are changes
	 *
	 * @access public
	 * @return void
	 */

	public function admin_profile_tags_edited( $user_id ) {

		if ( isset( $_POST['wlm_updating_profile'] ) ) {
			unset( $_POST['wlm_updating_profile'] );
		}

	}

	/**
	 * Creates WLM submenu item
	 *
	 * @access public
	 * @return void
	 */

	public function admin_menu() {

		$crm = wp_fusion()->crm->name;

		$id = add_submenu_page(
			'WishListMember',
			$crm . ' Integration',
			'WP Fusion',
			'manage_options',
			'wpf-wlm-settings',
			array( $this, 'render_admin_menu' )
		);

		add_action( 'load-' . $id, array( $this, 'enqueue_scripts' ) );

	}


	/**
	 * Enqueues WPF scripts and styles on WLM options page
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_scripts() {

		wp_enqueue_style( 'bootstrap', WPF_DIR_URL . 'includes/admin/options/css/bootstrap.min.css' );
		wp_enqueue_style( 'options-css', WPF_DIR_URL . 'includes/admin/options/css/options.css' );
		wp_enqueue_style( 'wpf-options', WPF_DIR_URL . 'assets/css/wpf-options.css' );

	}

	/**
	 * Renders WLM submenu item
	 *
	 * @access public
	 * @return mixed
	 */

	public function render_admin_menu() {

		// Save settings
		if ( isset( $_POST['wpf_wlm_settings_nonce'] ) && wp_verify_nonce( $_POST['wpf_wlm_settings_nonce'], 'wpf_wlm_settings' ) ) {
			update_option( 'wpf_wlm_settings', $_POST['wpf-settings'] );
			echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
		}

		?>

		<div class="wrap">
			<h2><?php echo wp_fusion()->crm->name; ?> Integration</h2>

			<form id="wpf-mm-settings" action="" method="post">
				<?php wp_nonce_field( 'wpf_wlm_settings', 'wpf_wlm_settings_nonce' ); ?>
				<input type="hidden" name="action" value="update">

				<h4>Product Tags</h4>
				<p class="description">


					<?php printf( __( 'For each product below, specify tags to be applied in %s when purchased.', 'wp-fusion' ), wp_fusion()->crm->name ); ?>

					<?php printf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/membership/wishlist-member/" target="_blank">', '</a>' ); ?>

				</p>
				<br/>

				<?php global $WishListMemberInstance; ?>
				<?php $levels = $WishListMemberInstance->get_option( 'wpm_levels' ); ?>
				<?php $settings = get_option( 'wpf_wlm_settings' ); ?>

				<?php
				if ( empty( $settings ) ) {
					$settings = array();
				}
				?>

				<table class="table table-hover wpf-settings-table" id="wpf-wishlist-levels-table">
					<thead>
					<tr>
						<th><?php _e( 'Membership Level', 'wp-fusion' ); ?></th>
						<th><?php _e( 'Apply Tags', 'wp-fusion' ); ?></th>
						<th><?php _e( 'Link with Tag', 'wp-fusion' ); ?></th>
						<th><?php _e( 'Apply Tags - Cancelled', 'wp-fusion' ); ?></th>
					</tr>
					</thead>
					<tbody>

					<?php foreach ( $levels as $level_id => $level ) : ?>

						<?php
						if ( ! isset( $settings[ $level_id ] ) ) {
							$settings[ $level_id ] = array(
								'apply_tags' => array(),
								'tag_link'   => array(),
							);
						}
						?>

						<tr>
							<td><?php echo $level['name']; ?></td>
							<td>
								<?php

								$args = array(
									'setting'   => $settings[ $level_id ]['apply_tags'],
									'meta_name' => "wpf-settings[{$level_id}][apply_tags]",
								);

								wpf_render_tag_multiselect( $args );

								?>

							</td>
							<td>
								<?php

								$args = array(
									'setting'   => $settings[ $level_id ]['tag_link'],
									'meta_name' => "wpf-settings[{$level_id}][tag_link]",
									'limit'     => 1,
									'no_dupes'  => array( 'apply_tags' ),
								);

								wpf_render_tag_multiselect( $args );

								?>

							</td>

							<td>
								<?php

								$args = array(
									'setting'   => $settings[ $level_id ]['apply_tags_cancelled'],
									'meta_name' => "wpf-settings[{$level_id}][apply_tags_cancelled]",
								);

								wpf_render_tag_multiselect( $args );

								?>

							</td>

						</tr>

					<?php endforeach; ?>

					</tbody>

				</table>

				<p class="submit"><input name="Submit" type="submit" class="button-primary" value="Save Changes"/>
				</p>

			</form>

		</div>

		<?php

	}

	/**
	 * Adds a WP Fusion tab to the WishList Member single post meta box.
	 *
	 * @since 3.38.5
	 */
	public function add_tab() {
		echo '<li><a href="#" data-target=".wlm-inside099" class="wlm-inside-toggle wpf-tab">WP Fusion</a></li>';
	}

	/**
	 * WP Fusion settings tab content for Pay Per Post settings.
	 *
	 * @since 3.38.5
	 */
	public function tab_content() {

		$settings = array(
			'apply_tags_pay_per_post' => array(),
		);

		$settings = wp_parse_args( get_post_meta( get_the_ID(), 'wpf-settings', true ), $settings );

		echo '<div class="wlm-inside wlm-inside099" style="display: none;">';
		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">' . esc_html__( 'Apply tags - Pay Per Post:', 'wp-fusion' ) . '</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_pay_per_post'],
			'meta_name' => 'wpf-settings',
			'field_id'  => 'apply_tags_pay_per_post',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . esc_html( sprintf( __( 'Apply these tags in %s when this content is purchased via Pay Per Post.', 'wp-fusion' ), wp_fusion()->crm->name ) ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table></div>';

	}


	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds WLM option to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['wishlist'] = array(
			'label'   => 'WishList membership statuses',
			'title'   => 'members',
			'tooltip' => __( 'Updates the tags for all members based on their current membership status. Does not create new contact records.', 'wp-fusion' ),
		);

		return $options;

	}

	/**
	 * Counts total number of members to be processed
	 *
	 * @access public
	 * @return array Members
	 */

	public function batch_init() {

		$members = array();

		$query = wlmapi_get_members();

		if ( ! empty( $query ) ) {

			foreach ( $query['members']['member'] as $member ) {
				$members[] = $member['id'];
			}
		}

		return $members;

	}

	/**
	 * Processes member actions in batches
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step( $user_id ) {

		$levels = wlmapi_get_member_levels( $user_id );

		if ( ! empty( $levels ) ) {

			$add_levels = array();

			foreach ( $levels as $level ) {
				$add_levels[] = $level->Level_ID;
			}

			$this->add_user_levels( $user_id, $add_levels );

		}

	}


}

new WPF_WishListMember();
