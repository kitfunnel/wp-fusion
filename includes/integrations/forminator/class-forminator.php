<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-forminator-addon-wp-fusion.php';
require_once __DIR__ . '/class-forminator-addon-wp-fusion-form-settings.php';
require_once __DIR__ . '/class-forminator-addon-wp-fusion-form-settings-exception.php';
require_once __DIR__ . '/class-forminator-addon-wp-fusion-form-hooks.php';

/**
 * Forminator integration main class.
 *
 * @since 3.42.0
 *
 * @link https://wpfusion.com/documentation/lead-generation/forminator/
 */
class WPF_Forminator extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.42.0
	 * @var string $slug
	 */

	public $slug = 'forminator';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.42.0
	 * @var string $name
	 */
	public $name = 'Forminator';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.42.0
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/lead-generation/forminator/';

	/**
	 * Gets things started.
	 *
	 * @since 3.42.0
	 */
	public function init() {}

	/**
	 * Return setup options(Tags) template.
	 *
	 * @param array $params
	 * @return array
	 */
	public static function setup_options_template( $params ) {

		$template_vars = $params;
		ob_start();
		// defaults.
		$vars = array(
			'error_message'        => '',
			'multi_id'             => '',
			'tags_error'           => '',
			'tags_selected_fields' => array(),
			'tags_fields'          => array(),
		);
		/** @var array $template_vars */
		foreach ( $template_vars as $key => $val ) {
			$vars[ $key ] = $val;
		}
		?>
		
		<div class="forminator-integration-popup__header">
		
			<h3 id="forminator-integration-popup__title" class="sui-box-title sui-lg" style="overflow: initial; white-space: normal; text-overflow: initial;"><?php esc_html_e( 'Additional Options', 'wp-fusion' ); ?></h3>
		
			<p id="forminator-integration-popup__description" class="sui-description">
				<?php esc_html_e( sprintf( __( 'Configure additional options for %s integration', 'wp-fusion' ), wp_fusion()->crm->name ) ); ?>
			</p>
		
			<?php if ( ! empty( $vars['error_message'] ) ) : ?>
				<div
					role="alert"
					class="sui-notice sui-notice-red sui-active"
					style="display: block; text-align: left;"
					aria-live="assertive"
				>
		
					<div class="sui-notice-content">
		
						<div class="sui-notice-message">
		
							<span class="sui-notice-icon sui-icon-info" aria-hidden="true"></span>
		
							<p><?php echo esc_html( $vars['error_message'] ); ?></p>
		
						</div>
		
					</div>
		
				</div>
			<?php endif; ?>
		
		</div>
		
		<form>
		
			<div class="sui-form-field<?php echo esc_attr( ! empty( $vars['tags_error'] ) ? ' sui-form-field-error' : '' ); ?>">
		
				<label class="sui-label" for="tags"><?php esc_html_e( 'Tags', 'wp-fusion' ); ?></label>
		
				<select name="tags[]"
					multiple="multiple"
					data-reorder="1"
					data-tags="true"
					data-token-separators="[',']"
					data-placeholder=""
					data-allow-clear="false"
					id="tags"
					class="sui-select"
				>
		
					<?php foreach ( $vars['tags_selected_fields'] as $key => $value ) : ?>

						<option value="<?php echo esc_attr( $key ); ?>"
							selected="selected">
							<?php echo esc_html( $value ); ?>
						</option>

					<?php endforeach; ?>


					<?php foreach ( $vars['tags_fields'] as $key => $value ) : ?>
		
						<option value="<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( $value ); ?>
						</option>
		
					<?php endforeach; ?>
		
				</select>
		
				<?php if ( ! empty( $vars['tags_error'] ) ) : ?>
					<span class="sui-error-message"><?php echo esc_html( $vars['tags_error'] ); ?></span>
				<?php endif; ?>
		
				<span class="sui-description">
					<?php esc_html_e( sprintf( __( 'Tags to apply in %s on form submission.', 'wp-fusion' ), wp_fusion()->crm->name ) ); ?>
				</span>
		
			</div>

			<input type="hidden" name="multi_id" value="<?php echo esc_attr( $vars['multi_id'] ); ?>">
		</form>

		<?php
		$html = ob_get_clean();
		return $html;
	}

	/**
	 * Return map fields template.
	 *
	 * @param array $params
	 * @return array
	 */
	public static function map_fields_template( $params ) {
		$template_vars = $params;
		ob_start();
		// defaults.
		$vars = array(
			'error_message' => '',
			'multi_id'      => '',
			'fields_map'    => array(),
			'fields'        => array(),
			'form_fields'   => array(),
			'email_fields'  => array(),
		);
		/** @var array $template_vars */
		foreach ( $template_vars as $key => $val ) {
			$vars[ $key ] = $val;
		}
		?>
		<div class="forminator-integration-popup__header">

			<h3 id="forminator-integration-popup__title" class="sui-box-title sui-lg" style="overflow: initial; white-space: normal; text-overflow: initial;">
				<?php esc_html_e( 'Assign Fields', 'wp-fusion' ); ?>
			</h3>

			<p id="forminator-integration-popup__description" class="sui-description">
				<?php esc_html_e( sprintf( __( 'Match up your form fields with your %s fields to make sure we\'re sending data to the right place.', 'wp-fusion' ), wp_fusion()->crm->name ) ); ?>
			</p>

			<?php if ( ! empty( $vars['error_message'] ) ) : ?>
				<div
					role="alert"
					class="sui-notice sui-notice-red sui-active"
					style="display: block; text-align: left;"
					aria-live="assertive"
				>

					<div class="sui-notice-content">

						<div class="sui-notice-message">

							<span class="sui-notice-icon sui-icon-info" aria-hidden="true"></span>

							<p><?php echo esc_html( $vars['error_message'] ); ?></p>

						</div>

					</div>

				</div>
			<?php endif; ?>

		</div>

		<form>
			<table class="sui-table">
				<thead>
				<tr>
					<th><?php esc_html_e( sprintf( __( '%s Field', 'wp-fusion' ), wp_fusion()->crm->name ) ); ?></th>
					<th><?php esc_html_e( 'Forminator Field', 'wp-fusion' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $vars['fields'] as $key => $field_title ) : ?>
					<tr>
						<td>
							<?php echo esc_html( $field_title ); ?>
							<?php if ( 'email' === $key ) : ?>
								<span class="integrations-required-field">*</span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$forminator_fields = $vars['form_fields'];
							if ( 'email' === $key ) {
								$forminator_fields = $vars['email_fields'];
							}
							$current_error    = '';
							$current_selected = '';
							if ( isset( $vars[ $key . '_error' ] ) && ! empty( $vars[ $key . '_error' ] ) ) {
								$current_error = $vars[ $key . '_error' ];
							}
							if ( isset( $vars['fields_map'][ $key ] ) && ! empty( $vars['fields_map'][ $key ] ) ) {
								$current_selected = $vars['fields_map'][ $key ];
							}
							?>
							<div class="sui-form-field <?php echo esc_attr( ! empty( $current_error ) ? 'sui-form-field-error' : '' ); ?>">
								<select data-allow-clear="true" name="fields_map[<?php echo esc_attr( $key ); ?>]" class="sui-select sui-select-sm" data-placeholder="<?php esc_html_e( 'Please select a field', 'wp-fusion' ); ?>">
									<option></option>
									<?php foreach ( $forminator_fields as $forminator_field ) : ?>
										<option value="<?php echo esc_attr( $forminator_field['element_id'] ); ?>"
											<?php selected( $current_selected, $forminator_field['element_id'] ); ?>>
											<?php echo esc_html( strip_tags( $forminator_field['field_label'] ) . ' | ' . $forminator_field['element_id'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( ! empty( $current_error ) ) : ?>
									<span class="sui-error-message"><?php echo esc_html( $current_error ); ?></span>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<input type="hidden" name="multi_id" value="<?php echo esc_attr( $vars['multi_id'] ); ?>">
		</form>

		<?php
		$html = ob_get_clean();
		return $html;
	}

	/**
	 * Return select list template.
	 *
	 * @param array $params
	 * @return array
	 */
	public static function select_list_template( $params ) {
		$template_vars = $params;
		ob_start();
		// defaults.
		$vars = array(
			'error_message' => '',
			'list_id'       => '',
			'list_id_error' => '',
			'multi_id'      => '',
			'lists'         => array(),
		);
		/** @var array $template_vars */
		foreach ( $template_vars as $key => $val ) {
			$vars[ $key ] = $val;
		}
		?>

		<div class="forminator-integration-popup__header">

			<h3 id="forminator-integration-popup__title" class="sui-box-title sui-lg" style="overflow: initial; white-space: normal; text-overflow: initial;"><?php esc_html_e( 'Choose Contact List', 'wp-fusion' ); ?></h3>

			<p id="forminator-integration-popup__description" class="sui-description"><?php esc_html_e( 'Pick WPFusion List for new contacts to be added to.', 'wp-fusion' ); ?></p>

			<?php if ( ! empty( $vars['error_message'] ) ) : ?>
				<div
					role="alert"
					class="sui-notice sui-notice-red sui-active"
					style="display: block; text-align: left;"
					aria-live="assertive"
				>

					<div class="sui-notice-content">

						<div class="sui-notice-message">

							<span class="sui-notice-icon sui-icon-info" aria-hidden="true"></span>

							<p><?php echo esc_html( $vars['error_message'] ); ?></p>

						</div>

					</div>

				</div>
			<?php endif; ?>

		</div>

		<form>
			<div class="sui-form-field <?php echo esc_attr( ! empty( $vars['list_id_error'] ) ? 'sui-form-field-error' : '' ); ?>" style="margin-bottom: 0;">
				<label class="sui-label" for="wpfusion-list-id"><?php esc_html_e( 'List', 'wp-fusion' ); ?></label>
				<select class="sui-select" name="list_id" id="wpfusion-list-id" data-placeholder="<?php esc_html_e( 'Please select a list', 'wp-fusion' ); ?>">
					<option></option>
					<?php foreach ( $vars['lists'] as $list_id => $list_name ) : ?>
						<option value="<?php echo esc_attr( $list_id ); ?>" <?php selected( $vars['list_id'], $list_id ); ?>><?php echo esc_html( $list_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( ! empty( $vars['list_id_error'] ) ) : ?>
					<span class="sui-error-message"><?php echo esc_html( $vars['list_id_error'] ); ?></span>
				<?php endif; ?>
			</div>
			<input type="hidden" name="multi_id" value="<?php echo esc_attr( $vars['multi_id'] ); ?>">
		</form>

		<?php
		$html = ob_get_clean();
		return $html;
	}

	/**
	 * Return pick name template
	 *
	 * @param array $params
	 * @return array
	 */
	public static function pick_name_template( $params ) {
		$template_vars = $params;
		ob_start();
		// defaults.
		$vars = array(
			'error_message' => '',
			'name'          => '',
			'name_error'    => '',
			'multi_id'      => '',
		);
		/** @var array $template_vars */

		foreach ( $template_vars as $key => $val ) {
			$vars[ $key ] = $val;
		}
		?>

		<div class="forminator-integration-popup__header">

			<h3 id="forminator-integration-popup__title" class="sui-box-title sui-lg" style="overflow: initial; white-space: normal; text-overflow: initial;"><?php esc_html_e( 'Set Up Name', 'wp-fusion' ); ?></h3>

			<p id="forminator-integration-popup__description" class="sui-description"><?php esc_html_e( 'Set up a friendly name for this integration, so you can easily identify it.', 'wp-fusion' ); ?></p>

			<?php if ( ! empty( $vars['error_message'] ) ) : ?>
				<div
					role="alert"
					class="sui-notice sui-notice-red sui-active"
					style="display: block; text-align: left;"
					aria-live="assertive"
				>

					<div class="sui-notice-content">

						<div class="sui-notice-message">

							<span class="sui-notice-icon sui-icon-info" aria-hidden="true"></span>

							<p><?php echo esc_html( $vars['error_message'] ); ?></p>

						</div>

					</div>

				</div>
			<?php endif; ?>

		</div>

		<form>
			<div class="sui-form-field <?php echo esc_attr( ! empty( $vars['name_error'] ) ? 'sui-form-field-error' : '' ); ?>">
				<label class="sui-label"><?php esc_html_e( 'Name', 'wp-fusion' ); ?></label>
				<input
						class="sui-form-control"
						name="name" placeholder="<?php esc_attr_e( 'Friendly Name', 'wp-fusion' ); ?>"
						value="<?php echo esc_attr( $vars['name'] ); ?>">
				<?php if ( ! empty( $vars['name_error'] ) ) : ?>
					<span class="sui-error-message"><?php echo esc_html( $vars['name_error'] ); ?></span>
				<?php endif; ?>
			</div>
			<input type="hidden" name="multi_id" value="<?php echo esc_attr( $vars['multi_id'] ); ?>">
		</form>
		<?php
		$html = ob_get_clean();
		return $html;
	}
}

Forminator_Addon_Loader::get_instance()->register( 'Forminator_Addon_WP_Fusion' );

new WPF_Forminator();
