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
	public $name = 'Accessally';

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

		// Tag syncing hooks
		add_action( 'wpf_tags_modified', array( $this, 'wpf_tags_modified' ), 10, 2 );
		add_action( 'updated_user_meta', array( $this, 'aa_tags_modified' ), 10, 4 );
		add_action( 'added_user_meta', array( $this, 'aa_tags_modified' ), 10, 4 );

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

		if ( isset( $_POST['wpf_accessally_admin'] ) && wp_verify_nonce( $_POST['wpf_accessally_admin'], 'wpf_aa_settings' ) && ! empty( $_POST['wpf_settings'] ) ) {

			$settings = get_option( 'wpf_accessally_settings', array() );

			foreach ( $_POST['wpf_settings'] as $id => $setting ) {
				$settings[ $id ] = $setting;
			}

			update_option( 'wpf_accessally_settings', $settings, false );

			echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';

		}

		$settings = get_option( 'wpf_accessally_settings', array() );

		$aa_settings = get_option( '_accessally_setting_api', array() );

		switch ( $aa_settings['system'] ) {
			case 'drip':
				$slug = AccessAllyDripUtilities::DRIP_TAG_OPTION_KEY;
				break;

			case 'Ontraport':
				$slug = AccessAllyOntraportUtilities::ONTRAPORT_TAG_OPTION_KEY;
				break;

			case 'Infusionsoft':
				$slug = AccessAllyInfusionUtilities::INFUSIONSOFT_TAG_OPTION_KEY;
				break;

			case 'active-campaign':
				$slug = AccessAllyActiveCampaignUtilities::ACTIVE_CAMPAIGN_TAG_OPTION_KEY;
				break;

			case 'convertkit':
				$slug = AccessAllyConvertkitUtilities::CONVERTKIT_TAG_OPTION_KEY;
				break;

			default:
				$slug = '';
				break;
		}

		$aa_tags  = get_option( $slug, array() );
		$limit    = 100;
		$wpf_tags = wpf_get_option( 'available_tags' );

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
		<div id="wrap">

			<h1 class="wp-heading-inline">WP Fusion - AccessAlly Integration</h1>
		
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

							&nbsp;Page <?php echo $page; ?> of <?php echo $total_pages; ?>&nbsp;

							<a href="?page=accessally-wpf&paged=<?php echo $page + 1; ?>">Next &raquo;</a>

						</div>


					<?php endif; ?>
				
					<table class="table table-hover" id="wpf-coursewre-levels-table">
						<thread>
				
							<tr>

								<th style="text-align:left;">Active</th>
							
								<th style="text-align:left;">AccessAlly Tag (<?php echo ucwords( str_replace( '-', '', $aa_settings['system'] ) ); ?>)</th>
					
								<th></th>

								<th style="text-align:left;">WP Fusion Tag (<?php echo wp_fusion()->crm->name; ?>)</th>
					
							</tr> 
						</thread>
						<tbody>

							<?php
							foreach ( $aa_tags as $tag ) :

								if ( ! isset( $settings[ $tag['Id'] ] ) ) {
									$settings[ $tag['Id'] ] = array(
										'wpf_tag' => array(),
										'active'  => false,
									);
								}

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
												'setting' => $settings[ $tag['Id'] ]['wpf_tag'],
												'meta_name' => "wpf_settings[{$tag['Id']}][wpf_tag]",
												'limit'   => 1,
												'placeholder' => __( 'Select a tag', 'wp-fusion' ),
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
	 * Push updates through WP Fusion when an AccessAlly user is updated
	 *
	 * @access  public
	 * @return  void
	 */

	public function user_updated( $user_id, $contact_id ) {

		wp_fusion()->user->push_user_meta( $user_id );

	}


	/**
	 * Sync WPF tag changes over to AccessAlly
	 *
	 * @access  public
	 * @return  void
	 */

	public function wpf_tags_modified( $user_id, $user_tags ) {

		$settings = get_option( 'wpf_accessally_settings', array() );

		$aa_user_tags = get_user_meta( $user_id, '_accessally_user_tag_ids', true );

		$aa_api_settings = get_option( '_accessally_setting_api', array() );

		if ( empty( $settings ) || empty( $aa_user_tags ) ) {
			return;
		}

		foreach ( $settings as $tag_id => $setting ) {

			if ( empty( $setting['active'] ) || $setting['active'] != true ) {
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


	/**
	 * Sync AA tag changes over to WPF
	 *
	 * @access public
	 * @return void
	 */

	public function aa_tags_modified( $meta_id, $object_id, $meta_key, $aa_user_tags ) {

		if ( $meta_key != AccessAllyUserPermission::WP_USER_TAG_IDS ) {
			return;
		}

		global $pagenow;

		if ( $pagenow == 'profile.php' || $pagenow == 'user-edit.php' ) {
			return;
		}

		$settings = get_option( 'wpf_accessally_settings', array() );

		if ( empty( $settings ) || empty( $aa_user_tags ) ) {
			return;
		}

		$user_tags = wp_fusion()->user->get_tags( $object_id );

		foreach ( $settings as $tag_id => $setting ) {

			if ( empty( $setting['active'] ) || $setting['active'] != true ) {
				continue;
			}

			remove_action( 'wpf_tags_modified', array( $this, 'wpf_tags_modified' ), 10, 2 );

			if ( in_array( $tag_id, $aa_user_tags['ids'] ) && ! in_array( $setting['wpf_tag'][0], $user_tags ) ) {

				wp_fusion()->user->apply_tags( array( $setting['wpf_tag'][0] ), $object_id );

			} elseif ( ! in_array( $tag_id, $aa_user_tags['ids'] ) && in_array( $setting['wpf_tag'][0], $user_tags ) ) {

				wp_fusion()->user->remove_tags( array( $setting['wpf_tag'][0] ), $object_id );

			}

			add_action( 'wpf_tags_modified', array( $this, 'wpf_tags_modified' ), 10, 2 );

		}

	}



}

new WPF_AccessAlly();
