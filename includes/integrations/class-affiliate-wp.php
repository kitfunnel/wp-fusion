<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WPF_AffiliateWP extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'affiliate-wp';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Affiliate WP';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/affiliates/affiliate-wp/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		// Settings fields
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		add_action( 'affwp_edit_affiliate_end', array( $this, 'edit_affiliate' ) );
		add_action( 'affwp_pre_update_affiliate', array( $this, 'save_edit_affiliate' ), 10, 3 );

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 20 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		add_action( 'affwp_insert_affiliate', array( $this, 'add_affiliate' ), 15 );
		add_action( 'affwp_update_affiliate', array( $this, 'update_affiliate' ), 5 );
		add_action( 'affwp_set_affiliate_status', array( $this, 'affiliate_approved' ), 10, 3 );
		add_action( 'affwp_set_affiliate_status', array( $this, 'affiliate_rejected' ), 10, 3 );

		// Accepted referrals
		add_action( 'affwp_referral_accepted', array( $this, 'referral_accepted' ), 10, 2 );
		add_action( 'wpf_fluent_forms_post_submission', array( $this, 'maybe_handle_fluent_forms_referral' ), 10, 5 );

		add_filter( 'wpf_get_user_meta', array( $this, 'get_user_meta' ), 10, 2 );
		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );

		// Batch operations
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_affiliatewp_init', array( $this, 'batch_init_affiliates' ) );
		add_action( 'wpf_batch_affiliatewp', array( $this, 'batch_step_affiliates' ) );

	}


	/**
	 * Registers additional AWP settings
	 *
	 * @access  public
	 * @return  array Settings
	 */

	public function register_settings( $settings, $options ) {

		$settings['awp_header'] = array(
			'title'   => __( 'AffiliateWP Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['awp_apply_tags'] = array(
			'title'   => __( 'Apply Tags - Affiliate Registration', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags to new affiliates registered through AffiliateWP.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['awp_apply_tags_approved'] = array(
			'title'   => __( 'Apply Tags - Affilate Approval', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags when affiliates are approved.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['awp_apply_tags_rejected'] = array(
			'title'   => __( 'Apply Tags - Affilate Rejected', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags when affiliates are rejected.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['awp_apply_tags_first_referral'] = array(
			'title'   => __( 'Apply Tags - First Referral', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags when affiliates get their first referral.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		if ( property_exists( wp_fusion()->integrations, 'woocommerce' ) ) {

			$settings['awp_apply_tags_customers'] = array(
				'title'   => __( 'Apply Tags - Customers', 'wp-fusion' ),
				'desc'    => __( 'Apply these tags to new WooCommerce customers who signed up via an affiliate link.', 'wp-fusion' ),
				'std'     => array(),
				'type'    => 'assign_tags',
				'section' => 'integrations',
			);

		}

		return $settings;

	}

	/**
	 * Settings on Edit Affiliate screen
	 *
	 * @access public
	 * @return mixed Affiliate Settings
	 */

	public function edit_affiliate( $affiliate ) {

		if ( ! property_exists( wp_fusion()->integrations, 'woocommerce' ) ) {
			return;
		}

		?>

		<tr class="form-row">

			<th scope="row">
				<label for="notes"><?php _e( 'Apply Tags', 'wp-fusion' ); ?></label>
			</th>

			<td>

				<?php

				$setting = affwp_get_affiliate_meta( $affiliate->affiliate_id, 'apply_tags_customers', true );

				if ( empty( $setting ) ) {
					$setting = array();
				}

				$args = array(
					'setting'   => $setting,
					'meta_name' => 'apply_tags_customers',
				);

				wpf_render_tag_multiselect( $args );

				?>


				<p class="description"><?php _e( 'These tags will be applied to any WooCommerce customers who purchase using this affiliate\'s referral URL.', 'wp-fusion' ); ?></p>
			</td>

		</tr>

		<?php

	}


	/**
	 * Save Edit Affiliate screen
	 *
	 * @access public
	 * @return void
	 */

	public function save_edit_affiliate( $affiliate, $args, $data ) {

		if ( ! empty( $data['apply_tags_customers'] ) ) {

			affwp_update_affiliate_meta( $affiliate->affiliate_id, 'apply_tags_customers', $data['apply_tags_customers'] );

		} else {

			affwp_delete_affiliate_meta( $affiliate->affiliate_id, 'apply_tags_customers' );

		}

	}

	/**
	 * Adds AWP field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['awp']          = array(
			'title'  => 'Affiliate WP - Affiliate',
			'fields' => array(),
		);
		$field_groups['awp_referrer'] = array(
			'title'  => 'Affiliate WP - Referrer',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Adds AWP meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields = array() ) {

		// Affiliate
		$meta_fields['awp_affiliate_id'] = array(
			'label'  => 'Affiliate\'s Affiliate ID',
			'type'   => 'text',
			'group'  => 'awp',
			'pseudo' => true,
		);

		$meta_fields['awp_referral_rate'] = array(
			'label'  => 'Affiliate\'s Referral Rate',
			'type'   => 'text',
			'group'  => 'awp',
			'pseudo' => true,
		);

		$meta_fields['awp_payment_email'] = array(
			'label'  => 'Affiliate\'s Payment Email',
			'type'   => 'text',
			'group'  => 'awp',
			'pseudo' => true,
		);

		$meta_fields['affwp_user_url'] = array(
			'label'  => 'Affiliate\'s Website URL',
			'type'   => 'text',
			'group'  => 'awp',
			'pseudo' => true,
		);

		$meta_fields['affwp_promotion_method'] = array(
			'label'  => 'Affiliate\'s Promotion Method',
			'type'   => 'text',
			'group'  => 'awp',
			'pseudo' => true,
		);

		$meta_fields['affwp_total_earnings'] = array(
			'label'  => 'Affiliate\'s Total Earnings',
			'type'   => 'int',
			'group'  => 'awp',
			'pseudo' => true,
		);

		$meta_fields['affwp_referral_count'] = array(
			'label'  => 'Affiliate\'s Total Referrals',
			'type'   => 'int',
			'group'  => 'awp',
			'pseudo' => true,
		);

		// Groups
		if ( class_exists( 'AffiliateWP_Affiliate_Groups' ) ) {

			$meta_fields['affiliate_groups'] = array(
				'label'  => 'Affiliate\'s Groups',
				'type'   => 'multiselect',
				'group'  => 'awp',
				'pseudo' => true,
			);

		}

		// Custom slugs
		if ( class_exists( 'AffiliateWP_Custom_Affiliate_Slugs' ) ) {

			$meta_fields['custom_slug'] = array(
				'label'  => 'Affiliate\'s Custom Slug',
				'type'   => 'text',
				'group'  => 'awp',
				'pseudo' => true,
			);

		}

		// Landing pages

		if ( class_exists( 'AffiliateWP_Affiliate_Landing_Pages' ) ) {

			$meta_fields['affwp_landing_page'] = array(
				'label'  => 'Affiliate\'s Landing Page',
				'type'   => 'text',
				'group'  => 'awp',
				'pseudo' => true,
			);

		}

		// Referrer
		$meta_fields['awp_referrer_id'] = array(
			'label'  => 'Referrer\'s Affiliate ID',
			'type'   => 'text',
			'group'  => 'awp_referrer',
			'pseudo' => true,
		);

		$meta_fields['awp_referrer_first_name'] = array(
			'label'  => 'Referrer\'s First Name',
			'type'   => 'text',
			'group'  => 'awp_referrer',
			'pseudo' => true,
		);

		$meta_fields['awp_referrer_last_name'] = array(
			'label'  => 'Referrer\'s Last Name',
			'type'   => 'text',
			'group'  => 'awp_referrer',
			'pseudo' => true,
		);

		$meta_fields['awp_referrer_email'] = array(
			'label'  => 'Referrer\'s Email',
			'type'   => 'text',
			'group'  => 'awp_referrer',
			'pseudo' => true,
		);

		$meta_fields['awp_referrer_username'] = array(
			'label'  => 'Referrer\'s Username',
			'type'   => 'text',
			'group'  => 'awp_referrer',
			'pseudo' => true,
		);

		$meta_fields['awp_referrer_url'] = array(
			'label'  => 'Referrer\'s Website URL',
			'type'   => 'text',
			'group'  => 'awp_referrer',
			'pseudo' => true,
		);

		return $meta_fields;

	}

	/**
	 * Gets all the relevant metdata for an affiliate
	 *
	 * @since 3.35.14
	 *
	 * @param int $affiliate_id The ID of the affiliate to get the data for
	 * @return array User Meta
	 */

	public function get_affiliate_meta( $affiliate_id ) {

		// Default fields
		$affiliate = affwp_get_affiliate( $affiliate_id );

		$rate = isset( $affiliate->rate ) ? $affiliate->rate : null;

		if ( empty( $rate ) ) {
			$rate = affiliate_wp()->settings->get( 'referral_rate', 20 );
		}

		$user = get_userdata( $affiliate->user_id );

		$affiliate_data = array(
			'awp_affiliate_id'  => $affiliate_id,
			'awp_referral_rate' => $rate,
			'awp_payment_email' => $affiliate->payment_email,
			'first_name'        => $user->first_name,
			'last_name'         => $user->last_name,
		);

		// Custom meta
		$data = affwp_get_affiliate_meta( $affiliate_id );

		if ( ! empty( $data ) ) {

			foreach ( $data as $key => $value ) {
				$affiliate_data[ $key ] = maybe_unserialize( $value[0] );
			}
		}

		// These fields require queries so let's only get that data if they're enabled for sync
		if ( wpf_is_field_active( 'affwp_referral_count' ) ) {
			$affiliate_data['affwp_referral_count'] = affwp_count_referrals( $affiliate_id, array( 'paid', 'unpaid' ) );
		}

		if ( wpf_is_field_active( 'affwp_total_earnings' ) ) {
			$affiliate_data['affwp_total_earnings'] = affiliate_wp()->referrals->get_earnings_by_status( array( 'paid', 'unpaid' ), $affiliate_id );
		}

		if ( function_exists( 'affwp_alp_get_landing_page_ids' ) ) {

			$page_ids = affwp_alp_get_landing_page_ids( $user->user_login );

			if ( ! empty( $page_ids ) ) {
				$affiliate_data['affwp_landing_page'] = get_the_permalink( $page_ids[0] );
			}
		}

		return $affiliate_data;

	}


	/**
	 * Gets any custom fields out of the wp_affiliatemeta table
	 *
	 * @since 3.35.14
	 *
	 * @param array $user_meta The user's metadata, in key => value pairs
	 * @param int   $user_id   The ID of the user to get the metadata for
	 * @return array User Meta
	 */

	public function get_user_meta( $user_meta, $user_id ) {

		$affiliate_id = affwp_get_affiliate_id( $user_id );

		if ( $affiliate_id ) {

			$affiliate_data = $this->get_affiliate_meta( $affiliate_id );
			$user_meta      = array_merge( $user_meta, $affiliate_data );

		}

		return $user_meta;

	}


	/**
	 * Triggered when new user registered through AWP
	 *
	 * @access  public
	 * @return  array Post Data
	 */

	public function user_register( $post_data, $user_id ) {

		$field_map = array(
			'affwp_user_name'     => 'display_name',
			'affwp_user_login'    => 'user_login',
			'affwp_user_email'    => 'user_email',
			'affwp_payment_email' => 'awp_payment_email',
			'affwp_user_url'      => 'user_url',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		return $post_data;

	}

	/**
	 * Triggered when affiliate updated
	 *
	 * @access  public
	 * @return  void
	 */

	public function update_affiliate( $data ) {

		$affiliate = affwp_get_affiliate( $data['affiliate_id'] );

		if ( empty( $data['rate'] ) ) {
			$data['rate'] = affiliate_wp()->settings->get( 'referral_rate', 20 );
		}

		$affiliate_data = array(
			'awp_affiliate_id'  => $data['affiliate_id'],
			'awp_referral_rate' => $data['rate'],
			'awp_payment_email' => $data['payment_email'],
		);

		// Groups.
		foreach ( $data as $key => $value ) {

			if ( 0 === strpos( $key, 'affiliate_group_' ) ) {

				if ( ! isset( $affiliate_data['affiliate_groups'] ) ) {
					$affiliate_data['affiliate_groups'] = array();
				}

				if ( ! in_array( $key, $affiliate_data['affiliate_groups'] ) ) {
					$affiliate_data['affiliate_groups'][] = $key;
				}
			}
		}

		wp_fusion()->user->push_user_meta( $affiliate->user_id, $affiliate_data );

	}

	/**
	 * Triggered when a referral is accepted
	 *
	 * @access  public
	 * @return  void
	 */

	public function referral_accepted( $affiliate_id, $referral ) {

		// Update data
		$aff_user_id = affwp_get_affiliate_user_id( $affiliate_id );

		$aff_user = get_user_by( 'id', $aff_user_id );

		$referrer_data = array(
			'awp_referrer_id'         => $affiliate_id,
			'awp_referrer_first_name' => $aff_user->first_name,
			'awp_referrer_last_name'  => $aff_user->last_name,
			'awp_referrer_email'      => $aff_user->user_email,
			'awp_referrer_url'        => $aff_user->user_url,
			'awp_referrer_username'   => $aff_user->user_login,
		);

		// Handle different referral contexts
		if ( 'woocommerce' === $referral->context ) {

			// Get the customer's ID
			$order = wc_get_order( $referral->reference );

			if ( false == $order ) {
				return;
			}

			$user_id    = $order->get_user_id();
			$contact_id = get_post_meta( $order->get_id(), WPF_CONTACT_ID_META_KEY, true );

			// Get any tags to apply
			$apply_tags = wpf_get_option( 'awp_apply_tags_customers' );

			if ( empty( $apply_tags ) ) {
				$apply_tags = array();
			}

			$setting = affwp_get_affiliate_meta( $affiliate_id, 'apply_tags_customers', true );

			if ( empty( $setting ) ) {
				$setting = array();
			}

			$apply_tags = array_merge( $apply_tags, $setting );

		} elseif ( 'gravityforms' === $referral->context ) {

			// The referral is awarded before WPF processes the feed, so we'll register the filter here to merge the data, using a closure.
			add_filter(
				'wpf_gform_pre_submission',
				function( $update_data, $user_id, $contact_id, $form_id ) use ( &$referrer_data ) {

					$referrer_data = wp_fusion()->crm->map_meta_fields( $referrer_data );

					$update_data = array_merge( $update_data, $referrer_data );

					return $update_data;

				},
				10,
				4
			);

		} elseif ( 'ultimate_member_signup' === $referral->context ) {

			// Get user ID from UM signup
			$user_id = $referral->reference;

		}elseif ( 'fluentforms' === $referral->context ) {

			if ( empty( $referral->contact_id ) ) {
				return; // WPF hasn't finished processing it yet.
			}

			$user_id    = $referral->user_id;
			$contact_id = $referral->contact_id;

		} else {

			wpf_log( 'info', wpf_get_current_user_id(), 'AffiliateWP referral detected but unable to sync referrer data since referral context <code>' . $referral->context . '</code> is not currently supported.' );

		}
		
		// If we've found a user or contact for the referral, update their record and apply tags
		if ( ! empty( $user_id ) ) {

			wp_fusion()->user->push_user_meta( $user_id, $referrer_data );

			if ( ! empty( $apply_tags ) ) {
				wp_fusion()->user->apply_tags( $apply_tags, $user_id );
			}
		} elseif ( ! empty( $contact_id ) ) {

			wpf_log( 'info', wpf_get_current_user_id(), 'Syncing AffiliateWP referrer meta:', array( 'meta_array' => $referrer_data ) );
			
			wp_fusion()->crm->update_contact( $contact_id, $referrer_data );

			if ( ! empty( $apply_tags ) ) {
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );
			}
		}

		// Maybe sync data to the affiliate.
		$affiliate_data = array();

		// These fields require queries so let's only get that data if they're enabled for sync
		if ( wpf_is_field_active( 'affwp_referral_count' ) ) {
			$affiliate_data['affwp_referral_count'] = affwp_count_referrals( $affiliate_id );
		}

		if ( wpf_is_field_active( 'affwp_total_earnings' ) ) {
			$affiliate_data['affwp_total_earnings'] = affiliate_wp()->referrals->get_earnings_by_status( array( 'paid', 'unpaid' ), $affiliate_id );
		}

		if ( ! empty( $affiliate_data ) ) {

			$user_id = affwp_get_affiliate_user_id( $affiliate_id );

			wp_fusion()->user->push_user_meta( $user_id, $affiliate_data );

		}

		// Maybe apply first referral tags to the affiliate
		$apply_tags = wpf_get_option( 'awp_apply_tags_first_referral', array() );

		if ( ! empty( $apply_tags ) ) {

			$referral_count = affiliate_wp()->referrals->unpaid_count( '', $affiliate_id );

			if ( $referral_count == 1 ) {

				$user_id = affwp_get_affiliate_user_id( $affiliate_id );

				wp_fusion()->user->apply_tags( $apply_tags, $user_id );

			}
		}

	}

	/**
	 * Handle a Fluent Forms referral.
	 *
	 * @since 3.40.37
	 *
	 * @param array           $update_data The data that was synced to the CRM.
	 * @param int|false       $user_id     The user ID, or false.
	 * @param string|WP_Error $contact_id  The contact ID in the CRM, or WP_Error.
	 * @param int             $form_id     The form ID.
	 * @param int|false       $entry_id    The entry ID, or false if unknown.
	 */
	public function maybe_handle_fluent_forms_referral( $update_data, $user_id, $contact_id, $form_id, $entry_id ) {

		$referral = affiliate_wp()->referrals->get_by( 'reference', $entry_id );

		if ( $referral ) {

			// Set the contact ID so it can be used in the next step.

			$referral->contact_id = $contact_id;
			$referral->user_id    = $user_id;

			// Sync the referrer fields.
			$this->referral_accepted( $referral->affiliate_id, $referral );

		}
	}

	/**
	 * Apply tags when affiliate is rejected.
	 *
	 * @param integer $affiliate_id
	 * @param string  $status
	 * @param string  $old_status
	 * @return void
	 */
	public function affiliate_rejected( $affiliate_id = 0, $status = '', $old_status = '' ) {

		if ( empty( $affiliate_id ) || 'rejected' !== $status ) {
			return;
		}

		$user_id = affwp_get_affiliate_user_id( $affiliate_id );

		$apply_tags = wpf_get_option( 'awp_apply_tags_rejected' );

		if ( ! empty( $apply_tags ) ) {

			wp_fusion()->user->apply_tags( $apply_tags, $user_id );

		}
	}


	/**
	 * Apply tags when affiliate approved
	 *
	 * @access  public
	 * @return  void
	 */

	public function affiliate_approved( $affiliate_id = 0, $status = '', $old_status = '' ) {

		if ( empty( $affiliate_id ) || 'active' !== $status ) {
			return;
		}

		/*
		 * Skip applying the tags for a now-'active' affiliate under
		 * certain conditions:
		 *
		 * 1. The affiliate was previously of 'inactive' or 'rejected' status.
		 * 2. The affiliate was previously of 'pending' status, where the status
		 *    transition wasn't triggered by a registration.
		 * 3. The affiliate's 'active' status didn't change, and the status
		 *    "transition" wasn't triggered by a registration, i.e. the affiliate
		 *    was updated in a bulk action and the 'active' status didn't change.
		 */
		if ( ! in_array( $old_status, array( 'active', 'pending' ), true ) && ! did_action( 'affwp_affiliate_register' ) ) {
			return;
		}

		$user_id    = affwp_get_affiliate_user_id( $affiliate_id );
		$apply_tags = wpf_get_option( 'awp_apply_tags_approved' );

		if ( ! empty( $apply_tags ) ) {

			wp_fusion()->user->apply_tags( $apply_tags, $user_id );

		}
	}

	/**
	 * Triggered when affiliate added
	 *
	 * @access  public
	 * @return  void
	 */

	public function add_affiliate( $affiliate_id ) {

		$affiliate = affwp_get_affiliate( $affiliate_id );

		$affiliate_data = $this->get_affiliate_meta( $affiliate_id );

		if ( ! wp_fusion()->user->has_contact_id( $affiliate->user_id ) ) {

			// This is necessary so the data gets sent when Auto Register Affiliates is enabled
			wp_fusion()->user->user_register( $affiliate->user_id, $affiliate_data );

			remove_action( 'user_register', array( wp_fusion()->user, 'user_register' ), 20 );

		} else {

			wp_fusion()->user->push_user_meta( $affiliate->user_id, $affiliate_data );

		}

		$apply_tags = wpf_get_option( 'awp_apply_tags', array() );

		// If the affiliates are approved, make sure they have these tags as well
		if ( 'active' == $affiliate->status ) {
			$approved_tags = wpf_get_option( 'awp_apply_tags_approved', array() );
			$apply_tags    = array_merge( $apply_tags, $approved_tags );
		}

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $affiliate->user_id );
		}

	}


	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds AffiliateWP to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['affiliatewp'] = array(
			'label'   => __( 'AffiliateWP affiliate data', 'wp-fusion' ),
			'title'   => 'affiliates',
			'tooltip' => sprintf( __( 'Syncs any enabled \'AffiliateWP - Affiliate\' fields to %s, and applies any configured affiliate tags.', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		return $options;

	}

	/**
	 * Get all affiliates to be processed
	 *
	 * @access public
	 * @return array Members
	 */

	public function batch_init_affiliates() {

		$args = array(
			'number' => -1,
			'fields' => 'ids',
		);

		$affiliates = affiliate_wp()->affiliates->get_affiliates( $args );

		return $affiliates;

	}

	/**
	 * Processes affiliate actions in batches
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step_affiliates( $affiliate_id ) {

		$this->add_affiliate( $affiliate_id );

	}


}

new WPF_AffiliateWP();
