<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper;

class WPF_FluentForms extends IntegrationManager {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'fluent-forms';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Fluent Forms';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/lead-generation/fluent-forms/';



	public function __construct() {
		parent::__construct(
			false,
			'WP Fusion',
			'wpfusion',
			'_fluentform_wpfusion_settings',
			'fluentform_wpfusion_feed',
			16
		);

		$this->logo = WPF_DIR_URL . 'assets/img/logo-wide-color.png';

		$this->description = sprintf( __( 'WP Fusion syncs your Fluent Forms entries to %s.', 'wp-fusion' ), wp_fusion()->crm->name );

		$this->registerAdminHooks();

		$this->slug                                 = 'fluent-forms';
		wp_fusion()->integrations->{'fluent-forms'} = $this;

		// If we're using Form Auto Login the form actions can't be processed asynchronously.
		if ( wpf_get_option( 'auto_login_forms' ) ) {
			add_filter( 'fluentform_notifying_async_wpfusion', '__return_false' );
		}

		add_filter( 'wpf_meta_field_groups', array( $this, 'add_meta_field_group' ) );
		add_filter( 'wpf_meta_fields', array( $this, 'add_meta_fields' ) );

		add_filter( 'fluentform_user_registration_feed', array( $this, 'merge_registration_data' ), 10, 3 );

		add_action( 'fluentform_user_registration_completed', array( $this, 'save_user_fields' ), 20, 3 );
		add_action( 'fluentform_user_update_completed', array( $this, 'save_user_fields' ), 20, 3 );

		add_filter( 'wpf_export_options', array( $this, 'export_options' ) );
		add_filter( 'wpf_batch_fluent_forms_init', array( $this, 'batch_init' ) );
		add_action( 'wpf_batch_fluent_forms', array( $this, 'batch_step' ) );

	}


	public function getGlobalFields( $fields ) {
		return array(
			'logo'             => $this->logo,
			'menu_title'       => __( 'WP Fusion Settings', 'wp-fusion' ),
			'menu_description' => sprintf( __( 'Fluent Forms is already connected to %s by WP Fusion, there\'s nothing to configure here. You can set up WP Fusion your individual forms under Settings &raquo; Marketing &amp; CRM Integrations. For more information <a href="https://wpfusion.com/documentation/lead-generation/fluent-forms/" target="_blank">see the documentation</a>.', 'wp-fusion' ), wp_fusion()->crm->name ),
			'valid_message'    => __( 'Your Mailchimp API Key is valid', 'fluentform' ),
			'invalid_message'  => ' ',
			'save_button_text' => ' ',
		);
	}

	/**
	 * Set integration to configured
	 *
	 * @access public
	 * @return bool Configured
	 */

	public function isConfigured() {
		return true;
	}

	/**
	 * Register the integration
	 *
	 * @access public
	 * @return array Integrations
	 */

	public function pushIntegration( $integrations, $form_id ) {

		$integrations[ $this->integrationKey ] = array(
			'title'                 => $this->title . ' Integration',
			'logo'                  => $this->logo,
			'is_active'             => true,
			'configure_title'       => 'Configration required!',
			'global_configure_url'  => admin_url( 'admin.php?page=fluent_forms_settings#general-wpfusion-settings' ),
			'configure_message'     => 'WP Fusion is not configured yet! Please configure your WP Fusion API first',
			'configure_button_text' => 'Set WP Fusion API',
		);

		return $integrations;

	}

	/**
	 * Get integration defaults
	 *
	 * @access public
	 * @return array Defaults
	 */

	public function getIntegrationDefaults( $settings, $form_id ) {

		return array(
			'name'                    => '',
			'fieldEmailAddress'       => '',
			'custom_field_mappings'   => (object) array(),
			'default_fields'          => (object) array(),
			'note'                    => '',
			'tags'                    => '',
			'conditionals'            => array(
				'conditions' => array(),
				'status'     => false,
				'type'       => 'all',
			),
			'instant_responders'      => false,
			'last_broadcast_campaign' => false,
			'enabled'                 => true,
		);
	}

	/**
	 * Get settings fields
	 *
	 * @access public
	 * @return array Settings
	 */

	public function getSettingsFields( $settings, $form_id ) {
		return array(
			'fields'              => array(
				array(
					'key'         => 'name',
					'label'       => 'Name',
					'required'    => true,
					'placeholder' => 'Your Feed Name',
					'component'   => 'text',
				),
				array(
					'key'                => 'custom_field_mappings',
					'require_list'       => false,
					'label'              => 'Map Fields',
					'tips'               => 'Select which Fluent Form fields pair with their respective ' . wp_fusion()->crm->name . ' fields.',
					'component'          => 'map_fields',
					'field_label_remote' => wp_fusion()->crm->name . ' Field',
					'field_label_local'  => 'Form Field',
					'default_fields'     => $this->getMergeFields( false, false, $form_id ),
				),
				array(
					'key'          => 'tags',
					'require_list' => false,
					'label'        => __( 'Tags', 'wp-fusion' ),
					'tips'         => __( 'Associate tags to your contacts with a comma separated list (e.g. new lead, FluentForms, web source).', 'wp-fusion' ),
					'component'    => 'value_text',
					'inline_tip'   => __( 'Enter tag names or tag IDs, separated by commas', 'wp-fusion' ),
				),
				array(
					'require_list' => false,
					'key'          => 'conditionals',
					'label'        => 'Conditional Logics',
					'tips'         => __( 'Allow WP Fusion integration conditionally based on your submission values', 'wp-fusion' ),
					'component'    => 'conditional_block',
				),
				array(
					'require_list'    => false,
					'key'             => 'enabled',
					'label'           => 'Status',
					'component'       => 'checkbox-single',
					'checkobox_label' => 'Enable This feed',
				),
			),
			'button_require_list' => false,
			'integration_title'   => $this->title,
		);
	}

	/**
	 * Get CRM fields
	 *
	 * @access public
	 * @return array Fields
	 */

	public function getMergeFields( $list, $list_id, $form_id ) {

		$fields = array();

		$available_fields = wp_fusion()->settings->get_crm_fields_flat();

		foreach ( $available_fields as $field_id => $field_label ) {

			$remote_required = false;

			if ( $field_label == 'Email' ) {
				$remote_required = true;
			}

			$fields[] = array(
				'name'     => $field_id,
				'label'    => $field_label,
				'required' => $remote_required,
			);

		}

		return $fields;

	}


	/**
	 * Handle form submission
	 *
	 * @access public
	 * @return void
	 */

	public function notify( $feed, $form_data, $entry, $form ) {

		$email_address = false;

		$update_data = array();

		foreach ( $feed['processedValues']['default_fields'] as $field => $value ) {

			if ( false !== strpos( $field, 'add_tag_' ) ) {

				// Don't run the filter on dynamic tagging inputs.
				$update_data[ $field ] = $value;
				continue;

			}

			$value = apply_filters( 'wpf_format_field_value', $value, 'text', $field );

			if ( ! empty( $value ) || 0 === $value || '0' === $value ) {

				// Don't sync empty values unless they're actually the number 0.
				$update_data[ $field ] = $value;
			}

			if ( $email_address == false && is_email( $value ) ) {
				$email_address = $value;
			}
		}

		// str_getcsv to preserve tags in quotes.

		$input_tags = array_filter( str_getcsv( $feed['processedValues']['tags'], ',' ) );

		$apply_tags = array();

		// Get tags to apply
		foreach ( $input_tags as $tag ) {

			$tag_id = wp_fusion()->user->get_tag_id( $tag );

			if ( false === $tag_id ) {

				wpf_log( 'notice', 0, 'Warning: ' . $tag . ' is not a valid tag name or ID.' );
				continue;

			}

			$apply_tags[] = $tag_id;

		}
		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => $apply_tags,
			'add_only'         => false,
			'integration_slug' => 'fluent_forms',
			'integration_name' => 'Fluent Forms',
			'form_id'          => $form->id,
			'form_title'       => $form->title,
			'form_edit_link'   => admin_url( 'admin.php?page=fluent_forms&route=editor&form_id=' . $form->id ),
			'entry_id'         => $entry->id,
		);

		$contact_id = WPF_Forms_Helper::process_form_data( $args );

		if ( is_wp_error( $contact_id ) ) {
			do_action( 'ff_integration_action_result', $feed, 'failed', $contact_id->get_error_message() );
		} else {

			do_action( 'ff_integration_action_result', $feed, 'success', 'Entry synced to ' . wp_fusion()->crm->name . ' (contact ID ' . $contact_id . ')' );
			FluentForm\App\Helpers\Helper::setSubmissionMeta( $entry->id, 'wpf_contact_id', $contact_id );

		}

	}



	/**
	 * Adds FE field group to meta fields list
	 *
	 * @since  3.38.22
	 *
	 * @param  array $field_groups The field groups.
	 * @return array The field groups.
	 */
	public function add_meta_field_group( $field_groups ) {

		$field_groups['fluent_forms_user_reg'] = array(
			'title'  => 'Fluent Forms User Registration',
			'fields' => array(),
		);

		return $field_groups;

	}


	/**
	 * Detect any FF user registration fields and make them available for
	 * mapping via the WPF Contact Fields list.
	 *
	 * @since  3.38.22
	 *
	 * @param  array $meta_fields The meta fields.
	 * @return array  The meta fields.
	 */
	public function add_meta_fields( $meta_fields ) {

		$settings = get_option( 'fluentform_global_modules_status' );

		if ( isset( $settings['UserRegistration'] ) && 'yes' === $settings['UserRegistration'] ) {

			$meta_fields['ff_generated_password'] = array(
				'label'  => 'Generated Password',
				'type'   => 'text',
				'group'  => 'fluent_forms_user_reg',
				'pseudo' => true,
			);

			$forms = wpFluent()->table( 'fluentform_forms' )
			->select( array( 'id' ) )
			->get();

			if ( empty( $forms ) ) {
				return $meta_fields;
			}

			foreach ( $forms as $form ) {

				$id    = $form->id;
				$feeds = wpFluent()->table( 'fluentform_form_meta' )
				->select( array( 'value' ) )
				->where( 'meta_key', 'user_registration_feeds' )
				->where( 'form_id', $id )
				->get();

				if ( empty( $feeds ) ) {
					continue;
				}
				foreach ( $feeds as $feed ) {

					$meta = json_decode( $feed->value );

					if ( empty( $meta->{'userMeta'} ) ) {
						continue;
					}

					foreach ( $meta->{'userMeta'}  as $meta_key => $val ) {

						if ( empty( $val->label ) ) {
							continue;
						}

						$meta_fields[ $val->label ] = array(
							'label' => $val->label,
							'type'  => 'text',
							'group' => 'fluent_forms_user_reg',
						);
					}
				}
			}
		}

		return $meta_fields;

	}


	/**
	 * Syncs the custom usermeta and generated password fields on a registration
	 * form.
	 *
	 * Fluent Forms doesn't currently have a hook that would let us get the
	 * password. However, since the password is only generated if it's blank, we
	 * can generate it early here and then we'll know what it is.
	 *
	 * @since  3.38.32
	 *
	 * @param  array $feed   The feed.
	 * @param  array $entry  The entry.
	 * @param  array $form   The form.
	 * @return array The feed.
	 */
	public function merge_registration_data( $feed, $entry, $form ) {

		$merge = array();

		if ( empty( $feed['processedValues']['password'] ) ) {

			$feed['processedValues']['password'] = wp_generate_password( 8 );

			$merge['ff_generated_password'] = $feed['processedValues']['password'];

		}

		if ( ! empty( $feed['processedValues']['userMeta'] ) ) {

			foreach ( $feed['processedValues']['userMeta'] as $meta ) {
				$merge[ $meta['label'] ] = $meta['item_value'];
			}
		}

		if ( ! empty( $feed['processedValues']['first_name'] ) ) {
			$merge['first_name'] = $feed['processedValues']['first_name'];
		}

		if ( ! empty( $feed['processedValues']['last_name'] ) ) {
			$merge['last_name'] = $feed['processedValues']['last_name'];
		}

		if ( ! empty( $merge ) ) {

			add_filter(
				'wpf_user_register',
				function( $user_meta ) use ( &$merge ) {

					$user_meta = array_merge( $user_meta, $merge );

					return $user_meta;

				}
			);
		}

		return $feed;
	}

	/**
	 * Saves user fields on form submission.
	 *
	 * @since 3.41.10
	 * @param int   $user_id The user ID.
	 * @param array $feed The feed.
	 * @param array $entry The entry.
	 * @return void
	 */
	public function save_user_fields( $user_id, $feed, $entry ) {
		$field_ids       = array();
		$prefixed_fields = array();
		$xprofile_fields = \FluentForm\Framework\Helpers\ArrayHelper::get( $feed, 'processedValues.bboss_profile_fields' );

		foreach ( $xprofile_fields as $field ) {
			$field_ids = $field['label'];

			$prefixed_fields[ 'bbp_field_' . trim( $field_ids ) ] = $field['item_value'];

		}

		wp_fusion()->user->push_user_meta( $user_id, $prefixed_fields );

	}

	/**
	 * Create new Batch Operation option
	 *
	 * @since 3.41.9
	 * @param mixed $options The options for the operation.
	 * @return mixed $options Options
	 */
	public function export_options( $options ) {

		$options['fluent_forms'] = array(
			'label'         => 'Fluent Forms entries',
			'process_again' => true,
			'title'         => 'Entries',
			'tooltip'       => 'Find Fluent Forms entries that have not been successfully processed by WP Fusion and syncs them to ' . wp_fusion()->crm->name . ' based on their configured feeds.',
		);

		return $options;

	}

	/**
	 * Gets total list of entries to be processed
	 *
	 * @since 3.41.9
	 * @param array $args Array key ['skip_processed'] Is an entry already exported.
	 * @return array Entry IDs.
	 */
	public function batch_init( $args ) {

		$formapi = fluentFormApi( 'forms' );
		$forms   = $formapi->forms();

		$entry_ids = array();

		foreach ( $forms['data'] as $form ) {

			$formapi = fluentFormApi( 'forms' )->entryInstance( $form->id );
			$atts    = array(
				'per_page'   => 10,
				'page'       => 1,
				'search'     => '',
				'sort_by'    => 'DESC',
				'entry_type' => 'all',
			);
			$entries = $formapi->entries( $atts, false );

			foreach ( $entries['data'] as $entry ) {

				if ( ! empty( $args['skip_processed'] ) ) {
					$contact_id = FluentForm\App\Helpers\Helper::getSubmissionMeta( $entry->id, 'wpf_contact_id' );
				}
				if ( empty( $contact_id ) ) {
					$entry_ids[] = $entry->id;
				}

			}
		}

		return $entry_ids;
	}

	/**
	 * Processes entry feeds.
	 *
	 * @since 3.41.9
	 * @param int $entry_id The ID of the entry to process.
	 * @return void
	 */
	public function batch_step( $entry_id ) {

		$notification_manager = new \FluentForm\App\Services\Integrations\GlobalNotificationManager( wpFluentForm() );

		$form     = fluentFormApi( 'submissions' )->find( $entry_id );
		$form_api = fluentFormApi( 'forms' )->entryInstance( $form->form_id );
		$entry    = $form_api->entry( $entry_id );

		$feed_keys      = apply_filters( 'fluentform_global_notification_active_types', array(), $form->id );
		$feed_meta_keys = array_keys( $feed_keys );

		$feeds = wpFluent()->table( 'fluentform_form_meta' )
		->where( 'form_id', $form->id )
		->whereIn( 'meta_key', $feed_meta_keys )
		->orderBy( 'id', 'ASC' )
		->get();

		$enabled_feeds = $notification_manager->getEnabledFeeds( $feeds, $form, $entry_id );

		foreach ( $enabled_feeds as $feed ) {
			$this->notify( $feed, false, $entry, $form );
		}

	}

}

new WPF_FluentForms();
