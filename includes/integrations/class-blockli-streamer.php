<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WPF_Blockli_Streamer.
 *
 * Blockli integration class.
 *
 * @since 3.41.28
 */
class WPF_Blockli_Streamer extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.41.28
	 * @var string $slug
	 */

	public $slug = 'blockli-streamer';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.41.28
	 * @var string $name
	 */
	public $name = 'Blockli Streamer';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.41.28
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/membership/blockli-streamer/';

	/**
	 * Gets things started
	 *
	 * @since 3.41.28
	 */
	public function init() {

		add_action( 'blockli_streamer_video_progression', array( $this, 'video_progress' ), 10, 4 );

		// Meta boxes.
		add_action( 'wpf_meta_box_content', array( $this, 'meta_box_content' ), 40, 2 );
		add_action( 'save_post_blockli_videos', array( $this, 'save_meta_box_data' ), 20, 2 );
	}

	/**
	 * Video Progress
	 * Applies tags based on the progression of a video and the tags set in wpf-settings.
	 * Fires when video data is recieved via an API request.
	 *
	 * @since 3.41.28
	 *
	 * @param int   $video_id  The video ID.
	 * @param int   $user_id   The user ID.
	 * @param float $percentage The percentage of the video watched.
	 * @param bool  $completed Whether the video was completed.
	 */
	public function video_progress( $video_id, $user_id, $percentage, $completed ) {

		$settings = get_post_meta( $video_id, 'wpf-settings', true );

		if ( empty( $settings ) ) {
			return;
		}

		$apply_tags = array();

		if ( $completed || $percentage >= 100 ) {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_video_end'] );
		} else {
			$apply_tags = array_merge( $apply_tags, $settings['apply_tags_video_start'] );
		}

		if ( ! empty( $apply_tags ) ) {
			wp_fusion()->user->apply_tags( $apply_tags, $user_id );
		}
	}

	/**
	 * Meta Box Content
	 * The meta box content for a Blockli video page.
	 *
	 * @since 3.41.28
	 *
	 * @param object $post     The post object.
	 * @param array  $settings The wpf-settings array.
	 */
	public function meta_box_content( $post, $settings ) {
		if ( ! 'blockli_videos' === $post->post_type ) {
			return;
		}

		$defaults = array(
			'apply_tags_video_start' => array(),
			'apply_tags_video_end'   => array(),
		);

		$settings = array_merge( $defaults, $settings );

		echo '<p><label for="wpf-apply-tags-blockli-start"><small>' . __( 'Apply these tags when video started', 'wp-fusion' ) . ':</small></label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_video_start'],
				'meta_name' => 'wpf-settings',
				'field_id'  => 'apply_tags_video_start',
			)
		);

		echo '</p>';

		echo '<p><label for="wpf-apply-tags-blockli-end"><small>' . __( 'Apply these tags when video finished', 'wp-fusion' ) . ':</small></label>';

		wpf_render_tag_multiselect(
			array(
				'setting'   => $settings['apply_tags_video_end'],
				'meta_name' => 'wpf-settings',
				'field_id'  => 'apply_tags_video_end',
			)
		);
	}

	/**
	 * Save Meta Box Data
	 * Saves the tags selected in the meta box for a Blockli video page.
	 *
	 * @since 3.41.28
	 *
	 * @param int    $post_id The post ID.
	 * @param object $post The post object.
	 */
	public function save_meta_box_data( $post_id, $post ) {

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$data = array();

		if ( ! empty( $_POST['wpf-settings'] ) ) {
			$data = $_POST['wpf-settings'];
		}

		if ( ! empty( $data ) ) {

			$data = WPF_Admin_Interfaces::sanitize_tags_settings( $data );
			update_post_meta( $post_id, 'wpf-settings', $data );
		}
	}
}

new WPF_Blockli_Streamer();
