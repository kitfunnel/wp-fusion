<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_EDD_Software_Licensing extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'edd-software-licensing';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'EDD Software Licensing';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/edd-software-licensing/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		add_action( 'edd_sl_store_license', array( $this, 'store_license' ), 10, 4 );
		add_action( 'edd_sl_license_upgraded', array( $this, 'license_upgraded' ), 10, 2 );
		add_action( 'edd_sl_post_set_status', array( $this, 'post_set_status' ), 10, 2 );
		add_action( 'edd_sl_post_set_expiration', array( $this, 'post_set_expiration' ), 10, 2 );
		add_action( 'edd_sl_post_set_lifetime', array( $this, 'post_set_lifetime' ) );

		// Settings
		add_action( 'wpf_edd_meta_box_inner', array( $this, 'meta_box' ), 10, 2 );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ) );

		// Batch operations
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_edd_sl_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_edd_sl', array( $this, 'batch_step' ) );

	}

	/**
	 * Sync license data when a license is added
	 *
	 * @access public
	 * @return void
	 */

	public function store_license( $license_id, $download_id, $payment_id, $type ) {

		$license = edd_software_licensing()->get_license( $license_id );

		$update_data = array(
			'edd_sl_license_id'          => $license_id,
			'edd_sl_license_key'         => $license->license_key,
			'edd_sl_license_status'      => $license->status,
			'edd_sl_license_renewal_url' => $license->get_renewal_url(),
		);

		if ( ! empty( $license->expiration ) ) {
			$update_data['edd_sl_license_expiration'] = date( 'Y-m-d H:i:s', $license->expiration );
		}

		wp_fusion()->user->push_user_meta( $license->user_id, $update_data );

	}

	/**
	 * Update tags when a license is upgraded
	 *
	 * @access public
	 * @return void
	 */

	public function license_upgraded( $license_id, $args ) {

		$license = edd_software_licensing()->get_license( $license_id );

		$settings = get_post_meta( $args['old_download_id'], 'wpf-settings-edd', true );

		if ( ! empty( $settings ) && isset( $settings['apply_tags_price'] ) ) {

			if ( ! empty( $settings['apply_tags_price'][ $args['old_price_id'] ] ) ) {

				wp_fusion()->user->remove_tags( $settings['apply_tags_price'][ $args['old_price_id'] ], $license->user_id );

			}
		}

	}

	/**
	 * Sync license status
	 *
	 * @access public
	 * @return void
	 */

	public function post_set_status( $license_id, $status ) {

		$license = edd_software_licensing()->get_license( $license_id );

		if ( ! $license ) {
			return;
		}

		wp_fusion()->user->push_user_meta( $license->user_id, array( 'edd_sl_license_status' => $status ) );

		$settings = get_post_meta( $license->download_id, 'wpf-settings-edd', true );

		if ( empty( $settings ) || empty( $settings['apply_tags_license_expired'] ) ) {
			return;
		}

		if ( 'expired' === $status ) {

			// Expired license tagging.

			wp_fusion()->user->apply_tags( $settings['apply_tags_license_expired'], $license->user_id );

		} elseif ( 'active' === $status ) {

			// Remove the tags if the license is reactivated.

			wp_fusion()->user->remove_tags( $settings['apply_tags_license_expired'], $license->user_id );

		}

	}

	/**
	 * Sync license expiration
	 *
	 * @access public
	 * @return void
	 */

	public function post_set_expiration( $license_id, $expiration ) {

		$license = edd_software_licensing()->get_license( $license_id );

		wp_fusion()->user->push_user_meta( $license->user_id, array( 'edd_sl_license_expiration' => $expiration ) );

	}

	/**
	 * License is lifetime
	 *
	 * @access public
	 * @return void
	 */

	public function post_set_lifetime( $license_id ) {

		$license = edd_software_licensing()->get_license( $license_id );

		wp_fusion()->user->push_user_meta( $license->user_id, array( 'edd_sl_license_expiration' => 'lifetime' ) );

	}

	/**
	 * Output meta box settings
	 *
	 * @access public
	 * @return void
	 */

	public function meta_box( $post, $settings ) {

		if ( ! isset( $settings['apply_tags_license_expired'] ) ) {
			$settings['apply_tags_license_expired'] = array();
		}

		echo '<th scope="row"><label for="apply_tags">License Expired:</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_license_expired'],
			'meta_name' => 'wpf-settings-edd',
			'field_id'  => 'apply_tags_license_expired',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . sprintf( __( 'Apply these tags in %s when the license expires', 'wp-fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</td>';

		echo '</tr>';

	}

	/**
	 * Adds EDD field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		$field_groups['eddsl'] = array(
			'title'  => 'EDD Software Licensing',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Adds EDD meta fields to WPF contact fields list
	 *
	 * @access  public
	 * @return  array Meta Fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$meta_fields['edd_sl_license_id'] = array(
			'label'  => 'License ID',
			'type'   => 'text',
			'group'  => 'eddsl',
			'pseudo' => true,
		);

		$meta_fields['edd_sl_license_key'] = array(
			'label'  => 'License Key',
			'type'   => 'text',
			'group'  => 'eddsl',
			'pseudo' => true,
		);

		$meta_fields['edd_sl_license_status'] = array(
			'label'  => 'License Status',
			'type'   => 'text',
			'group'  => 'eddsl',
			'pseudo' => true,
		);

		$meta_fields['edd_sl_license_expiration'] = array(
			'label'  => 'License Expiration',
			'type'   => 'date',
			'group'  => 'eddsl',
			'pseudo' => true,
		);

		$meta_fields['edd_sl_license_renewal_url'] = array(
			'label'  => 'License Renewal URL',
			'type'   => 'text',
			'group'  => 'eddsl',
			'pseudo' => true,
		);

		return $meta_fields;

	}



	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds EDD Licenses option to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['edd_sl'] = array(
			'label'   => 'EDD Software Licensing statuses',
			'title'   => 'Licenses',
			'tooltip' => sprintf( __( 'Syncs license statuses and expiration dates to %s, and tags any customers with expired licenses', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		return $options;

	}

	/**
	 * Counts total number of licenses to be processed
	 *
	 * @access public
	 * @return array License IDs
	 */

	public function batch_init() {

		$args = array(
			'number' => - 1,
			'fields' => 'ids',
		);

		$licenses = edd_software_licensing()->licenses_db->get_licenses( $args );

		return $licenses;

	}

	/**
	 * Processes licence actions in steps
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step( $license_id ) {

		$license = edd_software_licensing()->get_license( $license_id );

		$update_data = array(
			'edd_sl_license_id'          => $license_id,
			'edd_sl_license_key'         => $license->license_key,
			'edd_sl_license_status'      => $license->status,
			'edd_sl_license_expiration'  => date( 'Y-m-d H:i:s', $license->expiration ),
			'edd_sl_license_renewal_url' => $license->get_renewal_url(),
		);

		wp_fusion()->user->push_user_meta( $license->user_id, $update_data );

		// Expired license tagging
		if ( 'expired' == $license->status ) {

			$settings = get_post_meta( $license->download_id, 'wpf-settings-edd', true );

			if ( ! empty( $settings ) && ! empty( $settings['apply_tags_license_expired'] ) ) {

				wp_fusion()->user->apply_tags( $settings['apply_tags_license_expired'], $license->user_id );

			}
		}

	}


}

new WPF_EDD_Software_Licensing();
