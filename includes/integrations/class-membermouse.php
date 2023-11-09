<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_MemberMouse extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'membermouse';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Membermouse';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/membermouse/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// Settings
		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );
		add_filter( 'wpf_user_update', array( $this, 'user_update' ), 10, 2 );
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 20 );
		add_filter( 'wpf_meta_fields', array( $this, 'set_contact_field_names' ) );
		add_action( 'wpf_tags_modified', array( $this, 'update_membership' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// MemberMouse actions
		// NB: Much of MemberMouse's code is base64 encoded, you can use http://ddecode.com/phpdecoder/?results=d78a52c2d32e014fc981331c4e015cb6 to decode it.
		add_action( 'mm_member_account_update', array( $this, 'account_update' ) );
		add_action( 'mm_payment_received', array( $this, 'payment_received' ), 10, 2 );
		add_action( 'wp_ajax_module-handle', array( $this, 'module_handle' ), 5 );
		add_action( 'wp_ajax_nopriv_module-handle', array( $this, 'module_handle' ), 5 );

	}

	/**
	 * Creates MM submenu item
	 *
	 * @access public
	 * @return void
	 */

	public function admin_menu() {

		$crm = wp_fusion()->crm->name;

		$id = add_submenu_page(
			'mmdashboard',
			$crm . ' Integration',
			'WP Fusion',
			'manage_options',
			'mm-wpf-settings',
			array( $this, 'render_admin_menu' )
		);

		add_action( 'load-' . $id, array( $this, 'enqueue_scripts' ) );

	}


	/**
	 * Enqueues WPF scripts and styles on MM options page
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
	 * Renders MM submenu item
	 *
	 * @access public
	 * @return mixed
	 */

	public function render_admin_menu() {

		// Save settings
		if ( isset( $_POST['wpf_mm_settings_nonce'] ) && wp_verify_nonce( $_POST['wpf_mm_settings_nonce'], 'wpf_mm_settings' ) ) {
			update_option( 'wpf_mm_settings', $_POST['wpf-settings'] );
			echo '<div id="message" class="updated fade"><p><strong>Settings saved.</strong></p></div>';
		}

		?>

		<div class="wrap">
			<h2><?php echo wp_fusion()->crm->name; ?> Integration</h2>

			<form id="wpf-mm-settings" action="" method="post">
				<?php wp_nonce_field( 'wpf_mm_settings', 'wpf_mm_settings_nonce' ); ?>
				<input type="hidden" name="action" value="update">

				<h4>Product Tags</h4>
				<p class="description">For each product below, specify tags to be applied in <?php echo wp_fusion()->crm->name; ?> when purchased.</p>
				<br/>

				<?php
				$view     = new MM_ProductView();
				$data     = $view->search();
				$settings = get_option( 'wpf_mm_settings' );
				?>

				<table class="table form-table wpf-settings-table" id="wpf-mm-products-table">
					<thead>
					<tr>
						<th>Product Name</th>
						<th>Price</th>
						<th>Apply Tags</th>
					</tr>
					</thead>
					<tbody>

					<?php foreach ( $data->data[1] as $product ) : ?>

						<?php
						if ( ! isset( $settings['apply_tags'][ $product->id ] ) ) {
							$settings['apply_tags'][ $product->id ] = array();
						}
						?>

						<tr>
							<td><?php echo $product->name; ?></td>
							<td>$<?php echo number_format( (float) $product->price, 2, '.', '' ); ?></td>
							<td>
								<?php

								$args = array(
									'setting'   => $settings['apply_tags'][ $product->id ],
									'meta_name' => "wpf-settings[apply_tags][{$product->id}]",
								);

								wpf_render_tag_multiselect( $args );

								?>

							</td>
						</tr>

					<?php endforeach; ?>

					</tbody>

				</table>

				<h4>Membership Linked Tags</h4>
				<p class="description">For each <strong>free</strong> membership level, you can select a tag to be used as a link. When this tag is applied in <?php echo wp_fusion()->crm->name; ?>, the user will be enrolled. When the tag is removed, the membership will be paused.</p>
				<br/>

				<?php
				$view      = new MM_MembershipLevelsView();
				$data_grid = new MM_DataGrid( $_REQUEST, 'id', 'desc', 10 );
				$data      = $view->getViewData( $data_grid );
				?>

				<table class="table form-table wpf-settings-table" id="wpf-mm-products-table">
					<thead>
					<tr>
						<th>Membership Level</th>
						<th>Link with Tag</th>
					</tr>
					</thead>
					<tbody>

					<?php
					foreach ( $data as $key => $item ) :

						if ( ! $item->is_free ) {
							continue;
						}

						if ( ! isset( $settings['tag_link'][ $item->id ] ) ) {
							$settings['tag_link'][ $item->id ] = array();
						}
						?>

						<tr>
							<td><?php echo $item->name; ?></td>
							<td>

								<?php

								$args = array(
									'setting'   => $settings['tag_link'][ $item->id ],
									'meta_name' => "wpf-settings[tag_link][{$item->id}]",
									'limit'     => 1,
								);

								wpf_render_tag_multiselect( $args );

								?>
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
	 * Adds MM field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['membermouse'] ) ) {
			$field_groups['membermouse'] = array(
				'title'  => 'MemberMouse',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Set field labels from MM field labels
	 *
	 * @access public
	 * @return array Meta fields
	 */

	public function set_contact_field_names( $meta_fields ) {

		// Misc.
		$meta_fields['membership_level'] = array(
			'label' => 'Membership Level ID',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['membership_level_name'] = array(
			'label' => 'Membership Level Name',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['is_member'] = array(
			'label' => 'Is Member',
			'type'  => 'checkbox',
			'group' => 'membermouse',
		);

		$meta_fields['is_free'] = array(
			'label' => 'Member Is Free',
			'type'  => 'checkbox',
			'group' => 'membermouse',
		);

		$meta_fields['is_gift'] = array(
			'label' => 'Member Is Gift',
			'type'  => 'checkbox',
			'group' => 'membermouse',
		);

		$meta_fields['phone'] = array(
			'label' => 'Phone',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['coupon_code'] = array(
			'label' => 'Coupon Code',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		// Billing
		$meta_fields['billing_address'] = array(
			'label' => 'Billing Address',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['billing_city'] = array(
			'label' => 'Billing City',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['billing_state'] = array(
			'label' => 'Billing State',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['billing_zip'] = array(
			'label' => 'Billing ZIP',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['billing_country'] = array(
			'label' => 'Billing Country',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		// Shipping
		$meta_fields['shipping_address'] = array(
			'label' => 'Shipping Address',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['shipping_city'] = array(
			'label' => 'Shipping City',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['shipping_state'] = array(
			'label' => 'Shipping State',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['shipping_zip'] = array(
			'label' => 'Shipping ZIP',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		$meta_fields['shipping_country'] = array(
			'label' => 'Shipping Country',
			'type'  => 'text',
			'group' => 'membermouse',
		);

		// Custom fields
		$view      = new MM_CustomFieldView();
		$data_grid = new MM_DataGrid( $_REQUEST, 'id', 'desc', 10 );
		$data      = $view->getViewData( $data_grid );

		foreach ( $data as $field_object ) {

			if ( $field_object->type == 'input' ) {
				$type = 'text';
			} else {
				$type = $field_object->type;
			}

			$meta_fields[ 'cf_' . $field_object->id ] = array(
				'label' => $field_object->display_name,
				'type'  => $type,
				'group' => 'membermouse',
			);

		}

		return $meta_fields;

	}

	/**
	 * Triggered when new member is added
	 *
	 * @access  public
	 * @return  array Post data
	 */

	public function user_register( $post_data, $user_id ) {

		if ( ! isset( $post_data['mm-security'] ) && ! isset( $post_data['mm_checkout_url'] ) ) {
			return $post_data;
		}

		foreach ( $post_data as $key => $value ) {

			if ( strpos( $key, 'mm_custom_field_' ) !== false ) {

				unset( $post_data[ $key ] );
				$key                       = str_replace( 'mm_custom_field_', '', $key );
				$post_data[ 'cf_' . $key ] = $value;

			} elseif ( strpos( $key, 'mm_field_' ) !== false ) {

				unset( $post_data[ $key ] );
				$key               = str_replace( 'mm_field_', '', $key );
				$post_data[ $key ] = $value;

			} elseif ( strpos( $key, 'mm_' ) !== false ) {

				unset( $post_data[ $key ] );
				$key               = str_replace( 'mm_', '', $key );
				$post_data[ $key ] = $value;

			}
		}

		$field_map = array(
			'email'    => 'user_email',
			'password' => 'user_pass',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );

		return $post_data;

	}

	/**
	 * Triggered when profile updated
	 *
	 * @access  public
	 * @return  array Post data
	 */

	public function user_update( $post_data, $user_id ) {

		if ( isset( $post_data['pwd'] ) ) {
			$post_data['user_pass'] = $post_data['pwd'];
		}

		return $post_data;

	}


	/**
	 * Triggered when a member's account is updated
	 *
	 * @access public
	 * @return void
	 */

	public function account_update( $member_data ) {

		$member_data['user_email'] = $member_data['email'];

		wp_fusion()->user->push_user_meta( $member_data['member_id'], $member_data );

	}


	/**
	 * Payment received.
	 *
	 * Triggered when a payment is received, applies tags and syncs data (does
	 * not run on trial signups).
	 *
	 * @since 2.3.2
	 *
	 * @param array $member_data The member data.
	 */
	public function payment_received( $member_data ) {

		$user_id = $member_data['member_id'];

		wp_fusion()->user->push_user_meta( $user_id, $member_data );

		$settings = get_option( 'wpf_mm_settings', array() );

		if ( empty( $settings ) || ! isset( $settings['apply_tags'] ) ) {
			return;
		}

		$products = json_decode( $member_data['order_products'], true );

		if ( ! empty( $products ) ) {

			foreach ( $products as $product ) {

				if ( isset( $settings['apply_tags'][ $product['id'] ] ) ) {
					wp_fusion()->user->apply_tags( $settings['apply_tags'][ $product['id'] ], $user_id );
				}
			}
		}

	}

	/**
	 * AJAX edits in admin
	 *
	 * @access public
	 * @return void
	 */

	public function module_handle() {

		if ( isset( $_POST['mm_action'] ) && $_POST['mm_action'] == 'updateMember' ) {

			$post_data = $_POST;

			foreach ( $post_data as $key => $value ) {

				if ( strpos( $key, 'mm_field_' ) !== false ) {

					unset( $post_data[ $key ] );
					$key               = str_replace( 'mm_field_', '', $key );
					$post_data[ $key ] = $value;

				} elseif ( strpos( $key, 'mm_' ) !== false ) {

					unset( $post_data[ $key ] );
					$key               = str_replace( 'mm_', '', $key );
					$post_data[ $key ] = $value;

				}
			}

			$field_map = array(
				'email'        => 'user_email',
				'new_password' => 'user_pass',
			);

			$post_data = $this->map_meta_fields( $post_data, $field_map );

			wp_fusion()->user->push_user_meta( $post_data['id'], $post_data );

		}

	}

	/**
	 * Updates user's membership level if tag is linked
	 *
	 * @access public
	 * @return void
	 */

	public function update_membership( $user_id, $user_tags ) {

		$settings = get_option( 'wpf_mm_settings', array() );

		if ( empty( $settings['tag_link'] ) ) {
			return;
		}

		$data            = new stdClass();
		$data->member_id = $user_id;
		$member_data     = MM_APIService::getMember( $data );

		foreach ( (array) $settings['tag_link'] as $level_id => $tags ) {

			$linked_tag = $tags[0];

			if ( in_array( $linked_tag, $user_tags ) && $member_data->message['membership_level'] != $level_id || ( $member_data->message['membership_level'] == $level_id && $member_data->message['status'] != 1 ) ) {

				// Enroll a new member
				$userdata = get_userdata( $user_id );

				$data                      = new stdClass();
				$data->member_id           = $user_id;
				$data->membership_level_id = $level_id;
				$data->username            = $userdata->user_login;
				$data->email               = $userdata->user_email;
				$data->first_name          = $userdata->first_name;
				$data->last_name           = $userdata->last_name;
				$data->status              = 1;

				wpf_log( 'info', $user_id, 'Member added to MemberMouse level <strong>' . $level_id . '</strong> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $linked_tag ) . '</strong>', array( 'source' => 'membermouse' ) );

				$result = MM_APIService::updateMember( $data );

			} elseif ( ! in_array( $linked_tag, $user_tags ) && $member_data->message['membership_level'] == $level_id ) {

				// Unenroll a member when a linked tag is removed
				$userdata = get_userdata( $user_id );

				$data            = new stdClass();
				$data->member_id = $user_id;
				$data->status    = 4;

				wpf_log( 'info', $user_id, 'Membership paused for MemberMouse level <strong>' . $level_id . '</strong> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $linked_tag ) . '</strong>', array( 'source' => 'membermouse' ) );

				$result = MM_APIService::updateMember( $data );

			}
		}
	}

}

new WPF_MemberMouse();
