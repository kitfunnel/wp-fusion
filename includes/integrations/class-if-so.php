<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * If-So Dynamic Content integration
 *
 * @since 3.38.0
 *
 * @link https://wpfusion.com/documentation/other/if-so/
 */
class WPF_If_So extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'if_so';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'If-So';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/if-so/';

	/**
	 * Gets things started.
	 *
	 * @since 3.38.0
	 */
	public function init() {

		if ( ! wpf_get_option( 'restrict_content', true ) ) {
			return;
		}

		add_filter( 'ifso_data_rules_model_filter', array( $this, 'add_rule' ) );
		add_filter( 'ifso_custom_conditions_expand_data_reset_by_selector', array( $this, 'add_data' ) );
		add_filter( 'ifso_custom_conditions_new_rule_data_extension', array( $this, 'add_new_rule' ), 10, 2 );
		add_action( 'ifso_custom_conditions_ui_selector', array( $this, 'add_condition' ) );
		add_action( 'ifso_custom_conditions_ui_data_inputs', array( $this, 'condition_content' ), 10, 2 );

		// Add trigger class.
		add_filter( 'ifso_triggers_list_filter', array( $this, 'add_trigger' ) );

	}

	/**
	 * Add WPF trigger.
	 *
	 * @since  3.38.0
	 *
	 * @param  array $triggers The triggers.
	 * @return array The triggers.
	 */
	public function add_trigger( $triggers ) {
		if ( class_exists( 'WPF_Tags_Trigger' ) ) {
			$triggers[] = new \WPF_Tags_Trigger();
		}
		return $triggers;
	}


	/**
	 * Add new rule extension.
	 *
	 * @since  3.38.0
	 *
	 * @param  array $new_version_rules The new version rules.
	 * @param  array $group_item The group item.
	 * @return array The version rules.
	 */
	public function add_new_rule( $new_version_rules, $group_item ) {
		$new_version_rules['wpf-tags'] = isset( $group_item['wpfTags'] ) ? $group_item['wpfTags'] : null;
		return $new_version_rules;
	}

	/**
	 * Add data to the selector.
	 *
	 * @since  3.38.0
	 *
	 * @param  array $data The selector data.
	 * @return array
	 */
	public function add_data( $data ) {
		$data[] = 'wpf-tags';
		return $data;
	}

	/**
	 * Add new rule extension.
	 *
	 * @since  3.38.0
	 *
	 * @param  array $rules The rules.
	 * @return array
	 */
	public function add_rule( $rules ) {
		$rules['wpfTags'] = array( 'wpf-tags' );
		return $rules;
	}

	/**
	 * Add a condition option to the list.
	 *
	 * @since 3.38.0
	 *
	 * @param array $rule   The rule.
	 * @return mixed HTML output.
	 */
	public function add_condition( $rule ) { ?>
		<option value="wpfTags" 
		<?php
		echo ( isset( $rule['trigger_type'] ) && $rule['trigger_type'] == 'wpfTags' ) ? 'SELECTED' : '';
		echo generateDataAttributes( array( 'wpf-tags', 'groups-field' ) );
		?>
		>
			<?php printf( esc_html__( '%s Tags (any)', 'wp-fusion' ), esc_html( wp_fusion()->crm->name ) ); ?>
		</option>
		<?php
	}

	/**
	 * Add a condition content.
	 *
	 * @since 3.38.0
	 *
	 * @param array $rule                  The rule.
	 * @param int   $current_version_index The index.
	 * @return void
	 */
	public function condition_content( $rule, $current_version_index ) {

		if ( empty( $rule['wpf-tags'] ) ) {
			$rule['wpf-tags'] = array();
		}

		?>
		<style>
			.ifso-form-group .select4-container{
				min-width:100%!important;
			}
			.ifso-form-group .select4-selection__clear{
				display:none;
			}
		</style>

		<div class="ifso-form-group wpf_tags">

				<select name="repeater[<?php echo esc_attr( $current_version_index ); ?>][wpfTags][]" multiple class="wpf_ifso_select form-control referrer-custom <?php echo ( ! empty( $rule['trigger_type'] ) && $rule['trigger_type'] === 'wpfTags' ) ? 'show-selection' : ''; ?>" data-field="wpf-tags">
						<?php
						$available_tags = wp_fusion()->settings->get_available_tags_flat( true, false );
						foreach ( $available_tags as $id => $tag ) {
							// $selected = ( ( ! empty( $rule['wpf-tags'] ) && in_array( $tag, $rule['wpf-tags'] ) ) ? 'SELECTED' : '' );
							echo '<option value="' . esc_attr( $id ) . '" ' . selected( in_array( $id, $rule['wpf-tags'] ), true, false ) . '>' . esc_html( $tag ) . '</option>';
						}
						?>
				</select>
		</div>

		<script>
			function wpf_ifso_init_select(new_item=false){
				jQuery('.wpf_ifso_select').each(function(){
					var select4_attr = jQuery(this).attr('data-select4-id');
					if (typeof select4_attr === 'undefined' || select4_attr === false) {
						var hide_container = false;
						if(jQuery(this).is(':hidden')){
							hide_container = true;
						}
						jQuery(this).select4();
						jQuery(this).next().attr('data-field','wpf-tags');
						if(hide_container === true){
							jQuery(this).next().hide();
						}

					}
					if(new_item === true){
						jQuery('.ifso-versions-sortable li:last-child .select4-container').remove();
						jQuery(this).select4();
						jQuery(this).next().attr('data-field','wpf-tags').hide();
					}

				});
			}

			wpf_ifso_init_select();
			jQuery(document).on( 'versionAdded', function(e){
				wpf_ifso_init_select(true);
			});

		</script>
		<?php
	}


}

new WPF_If_So();

if ( IFSO_PLUGIN_SERVICES_BASE_DIR ) {

	require_once IFSO_PLUGIN_SERVICES_BASE_DIR . 'triggers-service/triggers/trigger-base.class.php';

	class WPF_Tags_Trigger extends \IfSo\PublicFace\Services\TriggersService\Triggers\TriggerBase {

		public function __construct() {
			
			if ( ! wpf_get_option( 'restrict_content', true ) ) {
				return;
			}

			parent::__construct( 'wpfTags' );
		}

		/**
		 * Should this condition run on the trigger.
		 *
		 * @since  3.38.0
		 *
		 * @param  TriggerBase $trigger_data The trigger data.
		 * @return bool        True if able to handle, False otherwise.
		 */
		public function can_handle( $trigger_data ) {
			$rule = $trigger_data->get_rule();
			if ( empty( $rule['trigger_type'] ) ) {
				return false;
			}
			if ( $rule['freeze-mode'] == 'true' ) {
				return false;
			}
			if ( $rule['trigger_type'] != $this->trigger_name ) {
				return false;
			}

			return true;
		}

		/**
		 * Handle the display of the trigger.
		 *
		 * @since  3.38.0
		 *
		 * @param  TriggerBase $trigger_data The trigger data.
		 * @return string|bool The HTML content, or false.
		 */
		public function handle( $trigger_data ) {
			$rule    = $trigger_data->get_rule();
			$content = $trigger_data->get_content();
			$tags    = $rule['wpf-tags'];

			if ( empty( $tags ) ) {
				return $content;
			}

			if ( ! wpf_is_user_logged_in() ) {
				return false;
			}

			if ( wpf_admin_override() ) {
				return $content;
			}

			$can_access = wpf_has_tag( $tags );

			$can_access = apply_filters( 'wpf_if_so_can_access', $can_access, $trigger_data );
			$can_access = apply_filters( 'wpf_user_can_access', $can_access, wpf_get_current_user_id(), false );

			if ( true === $can_access ) {
				return $content;
			} else {
				return false;
			}
		}
	}
}
