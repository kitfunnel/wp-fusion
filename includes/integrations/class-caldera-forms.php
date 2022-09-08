<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Caldera_Forms extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'caldera-forms';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Caldera-forms';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/lead-generation/caldera-forms/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   1.0
	 * @return  void
	 */

	public function init() {

		add_filter( 'caldera_forms_get_form_processors', array( $this, 'register_processor' ) );

		add_action( 'caldera_forms_processor_templates', array( $this, 'processor_template' ) );

	}


	/**
	 * Registers form processor
	 *
	 * @access public
	 * @return array Processors
	 */

	public function register_processor( $processors ) {

		$processors['wp_fusion'] = array(
			'name'        => 'WP Fusion',
			'description' => sprintf( __( 'Send form entries to %s' ), wp_fusion()->crm->name ),
			'processor'   => array( $this, 'process' ),
		);

		return $processors;

	}


	/**
	 * Output processor template
	 *
	 * @access public
	 * @return mixed
	 */

	public function processor_template( $processors ) {

		foreach ( $processors as $processor => $config ) {

			if ( $processor == 'wp_fusion' ) { ?>

				<script type="text/html" id="<?php echo $processor; ?>-tmpl">

					<p class="description"><?php echo $config['description']; ?></p><br>

					<div class="caldera-config-group">
						<label for="wpf-apply-tags"><?php echo __( 'Apply Tags', 'wp-fusion' ); ?></label>
						<div class="caldera-config-field">
							
							<input id="wpf-apply-tags" type="text" class="block-input field-config" name="{{_name}}[apply_tags]" value="{{apply_tags}}" />

							<span class="description"><?php echo __( 'Enter a comma-separated list of tag names or tag IDs to be applied when this form is submitted.', 'wp-fusion' ); ?></span>

						</div>
					</div>

					<hr />

					<div class="caldera-config-group">
						<label><strong><?php echo wp_fusion()->crm->name; ?> Field</strong></label>
						<div class="caldera-config-field"><strong>Select a form field</strong></div>
					</div>

					<?php

					$crm_fields = array();

					$available_fields = wpf_get_option( 'crm_fields' );

					if ( ! empty( $available_fields ) ) :

						foreach ( $available_fields as $group_header => $fields ) {

							if ( is_array( $fields ) ) {

								foreach ( $fields as $key => $value ) {
									$crm_fields[ $key ] = $value;
								}
							} else {

								$crm_fields[ $group_header ] = $fields;

							}
						}

						foreach ( $crm_fields as $field => $label ) :

							// Sanitize fields
							$field = str_replace( '[', 'lbracket', $field );
							$field = str_replace( ']', 'rbracket', $field );
							$field = str_replace( ',', 'comma', $field );

							?>

							<div class="caldera-config-group">
								<label for="wpf-crm-field-<?php echo $field; ?>">
									<?php echo $label; ?>
								</label>
								<div class="caldera-config-field">
									<input id="wpf-crm-field-<?php echo $field; ?>" type="text" class="block-input field-config magic-tag-enabled 
																		<?php
																		if ( strtolower( $label ) == 'email' ) {
																			echo 'required';}
																		?>
									" name="{{_name}}[<?php echo $field; ?>]" value="{{<?php echo $field; ?>}}" />
								</div>
							</div>

						<?php endforeach; ?>

					<?php endif; ?>

				</script>

				<?php

			}
		}

	}


	/**
	 * Process form submission
	 *
	 * @access public
	 * @return void
	 */

	public function process( $config, $form, $process_id ) {

		$email_address = false;
		$update_data   = array();

		foreach ( $config as $key => $value ) {

			if ( empty( $value ) || strpos( $value, '%' ) === false ) {
				continue;
			}

			// Un-sanitize fields
			$key = str_replace( 'lbracket', '[', $key );
			$key = str_replace( 'rbracket', ']', $key );
			$key = str_replace( 'comma', ',', $key );

			$update_data[ $key ] = Caldera_Forms::do_magic_tags( $value );

			if ( false == $email_address && is_email( $update_data[ $key ] ) ) {
				$email_address = $update_data[ $key ];
			}
		}

		$tags_exploded = explode( ',', $config['apply_tags'] );
		$apply_tags    = array();

		foreach ( $tags_exploded as $tag ) {

			$id = wp_fusion()->user->get_tag_id( $tag );

			if ( false !== $id ) {
				$apply_tags[] = $id;
			}
		}

		$args = array(
			'email_address'    => $email_address,
			'update_data'      => $update_data,
			'apply_tags'       => $apply_tags,
			'integration_slug' => 'caldera_forms',
			'integration_name' => 'Caldera Forms',
			'form_id'          => $form['ID'],
			'form_title'       => $form['name'],
			'form_edit_link'   => admin_url( 'admin.php?edit=' . $form['ID'] . '&page=caldera-forms' ),
		);

		$contact_id = WPF_Forms_Helper::process_form_data( $args );

	}


}

new WPF_Caldera_Forms();
