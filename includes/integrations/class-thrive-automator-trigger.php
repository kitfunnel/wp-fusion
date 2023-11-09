<?php
namespace Thrive\Automator\Items;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

class WPF_Thrive_Automator_Trigger extends \WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.40.24
	 * @var string $slug
	 */

	public $slug = 'thrive-automator-trigger';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.40.24
	 * @var string $name
	 */
	public $name = 'Thrive Automator Trigger';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.40.24
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/thrive-automator/';

	/**
	 * Init
	 *
	 * @since 3.40.24
	 */
	public function init() {
		add_action( 'thrive_automator_init', array( $this, 'register_classes' ) );
	}


	/**
	 * Register thrive automator classes.
	 * 
	 * @since 3.40.24
	 */
	public function register_classes() {
		thrive_automator_register_app( new WPFusion_App() );
		thrive_automator_register_trigger( new WPF_Applied_Trigger() );
		thrive_automator_register_trigger( new WPF_Removed_Trigger() );
		thrive_automator_register_trigger( new WPF_Modified_Trigger() );
		thrive_automator_register_data_field( new WPF_Tags_Field() );
		thrive_automator_register_data_object( new WPF_Data() );
	}

}
new WPF_Thrive_Automator_Trigger();

/**
 * Create WPF Thrive App.
 * @since 3.40.24
 */
class WPFusion_App extends App {

	/**
	 * Get thrive App id.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_id() {
		return 'wpfusion';
	}

	/**
	 * Get thrive App name.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_name() {
		return sprintf( __( 'WP Fusion - %s', 'wp-fusion' ), wp_fusion()->crm->name );
	}


	/**
	 * Get thrive App description.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_description() {
		return 'WP Fusion related items';
	}


	/**
	 * Get thrive App logo class.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_logo() {
		return 'tap-wpfusion-logo';
	}
}

/**
 * Run when a tag is modified.
 * @since 3.40.24
 */
class WPF_Modified_Trigger extends Trigger {

	/**
	 * Get the trigger identifier.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_id() {
		return 'wpfusion/tags_modified';
	}

	/**
	 * Get the trigger hook.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_wp_hook() {
		return 'wpf_tags_modified';
	}

	/**
	 * Get thrive APP id.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_app_id() {
		return WPFusion_App::get_id();
	}

	/**
	 * Get the trigger provided params.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public static function get_provided_data_objects() {
		return array( User_Data::get_id(), WPF_Data::get_id() );
	}

	/**
	 * Get the number of params.
	 *
	 * @since 3.40.24
	 * @return int
	 */
	public static function get_hook_params_number() {
		return 2;
	}


	/**
	 * Get the trigger name.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_name() {
		return sprintf( __( '%ss Modified', 'wp-fusion' ), wpf_get_option( 'crm_tag_type', 'Tag' ) );
	}

	/**
	 * Get the trigger description.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_description() {
		return sprintf( __( 'This trigger will be fired whenever a users %ss are modified', 'wp-fusion' ), wpf_get_option( 'crm_tag_type', 'Tag' ) );
	}

	/**
	 * Get the trigger logo class.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_image() {
		return 'tap-wpfusion-logo';
	}

	/**
	 * Process the trigger params.
	 *
	 * @since 3.40.24
	 * @param array $params The trigger params.
	 */
	public function process_params( $params = array() ) {
		$data_objects = array();
		if ( ! empty( $params ) ) {
			/* get all registered data objects and see which ones we use for this trigger */
			$data_object_classes = Data_Object::get();

			// Get user data.
			$user_data = null;
			if ( ! empty( $params[0] ) ) {
				$user_data = get_user_by( 'id', $params[0] );
			}
			if ( empty( $data_object_classes['user_data'] ) ) {
				$data_objects['user_data'] = $user_data;
			} else {
				$data_objects['user_data'] = new $data_object_classes['user_data']( $user_data, $this->get_automation_id() );
			}
		}

		return $data_objects;
	}

}

/**
 * Run when a tag is applied.
 * @since 3.40.24
 */
class WPF_Applied_Trigger extends Trigger {
	/**
	 * Get the trigger identifier.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_id() {
		return 'wpfusion/tags_applied';
	}

	/**
	 * Get the trigger hook.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_wp_hook() {
		return 'wpf_tags_applied';
	}

	/**
	 * Get thrive APP id.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_app_id() {
		return WPFusion_App::get_id();
	}

	/**
	 * Get the trigger provided params.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public static function get_provided_data_objects() {
		return array( User_Data::get_id(), WPF_Data::get_id() );
	}

	/**
	 * Get the number of params.
	 *
	 * @since 3.40.24
	 * @return int
	 */
	public static function get_hook_params_number() {
		return 2;
	}


	/**
	 * Get the trigger name.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_name() {
		return sprintf( __( '%ss Applied', 'wp-fusion' ), wpf_get_option( 'crm_tag_type', 'Tag' ) );
	}

	/**
	 * Get the trigger description.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_description() {
		return sprintf( __( 'This trigger will be fired whenever a %s is applied to a user', 'wp-fusion' ), wpf_get_option( 'crm_tag_type', 'Tag' ) );
	}

	/**
	 * Get the trigger logo class
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_image() {
		return 'tap-wpfusion-logo';
	}

	/**
	 * Process the trigger params.
	 *
	 * @since 3.40.24
	 * @param array $params The trigger params.
	 */
	public function process_params( $params = array() ) {
		$data_objects = array();
		if ( ! empty( $params ) ) {
			/* get all registered data objects and see which ones we use for this trigger */
			$data_object_classes = Data_Object::get();

			// Get tag data.
			$wpf_data = null;
			if ( ! empty( $params[1] ) ) {
				$wpf_data['wpf_tags'] = $params[1];
			}
			if ( empty( $data_object_classes['wpf_data'] ) ) {
				$data_objects['wpf_data'] = $wpf_data;
			} else {
				$data_objects['wpf_data'] = new $data_object_classes['wpf_data']( $wpf_data, $this->get_automation_id() );
			}

			// Get user data.
			$user_data = null;
			if ( ! empty( $params[0] ) ) {
				$user_data = get_user_by( 'id', $params[0] );
			}
			if ( empty( $data_object_classes['user_data'] ) ) {
				$data_objects['user_data'] = $user_data;
			} else {
				$data_objects['user_data'] = new $data_object_classes['user_data']( $user_data, $this->get_automation_id() );
			}
		}

		return $data_objects;
	}

}

/**
 * Run when a tag is removed.
 * @since 3.40.24
 */
class WPF_Removed_Trigger extends Trigger {
	/**
	 * Get the trigger identifier.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_id() {
		return 'wpfusion/tags_removed';
	}

	/**
	 * Get the trigger hook.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_wp_hook() {
		return 'wpf_tags_removed';
	}

	/**
	 * Get thrive APP id.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_app_id() {
		return WPFusion_App::get_id();
	}

	/**
	 * Get the trigger provided params.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public static function get_provided_data_objects() {
		return array( User_Data::get_id(), WPF_Data::get_id() );
	}

	/**
	 * Get the number of params.
	 *
	 * @since 3.40.24
	 * @return int
	 */
	public static function get_hook_params_number() {
		return 2;
	}


	/**
	 * Get the trigger name.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_name() {
		return sprintf( __( '%ss Removed', 'wp-fusion' ), wpf_get_option( 'crm_tag_type', 'Tag' ) );
	}

	/**
	 * Get the trigger description.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_description() {
		return sprintf( __( 'This trigger will be fired whenever a %s is removed from a user', 'wp-fusion' ), wpf_get_option( 'crm_tag_type', 'Tag' ) );
	}

	/**
	 * Get the trigger logo class.
	 * 
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_image() {
		return 'tap-wpfusion-logo';
	}

	/**
	 * Process the trigger params.
	 *
	 * @since 3.40.24
	 * @param array $params The trigger params.
	 */
	public function process_params( $params = array() ) {
		$data_objects = array();
		if ( ! empty( $params ) ) {
			/* get all registered data objects and see which ones we use for this trigger */
			$data_object_classes = Data_Object::get();

			// Get user data.
			$user_data = null;
			if ( ! empty( $params[0] ) ) {
				$user_data = get_user_by( 'id', $params[0] );
			}
			if ( empty( $data_object_classes['user_data'] ) ) {
				$data_objects['user_data'] = $user_data;
			} else {
				$data_objects['user_data'] = new $data_object_classes['user_data']( $user_data, $this->get_automation_id() );
			}
		}

		return $data_objects;
	}

}

/**
 * Create WPF trigger field.
 * @since 3.40.24
 */
class WPF_Tags_Field extends Data_Field {

	/**
	 * Get field name.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_name() {
		return sprintf( __( '%1$s %2$s', 'wp-fusion' ), wp_fusion()->crm->name, strtolower( wpf_get_option( 'crm_tag_type', 'Tag' ) ) );
	}

	/**
	 * Get field descriptions.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_description() {
		return sprintf( __( '%1$s %2$s', 'wp-fusion' ), wp_fusion()->crm->name, strtolower( wpf_get_option( 'crm_tag_type', 'Tag' ) ) );
	}

	/**
	 * Get field placeholder.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_placeholder() {
		return '';
	}

	/**
	 * Get dummy value.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_dummy_value() {
		return wpf_get_option( 'crm_tag_type', 'Tag' );
	}

	/**
	 * For multiple option inputs, name of the callback function called through ajax to get the options.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public static function get_options_callback() {
		$returned_tags = array();
		$tags          = wp_fusion()->settings->get_available_tags_flat();

		foreach ( $tags as $key => $value ) {
			$returned_tags[ $key ] = array(
				'label' => $value,
				'id'    => $key,
			);
		}

		return $returned_tags;
	}

	/**
	 * Get field id.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_id() {
		return 'wpf_tags';
	}

	/**
	 * Get field filters.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public static function get_supported_filters() {
		return array( 'autocomplete' );
	}

	/**
	 * Get field validators.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public static function get_validators() {
		return array( 'required' );
	}

	/**
	 * Check if the field loads using ajax.
	 *
	 * @since 3.40.24
	 * @return boolean
	 */
	public static function is_ajax_field() {
		return true;
	}
	
	/**
	 * Get field type.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_field_value_type() {
		return static::TYPE_ARRAY;
	}
}


/**
 * Create WPF Data object.
 * @since 3.40.24
 */
class WPF_Data extends Data_Object {

	/**
	 * Get the data-object identifier.
	 *
	 * @since 3.40.24
	 * @return string
	 */
	public static function get_id() {
		return 'wpf_data';
	}

	/**
	 * Array of field object keys that are contained by this data-object.
	 *
	 * @since 3.40.24
	 * @return array
	 */
	public static function get_fields() {
		return array(
			WPF_Tags_Field::get_id(),
		);
	}

	/**
	 * Create object.
	 *
	 * @since 3.40.24
	 * @param object $param
	 * @return object.
	 */
	public static function create_object( $param ) {
		return $param;
	}

}
