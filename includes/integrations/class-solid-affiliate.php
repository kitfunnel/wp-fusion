<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Solid Affiliate integration.
 *
 * @since 3.38.40
 *
 * @link https://wpfusion.com/documentation/affiliates/solid-affiliate/
 */
class WPF_Solid_Affiliate extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.40
	 * @var string $slug
	 */

	public $slug = 'solid-affiliate';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.40
	 * @var string $name
	 */
	public $name = 'Solid Affiliate';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.40
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/affiliates/solid-affiliate/';


	/**
	 * Gets things started
	 *
	 * @since   3.38.40
	 */
	public function init() {

		// Settings fields.
		add_filter( 'wpf_configure_settings', array( $this, 'register_settings' ), 15, 2 );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 20 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		// Accepted referrals.
		add_action( 'data_model_solid_affiliate_referrals_save', array( $this, 'referral_accepted' ) );

		// Batch operations.
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_solid_affiliate_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_solid_affiliate', array( $this, 'batch_step' ) );

		// Affiliate registration from frontend.
		add_action( 'data_model_solid_affiliate_affiliates_save', array( $this, 'affiliate_registration' ) );

		// Status change for affiliates in singualr and bluck.
		add_action( 'solid_affiliate/Affiliate/update', array( $this, 'affiliate_status_change' ), 10, 2 );

		if ( is_admin() ) {

			// Affiliate registration from dashboard.
			$this->admin_affiliate_registration();
		}

	}

	/**
	 * Triggered when an affiliate instance has been updated.
	 *
	 * @since 3.38.40
	 *
	 * @param int    $affiliate_id Affiliate ID.
	 * @param object $affiliate    SolidAffiliate\Models\Affiliate.
	 */
	public function affiliate_status_change( $affiliate_id, $affiliate ) {
		// We did that here because the action only returns the old affilaite instance not the updated one.
		$old_attributes     = $affiliate->__get( 'attributes' );
		$updated_affiliate  = \SolidAffiliate\Models\Affiliate::find( $affiliate_id );
		$updated_attributes = $updated_affiliate->__get( 'attributes' );

		// Same status nothing changed.
		if ( strtolower( $old_attributes['status'] ) === strtolower( $updated_attributes['status'] ) ) {
			return;
		}

		if ( strtolower( $updated_attributes['status'] ) === 'approved' ) {
			$this->affiliate_approved( $updated_attributes['user_id'] );
		}

		if ( strtolower( $updated_attributes['status'] ) === 'rejected' ) {
			$this->affiliate_rejected( $updated_attributes['user_id'] );
		}

	}


	/**
	 * Triggered when affiliate has registered through dashboard.
	 *
	 * @since  3.38.40
	 */
	public function admin_affiliate_registration() {

		$nonce = \SolidAffiliate\Controllers\AffiliatesController::NONCE_SUBMIT_AFFILIATE;

		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'solid-affiliate-affiliates' || ! isset( $_GET['action'] ) ) {
			return;
		}

		$submit_action = \SolidAffiliate\Controllers\AffiliatesController::POST_PARAM_SUBMIT_AFFILIATE;

		if ( isset( $_POST[ $submit_action ] ) ) {
			$field_id = intval( $_POST['field_id'] );
			if ( $_GET['action'] === 'new' ) {
				$this->add_affiliate( $field_id );
			}

			if ( $_GET['action'] === 'edit' ) {
				$this->update_affiliate( $field_id );
			}
		}

	}


	/**
	 * Triggered when affiliate has registered throught frontend form.
	 *
	 * @since  3.38.40
	 * @param object SolidAffiliate\Models\Affiliate $affiliate The affiliate.
	 */
	public function affiliate_registration( $affiliate ) {
		$attributes = $affiliate->__get( 'attributes' );
		$this->add_affiliate( $attributes['id'] );
	}


	/**
	 * Add an affiliate to crm.
	 *
	 * @since  3.38.40
	 * @param int $affiliate_id affiliate id.
	 */
	public function add_affiliate( $affiliate_id ) {
		$affiliate_data = $this->get_affiliate_meta( $affiliate_id );

		wp_fusion()->user->push_user_meta( $affiliate_data['user_id'], $affiliate_data );

		$apply_tags = wpf_get_option( 'saff_apply_tags', array() );

		// If the affiliates are approved, make sure they have these tags as well.
		if ( 'active' == $affiliate_data['saff_affiliate_status'] ) {
			$approved_tags = wpf_get_option( 'saff_apply_tags_approved', array() );
			$apply_tags    = array_merge( $apply_tags, $approved_tags );
		}

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $affiliate_data['user_id'] );
		}

	}

	/**
	 * Registers additional Solid Affiliate settings.
	 *
	 * @since  3.38.40
	 * @param array $settings wpf settings.
	 * @param array $options wpf options.
	 * @return  array Settings
	 */
	public function register_settings( $settings, $options ) {

		$settings['saff_header'] = array(
			'title'   => __( 'Solid Affiliate Integration', 'wp-fusion' ),
			'type'    => 'heading',
			'section' => 'integrations',
		);

		$settings['saff_apply_tags'] = array(
			'title'   => __( 'Apply Tags - Affiliate Registration', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags to new affiliates registered through Solid Affiliate.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['saff_apply_tags_approved'] = array(
			'title'   => __( 'Apply Tags - Approved', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags when affiliates are approved.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['saff_apply_tags_rejected'] = array(
			'title'   => __( 'Apply Tags - Rejected', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags when affiliates are rejected.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		$settings['saff_apply_tags_first_referral'] = array(
			'title'   => __( 'Apply Tags - First Referral', 'wp-fusion' ),
			'desc'    => __( 'Apply these tags when affiliates get their first referral.', 'wp-fusion' ),
			'std'     => array(),
			'type'    => 'assign_tags',
			'section' => 'integrations',
		);

		if ( property_exists( wp_fusion()->integrations, 'woocommerce' ) ) {

			$settings['saff_apply_tags_customers'] = array(
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
	 * Adds SolidAffiliate field group to meta fields list
	 *
	 * @since  3.38.40
	 * @param array $field_groups wpf field groups.
	 * @return  array Field groups
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['solid_aff'] = array(
			'title'  => 'Solid Affiliate - Affiliate',
			'fields' => array(),
		);

		$field_groups['solid_aff_referrer'] = array(
			'title'  => 'Solid Affiliate - Referrer',
			'fields' => array(),
		);

		return $field_groups;

	}

	/**
	 * Adds SolidAffiliate meta fields to WPF contact fields list
	 *
	 * @since  3.38.40
	 * @param array $meta_fields wpf meta fields.
	 * @return  array Meta Fields
	 */
	public function prepare_meta_fields( $meta_fields = array() ) {

		// Affiliate.
		$meta_fields['saff_affiliate_id'] = array(
			'label'  => 'Affiliate ID',
			'type'   => 'int',
			'group'  => 'solid_aff',
			'pseudo' => true,
		);

		$meta_fields['saff_affiliate_commission_type'] = array(
			'label'  => 'Affiliate Commission Type',
			'type'   => 'text',
			'group'  => 'solid_aff',
			'pseudo' => true,
		);

		$meta_fields['saff_affiliate_commission_rate'] = array(
			'label'  => 'Affiliate Commission Rate',
			'type'   => 'text',
			'group'  => 'solid_aff',
			'pseudo' => true,
		);

		/**
		 * Not currently in use.

		$meta_fields['saff_affiliate_group_id'] = array(
			'label'  => 'Affiliate Group ID',
			'type'   => 'integer',
			'group'  => 'solid_aff',
			'pseudo' => true,
		);

		$meta_fields['saff_affiliate_mailchimp_user_id'] = array(
			'label'  => 'Affiliate Mailchimp User ID',
			'type'   => 'integer',
			'group'  => 'solid_aff',
			'pseudo' => true,
		);

		*/

		$meta_fields['saff_affiliate_payment_email'] = array(
			'label'  => 'Affiliate Payment Email',
			'type'   => 'email',
			'group'  => 'solid_aff',
			'pseudo' => true,
		);

		$meta_fields['saff_affiliate_registration_notes'] = array(
			'label'  => 'Affiliate Registration Notes',
			'type'   => 'text',
			'group'  => 'solid_aff',
			'pseudo' => true,
		);

		$meta_fields['saff_affiliate_status'] = array(
			'label'  => 'Affiliate Status',
			'type'   => 'text',
			'group'  => 'solid_aff',
			'pseudo' => true,
		);

		$meta_fields['saff_total_earnings'] = array(
			'label'  => 'Affiliate\'s Total Earnings',
			'type'   => 'int',
			'group'  => 'solid_aff',
			'pseudo' => true,
		);

		$meta_fields['saff_referral_count'] = array(
			'label'  => 'Affiliate\'s Total Referrals',
			'type'   => 'int',
			'group'  => 'solid_aff',
			'pseudo' => true,
		);

		// Referrer.
		$meta_fields['saff_referrer_id'] = array(
			'label'  => 'Referrer\'s Affiliate ID',
			'type'   => 'text',
			'group'  => 'solid_aff_referrer',
			'pseudo' => true,
		);

		$meta_fields['saff_referrer_first_name'] = array(
			'label'  => 'Referrer\'s First Name',
			'type'   => 'text',
			'group'  => 'solid_aff_referrer',
			'pseudo' => true,
		);

		$meta_fields['saff_referrer_last_name'] = array(
			'label'  => 'Referrer\'s Last Name',
			'type'   => 'text',
			'group'  => 'solid_aff_referrer',
			'pseudo' => true,
		);

		$meta_fields['saff_referrer_email'] = array(
			'label'  => 'Referrer\'s Email',
			'type'   => 'text',
			'group'  => 'solid_aff_referrer',
			'pseudo' => true,
		);

		return $meta_fields;

	}

	/**
	 * Gets all the relevant metdata for an affiliate
	 *
	 * @since 3.38.40
	 * @param int $affiliate_id The ID of the affiliate to get the data for.
	 * @return array User Meta
	 */
	public function get_affiliate_meta( $affiliate_id ) {

		$affiliate = \SolidAffiliate\Models\Affiliate::find( $affiliate_id );

		if ( ! isset( $_POST['submit_affiliate'] ) ) {
			$attributes = $affiliate->__get( 'attributes' );
			$data       = $affiliate->__get( 'properties' );
		} else {
			$attributes = $_POST;
			$data       = array();
		}

		$rate = isset( $attributes['commission_rate'] ) ? $attributes['commission_rate'] : null;

		if ( empty( $rate ) ) {
			$solid_options = get_option( 'sld_affiliate_options_v1' );
			$rate          = $solid_options['referral_rate'];
		}

		$affiliate_data = array(
			'saff_affiliate_group_id'           => $attributes['affiliate_group_id'],
			'saff_affiliate_commission_rate'    => $rate,
			'saff_affiliate_commission_type'    => $attributes['commission_type'],
			'saff_affiliate_registration_notes' => $attributes['registration_notes'],
			'saff_affiliate_mailchimp_user_id'  => $attributes['mailchimp_user_id'],
			'saff_affiliate_status'             => $attributes['status'],
			'saff_affiliate_id'                 => $affiliate_id,
			'saff_affiliate_payment_email'      => $attributes['payment_email'],
			'first_name'                        => $attributes['first_name'],
			'last_name'                         => $attributes['last_name'],
			'user_id'                           => $attributes['user_id'],
		);

		// Custom meta.

		if ( ! empty( $data ) ) {

			foreach ( $data as $key => $value ) {
				$affiliate_data[ $key ] = maybe_unserialize( $value[0] );
			}
		}

		// These fields require queries so let's only get that data if they're enabled for sync.
		if ( $affiliate && wpf_is_field_active( 'saff_referral_count' ) ) {
			$affiliate_data['saff_referral_count'] = count( $affiliate->referrals() );
		}

		if ( $affiliate && wpf_is_field_active( 'saff_total_earnings' ) ) {
			$affiliate_data['saff_total_earnings'] = ( intval( $affiliate->paid_earnings( $affiliate ) ) + intval( $affiliate->unpaid_earnings( $affiliate ) ) );
		}

		return $affiliate_data;

	}


	/**
	 * Triggered when affiliate updated
	 *
	 * @since  3.38.40
	 * @param int $affiliate_id solid affiliate id.
	 */
	public function update_affiliate( $affiliate_id ) {
		$affiliate_data = $this->get_affiliate_meta( $affiliate_id );
		wp_fusion()->user->push_user_meta( $affiliate_data['user_id'], $affiliate_data );
	}


	/**
	 * Triggered when a referral is accepted
	 *
	 * @since  3.38.40
	 * @param object $referral \SolidAffiliate\Models\Referral.
	 */
	public function referral_accepted( $referral ) {

		$attributes   = $referral->__get( 'attributes' );
		$affiliate_id = $attributes['affiliate_id'];
		$aff_user_id  = $this->get_affiliate_user_id( $affiliate_id );
		$aff_user     = get_user_by( 'id', $aff_user_id );

		$referrer_data = array(
			'saff_referrer_id'         => $affiliate_id,
			'saff_referrer_first_name' => $aff_user->first_name,
			'saff_referrer_last_name'  => $aff_user->last_name,
			'saff_referrer_email'      => $aff_user->user_email,
			'saff_referrer_url'        => $aff_user->user_url,
			'saff_referrer_username'   => $aff_user->user_login,
		);

		// Handle different referral contexts.
		if ( 'woocommerce' === $attributes['order_source'] ) {

			// Get the customer's ID.
			$order = wc_get_order( $attributes['order_id'] );

			if ( false == $order ) {
				return;
			}

			$user_id    = $order->get_user_id();
			$contact_id = get_post_meta( $order->get_id(), WPF_CONTACT_ID_META_KEY, true );

			// Get any tags to apply.
			$apply_tags = wpf_get_option( 'saff_apply_tags_customers', array() );

		} else {

			wpf_log( 'info', wpf_get_current_user_id(), 'Solid Affiliate referral detected but unable to sync referrer data since referral context <code>' . $attributes['order_source'] . '</code> is not currently supported.' );

		}

		// If we've found a user or contact for the referral, update their record and apply tags.
		if ( ! empty( $user_id ) ) {

			wp_fusion()->user->push_user_meta( $user_id, $referrer_data );

			if ( ! empty( $apply_tags ) ) {
				wp_fusion()->user->apply_tags( $apply_tags, $user_id );
			}
		} elseif ( ! empty( $contact_id ) ) {

			wpf_log( 'info', wpf_get_current_user_id(), 'Syncing Solid Affiliate referrer meta:', array( 'meta_array' => $referrer_data ) );

			wp_fusion()->crm->update_contact( $contact_id, $referrer_data );

			if ( ! empty( $apply_tags ) ) {
				wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );
			}
		}

		// Maybe sync data to the affiliate.
		$affiliate_data = array();
		$affiliate      = \SolidAffiliate\Models\Affiliate::find( $affiliate_id );

		// These fields require queries so let's only get that data if they're enabled for sync.
		if ( wpf_is_field_active( 'saff_referral_count' ) ) {
			$affiliate_data['saff_referral_count'] = count( $affiliate->referrals() );
		}

		if ( wpf_is_field_active( 'saff_total_earnings' ) ) {
			$affiliate_data['saff_total_earnings'] = $affiliate->paid_earnings( $affiliate );
		}

		if ( ! empty( $affiliate_data ) ) {

			wp_fusion()->user->push_user_meta( $aff_user_id, $affiliate_data );

		}

		// Maybe apply first referral tags to the affiliate.
		$apply_tags = wpf_get_option( 'saff_apply_tags_first_referral', array() );

		if ( ! empty( $apply_tags ) ) {

			if ( 1 === count( $affiliate->referrals() ) ) {

				wp_fusion()->user->apply_tags( $apply_tags, $aff_user_id );

			}
		}

	}

	/**
	 * Get affiliate user ID by affiliate ID.
	 *
	 * @since  3.38.40
	 * @param int $affiliate_id The affiliate ID.
	 * @return integer
	 */
	public function get_affiliate_user_id( $affiliate_id ) {
		$affiliate  = \SolidAffiliate\Models\Affiliate::find( $affiliate_id );
		$attributes = $affiliate->__get( 'attributes' );
		return intval( $attributes['user_id'] );
	}

	/**
	 * Apply tags when affiliate rejected
	 *
	 * @since  3.38.40
	 * @param int $user_id solid user id.
	 */
	public function affiliate_rejected( $user_id ) {

		$apply_tags = wpf_get_option( 'saff_apply_tags_rejected' );

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $user_id );
		}

	}

	/**
	 * Apply tags when affiliate approved
	 *
	 * @since  3.38.40
	 * @param int $user_id The user ID.
	 */
	public function affiliate_approved( $user_id ) {

		$apply_tags = wpf_get_option( 'saff_apply_tags_approved' );

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $user_id );
		}

		$remove_tags = wpf_get_option( 'saff_apply_tags_rejected' );

		if ( ! empty( $remove_tags ) ) {
			wp_fusion()->user->remove_tags( $remove_tags, $user_id );
		}

	}



	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds solid_affiliate to available export options
	 *
	 * @since  3.38.40
	 *
	 * @param  array $options The export options.
	 * @return array The export options.
	 */
	public function export_options( $options ) {

		$options['solid_affiliate'] = array(
			'label'   => __( 'Solid Affiliate affiliates', 'wp-fusion' ),
			'title'   => __( 'Affiliates', 'wp-fusion' ),
			'tooltip' => sprintf( __( 'For each affiliate, syncs any enabled affiliate fields to %s, and applies any configured affiliate tags.', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		return $options;

	}

	/**
	 * Get all affiliates to be processed
	 *
	 * @since  3.38.40
	 *
	 * @return array The affiliate IDs.
	 */
	public function batch_init() {
		$list    = \SolidAffiliate\Models\Affiliate::where( array( 'status' => 'approved' ) );
		$aff_ids = array();
		if ( ! empty( $list ) ) {
			$aff_ids = array_map(
				function ( $affiliate ) {
					return intval( $affiliate->id );
				},
				$list
			);
		}

		return $aff_ids;
	}

	/**
	 * Processes affiliate actions in batches
	 *
	 * @since 3.38.40
	 *
	 * @param int $affiliate_id Affiliate ID.
	 */
	public function batch_step( $affiliate_id ) {

		$this->add_affiliate( $affiliate_id );

	}


}

new WPF_Solid_Affiliate();
