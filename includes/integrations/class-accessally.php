<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPF_AccessAlly extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'accessally';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'AccessAlly';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/accessally/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'admin_menu', array( $this, 'page_menu' ) );

		add_action( 'accessally_update_user', array( $this, 'user_updated' ), 10, 2 );

		// Tag syncing hooks.

		if ( version_compare( AccessAlly::VERSION, '4.0.0', '>=' ) ) {

			// AA versions 4.0.0 and above use the wp_aal_user_tags table. Of course there are no hooks...
			add_action( 'wpf_tags_modified', array( $this, 'wpf_tags_modified_v4' ), 10, 2 );

		} else {

			// AA versions pre 4.0.0 use usermeta.
			add_action( 'wpf_tags_modified', array( $this, 'wpf_tags_modified' ), 10, 2 );

		}

	}

	/**
	 * Creates WPPP submenu item
	 *
	 * @access public
	 * @return void
	 */

	function page_menu() {

		$id = add_submenu_page(
			'_accessally_setting_all',
			'WP Fusion - AccessAlly Integration',
			'WP Fusion',
			'manage_options',
			'accessally-wpf',
			array( $this, 'wpf_settings_page' )
		);

		add_action( 'load-' . $id, array( $this, 'enqueue_scripts' ) );

		$id_new = add_submenu_page(
			'_accessally_dashboard',
			'WP Fusion - AccessAlly Integration',
			'WP Fusion',
			'manage_options',
			'accessally-wpf',
			array( $this, 'wpf_settings_page' )
		);

		add_action( 'load-' . $id_new, array( $this, 'enqueue_scripts' ) );

	}

	/**
	 * Renders WPPP Styles
	 *
	 * @access public
	 * @return void
	 */

	public function enqueue_scripts() {

		wp_enqueue_style( 'options-css', WPF_DIR_URL . 'includes/admin/options/css/options.css' );
		wp_enqueue_style( 'wpf-options', WPF_DIR_URL . 'assets/css/wpf-options.css' );

	}


	/**
	 * Renders PP submenu item
	 *
	 * @access public
	 * @return mixed
	 */

	public function wpf_settings_page() {

		?>

		<div class="wrap">

			<h1>WP Fusion - AccessAlly Integration</h1>

			<?php

			$aa_tags = AccessAllyMembershipUtilities::get_all_tags();

			if ( isset( $_POST['wpf_accessally_admin'] ) && wp_verify_nonce( $_POST['wpf_accessally_admin'], 'wpf_aa_settings' ) && ! empty( $_POST['wpf_settings'] ) ) {

				$settings = get_option( 'wpf_accessally_settings', array() );

				if ( 100 < count( $aa_tags ) ) {
					// If there are more than 100 tags, try to merge them.
					foreach ( wpf_clean( $_POST['wpf_settings'] ) as $id => $setting ) {
						$settings[ $id ] = $setting;
					}
				} else {
					$settings = wpf_clean( $_POST['wpf_settings'] );
				}

				update_option( 'wpf_accessally_settings', $settings, false );

				echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';

			}

			$settings    = get_option( 'wpf_accessally_settings', array() );
			$aa_settings = get_option( '_accessally_setting_api', array() );

			$limit = 100;

			// Pagination.

			if ( count( $aa_tags ) > $limit ) {

				$total_pages = ceil( count( $aa_tags ) / $limit );

				if ( isset( $_GET['paged'] ) ) {
					$offset = absint( $_GET['paged'] ) * $limit;
					$page   = $_GET['paged'];
				} else {
					$offset = 0;
					$page   = 1;
				}

				$aa_tags = array_slice( $aa_tags, $offset, $limit );

			}

			?>

			<form id="wpf-aa-settings" action="" method="post" style="width: 100%; max-width: 800px;">

				<?php wp_nonce_field( 'wpf_aa_settings', 'wpf_accessally_admin' ); ?>	        	
				<input type="hidden" name="action" value="update">	

					<div class="alert alert-info">
						<?php if ( strtolower( str_replace( '-', '', $aa_settings['system'] ) ) == wp_fusion()->crm->slug ) : ?>

							<p style="margin-top: 0px;"><strong>AccessAlly and WP Fusion are both connected to <?php echo wp_fusion()->crm->name; ?></strong>.</p>

						<?php else : ?>

							<p style="margin-top: 0px;"><strong>AccessAlly is connected to <?php echo ucwords( str_replace( '-', '', $aa_settings['system'] ) ); ?></strong> and <strong>WP Fusion is connected to <?php echo wp_fusion()->crm->name; ?></strong>.</p>

						<?php endif; ?>

						<p>For each of the enabled rows below, when a tag is applied in AccessAlly it will also be applied for WP Fusion. Likewise, when a tag is applied in WP Fusion, it will also update the user's tags in AccessAlly.</p>

					</div>

					<br/>


					<?php if ( isset( $offset ) ) : ?>

						<div id="aa-pagination">

							<a href="?page=accessally-wpf&paged=<?php echo $page - 1; ?>">&laquo; Previous</a>

							&nbsp;Page <?php echo $page; ?> of <?php echo $total_pages - 1; ?>&nbsp;

							<a href="?page=accessally-wpf&paged=<?php echo $page; ?>">Next &raquo;</a>

						</div>


					<?php endif; ?>

					<table class="table table-hover" id="wpf-coursewre-levels-table">
						<thread>

							<tr>

								<th style="text-align:left;">Active</th>

								<th style="text-align:left;">AccessAlly Tag (<?php echo ucwords( str_replace( '-', '', $aa_settings['system'] ) ); ?>)</th>

								<th></th>

								<th style="text-align:left;"><?php printf( esc_html__( 'WP Fusion Tag (%s)', 'wp-fusion' ), esc_html( wp_fusion()->crm->name ) ); ?> </th>

							</tr> 
						</thread>
						<tbody>

							<?php
							foreach ( $aa_tags as $tag ) :

								$default = array(
									'wpf_tag' => array(),
									'active'  => false,
								);

								if ( ! isset( $settings[ $tag['Id'] ] ) ) {
									$settings[ $tag['Id'] ] = array();
								}

								$settings[ $tag['Id'] ] = wp_parse_args( $settings[ $tag['Id'] ], $default );

								?>

								<tr style="border-bottom: 2px solid #ddd !important;" 
								<?php
								if ( $settings[ $tag['Id'] ]['active'] == true ) {
									echo 'class="success"';}
								?>
								>

									<td>
										<input class="checkbox contact-fields-checkbox" type="checkbox" value="1" name="wpf_settings[<?php echo $tag['Id']; ?>][active]" <?php checked( $settings[ $tag['Id'] ]['active'], 1 ); ?> />
									</td>

									<td style="font-weight: bold;"><?php echo $tag['TagName']; ?></td>

									<td>&laquo; &raquo;</td>

									<td>

										<?php
											$args = array(
												'setting'   => $settings[ $tag['Id'] ]['wpf_tag'],
												'meta_name' => "wpf_settings[{$tag['Id']}][wpf_tag]",
												'limit'     => 1,
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
	 * Sync profile and tag changes when a user is updated in AccessAlly.
	 *
	 * @since 3.41.3
	 *
	 * @param int    $user_id    The user ID.
	 * @param string $contact_id The AccessAlly contact ID.
	 */
	public function user_updated( $user_id, $contact_id ) {

		// Sync the metadata.

		if ( ! did_action( 'profile_update' ) ) { // this is already handled in WPF_User::profile_update().
			wp_fusion()->user->push_user_meta( $user_id ); 
		}

		if ( did_action( 'wpf_admin_profile_tags_edited' ) ) {
			return; // don't try to process AA tags if WPF tags have just changed.
		}

		// See if tags have changed.

		$settings = get_option( 'wpf_accessally_settings', array() );

		if ( ! empty( $settings ) ) {

			$tag_ids = AccessAllyMembershipUtilities::get_contact_tags( $contact_id );

			$user_tags = wpf_get_tags( $user_id );

			foreach ( $settings as $tag_id => $setting ) {

				if ( empty( $setting['active'] ) || empty( $setting['wpf_tag'] ) ) {
					continue;
				}

				remove_action( 'wpf_tags_modified', array( $this, 'wpf_tags_modified' ), 10, 2 );
				remove_action( 'wpf_tags_modified', array( $this, 'wpf_tags_modified_v4' ), 10, 2 );

				if ( in_array( $tag_id, $tag_ids ) && ! in_array( $setting['wpf_tag'][0], $user_tags ) ) {

					wp_fusion()->user->apply_tags( $setting['wpf_tag'], $user_id );

				} elseif ( ! in_array( $tag_id, $tag_ids ) && in_array( $setting['wpf_tag'][0], $user_tags ) ) {

					wp_fusion()->user->remove_tags( $setting['wpf_tag'], $user_id );

				}

				add_action( 'wpf_tags_modified', array( $this, 'wpf_tags_modified' ), 10, 2 );
				add_action( 'wpf_tags_modified', array( $this, 'wpf_tags_modified_v4' ), 10, 2 );

			}
		}

	}

	/**
	 * Sync tag changes from WP Fusion over to AccessAlly, for AA 4.0.0+.
	 *
	 * @since 3.41.3
	 *
	 * @param int   $user_id   The user ID.
	 * @param array $user_tags The user's tags.
	 */
	public function wpf_tags_modified_v4( $user_id, $user_tags ) {

		$settings = get_option( 'wpf_accessally_settings', array() );

		if ( empty( $settings ) ) {
			return;
		}

		// AA's CRM contact ID.
		$updated    = false;
		$contact_id = AccessAllyUserPermission::get_user_contact_id( $user_id );

		if ( ! $contact_id ) {
			return;
		}

		$aa_tags = AccessAllyMembershipUtilities::get_contact_tags( $contact_id );

		// Keep track of the AA tag names and IDs.
		$tag_id_name_map = array();

		foreach ( AccessAllyMembershipUtilities::get_all_tags() as $tag ) {
			$tag_id_name_map[ $tag['Id'] ] = $tag['TagName'];
		}

		foreach ( $settings as $tag_id => $setting ) {

			if ( empty( $setting['active'] ) || empty( $setting['wpf_tag'] ) ) {
				continue;
			}

			if ( in_array( $setting['wpf_tag'][0], $user_tags ) && ! in_array( $tag_id, $aa_tags ) ) {

				wpf_log( 'info', $user_id, 'Applying AccessAlly \tag <strong>' . $tag_id_name_map[ $tag_id ] . '</strong> to user from linked ' . wp_fusion()->crm->name . ' tag <strong>' . wpf_get_tag_label( $setting['wpf_tag'][0] ) . '</strong>.' );

				// These don't trigger the accessally_update_user hook so we don't need to remove the action.
				AccessAllyMembershipUtilities::add_contact_tags( $contact_id, array( $tag_id ) );
				$updated = true;

				$aa_tags = array_merge( $aa_tags, array( $tag_id ) ); // update the aa tags to we can save them.

			} elseif ( ! in_array( $setting['wpf_tag'][0], $user_tags ) && in_array( $tag_id, $aa_tags ) ) {

				wpf_log( 'info', $user_id, 'Removing AccessAlly \tag <strong>' . $tag_id_name_map[ $tag_id ] . '</strong> from user from linked ' . wp_fusion()->crm->name . ' tag <strong>' . wpf_get_tag_label( $setting['wpf_tag'][0] ) . '</strong>.' );

				AccessAllyMembershipUtilities::remove_contact_tags( $contact_id, array( $tag_id ) );
				$updated = true;

				$aa_tags = array_diff( $aa_tags, array( $tag_id ) ); // update the aa tags to we can save them.

			}
		}

		if ( $updated ) {

			// Update the local user cache.
			$aa_setup = AccessAllySettingSetup::get_api_settings();
			AccessAllyUserTags::sync_user_tag_list( $contact_id, $aa_tags, $aa_setup['system'] );

		}

	}


	/**
	 * Sync WPF tag changes over to AccessAlly
	 *
	 * @access  public
	 * @return  void
	 */

	public function wpf_tags_modified( $user_id, $user_tags ) {

		$settings = get_option( 'wpf_accessally_settings', array() );

		// This is only for AccessAlly below 4.0.0.
		$aa_user_tags = get_user_meta( $user_id, '_accessally_user_tag_ids', true );

		if ( empty( $settings ) || empty( $aa_user_tags ) ) {
			return;
		}

		$aa_api_settings = get_option( '_accessally_setting_api', array() );

		foreach ( $settings as $tag_id => $setting ) {

			if ( empty( $setting['active'] ) ) {
				continue;
			}

			remove_action( 'updated_user_meta', array( $this, 'aa_tags_modified' ), 10, 4 );

			if ( in_array( $setting['wpf_tag'][0], $user_tags ) && ! in_array( $tag_id, $aa_user_tags['ids'] ) ) {

				$aa_user_tags['ids'][] = $tag_id;

				update_user_meta( $user_id, AccessAllyUserPermission::WP_USER_TAG_IDS, $aa_user_tags );

				// Clear AA cache
				wp_cache_set( AccessAllyUserPermission::WP_USER_TAG_IDS, $aa_user_tags, $user_id, time() + AccessAlly::CACHE_PERIOD );

				// Send API call to apply tags in other CRM if necessary
				if ( strtolower( str_replace( '-', '', $aa_api_settings['system'] ) ) != wp_fusion()->crm->slug ) {
					AccessAllyAPI::add_tag_by_wp_user_id( $tag_id, $user_id );
				}
			} elseif ( ! in_array( $setting['wpf_tag'][0], $user_tags ) && ( $key = array_search( $tag_id, $aa_user_tags['ids'] ) ) !== false ) {

				unset( $aa_user_tags['ids'][ $key ] );

				update_user_meta( $user_id, AccessAllyUserPermission::WP_USER_TAG_IDS, $aa_user_tags );

				// Clear AA cache
				wp_cache_set( AccessAllyUserPermission::WP_USER_TAG_IDS, $aa_user_tags, $user_id, time() + AccessAlly::CACHE_PERIOD );

			}

			add_action( 'updated_user_meta', array( $this, 'aa_tags_modified' ), 10, 4 );

		}

	}

}

new WPF_AccessAlly();
