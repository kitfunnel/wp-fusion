<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}



class WPF_Restrict_Content_Pro extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'restrict-content-pro';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Restrict Content Pro';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/restrict-content-pro/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		// Meta fields
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 20 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		// Global settings
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );

		// Registration and updates
		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'user_update' ), 10, 2 );
		add_action( 'wpf_user_created', array( $this, 'user_created' ), 10, 3 );
		add_filter( 'wpf_watched_meta_fields', array( $this, 'register_watched_fields' ) );
		add_action( 'wpf_tags_modified', array( $this, 'change_level' ), 10, 2 );

		// Add interfaces to admin
		add_action( 'rcp_edit_subscription_form', array( $this, 'subscription_settings' ) );
		add_action( 'rcp_add_subscription_form', array( $this, 'subscription_settings' ) );
		add_action( 'rcp_edit_subscription_level', array( $this, 'save_subscription_settings' ), 10, 2 );
		add_action( 'rcp_add_subscription', array( $this, 'save_subscription_settings_new' ), 10, 2 );

		// Registrations and future profile updates
		add_action( 'rcp_member_post_set_subscription_id', array( $this, 'subscription_id_changed' ), 1000, 3 );
		add_action( 'rcp_set_status', array( $this, 'status_changed' ), 10, 4 );
		add_action( 'rcp_membership_post_renew', array( $this, 'post_renew' ), 10, 3 );
		add_action( 'rcp_recurring_payment_failed', array( $this, 'recurring_payment_failed' ), 10, 2 );

		add_action( 'rcp_customer_add_note', array( $this, 'customer_add_note' ), 10, 3 );

		// Groups actions
		add_action( 'rcpga_add_member_to_group_after', array( $this, 'add_member_to_group' ), 10, 3 );
		add_action( 'rcpga_remove_member', array( $this, 'remove_member_from_group' ), 10, 2 );
		add_action( 'wpf_tags_modified', array( $this, 'update_group_memberships' ), 10, 2 );

		// Groups interfaces
		add_action( 'rcpga_add_group_form_fields_after', array( $this, 'edit_group_fields' ) );
		add_action( 'rcpga_edit_group_form_fields_after', array( $this, 'edit_group_fields' ) );
		add_action( 'rcpga_action_router', array( $this, 'save_group_fields' ), 10, 2 );

		// Batch operations
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_rcp_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_rcp', array( $this, 'batch_step' ) );

		add_action( 'wpf_batch_rcp_groups_init', array( $this, 'batch_init_groups' ) );
		add_action( 'wpf_batch_rcp_groups', array( $this, 'batch_step_groups' ) );

	}


	/**
	 * Maps RCP specific POST fields to standard ones
	 *
	 * @access  public
	 * @return  array Post Data
	 */

	public function user_register( $post_data, $user_id ) {

		$field_map = array(
			'rcp_user_pass'  => 'user_pass',
			'rcp_user_login' => 'user_login',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		if ( isset( $post_data['rcp_level'] ) ) {
			$post_data['rcp_level'] = rcp_get_subscription_name( $post_data['rcp_level'] );
		}

		return $post_data;

	}

	/**
	 * Maps RCP specific POST fields to standard ones
	 *
	 * @access  public
	 * @return  array Post Data
	 */

	public function user_update( $post_data, $user_id ) {

		$field_map = array(
			'rcp_first_name'     => 'first_name',
			'rcp_last_name'      => 'last_name',
			'rcp_display_name'   => 'display_name',
			'rcp_email'          => 'user_email',
			'rcp_new_user_pass1' => 'user_pass',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		return $post_data;

	}

	/**
	 * Send data after contact has been added if RCP auto-register is enabled
	 *
	 * @access  public
	 * @return  void
	 */

	public function user_created( $user_id, $contact_id, $post_data ) {

		global $rcp_options;

		if ( empty( $rcp_options['auto_add_users'] ) || empty( $rcp_options['auto_add_users_level'] ) ) {
			return;
		}

		$member = new RCP_Member( $user_id );

		// Apply tags

		$this->subscription_id_changed( $member->get_subscription_id(), $user_id, $member );

		$level_id = $member->get_subscription_id();

		// Update fields

		$update_data = array(
			'rcp_subscription_level'       => $level_id,
			'rcp_status'                   => $member->get_status(),
			'rcp_expiration'               => $member->get_expiration_date(),
			'rcp_notes'                    => $member->get_notes(),
			'rcp_joined_date_' . $level_id => $member->get_joined_date( $level_id ),
		);

		wp_fusion()->user->push_user_meta( $user_id, $update_data );

	}

	/**
	 * Registers RCP user_meta fields for automatic sync when change detected
	 *
	 * @access public
	 * @return array Watched Fields
	 */

	public function register_watched_fields( $watched_fields ) {

		$rcp_fields = array( 'rcp_subscription_level', 'rcp_status', 'rcp_expiration', 'rcp_signup_method', 'rcp_has_trialed' );

		return array_merge( $watched_fields, $rcp_fields );

	}

	/**
	 * Adds RCP field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['rcp'] = array(
			'title'  => 'Restrict Content Pro',
			'url'    => 'https://wpfusion.com/documentation/membership/restrict-content-pro/',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Adds RCP meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['rcp_subscription_level'] = array(
			'label' => 'Membership Level ID',
			'type'  => 'text',
			'group' => 'rcp',
		);

		$meta_fields['rcp_level'] = array(
			'label'  => 'Membership Level Name',
			'type'   => 'text',
			'group'  => 'rcp',
			'pseudo' => true,
		);

		$meta_fields['rcp_status'] = array(
			'label' => 'Account Status',
			'type'  => 'text',
			'group' => 'rcp',
		);

		$meta_fields['rcp_expiration'] = array(
			'label' => 'Expiration Date',
			'type'  => 'date',
			'group' => 'rcp',
		);

		$meta_fields['rcp_signup_method'] = array(
			'label' => 'Signup Method',
			'type'  => 'text',
			'group' => 'rcp',
		);

		$meta_fields['rcp_notes'] = array(
			'label'  => 'Customer Notes',
			'type'   => 'textarea',
			'group'  => 'rcp',
			'pseudo' => true,
		);

		foreach ( rcp_get_membership_levels() as $level ) {

			$meta_fields[ "rcp_joined_date_{$level->id}" ] = array(
				'label'  => 'Join Date - ' . $level->name,
				'type'   => 'date',
				'group'  => 'rcp',
				'pseudo' => true,
			);
		}

		if ( class_exists( 'RCP_Group_Accounts' ) ) {

			$meta_fields['rcp_group_leader_email'] = array(
				'label'  => 'Group Leader Email',
				'type'   => 'text',
				'group'  => 'rcp',
				'pseudo' => true,
			);

			$meta_fields['rcp_group_name'] = array(
				'label'  => 'Group Name',
				'type'   => 'text',
				'group'  => 'rcp',
				'pseudo' => true,
			);

		}

		return $meta_fields;

	}


	/**
	 * Registers additional EDD settings
	 *
	 * @access  public
	 * @return  array Settings
	 */

	public function register_settings( $settings, $options ) {

		if ( ! function_exists( 'rcpga_get_group' ) ) {
			return $settings;
		}

		$settings['rcp_header'] = array(
			'title'   => __( 'Restrict Content Pro Integration', 'wp-fusion' ),
			'std'     => 0,
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['rcp_group_member_tags'] = array(
			'title'   => __( 'Apply Tags to Group Members', 'wp-fusion' ),
			'desc'    => __( 'These tags will be applied to all members of any Restrict Content Pro group.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		return $settings;

	}

	/**
	 * Adds CRM tag association field to edit view for single Subscription
	 *
	 * @access  public
	 * @return  mixed
	 */

	public function subscription_settings( $level = false ) { ?>

		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="rcp-role"><?php _e( 'Apply Tags', 'wp-fusion' ); ?></label>
			</th>
			<td>
				<?php $saved_settings = get_option( 'wpf_rcp_tags', array() ); ?>

				<?php

				$settings = array(
					'apply_tags'            => array(),
					'remove_tags'           => false,
					'tag_link'              => array(),
					'status_active'         => array(),
					'status_cancelled'      => array(),
					'status_expired'        => array(),
					'status_trial'          => array(),
					'status_free'           => array(),
					'status_payment_failed' => array(),
				);

				if ( is_object( $level ) && isset( $saved_settings[ $level->id ] ) ) {

					$settings = array_merge( $settings, $saved_settings[ $level->id ] );

				}

				?>

				<?php

					$args = array(
						'setting'   => $settings['apply_tags'],
						'meta_name' => 'wpf-settings',
						'field_id'  => 'apply_tags',
					);

					wpf_render_tag_multiselect( $args );

					?>

				<p class="description"><?php printf( __( 'These tags will be applied to the user in %s when they register or are added to the membership level.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="remove-tags"><?php _e( 'Remove Tags', 'wp-fusion' ); ?></label>
			</th>
			<td>

				<input class="checkbox" type="checkbox" id="remove-tags" name="wpf-settings[remove_tags]" value="1" <?php checked( $settings['remove_tags'], 1 ); ?> />
				<label for="remove-tags"><?php _e( 'Remove original tags (above) when the membership expires', 'wp-fusion' ); ?>.</label>

			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="rcp-role"><?php _e( 'Link with Tag', 'wp-fusion' ); ?></label>
			</th>
			<td>
				<?php

					$args = array(
						'setting'     => $settings['tag_link'],
						'meta_name'   => 'wpf-settings',
						'field_id'    => 'tag_link',
						'limit'       => 1,
						'placeholder' => 'Select a tag',
					);

					wpf_render_tag_multiselect( $args );

					?>

				<p class="description"><?php _e( 'This tag will be applied to the user when they register or are added to the membership level. This tag will be removed if the membership expires, or if the member is removed from the level. You can also grant any user this membership level by manually applying the tag. If the tag is removed the level will be removed.', 'wp-fusion' ); ?></p>

			</td>
		</tr>

		</table>

		<hr />

		<h3><?php _e( 'Additional Status tagging', 'wp-fusion' ); ?></h3>
		<p class="description"><?php _e( 'For each membership status you can select additional tags to be applied. These are in addition to the more general "Apply tags" setting above. For more information <a href="https://wpfusion.com/documentation/membership/restrict-content-pro/#additional-status-tagging" target="_blank">see the documentation</a>.', 'wp-fusion' ); ?></p>

		<table class="form-table">
			<tbody>

				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="rcp-role"><?php _e( 'Active', 'wp-fusion' ); ?></label>
					</th>
					<td>
						<?php

							$args = array(
								'setting'   => $settings['status_active'],
								'meta_name' => 'wpf-settings',
								'field_id'  => 'status_active',
							);

							wpf_render_tag_multiselect( $args );

							?>

						<p class="description"><?php _e( 'These tags will be applied when a user\'s membership status is set to active. These tags will not be removed if the status changes.', 'wp-fusion' ); ?></p>

					</td>
				</tr>

				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="rcp-role"><?php _e( 'Free', 'wp-fusion' ); ?></label>
					</th>
					<td>
						<?php

							$args = array(
								'setting'   => $settings['status_free'],
								'meta_name' => 'wpf-settings',
								'field_id'  => 'status_free',
							);

							wpf_render_tag_multiselect( $args );

							?>

						<p class="description"><?php _e( 'These tags will be applied when a user registers for a free membership. These tags will not be removed if the status changes.', 'wp-fusion' ); ?></p>

					</td>
				</tr>

				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="rcp-role"><?php _e( 'Trial', 'wp-fusion' ); ?></label>
					</th>
					<td>
						<?php

							$args = array(
								'setting'   => $settings['status_trial'],
								'meta_name' => 'wpf-settings',
								'field_id'  => 'status_trial',
							);

							wpf_render_tag_multiselect( $args );

							?>

						<p class="description"><?php _e( 'These tags will be applied when a member signs up for a trial. These tags will not be removed if the status changes.', 'wp-fusion' ); ?></p>

					</td>
				</tr>

				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="rcp-role"><?php _e( 'Cancelled', 'wp-fusion' ); ?></label>
					</th>
					<td>
						<?php

							$args = array(
								'setting'   => $settings['status_cancelled'],
								'meta_name' => 'wpf-settings',
								'field_id'  => 'status_cancelled',
							);

							wpf_render_tag_multiselect( $args );

							?>

						<p class="description"><?php _e( 'These tags will be applied when a user\'s membership is cancelled. These tags will be removed if the membership is reactivated.', 'wp-fusion' ); ?></p>

					</td>
				</tr>

				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="rcp-role"><?php _e( 'Expired', 'wp-fusion' ); ?></label>
					</th>
					<td>
						<?php

							$args = array(
								'setting'   => $settings['status_expired'],
								'meta_name' => 'wpf-settings',
								'field_id'  => 'status_expired',
							);

							wpf_render_tag_multiselect( $args );

							?>

						<p class="description"><?php _e( 'These tags will be applied when a user\'s membership expires. These tags will be removed if the membership is reactivated.', 'wp-fusion' ); ?></p>

					</td>
				</tr>

				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="rcp-role"><?php _e( 'Renewal Payment Failed', 'wp-fusion' ); ?></label>
					</th>
					<td>
						<?php

							$args = array(
								'setting'   => $settings['status_payment_failed'],
								'meta_name' => 'wpf-settings',
								'field_id'  => 'status_payment_failed',
							);

							wpf_render_tag_multiselect( $args );

							?>

						<p class="description"><?php _e( 'These tags will be applied when a renewal payment fails. These tags will be removed if a sucessful renewal payment is received.', 'wp-fusion' ); ?></p>

					</td>
				</tr>

			</tbody>

		</table>

		<hr />

		<table class="form-table">
			<tbody>

		<?php

	}

	/**
	 * Saves changes to WPF fields on the subscription edit screen
	 *
	 * @access  public
	 * @return  void
	 */

	public function save_subscription_settings( $id, $args ) {

		if ( ! isset( $args['wpf-settings'] ) ) {
			return;
		}

		$settings        = get_option( 'wpf_rcp_tags', array() );
		$settings[ $id ] = $args['wpf-settings'];

		update_option( 'wpf_rcp_tags', $settings );

	}


	/**
	 * Saves WPF settings for new membership levels
	 *
	 * @access  public
	 * @return  void
	 */

	public function save_subscription_settings_new( $id, $args ) {

		if ( ! isset( $_POST['wpf-settings'] ) ) {
			return;
		}

		$settings        = get_option( 'wpf_rcp_tags', array() );
		$settings[ $id ] = $_POST['wpf-settings'];
		update_option( 'wpf_rcp_tags', $settings );

	}


	/**
	 * Triggered when a user's status is changed in RCP
	 *
	 * @access  public
	 * @return  void
	 */

	public function subscription_id_changed( $subscription_id, $user_id, $member ) {

		$status = $member->get_status();
		$tags   = get_option( 'wpf_rcp_tags', array() );

		if ( ! empty( $tags[ $subscription_id ] ) ) {

			// Disable tag link function
			remove_action( 'wpf_tags_modified', array( $this, 'change_level' ), 10, 2 );

			// Apply / remove tags
			if ( $status == 'active' || $status == 'free' ) {

				if ( ! empty( $tags[ $subscription_id ]['apply_tags'] ) ) {
					wp_fusion()->user->apply_tags( $tags[ $subscription_id ]['apply_tags'], $user_id );
				}

				if ( ! empty( $tags[ $subscription_id ]['tag_link'] ) ) {
					wp_fusion()->user->apply_tags( $tags[ $subscription_id ]['tag_link'], $user_id );
				}
			} elseif ( $status == 'expired' && ! empty( $tags[ $subscription_id ]['tag_link'] ) ) {
				wp_fusion()->user->remove_tags( $tags[ $subscription_id ]['tag_link'], $user_id );
			}

			add_action( 'wpf_tags_modified', array( $this, 'change_level' ), 10, 2 );

		}

		// Remove any linked tags from other levels

		if ( ! function_exists( 'rcp_multiple_memberships_enabled' ) || ! rcp_multiple_memberships_enabled() ) {

			foreach ( $tags as $level_id => $settings ) {

				if ( $level_id == $subscription_id ) {
					continue;
				}

				if ( ! empty( $settings['tag_link'] ) ) {

					remove_action( 'wpf_tags_modified', array( $this, 'change_level' ), 10, 2 );

					wp_fusion()->user->remove_tags( $settings['tag_link'], $user_id );

					add_action( 'wpf_tags_modified', array( $this, 'change_level' ), 10, 2 );

				}
			}
		}

		// Update sub name
		wp_fusion()->user->push_user_meta( $user_id, array( 'rcp_level' => rcp_get_subscription_name( $subscription_id ) ) );

	}


	/**
	 * Triggered when a user's membership status is changed
	 *
	 * @access  public
	 * @return  void
	 */

	public function status_changed( $new_status, $user_id, $old_status, $member ) {

		$subscription_id = $member->get_subscription_id();
		$tags            = get_option( 'wpf_rcp_tags', array() );

		$apply_tags  = array();
		$remove_tags = array();

		if ( ! empty( $tags[ $subscription_id ] ) ) {

			wpf_log( 'info', $user_id, 'RCP membership status changed from <strong>' . ucwords( $old_status ) . '</strong> to <strong>' . ucwords( $new_status ) . '</strong> for <a href="' . admin_url( "admin.php?page=rcp-member-levels&edit_subscription={$subscription_id}" ) . '">' . $member->get_subscription_name() . '</a>' );

			// Disable tag link function
			remove_action( 'wpf_tags_modified', array( $this, 'change_level' ), 10, 2 );

			// Apply / remove tags

			if ( $new_status == 'active' || $new_status == 'free' ) {

				// Apply tags for active

				if ( ! empty( $tags[ $subscription_id ]['apply_tags'] ) ) {
					$apply_tags = array_merge( $apply_tags, $tags[ $subscription_id ]['apply_tags'] );
				}

				if ( ! empty( $tags[ $subscription_id ]['tag_link'] ) ) {
					$apply_tags = array_merge( $apply_tags, $tags[ $subscription_id ]['tag_link'] );
				}

				// Remove tags from other statuses

				$statuses = array( 'payment_failed', 'expired', 'cancelled' );

				foreach ( $statuses as $status ) {

					if ( ! empty( $tags[ $subscription_id ][ 'status_' . $status ] ) ) {

						$remove_tags = array_merge( $remove_tags, $tags[ $subscription_id ][ 'status_' . $status ] );

					}
				}
			} elseif ( $new_status == 'expired' ) {

				// "Cancelled" means it was admin or user cancelled but it is still active until the end of the period, so we'll only remove linked tags when it actually becomes Expired

				if ( ! empty( $tags[ $subscription_id ]['tag_link'] ) ) {
					$remove_tags = array_merge( $remove_tags, $tags[ $subscription_id ]['tag_link'] );
				}

				if ( ! empty( $tags[ $subscription_id ]['remove_tags'] ) ) {
					$remove_tags = array_merge( $remove_tags, $tags[ $subscription_id ]['apply_tags'] );
				}
			}

			// Apply additional tags based on current status

			if ( ! empty( $tags[ $subscription_id ][ 'status_' . $new_status ] ) ) {
				$apply_tags = array_merge( $apply_tags, $tags[ $subscription_id ][ 'status_' . $new_status ] );
			}

			// Trials

			if ( $member->is_trialing() && ! empty( $tags[ $subscription_id ]['status_trial'] ) ) {
				$apply_tags = array_merge( $apply_tags, $tags[ $subscription_id ]['status_trial'] );
			}

			// Remove tags

			if ( ! empty( $remove_tags ) ) {
				wp_fusion()->user->remove_tags( $remove_tags, $user_id );
			}

			// Apply tags

			if ( ! empty( $apply_tags ) ) {
				wp_fusion()->user->apply_tags( $apply_tags, $user_id );
			}

			add_action( 'wpf_tags_modified', array( $this, 'change_level' ), 10, 2 );

		}

		// Remove any linked tags from other levels

		if ( ! function_exists( 'rcp_multiple_memberships_enabled' ) || ! rcp_multiple_memberships_enabled() ) {

			foreach ( $tags as $level_id => $settings ) {

				if ( $level_id == $subscription_id ) {
					continue;
				}

				if ( ! empty( $settings['tag_link'] ) ) {

					remove_action( 'wpf_tags_modified', array( $this, 'change_level' ), 10, 2 );

					wp_fusion()->user->remove_tags( $settings['tag_link'], $user_id );

					add_action( 'wpf_tags_modified', array( $this, 'change_level' ), 10, 2 );

				}
			}
		}

		// Send meta data.
		$update_data = array(
			'rcp_level'                           => $member->get_subscription_name(),
			'rcp_subscription_level'              => $subscription_id,
			'rcp_status'                          => $new_status,
			'rcp_expiration'                      => $member->get_expiration_date(),
			'rcp_notes'                           => $member->get_notes(),
			'rcp_joined_date_' . $subscription_id => $member->get_joined_date( $subscription_id ),
		);

		$membership = $member->get_membership();

		if ( ! empty( $membership ) ) {
			$update_data['rcp_signup_method'] = $membership->get_signup_method();
		}

		wp_fusion()->user->push_user_meta( $user_id, $update_data );

	}


	/**
	 * Update fields in the CRM after a successful renewal.
	 *
	 * @since 3.38.0
	 *
	 * @param string         $expiration    The new expiration in Y-m-d H:i:s.
	 * @param int            $membership_id The membership ID.
	 * @param RCP_Membership $membership    The membership.
	 */
	public function post_renew( $expiration, $membership_id, $membership ) {

		$update_data = array(
			'rcp_expiration' => $expiration,
		);

		wp_fusion()->user->push_user_meta( $membership->get_user_id(), $update_data );

	}

	/**
	 * Triggered when a recurring payment fails
	 *
	 * @access  public
	 * @return  void
	 */

	public function recurring_payment_failed( $member, $gateway ) {

		$membership_id = $member->get_subscription_id();

		$tags = get_option( 'wpf_rcp_tags', array() );

		if ( isset( $tags[ $membership_id ] ) && ! empty( $tags[ $membership_id ]['status_payment_failed'] ) ) {

			wp_fusion()->user->apply_tags( $tags[ $membership_id ]['status_payment_failed'], $member->ID );

		}

	}

	/**
	 * Sync customer notes when they are added.
	 *
	 * @since 3.38.25
	 *
	 * @param string       $note        The note.
	 * @param int          $customer_id The customer ID.
	 * @param RCP_Customer $customer    The customer.
	 */
	public function customer_add_note( $note, $customer_id, $customer ) {

		wp_fusion()->user->push_user_meta( $customer->get_user_id(), array( 'rcp_notes' => $customer->get_notes() ) );

	}

	/**
	 * Triggered when a user's tags are modified
	 *
	 * @access  public
	 * @return  void
	 */

	public function change_level( $user_id, $user_tags ) {

		$rcp_tag_map = get_option( 'wpf_rcp_tags', array() );

		if ( empty( $rcp_tag_map ) ) {
			return;
		}

		$user_current_level = rcp_get_subscription_id( $user_id );

		// Prevent looping

		remove_action( 'rcp_set_status', array( $this, 'status_changed' ), 10, 4 );

		foreach ( $rcp_tag_map as $level_id => $tags ) {

			if ( empty( $tags['tag_link'] ) ) {
				continue;
			}

			$tag = $tags['tag_link'][0];

			if ( in_array( $tag, (array) $user_tags ) && $level_id != $user_current_level ) {

				// WPF logging

				if ( false == $user_current_level ) {
					$note = sprintf(
						__( 'User added to membership level %1$s by linked tag %2$s', 'wp-fusion' ),
						'<a href="' . admin_url( "admin.php?page=rcp-member-levels&edit_subscription={$level_id}" ) . '">' . rcp_get_subscription_name( $level_id ) . '</a>',
						'<strong>' . wpf_get_tag_label( $tag ) . '</strong>'
					);
				} else {
					$note = sprintf(
						__( 'User\'s membership level changed from %1$s to %2$s by linked tag %3$s', 'wp-fusion' ),
						'<a href="' . admin_url( "admin.php?page=rcp-member-levels&edit_subscription={$user_current_level}" ) . '">' . rcp_get_subscription_name( $user_current_level ) . '</a>',
						'<a href="' . admin_url( "admin.php?page=rcp-member-levels&edit_subscription={$level_id}" ) . '">' . rcp_get_subscription_name( $level_id ) . '</a>',
						'<strong>' . wpf_get_tag_label( $tag ) . '</strong>'
					);
				}

				wpf_log( 'info', $user_id, $note );

				$args = array(
					'subscription_id' => $level_id,
				);

				rcp_add_user_to_subscription( $user_id, $args );

				$customer = rcp_get_customer_by_user_id( $user_id );

				if ( ! empty( $customer ) ) {

					// RCP logging
					$customer->add_note( $note );

				}

				if ( ! rcp_multiple_memberships_enabled() ) {
					// If members can't have multiple memberships, quit after assigning the first one
					break;
				}
			}
		}

		add_action( 'rcp_set_status', array( $this, 'status_changed' ), 10, 4 );

	}

	/**
	 * //
	 * // GROUPS
	 * //
	 **/

	/**
	 * Apply tags when member added to group
	 *
	 * @access  public
	 * @return  void
	 */

	public function add_member_to_group( $user_id, $args, $group_id ) {

		// Update group fields.
		$update_data = array();

		global $wpdb;
		$owner_user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}rcp_group_members WHERE role=%s AND group_id=%d", 'owner', $group_id ) );

		if ( $owner_user_id ) {
			$user                                  = get_user_by( 'id', $owner_user_id );
			$update_data['rcp_group_leader_email'] = $user->user_email;
		}

		$group_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}rcp_groups WHERE group_id=%d", $group_id ) );

		if ( $group_name ) {
			$update_data['rcp_group_name'] = $group_name;
		}

		if ( ! empty( $update_data ) ) {
			wp_fusion()->user->push_user_meta( $user_id, $update_data );
		}

		// Apply tags.

		$apply_tags = array();

		// Group settings

		$setting = get_metadata( 'rcp_group', $group_id, 'apply_tags', true );

		if ( ! empty( $setting ) ) {
			$apply_tags = array_merge( $apply_tags, $setting );
		}

		// Global tags

		$global_tags = wpf_get_option( 'rcp_group_member_tags' );

		if ( ! empty( $global_tags ) ) {
			$apply_tags = array_merge( $apply_tags, $global_tags );
		}

		$setting = get_metadata( 'rcp_group', $group_id, 'tag_link', true );

		if ( ! empty( $setting ) ) {
			$apply_tags = array_merge( $apply_tags, $setting );
		}

		if ( ! empty( $apply_tags ) ) {

			remove_action( 'wpf_tags_modified', array( $this, 'update_group_memberships' ), 10, 2 );

			wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			add_action( 'wpf_tags_modified', array( $this, 'update_group_memberships' ), 10, 2 );

		}

	}

	/**
	 * Remove linked tag when member removed from group
	 *
	 * @access  public
	 * @return  void
	 */

	public function remove_member_from_group( $user_id, $group_id ) {

		$setting = get_metadata( 'rcp_group', $group_id, 'tag_link', true );

		if ( ! empty( $setting ) ) {

			remove_action( 'wpf_tags_modified', array( $this, 'update_group_memberships' ), 10, 2 );

			wp_fusion()->user->remove_tags( $setting, $user_id );

			add_action( 'wpf_tags_modified', array( $this, 'update_group_memberships' ), 10, 2 );

		}

	}

	/**
	 * Triggered when a user's tags are modified
	 *
	 * @access  public
	 * @return  void
	 */

	public function update_group_memberships( $user_id, $user_tags ) {

		if ( ! function_exists( 'rcpga_get_group' ) ) {
			return;
		}

		global $wpdb;

		$results = $wpdb->get_results( "SELECT rcp_group_id, meta_value FROM {$wpdb->prefix}rcp_groupmeta WHERE meta_key = 'tag_link'" );

		if ( ! empty( $results ) ) {

			foreach ( $results as $result ) {

				$setting = maybe_unserialize( $result->meta_value );

				$group = rcpga_get_group( $result->rcp_group_id );

				$is_member = rcpga_user_is_member_of_group( $user_id, $result->rcp_group_id );

				if ( false === $is_member && ! empty( array_intersect( $user_tags, $setting ) ) ) {

					wpf_log( 'info', $user_id, 'User added to RCP group <strong>' . $group->get_name() . '</strong> by tag <strong>' . wp_fusion()->user->get_tag_label( $setting[0] ) . '</strong>', array( 'source' => 'restrict-content-pro' ) );

					$args = array(
						'user_id' => $user_id,
					);

					remove_action( 'rcpga_add_member_to_group_after', array( $this, 'add_member_to_group' ), 10, 3 );

					rcpga_add_group_member( $result->rcp_group_id, $args );

					add_action( 'rcpga_add_member_to_group_after', array( $this, 'add_member_to_group' ), 10, 3 );

				} elseif ( true === $is_member && empty( array_intersect( $user_tags, $setting ) ) ) {

					wpf_log( 'info', $user_id, 'User removed from RCP group <strong>' . $group->get_name() . '</strong> by tag <strong>' . wp_fusion()->user->get_tag_label( $setting[0] ) . '</strong>', array( 'source' => 'restrict-content-pro' ) );

					$member = rcpga_get_group_member( $user_id, $result->rcp_group_id );

					remove_action( 'rcpga_remove_member', array( $this, 'remove_member_from_group' ), 10, 2 );

					$member->remove();

					add_action( 'rcpga_remove_member', array( $this, 'remove_member_from_group' ), 10, 2 );

				}
			}
		}

	}

	/**
	 * Add settings to group
	 *
	 * @access  public
	 * @return  mixed HTML output
	 */

	public function edit_group_fields( $group = false ) {

		?>

		<tr>
			<th scope="row" class="row-title">
				<label for="wpf-settings-apply_tags"><?php _e( 'Apply tags', 'wp-fusion' ); ?>:</label>
			</th>
			<td>

				<?php

				if ( $group ) {
					$setting = get_metadata( 'rcp_group', $group->get_group_id(), 'apply_tags', true );
				} else {
					$setting = array();
				}

				$args = array(
					'setting'   => $setting,
					'meta_name' => 'wpf-settings',
					'field_id'  => 'apply_tags',
				);

				wpf_render_tag_multiselect( $args );

				?>

				<p class="description"><?php echo sprintf( __( 'These tags will be applied in %s when someone is enrolled in this group.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></p>

			</td>
		</tr>

		<tr>
			<th scope="row" class="row-title">
				<label for="wpf-settings-tag_link"><?php _e( 'Link with Tag', 'wp-fusion' ); ?>:</label>
			</th>
			<td>

				<?php

				if ( $group ) {
					$setting = get_metadata( 'rcp_group', $group->get_group_id(), 'tag_link', true );
				} else {
					$setting = array();
				}

				$args = array(
					'setting'     => $setting,
					'meta_name'   => 'wpf-settings',
					'field_id'    => 'tag_link',
					'limit'       => 1,
					'placeholder' => __( 'Select tag', 'wp-fusion' ),
				);

				wpf_render_tag_multiselect( $args );

				?>

				<p class="description"><?php echo sprintf( __( 'When a user is enrolled in this group, the tag will be applied. When a user is un-enrolled, the tag will be removed.<br />Likewise, if this tag is applied in %s, the user will be automatically enrolled.', 'wp-fusion' ), wp_fusion()->crm->name ); ?></p>

			</td>
		</tr>


		<?php

	}

	/**
	 * Save group settings
	 *
	 * @access  public
	 * @return  void
	 */

	public function save_group_fields( $action, $message ) {

		if ( 'edit-group' == $action && ! empty( $_POST['wpf-settings'] ) ) {

			$fields = array( 'apply_tags', 'tag_link' );

			foreach ( $fields as $field ) {

				if ( ! empty( $_POST['wpf-settings'][ $field ] ) ) {

					update_metadata( 'rcp_group', $_POST['rcpga-group'], $field, $_POST['wpf-settings'][ $field ] );

				} else {

					delete_metadata( 'rcp_group', $_POST['rcpga-group'], $field );

				}
			}
		}

	}

	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds PMPro checkbox to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['rcp'] = array(
			'label'   => __( 'Restrict Content Pro memberships', 'wp-fusion' ),
			'title'   => __( 'Members', 'wp-fusion' ),
			'tooltip' => sprintf( __( 'Updates tags for all members based on their current membership level and pushes Restrict Content Pro membership fields (level, status, expiration) to %s.', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		if ( function_exists( 'rcpga_get_group_members' ) ) {

			$options['rcp_groups'] = array(
				'label'   => __( 'Restrict Content Pro group memberships', 'wp-fusion' ),
				'title'   => __( 'Groups', 'wp-fusion' ),
				'tooltip' => __( 'Updates tags for all group members based on their current group enrollments.', 'wp-fusion' ),
			);

		}

		return $options;

	}

	/**
	 * Counts total number of members to be processed
	 *
	 * @access public
	 * @return array Members
	 */

	public function batch_init() {

		$user_ids = array();

		$members = rcp_get_customers( array( 'number' => 99999 ) );

		if ( ! empty( $members ) ) {

			foreach ( $members as $member ) {
				$user_ids[] = $member->get_id();
			}
		}

		return $user_ids;

	}

	/**
	 * Processes member actions in batches
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step( $user_id ) {

		$member = new RCP_Member( $user_id );

		$this->status_changed( $member->get_status(), $user_id, false, $member );

	}

	/**
	 * Counts total number of members to be processed
	 *
	 * @access public
	 * @return array Members
	 */

	public function batch_init_groups() {

		$group_member_ids = array();

		$args = array(
			'limit' => -1,
		);

		$members = rcpga_get_group_members( $args );

		if ( ! empty( $members ) ) {

			foreach ( $members as $member ) {
				$group_member_ids[] = $member->get_ID();
			}
		}

		return $group_member_ids;

	}

	/**
	 * Processes member actions in batches
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step_groups( $group_member_id ) {

		$member = rcpga_get_group_member_by_id( $group_member_id );

		$this->add_member_to_group( $member->get_user_id(), array(), $member->get_group_id() );

	}

}

new WPF_Restrict_Content_Pro();
