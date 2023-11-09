<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * SliceWP integration.
 *
 * @since 3.38.43
 *
 * @link https://wpfusion.com/documentation/affiliates/slicewp/
 */
class WPF_SliceWP extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.43
	 * @var string $slug
	 */

	public $slug = 'slice-wp';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.43
	 * @var string $name
	 */
	public $name = 'SliceWP';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.43
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/affiliates/slicewp/';

	/**
	 * Gets things started
	 *
	 * @since   3.38.43
	 * @return  void
	 */
	public function init() {

		// Settings fields.
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 20 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		// Merge affiliate meta with user meta.
		add_filter( 'wpf_get_user_meta', array( $this, 'get_user_meta' ), 10, 2 );

		// Affiliate registration from dashboard and frontend.
		add_action( 'slicewp_insert_affiliate', array( $this, 'affiliate_registration' ), 10, 2 );

		// Status change for affiliates.
		add_action( 'slicewp_update_affiliate', array( $this, 'affiliate_status_change' ), 10, 2 );

		// Affiliate update.
		add_action( 'slicewp_update_affiliate', array( $this, 'update_affiliate' ), 10, 2 );

		// Accepted referrals.
		add_action( 'slicewp_update_commission', array( $this, 'referral_accepted' ), 10, 2 );
		add_action( 'slicewp_insert_commission', array( $this, 'referral_accepted' ), 10, 2 );

		// Woocommerce field.
		add_action( 'slicewp_view_affiliates_add_affiliate_bottom', array( $this, 'add_affiliate_woo_field' ) );
		add_action( 'slicewp_view_affiliates_edit_affiliate_bottom', array( $this, 'add_affiliate_woo_field' ) );
		add_action( 'slicewp_insert_affiliate', array( $this, 'save_affiliate_woo_field' ) );
		add_action( 'slicewp_update_affiliate', array( $this, 'save_affiliate_woo_field' ) );

		// Batch operations.
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_slicewp_affiliate_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_slicewp_affiliate', array( $this, 'batch_step' ) );

	}


	/**
	 * Triggered when an affiliate instance has been updated.
	 *
	 * @since 3.38.43
	 *
	 * @param int    $affiliate_id Affiliate ID.
	 * @param object $data    Updated data.
	 */
	public function affiliate_status_change( $affiliate_id, $data ) {

		$affiliate = slicewp_get_affiliate( $affiliate_id );
		$user_id   = intval( $affiliate->get( 'user_id' ) );
		if ( strtolower( $data['status'] ) === 'active' ) {
			$this->affiliate_approved( $user_id );
		}

		if ( strtolower( $data['status'] ) === 'rejected' ) {
			$this->affiliate_rejected( $user_id );
		}

	}


	/**
	 * Triggered when affiliate has registered.
	 *
	 * @since  3.38.43
	 * @param int    $affiliate_id Affiliate ID.
	 * @param object $data    Updated data.
	 */
	public function affiliate_registration( $affiliate_id, $data ) {
		$this->add_affiliate( $affiliate_id );
	}


	/**
	 * Add an affiliate to the CRM.
	 *
	 * @since  3.38.43
	 * @param int $affiliate_id affiliate id.
	 */
	public function add_affiliate( $affiliate_id ) {
		$affiliate_data = $this->get_affiliate_meta( $affiliate_id );

		$exist = wp_fusion()->user->push_user_meta( $affiliate_data['user_id'], $affiliate_data );
		if ( ! $exist ) {
			// If a user is added through the dashboard and not already in CRM.
			wp_fusion()->crm->add_contact( $affiliate_data );
		}

		$apply_tags = wpf_get_option( 'slicewp_apply_tags', array() );

		// If the affiliates are approved, make sure they have these tags as well.
		if ( 'active' === $affiliate_data['slicewp_affiliate_status'] ) {
			$approved_tags = wpf_get_option( 'slicewp_apply_tags_approved', array() );
			$apply_tags    = array_merge( $apply_tags, $approved_tags );
		}

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $affiliate_data['user_id'] );
		}

	}

	/**
	 * Registers additional SliceWP settings.
	 *
	 * @since  3.38.43
	 *
	 * @param  array $settings The settings.
	 * @param  array $options  The options in the database.
	 * @return array Settings
	 */
	public function register_settings( $settings, $options ) {

		$settings['slicewp_header'] = array(
			'title'   => __( 'SliceWP Integration', 'wp-fusion' ),
			'url'     => 'https://wpfusion.com/documentation/affiliates/slicewp/',
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['slicewp_apply_tags'] = array(
			'title'   => __( 'Apply Tags - Registration', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags to new affiliates registered through SliceWP.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['slicewp_apply_tags_approved'] = array(
			'title'   => __( 'Apply Tags - Approved', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags when affiliates are approved.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['slicewp_apply_tags_rejected'] = array(
			'title'   => __( 'Apply Tags - Rejected', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags when affiliates are rejected.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['slicewp_apply_tags_first_referral'] = array(
			'title'   => __( 'Apply Tags - First Referral', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags when affiliates get their first referral.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		if ( property_exists( wp_fusion()->integrations, 'woocommerce' ) ) {

			$settings['slicewp_apply_tags_customers'] = array(
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
	 * Adds SliceWP field group to meta fields list
	 *
	 * @since  3.38.43
	 * @param array $field_groups wpf field groups.
	 * @return  array Field groups
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['slicewp_aff'] = array(
			'title'  => 'SliceWP - Affiliate',
			'fields' => array(),
		);

		$field_groups['slicewp_referrer'] = array(
			'title'  => 'SliceWP - Referrer',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Adds SliceWP meta fields to WPF contact fields list
	 *
	 * @since  3.38.43
	 * @param array $meta_fields wpf meta fields.
	 * @return  array Meta Fields
	 */
	public function prepare_meta_fields( $meta_fields = array() ) {

		// Affiliate.
		$meta_fields['slicewp_affiliate_id'] = array(
			'label'  => 'Affiliate ID',
			'type'   => 'int',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_affiliate_commission_type'] = array(
			'label'  => 'Affiliate Commission Type',
			'type'   => 'text',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_affiliate_commission_rate'] = array(
			'label'  => 'Affiliate Commission Rate',
			'type'   => 'text',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_affiliate_commission_type_sub'] = array(
			'label'  => 'Affiliate Commission Subscription Type',
			'type'   => 'text',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_affiliate_commission_rate_sub'] = array(
			'label'  => 'Affiliate Commission Subscription Rate',
			'type'   => 'text',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_affiliate_payment_email'] = array(
			'label'  => 'Affiliate Payment Email',
			'type'   => 'email',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_affiliate_registration_date'] = array(
			'label'  => 'Affiliate Registration Date',
			'type'   => 'date',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_affiliate_website'] = array(
			'label'  => 'Affiliate Website',
			'type'   => 'text',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_affiliate_registration_notes'] = array(
			'label'  => 'Affiliate Registration Notes',
			'type'   => 'text',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_affiliate_status'] = array(
			'label'  => 'Affiliate Status',
			'type'   => 'text',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_total_earnings'] = array(
			'label'  => 'Affiliate\'s Total Earnings',
			'type'   => 'int',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		$meta_fields['slicewp_referral_count'] = array(
			'label'  => 'Affiliate\'s Total Referrals',
			'type'   => 'int',
			'group'  => 'slicewp_aff',
			'pseudo' => true,
		);

		// Commission.
		$meta_fields['slicewp_referrer_id'] = array(
			'label'  => 'Referrer\'s Affiliate ID',
			'type'   => 'text',
			'group'  => 'slicewp_referrer',
			'pseudo' => true,
		);

		$meta_fields['slicewp_referrer_first_name'] = array(
			'label'  => 'Referrer\'s First Name',
			'type'   => 'text',
			'group'  => 'slicewp_referrer',
			'pseudo' => true,
		);

		$meta_fields['slicewp_referrer_last_name'] = array(
			'label'  => 'Referrer\'s Last Name',
			'type'   => 'text',
			'group'  => 'slicewp_referrer',
			'pseudo' => true,
		);

		$meta_fields['slicewp_referrer_email'] = array(
			'label'  => 'Referrer\'s Email',
			'type'   => 'text',
			'group'  => 'slicewp_referrer',
			'pseudo' => true,
		);

		$meta_fields['slicewp_referrer_url'] = array(
			'label'  => 'Referrer\'s URL',
			'type'   => 'text',
			'group'  => 'slicewp_referrer',
			'pseudo' => true,
		);

		$meta_fields['slicewp_referrer_username'] = array(
			'label'  => 'Referrer\'s Username',
			'type'   => 'text',
			'group'  => 'slicewp_referrer',
			'pseudo' => true,
		);

		return $meta_fields;

	}

	/**
	 * Merges affiliate meta into user meta when exporting user data.
	 *
	 * @since  3.38.46
	 *
	 * @param  array  $user_meta The user meta.
	 * @param  int    $user_id   The user ID.
	 * @return array  The user meta.
	 */
	public function get_user_meta( $user_meta, $user_id ) {

		$affiliate = slicewp_get_affiliate_by_user_id( $user_id );

		if ( null !== $affiliate ) {
			$user_meta = array_merge( $user_meta, $this->get_affiliate_meta( $affiliate->get( 'id' ) ) );
		}

		return $user_meta;

	}

	/**
	 * Gets all the relevant metdata for an affiliate
	 *
	 * @since 3.38.43
	 * @param int $affiliate_id The ID of the affiliate to get the data for.
	 * @return array User Meta
	 */
	public function get_affiliate_meta( $affiliate_id ) {

		$affiliate      = slicewp_get_affiliate( $affiliate_id );
		$affiliate_meta = slicewp_get_affiliate_meta( $affiliate_id );
		$settings       = get_option( 'slicewp_settings' );

		// Get user data.
		$user_id = intval( $affiliate->get( 'user_id' ) );
		$user    = get_user_by( 'id', $user_id );

		$affiliate_data = array(
			'slicewp_affiliate_commission_rate'     => $settings['commission_rate_sale'],
			'slicewp_affiliate_commission_type'     => $settings['commission_rate_type_sale'],
			'slicewp_affiliate_commission_rate_sub' => $settings['commission_rate_subscription'],
			'slicewp_affiliate_commission_type_sub' => $settings['commission_rate_type_subscription'],
			'slicewp_affiliate_registration_notes'  => ! empty( $affiliate_meta['promotional_methods'] ) ? $affiliate_meta['promotional_methods'][0] : false,
			'slicewp_affiliate_status'              => $affiliate->get( 'status' ),
			'slicewp_affiliate_id'                  => $affiliate_id,
			'slicewp_affiliate_payment_email'       => $affiliate->get( 'payment_email' ),
			'slicewp_affiliate_website'             => $affiliate->get( 'website' ),
			'first_name'                            => $user->first_name,
			'last_name'                             => $user->last_name,
			'user_email'                            => $user->user_email,
			'user_id'                               => $user_id,
		);

		// Custom meta.

		if ( ! empty( $affiliate_meta ) ) {

			foreach ( $affiliate_meta as $key => $value ) {
				$affiliate_data[ $key ] = maybe_unserialize( $value[0] );
			}
		}

		// These fields require queries so let's only get that data if they're enabled for sync.
		if ( wpf_is_field_active( 'slicewp_referral_count' ) ) {
			$affiliate_data['slicewp_referral_count'] = slicewp_get_commissions(
				array(
					'number'       => -1,
					'affiliate_id' => $affiliate_id,
					'status'       => array( 'unpaid', 'paid', 'pending' ),
				),
				true
			);
		}

		if ( wpf_is_field_active( 'slicewp_total_earnings' ) ) {

			$args = array(
				'number'       => -1,
				'affiliate_id' => $affiliate_id,
				'status'       => array( 'unpaid', 'paid' ),
			);

			$commissions = slicewp_get_commissions( $args );

			$affiliate_data['slicewp_total_earnings'] = 0;

			foreach( $commissions as $commission ) {

				$affiliate_data['slicewp_total_earnings'] += (float)$commission->get('amount');

			}

		}

		return $affiliate_data;

	}


	/**
	 * Triggered when affiliate updated
	 *
	 * @since  3.38.43
	 * @param int    $affiliate_id Affiliate ID.
	 * @param object $data    Updated data.
	 */
	public function update_affiliate( $affiliate_id, $data ) {
		$affiliate_data = $this->get_affiliate_meta( $affiliate_id );
		wp_fusion()->user->push_user_meta( $affiliate_data['user_id'], $affiliate_data );
	}


	/**
	 * Triggered when a referral is accepted
	 *
	 * @since  3.38.43
	 * @param int    $commission_id Commission ID.
	 * @param object $data    Updated data.
	 */
	public function referral_accepted( $commission_id, $data ) {

		// Record it when only created or changed to paid.
		if ( ! in_array( $data['status'], array( 'paid', 'unpaid' ) ) ) {
			return;
		}

		$commission    = slicewp_get_commission( $commission_id );
		$affiliate_id  = $commission->get( 'affiliate_id' );
		$affiliate     = slicewp_get_affiliate( $affiliate_id );
		$aff_user      = get_user_by( 'id', $affiliate->get( 'user_id' ) );
		$referrer_data = array(
			'slicewp_referrer_id'         => $affiliate_id,
			'slicewp_referrer_first_name' => $aff_user->first_name,
			'slicewp_referrer_last_name'  => $aff_user->last_name,
			'slicewp_referrer_email'      => $aff_user->user_email,
			'slicewp_referrer_url'        => $aff_user->user_url,
			'slicewp_referrer_username'   => $aff_user->user_login,
		);

		// Handle different referral contexts.
		if ( 'woo' === $commission->get( 'origin' ) ) {

			$order = wc_get_order( $commission->get( 'reference' ) );

			if ( false === $order ) {
				return;
			}

			$user_id    = $order->get_user_id();
			$contact_id = get_post_meta( $order->get_id(), WPF_CONTACT_ID_META_KEY, true );

			// Get any tags to apply.
			$apply_tags = wpf_get_option( 'slicewp_apply_tags_customers', array() );

			$setting = slicewp_get_affiliate_meta( $affiliate_id, 'apply_tags_customers', true );

			if ( empty( $setting ) ) {
				$setting = array();
			}

			$apply_tags = array_merge( $apply_tags, $setting );

		}

		// If we've found a user or contact for the referral, update their record and apply tags.
		if ( ! empty( $user_id ) ) {

			wp_fusion()->user->push_user_meta( $user_id, $referrer_data );

			if ( ! empty( $apply_tags ) ) {
				wp_fusion()->user->apply_tags( $apply_tags, $user_id );
			}
		} elseif ( ! empty( $contact_id ) ) {

			wpf_log( 'info', wpf_get_current_user_id(), 'Syncing SliceWP referrer meta to contact #' . $contact_id . ':', array( 'meta_array' => $referrer_data ) );

			wp_fusion()->crm->update_contact( $contact_id, $referrer_data );

			if ( ! empty( $apply_tags ) ) {

				wpf_log( 'info', 0, 'Applying tags to contact #' . $contact_id . ' for SliceWP referral: ', array( 'tag_array' => $apply_tags ) );
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );
			}
		}

		// Maybe sync data to the affiliate.

		// These fields require queries so let's only get that data if they're enabled for sync.
		if ( wpf_is_field_active( 'slicewp_referral_count' ) || wpf_is_field_active( 'slicewp_total_earnings' ) ) {

			$args = array(
				'number'       => -1,
				'affiliate_id' => $affiliate_id,
				'status'       => array( 'unpaid', 'paid' ),
			);

			$commissions = slicewp_get_commissions( $args );

			$earnings = 0;

			foreach( $commissions as $commission ) {

				$earnings += (float)$commission->get('amount');

			}

			$affiliate_data = array(
				'slicewp_referral_count' => count( $commissions ),
				'slicewp_total_earnings' => $earnings,
			);

			wp_fusion()->user->push_user_meta( $affiliate->get( 'user_id' ), $affiliate_data );
		}

		// Maybe apply first referral tags to the affiliate.
		$apply_tags = wpf_get_option( 'slicewp_apply_tags_first_referral', array() );

		if ( ! empty( $apply_tags ) ) {

			if ( 1 === slicewp_get_commissions(
				array(
					'number'       => -1,
					'affiliate_id' => $affiliate_id,
					'status'       => array( 'unpaid', 'paid' ),
				),
				true
			) ) {

				wp_fusion()->user->apply_tags( $apply_tags, $affiliate->get( 'user_id' ) );

			}
		}

	}

	/**
	 * Apply tags when affiliate rejected
	 *
	 * @since  3.38.43
	 * @param int $user_id SliceWP user id.
	 */
	public function affiliate_rejected( $user_id ) {

		$apply_tags = wpf_get_option( 'slicewp_apply_tags_rejected' );

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $user_id );
		}

	}

	/**
	 * Apply tags when affiliate approved
	 *
	 * @since  3.38.43
	 * @param int $user_id The user ID.
	 */
	public function affiliate_approved( $user_id ) {

		$apply_tags = wpf_get_option( 'slicewp_apply_tags_approved' );

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $user_id );
		}

		$remove_tags = wpf_get_option( 'slicewp_apply_tags_rejected' );

		if ( ! empty( $remove_tags ) ) {
			wp_fusion()->user->remove_tags( $remove_tags, $user_id );
		}

	}


	/**
	 * Save affiliate woo field.
	 *
	 * @since 3.38.43
	 * @param integer $affiliate_id Affiliate ID.
	 */
	public function save_affiliate_woo_field( $affiliate_id ) {

		if ( ! empty( $_POST['apply_tags_customers'] ) ) {
			slicewp_update_affiliate_meta( $affiliate_id, 'apply_tags_customers', $_POST['apply_tags_customers'] );
		} else {
			slicewp_update_affiliate_meta( $affiliate_id, 'apply_tags_customers', array() );
		}
	}

	/**
	 * Add affiliate woo field.
	 *
	 * @since 3.38.43
	 */
	public function add_affiliate_woo_field() {
		if ( ! property_exists( wp_fusion()->integrations, 'woocommerce' ) ) {
			return;
		}
		?>
		<div class="slicewp-card slicewp-first">

			<div class="slicewp-card-header">
				<?php echo __( 'WP Fusion', 'wp-fusion' ); ?>
			</div>

			<div class="slicewp-card-inner">

				<div class="slicewp-field-wrapper slicewp-field-wrapper-inline">

					<div class="slicewp-field-label-wrapper">
						<label for="slicewp-affiliate-status"><?php echo __( 'Apply Tags', 'wp-fusion' ); ?></label>
					</div>
					<?php

					$setting = slicewp_get_affiliate_meta( ( isset( $_GET['affiliate_id'] ) ? $_GET['affiliate_id'] : '' ), 'apply_tags_customers', true );

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
				</div>
			</div>

		</div>

		<?php
	}



	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds slicewp_affiliate to available export options
	 *
	 * @since  3.38.43
	 *
	 * @param  array $options The export options.
	 * @return array The export options.
	 */
	public function export_options( $options ) {

		$options['slicewp_affiliate'] = array(
			'label'   => __( 'SliceWP affiliates', 'wp-fusion' ),
			'title'   => __( 'Affiliates', 'wp-fusion' ),
			'tooltip' => sprintf( __( 'For each affiliate, syncs any enabled affiliate fields to %s, and applies any configured tags based on the affiliate\'s current status.', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		return $options;

	}

	/**
	 * Get all affiliates to be processed
	 *
	 * @since  3.38.43
	 *
	 * @return array The affiliate IDs.
	 */
	public function batch_init() {
		$affiliates = slicewp_get_affiliates(
			array(
				'status' => 'active',
				'number' => -1,
				'fields' => 'id',
			)
		);
		return $affiliates;
	}

	/**
	 * Processes affiliate actions in batches
	 *
	 * @since 3.38.43
	 *
	 * @param int $affiliate_id Affiliate ID.
	 */
	public function batch_step( $affiliate_id ) {

		$this->add_affiliate( $affiliate_id );

	}


}

new WPF_SliceWP();
