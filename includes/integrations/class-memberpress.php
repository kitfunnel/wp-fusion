<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

//
// NOTES:
// A subscription purchase creates an active subscription and a 'complete' transaction.
// A subscription purchase with a trial creates a pending subscription and a 'pending' transaction.
// An offline payment creates an active subscription but a 'pending' transaction.
// A one-off purchase creates a 'complete' transaction and no subscription.
// A offline gateway purchase with "Admin Must Manually Complete Transactions" enabled creates an active subscription but a pending transaction.
// "Subscription is linked to Stripe, transaction is linked to membership".
//

class WPF_MemberPress extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'memberpress';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'MemberPress';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/integrations/memberpress/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @return  void
	 */

	public function init() {

		// WPF Settings.
		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ), 20 );
		add_filter( 'wpf_meta_fields', array( $this, 'prepare_meta_fields' ), 10 );

		// MemberPress admin tools.
		add_action( 'mepr-product-options-tabs', array( $this, 'output_product_nav_tab' ) );
		add_action( 'mepr-product-options-pages', array( $this, 'output_product_content_tab' ) );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ) );
		add_filter( 'mepr-admin-transactions-cols', array( $this, 'admin_columns' ) );
		add_action( 'mepr-admin-transactions-cell', array( $this, 'admin_columns_content' ), 10, 3 );
		add_action( 'mepr_edit_transaction_table_after', array( $this, 'transaction_table_after' ) );
		add_action( 'mepr_table_controls_search', array( $this, 'transactions_debug' ), 5 );

		// Completed purchase / status changes.
		add_filter( 'wpf_user_register', array( $this, 'user_register' ), 10, 2 );
		add_action( 'mepr_subscription_transition_status', array( $this, 'subscription_status_changed' ), 10, 3 );

		// New account / transcation stuff.
		add_action( 'mepr-signup', array( $this, 'apply_tags_checkout' ) );                      // It is used for processing the signup form before the logic progresses on to 'the_content'.
		// add_action( 'mepr-event-transaction-completed', array( $this, 'apply_tags_checkout' ) ); // Capture first normal payment after trial period on MemberPress Transaction.
		add_action( 'mepr-event-non-recurring-transaction-completed', array( $this, 'apply_tags_checkout' ) ); // No idea. See https://secure.helpscout.net/conversation/1963242594/22854/.
		add_action( 'mepr-txn-status-complete', array( $this, 'apply_tags_checkout' ) );         // Called after completed payment.
		add_action( 'mepr-txn-status-refunded', array( $this, 'transaction_refunded' ) );        // Refunds.
		add_action( 'mepr-txn-status-pending', array( $this, 'transaction_pending' ) );          // Pending.
		add_action( 'mepr-txn-transition-status', array( $this, 'sync_transaction_status' ), 10, 3 ); // Sync the transaction status.

		// Recurring transcation stuff.
		add_action( 'mepr-event-recurring-transaction-failed', array( $this, 'recurrring_transaction_failed' ) );
		add_action( 'mepr-event-recurring-transaction-completed', array( $this, 'recurrring_transaction_completed' ) );
		add_action( 'mepr-transaction-expired', array( $this, 'transaction_expired' ), 20 ); // 20 so MP can set the subscription status.

		// Upgrades and downgrades.
		add_action( 'mepr-upgraded-sub', array( $this, 'upgraded_subscription' ), 10, 2 );
		add_action( 'mepr-downgraded-sub', array( $this, 'downgraded_subscription' ), 10, 2 );

		// Corporate Accounts addon.
		add_action( 'mepr-txn-status-complete', array( $this, 'corporate_accounts_tagging' ) );

		// Profile updates (bidirectional).
		add_action( 'mepr_save_account', array( $this, 'save_account' ) );
		add_filter( 'wpf_user_update', array( $this, 'user_update' ), 10, 2 );
		add_action( 'wpf_pulled_user_meta', array( $this, 'pulled_user_meta' ), 10, 2 );

		// Add user to membership when tag-link tags are applied.
		add_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

		// Coupons.
		add_action( 'add_meta_boxes', array( $this, 'add_coupon_meta_box' ), 20, 2 );

		// Courses.
		if ( class_exists( 'memberpress\courses\models\course' ) ) {
			add_action( 'add_meta_boxes', array( $this, 'add_courses_meta_box' ) );
			add_action( 'mpcs_completed_course', array( $this, 'course_completed' ) );
			add_action( 'mpcs_completed_lesson', array( $this, 'lesson_completed' ) );
			add_action( 'mpcs_completed_lesson', array( $this, 'quiz_progress' ) );
		}

		// Batch operations.
		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_action( 'wpf_batch_memberpress_init', array( $this, 'batch_init_subscriptions' ) );
		add_action( 'wpf_batch_memberpress', array( $this, 'batch_step_subscriptions' ) );

		add_action( 'wpf_batch_memberpress_transactions_init', array( $this, 'batch_init_transactions' ) );
		add_action( 'wpf_batch_memberpress_transactions', array( $this, 'sync_transaction_fields' ) );

		add_action( 'wpf_batch_memberpress_memberships_init', array( $this, 'batch_init_memberships' ) );
		add_action( 'wpf_batch_memberpress_memberships', array( $this, 'batch_step_memberships' ) );

	}

	/**
	 * Syncs any enabled fields for a transaction.
	 *
	 * @since 3.41.46
	 *
	 * @param int $transaction_id The transaction ID.
	 */
	public function sync_transaction_fields( $transaction_id ) {

		$txn = new MeprTransaction( $transaction_id );

		$user_id = $txn->user_id;

		if ( ! $user_id ) {
			return;
		}

		$payment_method = $txn->payment_method();
		$product_id     = $txn->product_id;

		$update_data = array(
			'mepr_membership_level'   => html_entity_decode( get_the_title( $product_id ) ),
			'mepr_reg_date'           => $txn->created_at,
			'mepr_payment_method'     => $payment_method->name,
			'mepr_transaction_total'  => $txn->total,
			'mepr_transaction_status' => $txn->status,
			'mepr_membership_status'  => $this->get_membership_status( $user_id ),
		);

		// Add expiration only if applicable
		if ( strtotime( $txn->expires_at ) >= 0 && 'subscription_confirmation' !== $txn->txn_type ) {
			$update_data['mepr_expiration'] = $txn->expires_at;
		}

		// Coupons
		if ( ! empty( $txn->coupon_id ) ) {
			$update_data['mepr_coupon'] = get_the_title( $txn->coupon_id );
		}

		wp_fusion()->user->push_user_meta( $user_id, $update_data );

	}

	/**
	 * Get the membership status for a user by ID.
	 *
	 * @since 3.41.46
	 *
	 * @param int $user_id The user ID.
	 * @return string The status.
	 */
	public function get_membership_status( $user_id ) {

		$mepr_user = new MeprUser( $user_id );

		if ( $mepr_user->is_active() ) {
			return 'Active';
		} elseif ( $mepr_user->has_expired() ) {
			return 'Inactive';
		} else {
			return 'None';
		}

	}

	/**
	 * Record quiz progress.
	 *
	 * @since 3.41.15
	 * @param object $progress The quiz object.
	 */
	public function quiz_progress( $progress ) {
		$attrs   = $progress->get_values();
		$quiz_id = $attrs['lesson_id'];
		if ( get_post_type( $quiz_id ) !== 'mpcs-quiz' ) {
			return;
		}

		$settings = get_post_meta( $quiz_id, 'wpf-settings', true );

		$attempt       = \memberpress\courses\models\Attempt::get_one(
			array(
				'quiz_id' => $quiz_id,
				'user_id' => $attrs['user_id'],
			)
		);
		$attempt_attrs = $attempt->get_values();

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_quiz_pass'] ) && intval( $attempt_attrs['score'] ) >= 50 ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_quiz_pass'], $attrs['user_id'] );
		}

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_quiz_fail'] ) && intval( $attempt_attrs['score'] ) < 50 ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_quiz_fail'], $attrs['user_id'] );
		}

	}


	/**
	 * Track lesson completion.
	 *
	 * @since 3.41.15
	 * @param object $progress The lesson object.
	 */
	public function lesson_completed( $progress ) {
		$attrs     = $progress->get_values();
		$lesson_id = $attrs['lesson_id'];
		if ( get_post_type( $lesson_id ) !== 'mpcs-lesson' ) {
			return;
		}

		$settings = get_post_meta( $lesson_id, 'wpf-settings', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_lesson_complete'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_lesson_complete'], $attrs['user_id'] );
		}
	}


	/**
	 * Track course completion.
	 *
	 * @since 3.41.15
	 * @param object $progress The course object.
	 */
	public function course_completed( $progress ) {
		$attrs     = $progress->get_values();
		$course_id = $attrs['course_id'];
		$settings  = get_post_meta( $course_id, 'wpf-settings', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_course_complete'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_course_complete'], $attrs['user_id'] );
		}
	}

	/**
	 * Adds coruse/lesson/quiz meta boxes.
	 *
	 * @since 3.41.15
	 * @access public
	 * @return mixed
	 */
	public function add_courses_meta_box() {

		add_meta_box( 'wpf-memberpress-course-meta', 'WP Fusion - Course Settings', array( $this, 'course_meta_box_callback' ), 'mpcs-course' );

		add_meta_box( 'wpf-memberpress-lesson-meta', 'WP Fusion - Lesson Settings', array( $this, 'lesson_meta_box_callback' ), 'mpcs-lesson' );

		add_meta_box( 'wpf-memberpress-quiz-meta', 'WP Fusion - Quiz Settings', array( $this, 'quiz_meta_box_callback' ), 'mpcs-quiz' );

	}

	/**
	 * Add quiz meta box fields.
	 *
	 * @since 3.41.15
	 * @param object $post The quiz post object.
	 * @return mixed
	 */
	public function quiz_meta_box_callback( $post ) {
		$settings = array(
			'apply_tags_quiz_pass' => array(),
			'apply_tags_quiz_fail' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings', true ) );
		}

		wp_nonce_field( 'wpf_meta_box_memberpress', 'wpf_meta_box_memberpress_nonce' );

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_quiz_pass">Apply tags - Quiz Pass:</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_quiz_pass'],
			'meta_name' => 'wpf-settings',
			'field_id'  => 'apply_tags_quiz_pass',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">These tags will be applied when this quiz is passed.</span>';
		echo '</td>';

		echo '</tr>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_quiz_fail">Apply tags - Quiz Fail:</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_quiz_fail'],
			'meta_name' => 'wpf-settings',
			'field_id'  => 'apply_tags_quiz_fail',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">These tags will be applied when this quiz is failed.</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';
	}

	/**
	 * Add lesson meta box fields.
	 *
	 * @since 3.41.15
	 * @param object $post The lesson post object.
	 * @return mixed
	 */
	public function lesson_meta_box_callback( $post ) {
		$settings = array(
			'apply_tags_lesson_complete' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings', true ) );
		}

		wp_nonce_field( 'wpf_meta_box_memberpress', 'wpf_meta_box_memberpress_nonce' );

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_lesson_complete">Apply tags - Lesson Complete:</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_lesson_complete'],
			'meta_name' => 'wpf-settings',
			'field_id'  => 'apply_tags_lesson_complete',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">These tags will be applied when this lesson is completed.</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';
	}


	/**
	 * Add course meta box fields.
	 *
	 * @since 3.41.15
	 * @param object $post The course post object.
	 * @return mixed
	 */
	public function course_meta_box_callback( $post ) {
		$settings = array(
			'apply_tags_course_complete' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings', true ) );
		}

		wp_nonce_field( 'wpf_meta_box_memberpress', 'wpf_meta_box_memberpress_nonce' );

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="apply_tags_course_complete">Apply tags - Course Complete:</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_course_complete'],
			'meta_name' => 'wpf-settings',
			'field_id'  => 'apply_tags_course_complete',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">These tags will be applied when this course is completed.</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';
	}


	/**
	 * Adds a user to a membership level if a "link" tag is applied
	 *
	 * @access public
	 * @return void
	 */

	public function add_to_membership( $user_id, $user_tags ) {

		$linked_products = get_posts(
			array(
				'post_type'  => 'memberpressproduct',
				'nopaging'   => true,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => 'wpf-settings-memberpress',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( empty( $linked_products ) ) {
			return;
		}

		// Update role based on user tags
		foreach ( $linked_products as $product_id ) {

			$settings = get_post_meta( $product_id, 'wpf-settings-memberpress', true );

			if ( empty( $settings ) || empty( $settings['tag_link'] ) ) {
				continue;
			}

			$tag_id = $settings['tag_link'][0];

			$mepr_user = new MeprUser( $user_id );

			if ( in_array( $tag_id, $user_tags ) && ! $mepr_user->is_already_subscribed_to( $product_id ) ) {

				// Prevent looping
				remove_action( 'mepr-txn-status-complete', array( $this, 'apply_tags_checkout' ) );

				// Auto enroll
				wpf_log( 'info', $user_id, 'User auto-enrolled in <a href="' . admin_url( 'post.php?post=' . $product_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $product_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

				// Create the MemberPress transaction
				$txn             = new MeprTransaction();
				$txn->user_id    = $user_id;
				$txn->product_id = $product_id;
				$txn->txn_type   = 'subscription_confirmation';
				$txn->gateway    = 'manual';
				$txn->created_at = current_time( 'mysql' );

				$product = new MeprProduct( $txn->product_id );

				// Can't use $txn->create_free_transaction( $txn ); since it forces a redirect, so copied the code from MeprTransaction
				if ( $product->period_type != 'lifetime' ) { // A free recurring subscription? Nope - let's make it lifetime for free here folks

					$expires_at = MeprUtils::db_lifetime();

				} else {
					$product_expiration = $product->get_expires_at( strtotime( $txn->created_at ) );

					if ( is_null( $product_expiration ) ) {
						$expires_at = MeprUtils::db_lifetime();
					} else {
						$expires_at = MeprUtils::ts_to_mysql_date( $product_expiration, 'Y-m-d 23:59:59' );
					}
				}

				$txn->trans_num  = MeprTransaction::generate_trans_num();
				$txn->status     = 'pending';
				$txn->txn_type   = 'payment';
				$txn->gateway    = 'free';
				$txn->expires_at = $expires_at;

				// This will only work before maybe_cancel_old_sub is run
				$upgrade   = $txn->is_upgrade();
				$downgrade = $txn->is_downgrade();

				$event_txn   = $txn->maybe_cancel_old_sub();
				$txn->status = 'complete';
				$txn->store();

				$free_gateway = new MeprBaseStaticGateway( 'free', __( 'Free', 'memberpress' ), __( 'Free', 'memberpress' ) );

				if ( $upgrade ) {

					$free_gateway->upgraded_sub( $txn, $event_txn );

				} elseif ( $downgrade ) {

					$free_gateway->downgraded_sub( $txn, $event_txn );

				}

				MeprEvent::record( 'transaction-completed', $txn ); // Delete this if we use $free_gateway->send_transaction_receipt_notices later
				MeprEvent::record( 'non-recurring-transaction-completed', $txn ); // Delete this if we use $free_gateway->send_transaction_receipt_notices later

				remove_action( 'mepr-signup', array( $this, 'apply_tags_checkout' ) );

				MeprHooks::do_action( 'mepr-signup', $txn ); // This lets the Corportate Accounts addon know there's been a new signup

				add_action( 'mepr-signup', array( $this, 'apply_tags_checkout' ) );

			} elseif ( ! in_array( $tag_id, $user_tags ) && $mepr_user->is_already_subscribed_to( $product_id ) ) {

				// Auto un-enroll
				wpf_log( 'info', $user_id, 'User unenrolled from <a href="' . admin_url( 'post.php?post=' . $product_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $product_id ) . '</a> by linked tag <strong>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</strong>' );

				$transactions = $mepr_user->active_product_subscriptions( 'transactions' );
				$did_it       = false;

				foreach ( $transactions as $transaction ) {

					if ( $transaction->product_id == $product_id && ( 'free' == $transaction->gateway || 'manual' == $transaction->gateway ) ) {

						remove_action( 'mepr-event-transaction-expired', array( $this, 'transaction_expired' ), 20 ); // no need to apply Expired tags

						$transaction->destroy();
						$did_it = true;

						add_action( 'mepr-event-transaction-expired', array( $this, 'transaction_expired' ), 20 );

					}
				}

				if ( ! $did_it ) {
					wpf_log( 'notice', $user_id, 'Automated unenrollment failed. For security reasons WP Fusion can only unenroll users from transactions created with the "free" or "manual" gateways.' );
				}
			}
		}

	}

	/**
	 * Formats special fields for sending
	 *
	 * @access  public
	 * @return  array User meta
	 */

	private function format_fields( $user_meta, $remove_empty = false ) {

		if ( empty( $user_meta ) ) {
			return $user_meta;
		}

		$mepr_options = MeprOptions::fetch();

		foreach ( $user_meta as $key => $value ) {

			// Convert checkboxes to an array of their labels (not values).
			if ( is_array( $value ) && ( 'checkboxes' === wpf_get_field_type( $key ) || 'multiselect' === wpf_get_field_type( $key ) ) ) {

				$value_labels = array();

				foreach ( $mepr_options->custom_fields as $field_object ) {

					if ( $field_object->field_key == $key ) {

						foreach ( $field_object->options as $option ) {

							if ( isset( $value[ $option->option_value ] ) ) {

								// Checkboxes.
								$value_labels[] = $option->option_name;

							} elseif ( in_array( $option->option_value, $value, true ) ) {

								// Multiselects.
								$value_labels[] = $option->option_name;

							}
						}

						$user_meta[ $key ] = $value_labels;

					}
				}
			} elseif ( 'radio' === wpf_get_field_type( $key ) ) {

				foreach ( $mepr_options->custom_fields as $field_object ) {

					if ( $field_object->field_key == $key ) {

						foreach ( $field_object->options as $option ) {

							if ( $option->option_value == $value ) {

								$user_meta[ $key ] = $option->option_name;

							}
						}
					}
				}
			} elseif ( 'checkbox' === wpf_get_field_type( $key ) ) {

				// MemberPress checkboxes sync as 'on' by default.

				$user_meta[ $key ] = true;

			}
		}

		// Possibly clear out empty checkboxes if it's a MP form.

		if ( $remove_empty ) {

			foreach ( $mepr_options->custom_fields as $field_object ) {

				if ( 'checkbox' === $field_object->field_type && $field_object->show_in_account && ! isset( $user_meta[ $field_object->field_key ] ) ) {

					$user_meta[ $field_object->field_key ] = null;

				}
			}
		}

		if ( ! empty( $user_meta['mpca_corporate_account_id'] ) && class_exists( 'MPCA_Corporate_Account' ) ) {

			$corporate_user = MPCA_Corporate_Account::get_one( $user_meta['mpca_corporate_account_id'] );

			if ( $corporate_user ) {
				$corporate_wp_user                        = get_user_by( 'id', $corporate_user->user_id );
				$user_meta['mepr_corporate_parent_email'] = $corporate_wp_user->user_email;
			}
		}

		return $user_meta;

	}

	/**
	 * Triggered when new member is added
	 *
	 * @access  public
	 * @return  array Post data
	 */

	public function user_register( $post_data, $user_id ) {

		$field_map = array(
			'user_first_name'    => 'first_name',
			'user_last_name'     => 'last_name',
			'mepr_user_password' => 'user_pass',
		);

		$post_data = $this->map_meta_fields( $post_data, $field_map );
		$post_data = $this->format_fields( $post_data );

		return $post_data;

	}

	/**
	 * Triggered when MemberPress account is saved
	 *
	 * @access  public
	 * @return  void
	 */

	public function save_account( $user ) {

		// Modify post data so user_update knows to remove empty fields
		$_POST['from'] = 'profile';

		wp_fusion()->user->push_user_meta( $user->ID, $_POST );

	}

	/**
	 * Adjusts field formatting for custom MemberPress fields
	 *
	 * @access  public
	 * @return  array User meta
	 */

	public function user_update( $user_meta, $user_id ) {

		if ( isset( $user_meta['from'] ) && $user_meta['from'] == 'profile' ) {
			$remove_empty = true;
		} else {
			$remove_empty = false;
		}

		$user_meta = $this->format_fields( $user_meta, $remove_empty );

		$field_map = array(
			'mepr-new-password' => 'user_pass',
		);

		$user_meta = $this->map_meta_fields( $user_meta, $field_map );

		return $user_meta;

	}

	/**
	 * Loads array type fields into array format
	 *
	 * @access  public
	 * @return  array User Meta
	 */

	public function pulled_user_meta( $user_meta, $user_id ) {

		$mepr_options = MeprOptions::fetch();

		foreach ( $mepr_options->custom_fields as $field_object ) {

			if ( ! empty( $user_meta[ $field_object->field_key ] ) && 'checkboxes' === $field_object->field_type ) {

				if ( ! is_array( $user_meta[ $field_object->field_key ] ) ) {
					$loaded_value = explode( ',', $user_meta[ $field_object->field_key ] );
				} else {
					$loaded_value = $user_meta[ $field_object->field_key ];
				}

				$new_value = array();

				foreach ( $field_object->options as $option ) {

					if ( in_array( $option->option_name, $loaded_value ) ) {
						$new_value[ $option->option_value ] = 'on';
					}
				}

				$user_meta[ $field_object->field_key ] = $new_value;

			} elseif ( ! empty( $user_meta[ $field_object->field_key ] ) && $field_object->field_type == 'radios' ) {

				foreach ( $field_object->options as $option ) {

					if ( $user_meta[ $field_object->field_key ] == $option->option_name ) {

						$user_meta[ $field_object->field_key ] = $option->option_value;

					}
				}
			}
		}

		return $user_meta;

	}

	/**
	 * Triggered when payment for membership / product is complete (for one-time or free billing)
	 *
	 * @access  public
	 * @return  void
	 */

	public function apply_tags_checkout( $event ) {

		// The mepr-signup hook passes a transaction already.
		if ( is_a( $event, 'MeprTransaction' ) ) {
			$txn = $event;
		} else {
			$txn = $event->get_data();
		}

		// Fallback transactions are created with "complete" status when a transaction is refunded, so we don't want to apply any tags for those.

		if ( 'complete' !== $txn->status || 'fallback' === $txn->txn_type ) {
			return;
		}

		// When someone switches between two free (lifetime) memberships, the original transaction triggers a mepr-txn-status-complete
		// with an expiration date of yesterday. If we don't quit here, the details of the expiring transaction are synced instead of
		// the new one.

		if ( $txn->is_expired() ) {
			return;
		}

		// No need to run this twice if another action fires.
		remove_action( 'mepr-signup', array( $this, 'apply_tags_checkout' ) );
		remove_action( 'mepr-event-non-recurring-transaction-completed', array( $this, 'apply_tags_checkout' ) );

		if ( current_action() !== 'mepr-txn-status-complete' ) {
			// This allows the callback to run multiple times when importing users from a .csv using the Corporate Accounts addon.
			remove_action( 'mepr-txn-status-complete', array( $this, 'apply_tags_checkout' ) );
		}

		// Logger.
		wpf_log( 'info', $txn->user_id, 'New MemberPress transaction <a href="' . admin_url( 'admin.php?page=memberpress-trans&action=edit&id=' . $txn->id ) . '" target="_blank">#' . $txn->id . '</a> (' . current_action() . ')' );

		//
		// Get meta fields
		//

		$payment_method = $txn->payment_method();
		$product_id     = $txn->product_id;

		$update_data = array(
			'mepr_membership_level'  => html_entity_decode( get_the_title( $product_id ) ),
			'mepr_reg_date'          => $txn->created_at,
			'mepr_payment_method'    => $payment_method->name,
			'mepr_transaction_total' => $txn->total,
			'mepr_membership_status' => $this->get_membership_status( $txn->user_id ),
		);

		// The subscription total can be different from the transaction total in cases of discounts, trials, etc.
		$subscription = $txn->subscription();

		if ( false !== $subscription ) {
			$update_data['mepr_sub_total'] = $subscription->total;
		}

		// Add expiration only if applicable
		if ( strtotime( $txn->expires_at ) >= 0 && 'subscription_confirmation' !== $txn->txn_type ) {
			$update_data['mepr_expiration'] = $txn->expires_at;
		}

		// Coupons
		if ( ! empty( $txn->coupon_id ) ) {
			$update_data['mepr_coupon'] = get_the_title( $txn->coupon_id );
		}

		// Push all meta as well to get any updated custom field values during upgrades, if it's not a new user.
		if ( ! did_action( 'wpf_user_created' ) ) {
			$user_meta   = wp_fusion()->user->get_user_meta( $txn->user_id );
			$update_data = array_merge( $user_meta, $update_data );
		}

		// Corporate account data.
		$corporate_account = get_user_meta( $txn->user_id, 'mpca_corporate_account_id', true );

		if ( ! empty( $corporate_account ) ) {

			$corporate_user = MPCA_Corporate_Account::get_one( $corporate_account );

			if ( $corporate_user ) {
				$corporate_user_id                          = $corporate_user->user_id;
				$corporate_wp_user                          = get_user_by( 'id', $corporate_user_id );
				$update_data['mepr_corporate_parent_email'] = $corporate_wp_user->user_email;
			}
		}

		wp_fusion()->user->push_user_meta( $txn->user_id, $update_data );

		//
		// Remove any tags from previous levels where Remove Tags is checked
		//

		$remove_tags = array();

		$user = new MeprUser( $txn->user_id );

		$transactions = $user->transactions();

		if ( ! empty( $transactions ) ) {

			$product_ids = array();

			foreach ( $transactions as $transaction ) {

				// Don't run on this one

				if ( $transaction->id === $txn->id || $transaction->product_id === $txn->product_id ) {
					continue;
				}

				// Don't remove any tags if the user is still subscribed (via concurrent memberships)

				if ( $user->is_already_subscribed_to( $transaction->product_id ) ) {
					continue;
				}

				// Don't need to do it more than once per product

				if ( in_array( $transaction->product_id, $product_ids ) ) {
					continue;
				}

				$product_ids[] = $transaction->product_id;

				$settings = get_post_meta( $transaction->product_id, 'wpf-settings-memberpress', true );

				if ( empty( $settings ) || empty( $settings['remove_tags'] ) || empty( $settings['apply_tags_registration'] ) ) {
					continue;
				}

				// If "remove tags" is checked and we're no longer at that level, remove them.

				wpf_log( 'info', $txn->user_id, 'User is no longer at level <strong>' . get_the_title( $transaction->product_id ) . ' and Remove Tags is checked on that level. Removing tags.' );

				$remove_tags = array_merge( $remove_tags, $settings['apply_tags_registration'] );

			}
		}

		if ( ! empty( $remove_tags ) ) {

			// Prevent looping when tags are applied
			remove_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

			wp_fusion()->user->remove_tags( $remove_tags, $txn->user_id );

			add_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

		}

		//
		// Update tags based on the product purchased
		//

		$apply_tags = array();

		$remove_tags = array();

		$settings = get_post_meta( $txn->product_id, 'wpf-settings-memberpress', true );

		if ( ! empty( $settings ) ) {

			if ( ! empty( $settings['apply_tags_registration'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['apply_tags_registration'] );
			}

			if ( ! empty( $settings['tag_link'] ) ) {
				$apply_tags = array_merge( $apply_tags, $settings['tag_link'] );
			}

			if ( ! empty( $settings['apply_tags_payment_failed'] ) ) {

				// Remove any failed tags.
				$remove_tags = array_merge( $remove_tags, $settings['apply_tags_payment_failed'] );

			}

			if ( ! empty( $settings['apply_tags_expired'] ) ) {

				// Remove any failed tags.
				$remove_tags = array_merge( $remove_tags, $settings['apply_tags_expired'] );

			}

			if ( ! empty( $settings['apply_tags_pending'] ) ) {

				// Remove any pending tags.
				$remove_tags = array_merge( $remove_tags, $settings['apply_tags_pending'] );

			}

			// If this transaction was against a subscription that had a trial, and is no longer in a trial, consider it "converted"
			$subscription = $txn->subscription();

			if ( false !== $subscription && true == $subscription->trial ) {

				// Figure out if it's the first real payment

				$first_payment = false;

				if ( $subscription->trial_amount > 0.00 && $subscription->txn_count == 2 ) {
					$first_payment = true;
				} elseif ( $subscription->trial_amount == 0.00 && $subscription->txn_count == 1 ) {
					$first_payment = true;
				}

				if ( true == $first_payment && ! empty( $settings['apply_tags_converted'] ) ) {

					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_converted'] );

				}
			}
		}

		// Coupons
		if ( ! empty( $txn->coupon_id ) ) {

			$coupon_settings = get_post_meta( $txn->coupon_id, 'wpf-settings', true );

			if ( ! empty( $coupon_settings ) && ! empty( $coupon_settings['apply_tags_coupon'] ) ) {
				$apply_tags = array_merge( $apply_tags, $coupon_settings['apply_tags_coupon'] );
			}
		}

		// Corporate accounts

		if ( ! empty( $corporate_account ) && ! empty( $settings['apply_tags_corporate_accounts'] ) ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_corporate_accounts'] );
		}

		// Prevent looping when tags are applied
		remove_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

		wp_fusion()->user->remove_tags( $remove_tags, $txn->user_id );

		wp_fusion()->user->apply_tags( $apply_tags, $txn->user_id );

		add_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

		$txn->update_meta( 'wpf_complete', current_time( 'Y-m-d H:i:s' ) );

	}

	/**
	 * Apply refunded tags
	 *
	 * @access  public
	 * @return  void
	 */

	public function transaction_refunded( $txn ) {

		$settings = get_post_meta( $txn->product_id, 'wpf-settings-memberpress', true );

		remove_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

		if ( ! empty( $settings['tag_link'] ) ) {
			wp_fusion()->user->remove_tags( $settings['tag_link'], $txn->user_id );
		}

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_refunded'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_refunded'], $txn->user_id );
		}

		add_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

	}


	/**
	 * Apply pending tags.
	 *
	 * @since 3.40.11
	 *
	 * @param MeprTransaction $txn The transaction.
	 */
	public function transaction_pending( $txn ) {

		$settings = get_post_meta( $txn->product_id, 'wpf-settings-memberpress', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_pending'] ) ) {

			remove_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

			wp_fusion()->user->apply_tags( $settings['apply_tags_pending'], $txn->user_id );

			add_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

		}

	}

	/**
	 * Sync transaction statuses when they're changed.
	 *
	 * @since 3.41.43
	 *
	 * @param string          $old_status The old status.
	 * @param string          $new_status The new status.
	 * @param MeprTransaction $txn The transaction.
	 */
	public function sync_transaction_status( $old_status, $new_status, $txn ) {

		$this->sync_transaction_fields( $txn->id );

	}

	/**
	 * Applies tags when a recurring transaction fails
	 *
	 * @access  public
	 * @return  void
	 */

	public function recurrring_transaction_failed( $event ) {

		$txn = $event->get_data();

		$settings = get_post_meta( $txn->product_id, 'wpf-settings-memberpress', true );

		remove_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

		// A payment failure removes them from the membership so we need to prevent the linked tag from re-enrolling them

		if ( ! empty( $settings['tag_link'] ) ) {
			wp_fusion()->user->remove_tags( $settings['tag_link'], $txn->user_id );
		}

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_payment_failed'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_payment_failed'], $txn->user_id );
		}

		add_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

	}


	/**
	 * Removes tags when a recurring transaction is complete
	 *
	 * @access  public
	 * @return  void
	 */

	public function recurrring_transaction_completed( $event ) {

		$txn = $event->get_data();

		$settings = get_post_meta( $txn->product_id, 'wpf-settings-memberpress', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_payment_failed'] ) ) {
			wp_fusion()->user->remove_tags( $settings['apply_tags_payment_failed'], $txn->user_id );
		}

		if ( ! empty( $settings['apply_tags_expired'] ) ) {
			wp_fusion()->user->remove_tags( $settings['apply_tags_expired'], $txn->user_id );
		}

	}

	/**
	 * Apply expired tags.
	 *
	 * @since 3.22.3
	 * @since 3.40.28 Moved from mepr-event-transaction-expired to mepr-txn-expired hook.
	 *
	 * @param Mepr_Transaction $txn The expiring transaction.
	 */
	public function transaction_expired( $txn ) {

		$subscription = $txn->subscription();

		if ( strtotime( $txn->expires_at ) <= time() && ( empty( $subscription ) || $subscription->is_expired() ) ) {

			$settings = get_post_meta( $txn->product_id, 'wpf-settings-memberpress', true );

			if ( empty( $settings ) ) {
				return;
			}

			// Extra check to see if the user might have a separate active transaction to the same product.

			$member = new MeprUser( $txn->user_id );

			if ( $member->is_already_subscribed_to( $txn->product_id ) ) {

				wpf_log(
					'notice',
					$txn->user_id,
					'Transaction <a href="' . admin_url( 'admin.php?page=memberpress-trans&action=edit&id=' . $txn->id ) . '" target="_blank">#' . $txn->id . '</a> expired for product <a href="' . get_edit_post_link( $txn->product_id ) . '" target="_blank">' . get_the_title( $txn->product_id ) . '</a>, but user still has another active subscription to the same product, so the status change will be ignored.'
				);

				return;

			}

			wpf_log( 'info', $txn->user_id, 'Transaction <a href="' . admin_url( 'admin.php?page=memberpress-trans&action=edit&id=' . $txn->id ) . '" target="_blank">#' . $txn->id . '</a> expired for product <a href="' . get_edit_post_link( $txn->product_id ) . '" target="_blank">' . get_the_title( $txn->product_id ) . '</a>.', array( 'source' => 'memberpress' ) );

			remove_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

			if ( ! empty( $settings['tag_link'] ) ) {
				wp_fusion()->user->remove_tags( $settings['tag_link'], $txn->user_id );
			}

			if ( ! empty( $settings['remove_tags'] ) ) {
				wp_fusion()->user->remove_tags( $settings['apply_tags_registration'], $txn->user_id );
			}

			if ( ! empty( $settings['apply_tags_expired'] ) ) {
				wp_fusion()->user->apply_tags( $settings['apply_tags_expired'], $txn->user_id );
			}

			add_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

		}

	}

	/**
	 * Runs during upgrades / downgrades, gets any tags to remove from previous
	 * transactions where Remove Tags is checked on the membership product.
	 *
	 * @since 3.40.51
	 *
	 * @param int $user_id The user ID.
	 * @param int $product_id The product ID.
	 * @return array The tags to remove.
	 */
	public function get_remove_tags_from_prior_transactions( $user_id, $product_id ) {

		$remove_tags = array();

		$user = new MeprUser( $user_id );

		$transactions = $user->transactions();

		if ( ! empty( $transactions ) ) {

			$product_ids = array();

			foreach ( $transactions as $transaction ) {

				// Don't run on this one.

				if ( $transaction->product_id === $product_id || 'pending' === $transaction->status ) {
					continue;
				}

				// Don't remove any tags if the user is still subscribed (via concurrent memberships).

				if ( $user->is_already_subscribed_to( $transaction->product_id ) ) {
					continue;
				}

				// Don't need to do it more than once per product.

				if ( in_array( $transaction->product_id, $product_ids ) ) {
					continue;
				}

				$product_ids[] = $transaction->product_id;

				$settings = get_post_meta( $transaction->product_id, 'wpf-settings-memberpress', true );

				if ( empty( $settings ) || empty( $settings['remove_tags'] ) ) {
					continue;
				}

				// If "remove tags" is checked and we're no longer at that level, remove them.

				$remove_tags = array_merge( $remove_tags, $settings['apply_tags_registration'] );

			}
		}

		return $remove_tags;

	}

	/**
	 * Subscription upgraded.
	 *
	 * Runs when an existing subscription is upgraded to a new subscription.
	 * Does not run if a trial or free transaction is upgraded to a
	 * subscription.
	 *
	 * @since 3.35.8
	 *
	 * @param string           $type   The subscription type (recurring or
	 *                                 single).
	 * @param MeprSubscription $sub    The subscription object.
	 */

	public function upgraded_subscription( $type, $sub ) {

		$settings = get_post_meta( $sub->product_id, 'wpf-settings-memberpress', true );

		if ( empty( $settings ) ) {
			return;
		}

		if ( ! empty( $settings['apply_tags_upgraded'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_upgraded'], $sub->user_id );
		}

		// Remove any tags from previous levels where Remove Tags is checked.

		$remove_tags = $this->get_remove_tags_from_prior_transactions( $sub->user_id, $sub->product_id );

		if ( ! empty( $remove_tags ) ) {
			wp_fusion()->user->remove_tags( $remove_tags, $sub->user_id );
		}

	}

	/**
	 * Subscription downgraded.
	 *
	 * Runs when an existing subscription is downgraded to a new subscription.
	 *
	 * @since 3.35.8
	 *
	 * @param string           $type   The subscription type (recurring or
	 *                                 single).
	 * @param MeprSubscription $sub    The subscription object.
	 */

	public function downgraded_subscription( $type, $sub ) {

		$settings = get_post_meta( $sub->product_id, 'wpf-settings-memberpress', true );

		if ( empty( $settings ) ) {
			return;
		}

		if ( ! empty( $settings['apply_tags_downgraded'] ) ) {
			wp_fusion()->user->apply_tags( $settings['apply_tags_downgraded'], $sub->user_id );
		}

		// Remove any tags from previous levels where Remove Tags is checked.

		$remove_tags = $this->get_remove_tags_from_prior_transactions( $sub->user_id, $sub->product_id );

		if ( ! empty( $remove_tags ) ) {
			wp_fusion()->user->remove_tags( $remove_tags, $sub->user_id );
		}

	}

	/**
	 * Apply tags for corporate / sub-accounts
	 *
	 * @access  public
	 * @return  void
	 */

	public function corporate_accounts_tagging( $txn ) {

		if ( 'sub_account' === $txn->txn_type ) {

			$settings = get_post_meta( $txn->product_id, 'wpf-settings-memberpress', true );

			if ( ! empty( $settings['apply_tags_corporate_accounts'] ) ) {
				wp_fusion()->user->apply_tags( $settings['apply_tags_corporate_accounts'], $txn->user_id );
			}
		}

	}

	/**
	 * Triggered when a subscription status is changed
	 *
	 * @access  public
	 * @return  void
	 */

	public function subscription_status_changed( $old_status, $new_status, $subscription ) {

		// Sometimes during registration a subscription status change is triggered when there is no
		// change (i.e. from Active to Active). We can ignore these.
		//
		// NB: Until v3.40.54, this was running on new Pending subscriptions. That is now handled
		// by $this->transaction_pending().

		if ( $old_status === $new_status ) {
			return;
		}

		if ( 'pending' === $old_status && 'cancelled' === $new_status ) {
			return; // failed initial transactions (i.e. a Stripe card decline).
		}

		// Don't require the checkout callback
		remove_action( 'mepr-signup', array( $this, 'apply_tags_checkout' ) );
		remove_action( 'mepr-event-transaction-completed', array( $this, 'apply_tags_checkout' ) );
		remove_action( 'mepr-txn-status-complete', array( $this, 'apply_tags_checkout' ) );

		// Get subscription data.
		$data = $subscription->get_values();

		wpf_log(
			'info',
			$data['user_id'],
			sprintf(
				// translators: 1: MemberPress subscription ID, 2: MemberPress subscription product, 3: Old status, 4: New status.
				esc_html__( 'Memberpress subscription %1$s to %2$s status changed from %3$s to %4$s.', 'wp-fusion' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=memberpress-subscriptions&action=edit&id=' . $subscription->id ) ) . '">#' . esc_html( $subscription->id ) . '</a>',
				'<a href="' . esc_url( admin_url( 'post.php?post=' . $data['product_id'] . '&action=edit' ) ) . '">' . esc_html( get_the_title( $data['product_id'] ) ) . '</a>',
				'<strong>' . esc_html( ucwords( $old_status ) ) . '</strong>',
				'<strong>' . esc_html( ucwords( $new_status ) ) . '</strong>'
			)
		);

		// Get WPF settings
		$settings = get_post_meta( $data['product_id'], 'wpf-settings-memberpress', true );

		if ( empty( $settings ) ) {
			$settings = array();
		}

		$defaults = array(
			'apply_tags_registration'       => array(),
			'apply_tags_pending'            => array(),
			'remove_tags'                   => false,
			'tag_link'                      => array(),
			'apply_tags_suspended'          => array(),
			'apply_tags_cancelled'          => array(),
			'apply_tags_expired'            => array(),
			'apply_tags_payment_failed'     => array(),
			'apply_tags_corporate_accounts' => array(),
			'apply_tags_trial'              => array(),
			'apply_tags_converted'          => array(),
		);

		$settings = wp_parse_args( $settings, $defaults );

		$apply_tags  = array();
		$remove_tags = array();

		// Pending subscriptions.
		if ( 'pending' === $new_status ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_pending'] );
		}

		// New subscriptions.
		if ( 'active' === $new_status ) {

			$payment_method = $subscription->payment_method();

			if ( $payment_method instanceof MeprArtificialGateway && $payment_method->settings->manually_complete ) {

				// This happens when using the offline payment gateway, and "Admin Must Manually Complete Transactions" is enabled,
				// an active subscription is created, but the user has no transaction, therefore the user is not "subscribed" to
				// the product.

				wpf_log( 'notice', $data['user_id'], 'Subscription status was changed to active, but the transaction requires admin approval. No tags will be applied.' );
				return;
			}

			// Apply tags.
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_registration'], $settings['tag_link'] );

			// Remove cancelled / expired tags.
			$remove_tags = array_merge( $remove_tags, $settings['apply_tags_cancelled'], $settings['apply_tags_expired'], $settings['apply_tags_suspended'], $settings['apply_tags_pending'] );

			// Update data.
			$update_data = array(
				'mepr_reg_date'          => $data['created_at'],
				'mepr_payment_method'    => $payment_method->name,
				'mepr_membership_level'  => html_entity_decode( get_the_title( $data['product_id'] ) ),
				'mepr_expiration'        => $subscription->expires_at,
				'mepr_sub_status'        => $new_status,
				'mepr_sub_total'         => $subscription->total,
				'mepr_membership_status' => $this->get_membership_status( $data['user_id'] ),
			);

			// Sync trial duration and expiration.
			if ( $subscription->trial ) {
				$update_data['mepr_trial_duration'] = $subscription->trial_days;
				$update_data['mepr_expiration']     = gmdate( 'c', strtotime( $data['created_at'] ) + MeprUtils::days( $subscription->trial_days ) );
			}

			// Coupon used
			if ( ! empty( $subscription->coupon_id ) ) {

				$update_data['mepr_coupon'] = get_the_title( $subscription->coupon_id );

				$coupon_settings = get_post_meta( $subscription->coupon_id, 'wpf-settings', true );

				if ( ! empty( $coupon_settings ) && ! empty( $coupon_settings['apply_tags_coupon'] ) ) {
					$apply_tags = array_merge( $apply_tags, $coupon_settings['apply_tags_coupon'] );
				}
			}

			// Push all meta as well to get any updated custom field values during upgrades, if it's not a new user.
			if ( ! did_action( 'wpf_user_created' ) ) {
				$user_meta   = wp_fusion()->user->get_user_meta( $data['user_id'] );
				$update_data = array_merge( $user_meta, $update_data );
			}

			wp_fusion()->user->push_user_meta( $data['user_id'], $update_data );

		}

		// Other status changes
		if ( $subscription->is_expired() && ! in_array( $new_status, array( 'active', 'pending' ) ) ) {

			// Expired subscription
			$remove_tags = array_merge( $remove_tags, $settings['tag_link'] );
			$apply_tags  = array_merge( $apply_tags, $settings['apply_tags_expired'] );

			if ( ! empty( $settings['remove_tags'] ) ) {
				$remove_tags = array_merge( $remove_tags, $settings['apply_tags_registration'] );
			}
		}

		if ( 'cancelled' === $new_status ) {

			// Cancelled subscription
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_cancelled'] );

		} elseif ( 'suspended' === $new_status ) {

			// Paused / suspended subscription
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_suspended'] );

		} if ( 'active' === $new_status && 'suspended' === $old_status ) {

			// Reactivated subscription
			$apply_tags  = array_merge( $apply_tags, $settings['apply_tags_resumed'] );

		} elseif ( $subscription->in_trial() ) {

			// If is in a trial and isn't cancelled / expired
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_trial'] );

		}

		// We don't want to remove any tags from plans that the user is still subscribed to (@since 3.41.21).

		$active_subscription_tags    = array();
		$cancelled_subscription_tags = array();
		$mepr_user                   = new MeprUser( $data['user_id'] );
		$active_subscriptions        = $mepr_user->subscriptions();

		foreach ( $active_subscriptions as $active_subscription ) {

			if ( ! is_a( $active_subscription, 'MeprSubscription' ) ) {
				continue;
			}

			if ( $active_subscription->id === $subscription->id || 'active' !== $active_subscription->status ) {
				continue;
			}

			$settings = get_post_meta( $active_subscription->product_id, 'wpf-settings-memberpress', true );

			if ( empty( $settings ) ) {
				continue;
			}

			if ( ! empty( $settings['apply_tags_registration'] ) ) {

				$diff = array_intersect( $remove_tags, $settings['apply_tags_registration'] );

				if ( $diff ) {

					wpf_log(
						'notice',
						$data['user_id'],
						'Memberpress subscription <a href="' . admin_url( 'post.php?post=' . $subscription->product_id . '&action=edit' ) . '" target="_blank">#' . $subscription->product_id . '</a> status changed to <strong>' . $new_status . '</strong>, but user still has another active subscription to membership <a href="' . admin_url( 'post.php?post=' . $active_subscription->product_id . '&action=edit' ) . '" target="_blank">' . get_the_title( $active_subscription->product_id ) . '</a>, so the tag(s) <strong>' . implode( ', ', array_map( 'wpf_get_tag_label', $diff ) ) . '</strong> will not be removed.'
					);

				}

				$active_subscription_tags = array_merge( $active_subscription_tags, $settings['apply_tags_registration'] );

			}

			// Also don't apply cancelled tags if they are still active on another subscription.
			if ( ! empty( $settings['apply_tags_cancelled'] ) ) {
				$cancelled_subscription_tags = array_merge( $cancelled_subscription_tags, $settings['apply_tags_cancelled'] );
			}
		}

		if ( 'active' !== $new_status ) {

			// This was synced for the active status above.

			$update_data = array(
				'mepr_sub_status'        => $new_status,
				'mepr_membership_status' => $this->get_membership_status( $data['user_id'] ),
			);

			wp_fusion()->user->push_user_meta( $data['user_id'], $update_data );

		}

		$remove_tags = array_diff( $remove_tags, $active_subscription_tags );
		$apply_tags  = array_diff( $apply_tags, $cancelled_subscription_tags );

		// Prevent looping when tags are modified
		remove_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

		// Remove any tags
		if ( ! empty( $remove_tags ) ) {
			wp_fusion()->user->remove_tags( $remove_tags, $data['user_id'] );
		}

		// Apply any tags
		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $data['user_id'] );
		}

		add_action( 'wpf_tags_modified', array( $this, 'add_to_membership' ), 10, 2 );

	}

	/**
	 * Adds MemberPress field group to meta fields list
	 *
	 * @access  public
	 * @return  array Field groups
	 */

	public function add_meta_field_group( $field_groups ) {

		if ( ! isset( $field_groups['memberpress'] ) ) {
			$field_groups['memberpress'] = array(
				'title'  => 'MemberPress',
				'fields' => array(),
			);
		}

		return $field_groups;

	}

	/**
	 * Sets field labels and types for Custom MemberPress fields
	 *
	 * @access  public
	 * @return  array Meta fields
	 */

	public function prepare_meta_fields( $meta_fields ) {

		$mepr_options = MeprOptions::fetch();
		$mepr_fields  = array_merge( $mepr_options->custom_fields, $mepr_options->address_fields );

		foreach ( $mepr_fields as $field_object ) {

			if ( 'radios' == $field_object->field_type ) {
				$field_object->field_type = 'radio';
			}

			if ( 'checkboxes' == $field_object->field_type ) {
				$field_object->field_type = 'multiselect';
			}

			$meta_fields[ $field_object->field_key ] = array(
				'label' => $field_object->field_name,
				'type'  => $field_object->field_type,
				'group' => 'memberpress',
			);

			if ( $field_object->field_key == 'mepr-address-country' ) {
				$meta_fields[ $field_object->field_key ]['type'] = 'country';
			}

			if ( $field_object->field_key == 'mepr-address-state' ) {
				$meta_fields[ $field_object->field_key ]['type'] = 'state';
			}
		}

		$meta_fields['mepr_membership_level'] = array(
			'label'  => 'Membership Level Name',
			'type'   => 'text',
			'group'  => 'memberpress',
			'pseudo' => true,
		);

		$meta_fields['mepr_reg_date'] = array(
			'label'  => 'Registration Date',
			'type'   => 'date',
			'group'  => 'memberpress',
			'pseudo' => true,
		);

		$meta_fields['mepr_membership_status'] = array(
			'label'   => 'Membership Status',
			'tooltip' => __( 'Either <strong>Active</strong>, <strong>Inactive</strong>, or <strong>None</strong>' ),
			'type'    => 'text',
			'group'   => 'memberpress',
			'pseudo'  => true,
		);

		$meta_fields['mepr_sub_status'] = array(
			'label'   => 'Subscription Status',
			'tooltip' => __( 'Either <strong>pending</strong>, <strong>active</strong>, <strong>suspended</strong> or <strong>cancelled</strong>' ),
			'type'    => 'text',
			'group'   => 'memberpress',
			'pseudo'  => true,
		);

		$meta_fields['mepr_transaction_status'] = array(
			'label'   => 'Transaction Status',
			'tooltip' => __( 'Either <strong>pending</strong>, <strong>complete</strong>, <strong>failed</strong> or <strong>refunded</strong>' ),
			'type'    => 'text',
			'group'   => 'memberpress',
			'pseudo'  => true,
		);

		$meta_fields['mepr_expiration'] = array(
			'label'  => 'Expiration Date',
			'type'   => 'date',
			'group'  => 'memberpress',
			'pseudo' => true,
		);

		$meta_fields['mepr_trial_duration'] = array(
			'label'  => 'Trial Duration (days)',
			'type'   => 'text',
			'group'  => 'memberpress',
			'pseudo' => true,
		);

		$meta_fields['mepr_payment_method'] = array(
			'label'  => 'Payment Method',
			'type'   => 'text',
			'group'  => 'memberpress',
			'pseudo' => true,
		);

		$meta_fields['mepr_sub_total'] = array(
			'label'  => 'Subscription Total',
			'type'   => 'text',
			'group'  => 'memberpress',
			'pseudo' => true,
		);

		$meta_fields['mepr_transaction_total'] = array(
			'label'  => 'Transaction Total',
			'type'   => 'text',
			'group'  => 'memberpress',
			'pseudo' => true,
		);

		$meta_fields['mepr_coupon'] = array(
			'label'  => 'Coupon Used',
			'type'   => 'text',
			'group'  => 'memberpress',
			'pseudo' => true,
		);

		if ( defined( 'MPCA_PLUGIN_NAME' ) ) {

			$meta_fields['mepr_corporate_parent_email'] = array(
				'label'  => 'Corporate Account Parent Email',
				'type'   => 'email',
				'group'  => 'memberpress',
				'pseudo' => true,
			);
		}

		return $meta_fields;

	}


	/**
	 * Outputs <li> nav item for membership level configuration
	 *
	 * @access public
	 * @return mixed
	 */

	public function output_product_nav_tab( $product ) {

		echo '<a class="nav-tab main-nav-tab" href="#" id="wp-fusion">WP Fusion</a>';

	}

	/**
	 * Outputs tabbed content area for WPF membership settings
	 *
	 * @access public
	 * @return mixed
	 */

	public function output_product_content_tab( $product ) {

		echo '<div class="product_options_page wp-fusion">';

		echo '<div class="product-options-panel">';

		echo '<p>';

		printf( __( 'For more information on these settings, %1$ssee our documentation%2$s.', 'wp-fusion' ), '<a href="https://wpfusion.com/documentation/membership/memberpress/" target="_blank">', '</a>' );

		echo '</p>';

		wp_nonce_field( 'wpf_meta_box_memberpress', 'wpf_meta_box_memberpress_nonce' );

		$settings = array(
			'apply_tags_registration'       => array(),
			'apply_tags_pending'            => array(),
			'remove_tags'                   => false,
			'tag_link'                      => array(),
			'apply_tags_cancelled'          => array(),
			'apply_tags_suspended'          => array(),
			'apply_tags_resumed'            => array(),
			'apply_tags_upgraded'           => array(),
			'apply_tags_downgraded'         => array(),
			'apply_tags_refunded'           => array(),
			'apply_tags_expired'            => array(),
			'apply_tags_payment_failed'     => array(),
			'apply_tags_corporate_accounts' => array(),
			'apply_tags_trial'              => array(),
			'apply_tags_converted'          => array(),
		);

		if ( get_post_meta( $product->ID, 'wpf-settings-memberpress', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $product->ID, 'wpf-settings-memberpress', true ) );
		}

		// Active

		echo '<label><strong>' . __( 'Apply Tags - Active', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_registration'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_registration',
			'no_dupes'  => array( 'tag_link' ),
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . sprintf( __( 'These tags will be applied to the customer in %s upon registering for this membership, as well as when a subscription to this membership changes status to Active, and when a renewal transaction is received (if the member doesn\'t already have the tags).', 'wp-fusion' ), wp_fusion()->crm->name ) . '</small></span>';

		echo '<br /><br /><input class="checkbox" type="checkbox" id="wpf-remove-tags-memberpress" name="wpf-settings-memberpress[remove_tags]" value="1" ' . checked( $settings['remove_tags'], 1, false ) . ' />';
		echo '<label for="wpf-remove-tags-memberpress">' . __( 'Remove original tags (above) when the membership expires or is changed to a different level.', 'wp-fusion' ) . '</label>';

		echo '<br /><br /><label><strong>' . __( 'Link with Tag', 'wp-fusion' ) . ':</strong></label><br >';

		// Tag link

		$args = array(
			'setting'     => $settings['tag_link'],
			'meta_name'   => 'wpf-settings-memberpress',
			'field_id'    => 'tag_link',
			'placeholder' => 'Select Tag',
			'limit'       => 1,
			'no_dupes'    => array( 'apply_tags_registration', 'apply_tags_cancelled' ),
		);

		wpf_render_tag_multiselect( $args );

		echo '<br/><span class="description"><small>' . sprintf( __( 'This tag will be applied in %1$s when a member is registered. Likewise, if this tag is applied to a user from within %2$s, they will be automatically enrolled in this membership. If the tag is removed they will be removed from the membership.', 'wp-fusion' ), wp_fusion()->crm->name, wp_fusion()->crm->name ) . '</small></span><br />';

		// Cancelled

		echo '<br /><label><strong>' . __( 'Apply Tags - Subscription Cancelled', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_cancelled'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_cancelled',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a subscription is cancelled. Happens when an admin or user cancels a subscription, or if the payment gateway has canceled the subscription due to too many failed payments (will be removed if the membership is resumed).', 'wp-fusion' ) . '</small></span><br />';

		// Suspended

		echo '<br /<br /><label><strong>' . __( 'Apply Tags - Subscription Paused', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_suspended'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_suspended',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a subscription is paused.', 'wp-fusion' ) . '</small></span><br />';

		// Resumed

		echo '<br /<br /><label><strong>' . __( 'Apply Tags - Subscription Resumed', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_resumed'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_resumed',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a paused subscription is resumed. The Subscription Paused tags will be removed automatically.', 'wp-fusion' ) . '</small></span><br />';

		// Upgraded

		echo '<br /<br /><label><strong>' . __( 'Apply Tags - Subscription Upgraded', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_upgraded'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_upgraded',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a subscription at another level is upgraded to a subscription at this membership level.', 'wp-fusion' ) . '</small></span><br />';

		// Downgraded

		echo '<br /<br /><label><strong>' . __( 'Apply Tags - Subscription Downgraded', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_downgraded'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_downgraded',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a subscription at another level is downgraded to a subscription at this membership level.', 'wp-fusion' ) . '</small></span>';

		// Payment failed

		echo '<br /><br /><label><strong>' . __( 'Apply Tags - Subscription Payment Failed', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_payment_failed'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_payment_failed',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a recurring payment fails (will be removed if a payment is made).', 'wp-fusion' ) . '</small></span>';

		// Trial

		echo '<br /><br /><label><strong>' . __( 'Apply Tags - Trial', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_trial'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_trial',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a subscription is created in a trial status.', 'wp-fusion' ) . '</small></span>';

		// Converted

		echo '<br /><br /><label><strong>' . __( 'Apply Tags - Subscription Converted', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_converted'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_converted',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a trial converts to a normal subscription.', 'wp-fusion' ) . '</small></span>';

		// Refunded

		echo '<br /><br /><label><strong>' . __( 'Apply Tags - Transaction Refunded', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_refunded'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_refunded',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a transaction is refunded.', 'wp-fusion' ) . '</small></span>';

		// Expired

		echo '<br /><br /><label><strong>' . __( 'Apply Tags - Transaction Expired', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_expired'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_expired',
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a transaction expires (will be removed if the membership is resumed).', 'wp-fusion' ) . '</small></span>';

		// Pending

		echo '<br><br><label><strong>' . __( 'Apply Tags - Pending', 'wp-fusion' ) . ':</strong></label><br />';

		$args = array(
			'setting'   => $settings['apply_tags_pending'],
			'meta_name' => 'wpf-settings-memberpress',
			'field_id'  => 'apply_tags_pending',
			'no_dupes'  => array( 'tag_link' ),
		);

		wpf_render_tag_multiselect( $args );

		echo '<br /><span class="description"><small>' . __( 'Apply these tags when a subscription or transaction is pending.', 'wp-fusion' ) . '</small></span>';

		// Corporate accounts addon

		if ( defined( 'MPCA_PLUGIN_NAME' ) ) {

			echo '<br /><br /><label><strong>' . __( 'Apply Tags - Corporate Accounts', 'wp-fusion' ) . ':</strong></label><br />';

			$args = array(
				'setting'   => $settings['apply_tags_corporate_accounts'],
				'meta_name' => 'wpf-settings-memberpress',
				'field_id'  => 'apply_tags_corporate_accounts',
			);

			wpf_render_tag_multiselect( $args );

			echo '<br /><span class="description"><small>' . __( 'Apply these tags to members added as sub-accounts to this account.', 'wp-fusion' ) . '</small></span>';

		}

		do_action( 'wpf_memberpress_meta_box', $settings, $product );

		echo '</div>';

		echo '</div>';

	}

	/**
	 * Saves data captured in the new interfaces to a post meta field for the membership
	 *
	 * @access public
	 * @return void
	 */

	public function save_meta_box_data( $post_id ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['wpf_meta_box_memberpress_nonce'] ) || ! wp_verify_nonce( $_POST['wpf_meta_box_memberpress_nonce'], 'wpf_meta_box_memberpress' ) || $_POST['post_type'] == 'revision' ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( $_POST['post_type'] == 'memberpressproduct' ) {

			// Memberships
			if ( isset( $_POST['wpf-settings-memberpress'] ) ) {
				$data = $_POST['wpf-settings-memberpress'];
			} else {
				$data = array();
			}

			// Update the meta field in the database.
			update_post_meta( $post_id, 'wpf-settings-memberpress', $data );

		} elseif ( $_POST['post_type'] == 'memberpresscoupon' ) {

			// Coupons
			if ( isset( $_POST['wpf-settings'] ) ) {
				$data = $_POST['wpf-settings'];
			} else {
				$data = array();
			}

			// Update the meta field in the database.
			update_post_meta( $post_id, 'wpf-settings', $data );

		}

	}

	/**
	 * Adds meta box
	 *
	 * @access public
	 * @return mixed
	 */

	public function add_coupon_meta_box( $post_id, $data ) {

		add_meta_box( 'wpf-memberpress-meta', 'WP Fusion - Coupon Settings', array( $this, 'meta_box_callback' ), 'memberpresscoupon' );

	}


	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback( $post ) {

		$settings = array(
			'apply_tags_coupon' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf-settings', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf-settings', true ) );
		}

		wp_nonce_field( 'wpf_meta_box_memberpress', 'wpf_meta_box_memberpress_nonce' );

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">Apply tags:</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_coupon'],
			'meta_name' => 'wpf-settings',
			'field_id'  => 'apply_tags_coupon',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">These tags will be applied when this coupon is used.</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

	}

	/**
	 * Adds status column to transactions list.
	 *
	 * @since 3.41.23
	 *
	 * @param array $columns The columns.
	 * @return array The columns.
	 */
	public function admin_columns( $columns ) {

		$new_column = '<span class="wpf-tip wpf-tip-bottom wpf-woo-column-title" data-tip="' . esc_attr__( 'WP Fusion Status', 'wp-fusion' ) . '"><span>' . __( 'WP Fusion Status', 'wp-fusion' ) . '</span>' . wpf_logo_svg( 14 ) . '</span>';

		return wp_fusion()->settings->insert_setting_after( 'col_status', $columns, array( 'wp_fusion' => $new_column ) );

	}

	/**
	 * Adds content to the status column.
	 *
	 * @since 3.41.23
	 *
	 * @param string $column_name The column name.
	 * @param object $rec         The record.
	 * @param string $attributes  The attributes.
	 */
	public function admin_columns_content( $column_name, $rec, $attributes ) {

		if ( 'wp_fusion' === $column_name ) {

			echo '<td ' . $attributes . '>';

			$txn = new MeprTransaction( $rec->id );

			$complete = $txn->get_meta( 'wpf_complete', true );

			if ( $complete || $txn->get_meta( 'wpf_ec_complete', true ) ) {

				$class = 'success';

				// Get the contact edit URL.

				$contact_id = wpf_get_contact_id( $txn->user_id );

				if ( $contact_id ) {

					$url = wp_fusion()->crm->get_contact_edit_url( $contact_id );

					if ( $url ) {
						$id_text = '<a href="' . esc_url_raw( $url ) . '" target="_blank">#' . esc_html( $contact_id ) . '</a>';
					} else {
						$id_text = '#' . esc_html( $contact_id );
					}
				} else {
					$class   = 'partial-success';
					$id_text = '<em>' . __( 'unknown', 'wp-fusion' ) . '</em>';
				}

				if ( $complete ) {
					$show_date = date_i18n( get_option( 'date_format' ) . ' \a\t ' . get_option( 'time_format' ), strtotime( $complete ) );
				} else {
					$show_date = '<em>' . __( 'unknown', 'wp-fusion' ) . '</em>';
				}
				$tooltip   = sprintf(
					__( 'This transaction was synced to %1$s contact ID %2$s on %3$s.', 'wp-fusion' ),
					esc_html( wp_fusion()->crm->name ),
					$id_text,
					esc_html( $show_date )
				);

				if ( function_exists( 'wp_fusion_ecommerce' ) ) {

					// Enhanced ecommerce.

					if ( $txn->get_meta( 'wpf_ec_complete', true ) ) {

						$invoice_id = $txn->get_meta( 'wpf_ec_' . wp_fusion()->crm->slug . '_invoice_id', true );

						if ( $invoice_id ) {
							$tooltip .= '<br /><br />' . sprintf( __( 'It was processed by Enhanced Ecommerce with invoice ID #%s.', 'wp-fusion' ), $invoice_id );
						} else {
							$tooltip .= '<br /><br />' . __( 'It was processed by Enhanced Ecommerce.', 'wp-fusion' );
						}
					} else {

						$class    = 'partial-success';
						$tooltip .= '<br /><br />' . __( 'It was not processed by Enhanced Ecommerce.', 'wp-fusion' );

					}
				}
			} else {
				$class   = 'fail';
				$tooltip = sprintf( __( 'This transaction was not synced to %s.', 'wp-fusion' ), wp_fusion()->crm->name );
			}

			echo '<i class="icon-wp-fusion wpf-tip wpf-tip-bottom ' . esc_attr( $class ) . '" data-tip="' . esc_attr( $tooltip ) . '"></i>';

			echo '</td>';

		}

	}

	/**
	 * Adds WP Fusion info to a single transaction edit page.
	 *
	 * @since 3.41.23
	 *
	 * @param Mepr_Transaction $txn The transaction
	 * @return mixed HTML output.
	 */
	public function transaction_table_after( $txn ) {

		if ( isset( $_GET['order_action'] ) && 'wpf_process' === $_GET['order_action'] ) {

			$this->apply_tags_checkout( $txn );

			if ( function_exists( 'wp_fusion_ecommerce' ) ) {
				wp_fusion_ecommerce()->integrations->memberpress->transaction_created( $txn );
			}

			// Redirect so the query var is removed.
			wp_safe_redirect( admin_url( 'admin.php?page=memberpress-trans&action=edit&id=' . $txn->id ) );
			exit;

		}

		?>

		<tr id="wp-fusion-user-profile-settings"><th><?php echo wpf_logo_svg(); ?><h2 style="margin: 0;display: inline-block;vertical-align: super;margin-left: 10px;"><?php esc_html_e( 'WP Fusion', 'wp-fusion' ) ?></h2></th></tr>

		<tr class="wp-fusion-status-row">
			<th scope="row"><label><?php printf( __( 'Synced to %s:', 'wp-fusion' ), wp_fusion()->crm->name ); ?></label></th>
			<td>
				<?php if ( $txn->get_meta( 'wpf_complete', true ) ) : ?>
					<span><?php echo date_i18n( get_option( 'date_format' ) . ' \a\t ' . get_option( 'time_format' ), strtotime( $txn->get_meta( 'wpf_complete', true ) ) ); ?></span>
					<span class="dashicons dashicons-yes-alt"></span>
				<?php elseif ( $txn->get_meta( 'wpf_ec_complete', true ) ) : ?>
					<?php // from before we stored wpf_complete on the transaction (3.41.23). ?>
					<span><?php _e( 'Yes', 'wp-fusion' ); ?></span>
					<span class="dashicons dashicons-yes-alt"></span>
				<?php else : ?>
					<span><?php _e( 'No', 'wp-fusion' ); ?></span>
					<span class="dashicons dashicons-no"></span>
				<?php endif; ?>

				<?php if ( 'complete' !== $txn->status ) : ?>

					- <?php esc_html_e( 'Transaction is not Complete', 'wp-fusion' ); ?>

				<?php endif; ?>

			</td>
		</tr>

		<?php $contact_id = wpf_get_contact_id( $txn->user_id ); ?>

		<?php if ( $contact_id ) : ?>

			<tr class="wp-fusion-status-row">
				<th scope="row"><label><?php _e( 'Contact ID:', 'wp-fusion' ); ?></label></th>
				<td>
					<?php echo esc_html( $contact_id ); ?>
					<?php $url = wp_fusion()->crm->get_contact_edit_url( $contact_id ); ?>
					<?php if ( false !== $url ) : ?>
						- <a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php printf( esc_html__( 'View in %s', 'wp-fusion' ), esc_html( wp_fusion()->crm->name ) ); ?> &rarr;</a>
					<?php endif; ?>
				</td>
			</tr>

		<?php endif; ?>

		<?php /* if ( wpf_get_option( 'email_optin' ) ) : ?>

			<tr class="wp-fusion-status-row">
				<th scope="row"><label><?php _e( 'Opted In:', 'wp-fusion' ); ?></label></th>

				<td>
					<?php if ( $txn->get_meta( 'email_optin', true ) ) : ?>
						<span><?php _e( 'Yes', 'wp-fusion' ); ?></span>
						<span class="dashicons dashicons-yes-alt"></span>
					<?php else : ?>
						<span><?php _e( 'No', 'wp-fusion' ); ?></span>
						<span class="dashicons dashicons-no"></span>
					<?php endif; ?>
				</td>
			</tr>

		<?php endif; */ ?>

		<?php if ( class_exists( 'WP_Fusion_Ecommerce' ) ) : ?>

			<tr class="wp-fusion-status-row">
				<th scope="row"><label><?php printf( __( 'Enhanced Ecommerce:', 'wp-fusion' ), wp_fusion()->crm->name ); ?></label></th>
				<td>
					<?php if ( $txn->get_meta( 'wpf_ec_complete', true ) ) : ?>
						<span><?php _e( 'Yes', 'wp-fusion' ); ?></span>
						<span class="dashicons dashicons-yes-alt"></span>
					<?php else : ?>
						<span><?php _e( 'No', 'wp-fusion' ); ?></span>
						<span class="dashicons dashicons-no"></span>
					<?php endif; ?>
				</td>
			</tr>

			<?php $invoice_id = $txn->get_meta( 'wpf_ec_' . wp_fusion()->crm->slug . '_invoice_id', true ); ?>

			<?php if ( $invoice_id ) : ?>

				<tr class="wp-fusion-status-row">
					<th scope="row"><label><?php _e( 'Invoice ID:', 'wp-fusion' ); ?></label></th>
					<td>
						<span><?php echo esc_html( $invoice_id ); ?></span>
					</td>
			</tr>

			<?php endif; ?>

		<?php endif; ?>

		<?php if ( 'complete' === $txn->status ) : ?>

			<tr class="wp-fusion-status-row">

				<th scope="row"><label><?php _e( 'Actions:', 'wp-fusion' ); ?></label></th>
				<td>
					<a
					href="<?php echo esc_url( add_query_arg( array( 'order_action' => 'wpf_process' ) ) ); ?>"
					class="wpf-action-button button-secondary wpf-tip wpf-tip-bottom"
					data-tip="<?php printf( esc_html__( 'The transaction will be processed again as if the customer had just checked out. Any enabled fields will be synced to %s, and any configured tags will be applied.', 'wp-fusion' ), wp_fusion()->crm->name ); ?>">
						<?php _e( 'Process WP Fusion actions again ', 'wp-fusion' ); ?>
					</a>
					<br />
					<br />
				</td>

			</tr>

		<?php endif; ?>

		<?php

	}

	/**
	 * Adds WP Fusion info to a single transaction edit page.
	 *
	 * @since 3.41.23
	 *
	 * @param Mepr_Transaction $txn The transaction
	 * @return mixed HTML output.
	 */
	public function transactions_debug() {

		if ( isset( $_REQUEST['page'] ) && 'memberpress-trans' === $_REQUEST['page'] && isset( $_REQUEST['wpf_debug'] ) ) {

			$transactions_db = MeprTransaction::get_all();

			$transactions_by_status = array();

			foreach ( $transactions_db as $txn ) {

				$txn = new MeprTransaction( $txn->id );

				if ( ! isset( $transactions_by_status[ $txn->status ] ) ) {
					$transactions_by_status[ $txn->status ] = array();
				}

				$transactions_by_status[ $txn->status ][ $txn->id ] = array(
					'user_id'         => $txn->user_id,
					'wpf_complete'    => $txn->get_meta( 'wpf_complete', true ),
					'wpf_ec_complete' => $txn->get_meta( 'wpf_ec_complete', true ),
					'wpf_invoice_id'  => $txn->get_meta( 'wpf_ec_' . wp_fusion()->crm->slug . '_invoice_id', true ),
				);

			}

			echo '<ul>';

			foreach ( $transactions_by_status as $status => $transactions ) {

				echo '<li><strong>Status ' . $status . '</strong> - ' . count( $transactions ) . '</li>';
				//echo '</li><strong>' . $status . ' + wpf_complete</strong> - ' . count( array_filter( wp_list_pluck( $transactions, 'wpf_complete' ) ) ) . '</li>';
				echo '<li><strong>' . $status . ' + wpf_ec_complete</strong> - ' . count( array_filter( wp_list_pluck( $transactions, 'wpf_ec_complete' ) ) ) . '</li>';
				echo '<li><strong>' . $status . ' + ' . wp_fusion()->crm->name . ' invoice</strong> - ' . count( array_filter( wp_list_pluck( $transactions, 'wpf_invoice_id' ) ) ) . '</li>';
				echo '<li>';

					echo '<pre>';
					print_r( $transactions );
					echo '</pre>';

				echo '</li>';

			}

		}

	}

	/**
	 * //
	 * // BATCH TOOLS
	 * //
	 **/

	/**
	 * Adds Memberpress checkbox to available export options
	 *
	 * @access public
	 * @return array Options
	 */

	public function export_options( $options ) {

		$options['memberpress'] = array(
			'label'   => 'MemberPress subscriptions meta',
			'title'   => 'subscriptions',
			'tooltip' => __( 'Syncs the registration date, expiration date, and membership level name for all existing MemberPress subscriptions. Does not modify tags or create new contact records.', 'wp-fusion' ),
		);

		$options['memberpress_transactions'] = array(
			'label'   => 'MemberPress transactions meta',
			'title'   => 'transactions',
			'tooltip' => __( 'Syncs the registration date, expiration date, payment method, and membership level name for all existing MemberPress transactions. Does not modify tags or create new contact records.', 'wp-fusion' ),
		);

		// Corporate accounts addon
		$corporate_account_text = '';
		if ( defined( 'MPCA_PLUGIN_NAME' ) ) {
			$corporate_account_text = __( ', Also applies tags to any corporate sub-account members based on their parent account and the settings configured on the parent membership product.', 'wp-fusion' );
		}

		$options['memberpress_memberships'] = array(
			'label'   => 'MemberPress memberships statuses',
			'title'   => 'memberships',
			'tooltip' => __( 'Updates the tags for all members based on their current membership status. Does not create new contact records' . ( ! empty( $corporate_account_text ) ? $corporate_account_text : '.' ) . '', 'wp-fusion' ),
		);

		return $options;

	}

	/**
	 * Counts total number of members to be processed
	 *
	 * @access public
	 * @return array Members
	 */

	public function batch_init_subscriptions() {

		$subscriptions_db = MeprSubscription::get_all();
		$subscriptions    = array();

		foreach ( $subscriptions_db as $subscription ) {
			$subscriptions[] = $subscription->id;
		}

		return $subscriptions;

	}

	/**
	 * Processes member actions in batches
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step_subscriptions( $subscription_id ) {

		$subscription = new MeprSubscription( $subscription_id );

		$data = array(
			'mepr_reg_date'         => $subscription->created_at,
			'mepr_expiration'       => $subscription->expires_at,
			'mepr_membership_level' => html_entity_decode( get_the_title( $subscription->product_id ) ),
			'mepr_sub_total'        => $subscription->total,
			'mepr_sub_status'       => $subscription->status,
		);

		if ( ! empty( $subscription->user_id ) ) {
			wp_fusion()->user->push_user_meta( $subscription->user_id, $data );
		}

	}

	/**
	 * Counts total number of members to be processed
	 *
	 * @access public
	 * @return array Members
	 */

	public function batch_init_transactions() {

		$transactions_db = MeprTransaction::get_all();
		$transactions    = array();

		foreach ( $transactions_db as $transaction ) {

			if ( 'complete' != $transaction->status || empty( $transaction->user_id ) ) {
				continue;
			}

			$transactions[] = $transaction->id;
		}

		return $transactions;

	}

	/**
	 * Counts total number of members to be processed
	 *
	 * @access public
	 * @return array Members
	 */

	public function batch_init_memberships() {

		$members = MeprUser::all( 'ids' );

		return $members;

	}

	/**
	 * Processes member actions in batches
	 *
	 * @access public
	 * @return void
	 */

	public function batch_step_memberships( $member_id ) {

		$member      = new MeprUser( $member_id );
		$product_ids = $member->current_and_prior_subscriptions();

		// Subscriptions.
		$subscriptions = $member->subscriptions();

		// Get products from transactions.

		$transactions = $member->transactions();

		$product_ids = array_merge( $product_ids, wp_list_pluck( $transactions, 'product_id' ) );
		$product_ids = array_unique( $product_ids );

		if ( empty( $product_ids ) ) {
			return;
		}

		$apply_tags = array();

		foreach ( $product_ids as $product_id ) {

			$settings = get_post_meta( $product_id, 'wpf-settings-memberpress', true );

			if ( $member->is_already_subscribed_to( $product_id ) ) {

				if ( ! empty( $settings['apply_tags_registration'] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_registration'] );
				}

				if ( ! empty( $settings['tag_link'] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['tag_link'] );
				}

				if ( ! empty( $settings['apply_tags_expired'] ) ) {
					wp_fusion()->user->remove_tags( $settings['apply_tags_expired'], $member_id );
				}

				// Corporate accounts
				$corporate_account = get_user_meta( $member->ID, 'mpca_corporate_account_id', true );

				if ( ! empty( $corporate_account ) && ! empty( $settings['apply_tags_corporate_accounts'] ) ) {
					$apply_tags = array_merge( $apply_tags, $settings['apply_tags_corporate_accounts'] );
				}
			} else {

				if ( ! empty( $settings['remove_tags'] ) ) {
					wp_fusion()->user->remove_tags( $settings['apply_tags_registration'], $member_id );
				}

				// Maybe apply tags based on status.

				foreach ( $subscriptions as $subscription ) {

					if ( ! is_a( $subscription, 'MeprSubscription' ) ) {
						continue;
					}

					if ( $subscription->product_id === $product_id ) {

						if ( ! empty( $settings['apply_tags_cancelled'] ) && $subscription->is_cancelled() ) {

							$apply_tags = array_merge( $apply_tags, $settings['apply_tags_cancelled'] );

						} elseif ( ! empty( $settings['apply_tags_expired'] ) && $subscription->is_expired() ) {

							$apply_tags = array_merge( $apply_tags, $settings['apply_tags_expired'] );

						} elseif ( ! empty( $settings['apply_tags_trial'] ) && $subscription->in_trial() ) {

							$apply_tags = array_merge( $apply_tags, $settings['apply_tags_trial'] );

						}
					}
				}

				// Let's get expired transactions too.

				foreach ( $transactions as $transaction ) {

					if ( ! isset( $transaction->expires_at ) || 0 === intval( $transaction->expires_at ) ) {
						continue;
					}

					if ( $transaction->product_id === $product_id ) {

						if ( ! empty( $settings['apply_tags_expired'] ) && strtotime( $transaction->expires_at ) <= time() ) {
							$apply_tags = array_merge( $apply_tags, $settings['apply_tags_expired'] );
						}
					}
				}
			}
		}

		if ( ! empty( $apply_tags ) ) {

			wp_fusion()->user->apply_tags( $apply_tags, $member_id );

		}

	}


}

new WPF_MemberPress();
