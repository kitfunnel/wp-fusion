<?php
use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Services\ConditionAssesor;
use WPPayForm\App\Services\Integrations\IntegrationManager;
use WPPayForm\App\Models\SubmissionActivity;
use WPPayForm\Framework\Foundation\App;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WPPayForm Pro integration.
 *
 * @since 3.39.4
 */
class WPF_WPPayForm extends IntegrationManager {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.39.4
	 * @var string $slug
	 */

	public $slug = 'paymattic';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.39.4
	 * @var string $name
	 */
	public $name = 'Paymattic';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.39.4
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/ecommerce/wppayform/';

	/**
	 * Settings are configured per feed, not globally.
	 *
	 * @since 3.39.4
	 * @var string $disableGlobalSettings
	 */
	public $disableGlobalSettings = 'yes';

	/**
	 * Constructor.
	 *
	 * @since 3.39.4
	 */
	public function __construct() {

		parent::__construct(
			App::getInstance(),
			__( 'WP Fusion', 'wp-fusion' ),
			'wpfusion',
			'_wppayform_wpfusion_settings',
			'wppayform_wpfusion_feed',
			16
		);

		$this->logo = WPF_DIR_URL . '/assets/img/logo-wide-color.png';

		$this->description = sprintf( __( 'WP Fusion syncs your Paymattic form submissions with %s and applies tags based on payments and subscription statuses.', 'wp-fusion' ), wp_fusion()->crm->name );

		add_filter( 'wppayform_notifying_async_wpfusion', '__return_false' );

		$this->registerAdminHooks();

		// Payment status hooks.

		add_filter( 'wppayform/all_placeholders', array( $this, 'add_crm_fields' ), 10, 2 );

		add_action( 'wppayform/form_payment_success', array( $this, 'payment_received' ), 10, 3 );
		add_action( 'wppayform/form_payment_failed', array( $this, 'payment_failed' ), 10, 3 );
		add_action( 'wppayform/after_payment_status_change_manually', array( $this, 'payment_status_change' ), 10, 3 );
		add_action( 'wppayform/subscription_payment_canceled', array( $this, 'subscription_cancelled' ), 10, 4 );

	}


	/**
	 * Registers the integration.
	 *
	 * @since  3.39.4
	 *
	 * @param  array $integrations The integrations.
	 * @param  int   $form_id      The form ID.
	 * @return array The integrations.
	 */
	public function pushIntegration( $integrations, $form_id ) {

		$integrations['wpfusion'] = array(
			'title'                   => $this->title,
			'logo'                    => $this->logo,
			'is_active'               => $this->isConfigured(),
			'enabled'                 => 'yes',
			'disable_global_settings' => 'yes',
		);

		return $integrations;
	}

	/**
	 * Gets the integration defaults.
	 *
	 * @since  3.39.4
	 *
	 * @param  array $settings The settings.
	 * @param  int $form_id   The form ID.
	 * @return array The integration defaults.
	 */
	public function getIntegrationDefaults( $settings, $form_id = null ) {
		$fields = array(
			'name'                              => '',
			'CustomFields'                      => (object) array(),
			'apply_tags_form_submission'        => array(),
			'apply_tags_payment_received'       => array(),
			'apply_tags_payment_failed'         => array(),
			'apply_tags_subscription_cancelled' => array(),
			'conditionals'                      => array(
				'conditions' => array(),
				'status'     => false,
				'type'       => 'all',
			),
			'enabled'                           => true,
		);

		return apply_filters( 'wppayform_wpfusion_field_defaults', $fields, $form_id );
	}

	/**
	 * Regiser the feed settings.
	 *
	 * @since  3.39.4
	 *
	 * @param  array $settings The settings.
	 * @param  int   $form_id  The form ID.
	 * @return array The settings.
	 */
	public function getSettingsFields( $settings, $form_id = null ) {

		$shortcodes = \WPPayForm\App\Models\Form::getEditorShortCodes( $form_id );

		$map_fields = array();

		foreach ( $shortcodes[0]['shortcodes'] as $key => $field ) {
			$map_fields[] = array(
				'key'      => $key,
				'label'    => $field,
				'required' => false,
			);
		}

		$fields = array();

		$fields[] = array(
			'key'         => 'name',
			'label'       => __( 'Name', 'wp-fusion' ),
			'required'    => true,
			'placeholder' => __( 'Your Feed Name', 'wp-fusion' ),
			'component'   => 'text',
		);

		$fields[] = array(
			'key'                => 'CustomFields',
			'require_list'       => false,
			'label'              => __( 'Map Fields', 'wp-fusion' ),
			'tips'               => sprintf( __( 'For each form field, select a corresponding custom field in %s to sync with.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'component'          => 'map_fields',
			'field_label_remote' => __( 'Form Fields', 'wp-fusion' ),
			'field_label_local'  => sprintf( __( '%s Fields', 'wp-fusion' ), wp_fusion()->crm->name ),
			'primary_fileds'     => $map_fields,
		);

		$available_tags = wp_fusion()->settings->get_available_tags_flat();

		$fields[] = array(
			'require_list' => false,
			'key'          => 'apply_tags_form_submission',
			'label'        => __( 'Tags - Form Submission', 'wp-fusion' ),
			'component'    => 'select',
			'options'      => $available_tags,
			'is_multiple'  => true,
			'tips'         => sprintf( __( 'These tags will be applied in %s whenever the form is submitted.', 'wp-fusion' ), wp_fusion()->crm->name ),
		);

		if ( strtolower( get_post_meta( $form_id, 'wpf_has_payment_field', true ) ) === 'yes' ) {
			$fields[] = array(
				'require_list' => false,
				'key'          => 'apply_tags_payment_received',
				'label'        => __( 'Tags - Payment Received', 'wp-fusion' ),
				'component'    => 'select',
				'options'      => $available_tags,
				'is_multiple'  => true,
				'tips'         => sprintf( __( 'These tags will be applied in %s when a payment on this form is received.', 'wp-fusion' ), wp_fusion()->crm->name ),
			);

			$fields[] = array(
				'require_list' => false,
				'key'          => 'apply_tags_payment_failed',
				'label'        => __( 'Tags - Payment Failed', 'wp-fusion' ),
				'component'    => 'select',
				'options'      => $available_tags,
				'is_multiple'  => true,
				'tips'         => sprintf( __( 'These tags will be applied in %s when a payment on this form fails, including for failed subscription payments.', 'wp-fusion' ), wp_fusion()->crm->name ),
			);
		}

		if ( strtolower( get_post_meta( $form_id, 'wpf_has_recurring_field', true ) ) === 'yes' ) {
			$fields[] = array(
				'require_list' => false,
				'key'          => 'apply_tags_subscription_cancelled',
				'label'        => __( 'Apply Tags - Subscription Cancelled', 'wp-fusion' ),
				'component'    => 'select',
				'options'      => $available_tags,
				'is_multiple'  => true,
				'tips'         => sprintf( __( 'These tags will be applied in %s when a customer cancels their subscription to a product on this form.', 'wp-fusion' ), wp_fusion()->crm->name ),
			);
		}

		$fields[] = array(
			'require_list' => true,
			'key'          => 'conditionals',
			'label'        => __( 'Conditional Logic', 'wp-fusion' ),
			'tips'         => sprintf( __( 'Allow %s integration conditionally based on your submission values', 'wp-fusion' ), wp_fusion()->crm->name ),
			'component'    => 'conditional_block',
		);

		$fields[] = array(
			'require_list'   => false,
			'key'            => 'enabled',
			'label'          => __( 'Status', 'wp-fusion' ),
			'component'      => 'checkbox-single',
			'checkbox_label' => 'Enable This feed',
		);

		return array(
			'fields'              => $fields,
			'button_require_list' => false,
			'integration_title'   => $this->title,
		);
	}

	/**
	 * Processes an entry when a form is submitted,
	 *
	 * @since 3.39.4
	 *
	 * @param array $feed      The feed.
	 * @param array $form_data The form data.
	 * @param array $entry     The entry.
	 * @param int   $form_id   The form ID.
	 */
	public function notify( $feed, $form_data, $entry, $form_id ) {

		if ( ! $feed['processedValues']['enabled'] ) {
			return;
		}

		$feed          = $feed['processedValues'];
		$email_address = false;
		$update_data   = array();

		foreach ( $form_data as $key => $value ) {

			if ( is_email( $value ) && wpf_get_lookup_field() === $feed[ '{input.' . $key . '}' ] ) {
				$email_address = $value;
			} elseif ( false === $email_address && is_email( $value ) ) {
				$email_address = $value;
			}

			if ( isset( $feed[ '{input.' . $key . '}' ] ) && ! empty( $feed[ '{input.' . $key . '}' ] ) ) {
				$update_data[ $feed[ '{input.' . $key . '}' ] ] = $value;
			}
		}

		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => $feed['apply_tags_form_submission'],
			'integration_slug' => 'wppayform',
			'integration_name' => 'WPPayForm',
			'form_id'          => $form_id,
			'form_title'       => get_the_title( $form_id ),
			'form_edit_link'   => admin_url( 'admin.php?page=wppayform.php#/edit-form/' . $form_id . '/form-builder' ),
		);

		$contact_id = \WPF_Forms_Helper::process_form_data( $args );

		if ( ! is_wp_error( $contact_id ) ) {

			update_post_meta( $entry->id, '_wpf_complete', current_time( 'Y-m-d H:i:s' ) );
			update_post_meta( $entry->id, '_wpf_' . WPF_CONTACT_ID_META_KEY, $contact_id );

			// Get the message for the note.

			$message = sprintf( __( 'Entry synced to %s. Contact ID ' ), wp_fusion()->crm->name );

			$edit_url = wp_fusion()->crm->get_contact_edit_url( $contact_id );

			if ( false !== $edit_url ) {
				$message .= '<a href="' . $edit_url . '" target="_blank">#' . $contact_id . '</a>.';
			} else {
				$message .= '#' . $contact_id . '.';
			}
		} else {

			$message = sprintf( __( 'Error adding contact to %1%s: %2$s', 'wp-fusion' ), wp_fusion()->crm->name, $contact_id->get_error_message() );

		}

		// Log the result.

		SubmissionActivity::createActivity(
			array(
				'form_id'       => $form_id,
				'submission_id' => $entry->id,
				'type'          => 'activity',
				'created_by'    => __( 'WP Fusion', 'wp-fusion' ),
				'content'       => $message,
			)
		);

	}


	/**
	 * There is no global settings, so we need to return true to make this
	 * module work.
	 *
	 * @since  3.39.4
	 *
	 * @return bool  True if configured, False otherwise.
	 */
	public function isConfigured() {
		return true;
	}


	/**
	 * This is an absttract method, so it's required.
	 *
	 * @since 3.39.4
	 *
	 * @param unknown $list    The list.
	 * @param int     $list_id The list ID.
	 * @param int     $form_id The form ID.
	 */
	public function getMergeFields( $list, $list_id, $form_id ) {}

	/**
	 * This method should return global settings. It's not required for this
	 * class. So we should return the default settings otherwise there will be
	 * an empty global settings page for this module.
	 *
	 * @since  3.39.4
	 *
	 * @param  array $setting The setting.
	 * @return array The setting.
	 */
	public function addGlobalMenu( $setting ) {
		return $setting;
	}

	/**
	 * Triggered when manually changing payment status.
	 *
	 * @since 3.39.4
	 *
	 * @param int    $submission_id  The submission ID.
	 * @param string $new_status     The new payment status.
	 * @param string $payment_status The old payment status.
	 */
	public function payment_status_change( $submission_id, $new_status, $payment_status ) {

		if ( 'paid' === $new_status || 'failed' === $new_status ) {

			$submission = ( new \WPPayForm\App\Models\Submission() )->getSubmission( $submission_id );
			$form_id    = $submission->form_id;

			$data = $submission->form_data_formatted;
			if ( empty( $data ) ) {
				return;
			}

			$feeds = $this->get_form_feeds( $form_id );

			if ( empty( $feeds ) ) {
				return;
			}

			if ( 'paid' === $new_status ) {
				$tag_name = 'apply_tags_payment_received';
			} else {
				$tag_name = 'apply_tags_payment_failed';
			}

			foreach ( $feeds as $feed ) {

				if ( ! empty( $feed[ $tag_name ] ) ) {
					$feed['submission_id'] = $submission->id;
					$this->process_feed( $feed, $data, $tag_name );
				}
			}
		}
	}

	/**
	 * Get WP Fusion feeds from a form ID.
	 *
	 * @since  3.39.4
	 *
	 * @param  int $form_id The form ID.
	 * @return array The feeds.
	 */
	public function get_form_feeds( $form_id ) {

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wpf_meta WHERE form_id=%d AND meta_key=%s", $form_id, 'wppayform_wpfusion_feed' ),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return array();
		}

		$feeds = array();
		foreach ( $results as $result ) {

			$result = json_decode( $result['meta_value'], ARRAY_A );

			if ( $result['enabled'] ) {
				$feeds[] = $result;
			}
		}

		return $feeds;

	}

	/**
	 * Triggered when a new payment is received.
	 *
	 * @since 3.39.4
	 *
	 * @param object $submission  The form submission.
	 * @param object $transaction The transaction info.
	 * @param int    $form_id     The form ID.
	 */
	public function payment_received( $submission, $transaction, $form_id ) {

		$data = $submission->form_data_formatted;

		if ( empty( $data ) ) {
			return;
		}

		$feeds = $this->get_form_feeds( $form_id );

		if ( empty( $feeds ) ) {
			return;
		}

		foreach ( $feeds as $feed ) {

			if ( ! empty( $feed['apply_tags_payment_received'] ) ) {
				$feed['submission_id'] = $submission->id;
				$this->process_feed( $feed, $data, 'apply_tags_payment_received' );
			}
		}

	}

	/**
	 * Triggered when a payment fails, either a new payment or a subscription
	 * payment failure.
	 *
	 * @since 3.39.4
	 *
	 * @param object $submission  The form submission.
	 * @param object $transaction The transaction info.
	 * @param int    $form        The form ID.
	 */
	public function payment_failed( $submission, $transaction, $form ) {

		$data = $submission->form_data_formatted;

		if ( empty( $data ) ) {
			return;
		}

		$feeds = $this->get_form_feeds( $form->form_id );

		if ( empty( $feeds ) ) {
			return;
		}

		foreach ( $feeds as $feed ) {
			if ( ! empty( $feed['apply_tags_payment_failed'] ) ) {
				$feed['submission_id'] = $submission->id;
				$this->process_feed( $feed, $data, 'apply_tags_payment_failed' );
			}
		}

	}

	/**
	 * Triggered when a subscription is cancelled.
	 *
	 * @since 3.39.4
	 *
	 * @param object $submission   The form submission.
	 * @param object $subscription The subscription info.
	 * @param int    $form_id      The form ID.
	 */
	public function subscription_cancelled( $submission, $subscription, $form_id ) {

		$data = $submission->form_data_formatted;

		if ( empty( $data ) ) {
			return;
		}

		$feeds = $this->get_form_feeds( $form_id );

		if ( empty( $feeds ) ) {
			return;
		}

		foreach ( $feeds as $feed ) {
			if ( ! empty( $feed['apply_tags_subscription_cancelled'] ) ) {
				$feed['submission_id'] = $submission->id;
				$this->process_feed( $feed, $data, 'apply_tags_subscription_cancelled' );
			}
		}
	}

	/**
	 * Handles applying tags based on payment status changes.
	 *
	 * @since 3.39.4
	 *
	 * @param array  $feed      The feed.
	 * @param array  $data      The submitted form data.
	 * @param string $tags_name The setting key containing the tags to apply.
	 */
	private function process_feed( $feed, $data, $tags_name ) {

		$email_address = false;

		foreach ( $data as $key => $value ) {
			if ( is_email( $value ) ) {
				$email_address = $value;
				break;
			}
		}

		$user = get_user_by( 'email', $email_address );

		if ( ! empty( $user ) ) {

			wp_fusion()->user->apply_tags( $feed[ $tags_name ], $user->ID );

		} else {

			$contact_id = get_post_meta( $feed['submission_id'], '_wpf_' . WPF_CONTACT_ID_META_KEY, true );

			if ( ! empty( $contact_id ) ) {

				wpf_log( 'info', 0, 'Applying tags for contact #' . $contact_id . ': ', array( 'tag_array' => $feed[ $tags_name ] ) );
				wp_fusion()->crm->apply_tags( $feed[ $tags_name ], $contact_id );

			}
		}

	}

	/**
	 * Add WP Fusion crm fields instead of the payforms fields.
	 *
	 * @since  3.39.4
	 *
	 * @param  array $all_fields The fields.
	 * @param  int   $form_id    The form ID.
	 * @return array The fields.
	 */
	public function add_crm_fields( $all_fields, $form_id ) {

		// Only trigger it in WP Fusion feeds.

		if ( empty( $_REQUEST ) || ! isset( $_REQUEST['integration_name'] ) || $_REQUEST['integration_name'] !== 'wpfusion' ) {
			return $all_fields;
		}

		$crm_fields             = wp_fusion()->settings->get_crm_fields_flat();
		$crm_fields_placeholder = array();
		foreach ( $crm_fields as $key => $value ) {
			$crm_fields_placeholder[] = array(
				'id'       => $key,
				'tag'      => $value,
				'label'    => $value,
				'callback' => false,
			);
		}

		$wpf_crm = array(
			'title'      => wp_fusion()->crm->name . ' Fields',
			'shortcodes' => $crm_fields,
		);

		return array( $wpf_crm );
	}

}

wp_fusion()->integrations->{'paymattic'} = new WPF_WPPayForm();
