<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_E_Signature extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'e-signature';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'WP E-Signature';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/other/e-signature/';

	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.25.15
	 * @return  void
	 */

	public function init() {

		add_action( 'esig_document_basic_closing', array( $this, 'document_signed' ) );

		// "Standalone Docs"
		add_action( 'esig_signature_saved', array( $this, 'document_signed' ) );

		add_filter( 'esig-add-document-form-meta-box', array( $this, 'meta_box' ), 20 );
		add_action( 'esig_document_after_save', array( $this, 'document_after_save' ) );

	}

	/**
	 * Apply tags when document signed
	 *
	 * @access public
	 * @return void
	 */

	public function document_signed( $data ) {

		$apply_tags = WP_E_Sig()->meta->get( $data['sad_doc_id'], 'apply_tags' );

		if ( empty( $apply_tags ) ) {
			return;
		}

		$user_id = $data['recipient']->wp_user_id;

		if ( empty( $user_id ) ) {

			$user = get_user_by( 'email', $data['recipient']->user_email );

			if ( ! empty( $user ) ) {
				$user_id = $user->ID;
			}
		}

		$apply_tags = maybe_unserialize( $apply_tags );

		if ( ! empty( $user_id ) ) {

			wp_fusion()->user->apply_tags( $apply_tags, $user_id );

		} else {

			$contact_id = $this->guest_registration( $data['recipient']->user_email, (array) $data['recipient'] );

			wpf_log( 'info', 0, 'Applying tags for document signing to contact #' . $contact_id . ': ', array( 'tag_array' => $apply_tags ) );

			wp_fusion()->crm->apply_tags( $apply_tags, $contact_id );

		}

	}

	/**
	 * Meta box content
	 *
	 * @access public
	 * @return array Registration / Update Data
	 */

	public function meta_box() {

		ob_start(); ?>

		<div class="esign-form-document-panel">	

			<!-- Start of Meta Box -->
			<div id="wpf-center-meta" class="postbox esign-form-panel">
				<h3 class="hndle esig-section-title"><span><?php _e( 'WP Fusion', 'wp-fusion' ); ?></span></h3>
				<div class="esig-inside">

					<p><strong><?php _e( 'Apply Tags', 'wp-fusion' ); ?></strong></p>
					<p>

						<?php

						$apply_tags = WP_E_Sig()->meta->get( absint( $_GET['document_id'] ), 'apply_tags' );

						$apply_tags = maybe_unserialize( $apply_tags );

						if ( empty( $apply_tags ) ) {
							$apply_tags = array();
						}

						$args = array(
							'setting'   => $apply_tags,
							'meta_name' => 'apply_tags',
						);

						wpf_render_tag_multiselect( $args );

						?>

						<span class="description"><?php echo sprintf( __( 'Apply these tags in %s when the document is signed', 'wp-fusion' ), wp_fusion()->crm->name ); ?></span>

					</p>

				</div>
			</div>
		</div>

		<?php

		return ob_get_clean();

	}

	/**
	 * Save WPF settings on document
	 *
	 * @access public
	 * @return void
	 */

	public function document_after_save( $args ) {

		if ( ! empty( $_POST['apply_tags'] ) ) {

			WP_E_Sig()->meta->add( $args['document']->document_id, 'apply_tags', serialize( $_POST['apply_tags'] ) );

		}

	}


}

new WPF_E_Signature();
