<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class WPF_Memberoni extends WPF_Integrations_Base {

	/**
	 * The slug for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $slug
	 */

	public $slug = 'memberoni';

	/**
	 * The plugin name for WP Fusion's module tracking.
	 *
	 * @since 3.38.14
	 * @var string $name
	 */
	public $name = 'Memberoni';

	/**
	 * The link to the documentation on the WP Fusion website.
	 *
	 * @since 3.38.14
	 * @var string $docs_url
	 */
	public $docs_url = 'https://wpfusion.com/documentation/learning-management/memberoni/';


	/**
	 * Gets things started
	 *
	 * @access  public
	 * @since   3.26.1
	 * @return  void
	 */

	public function init() {

		$this->slug = 'memberoni';

		add_action( 'memberoni_after_mark_complete', array( $this, 'mark_course_complete' ) );
		add_action( 'memberoni_after_track_lesson', array( $this, 'mark_lesson_complete' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20, 2 );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ) );

	}


	/**
	 * Apply tags when course marked complete
	 *
	 * @access public
	 * @return void
	 */

	public function mark_course_complete() {

		$post_id = get_the_ID();

		$settings = get_post_meta( $post_id, 'wpf_settings_memberoni', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_complete'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_complete'] );

		}

	}

	/**
	 * Apply tags when lesson marked complete
	 *
	 * @access public
	 * @return void
	 */

	public function mark_lesson_complete() {

		if ( ! isset( $_POST['lesson_id'] ) ) {
			return;
		}

		$post_id = intval( $_POST['lesson_id'] );

		$settings = get_post_meta( $post_id, 'wpf_settings_memberoni', true );

		if ( ! empty( $settings ) && ! empty( $settings['apply_tags_complete'] ) ) {

			wp_fusion()->user->apply_tags( $settings['apply_tags_complete'] );

		}

	}


	/**
	 * Adds meta box
	 *
	 * @access public
	 * @return void
	 */

	public function add_meta_box( $post_id, $data ) {

		add_meta_box( 'wpf-memberoni-meta', 'WP Fusion - Course Settings', array( $this, 'meta_box_callback' ), 'memberoni_course' );

	}


	/**
	 * Displays meta box content
	 *
	 * @access public
	 * @return mixed
	 */

	public function meta_box_callback( $post ) {

		$settings = array(
			'apply_tags_complete' => array(),
		);

		if ( get_post_meta( $post->ID, 'wpf_settings_memberoni', true ) ) {
			$settings = array_merge( $settings, get_post_meta( $post->ID, 'wpf_settings_memberoni', true ) );
		}

		echo '<table class="form-table"><tbody>';

		echo '<tr>';

		echo '<th scope="row"><label for="tag_link">Apply tags when completed:</label></th>';
		echo '<td>';

		$args = array(
			'setting'   => $settings['apply_tags_complete'],
			'meta_name' => 'wpf_settings_memberoni',
			'field_id'  => 'apply_tags_complete',
		);

		wpf_render_tag_multiselect( $args );

		echo '<span class="description">' . sprintf( __( 'The selected tags will be applied in %s when this course or lesson is marked complete.', 'wp_fusion' ), wp_fusion()->crm->name ) . '</span>';
		echo '</td>';

		echo '</tr>';

		echo '</tbody></table>';

	}

	/**
	 * Runs when WPF meta box is saved
	 *
	 * @access public
	 * @return void
	 */

	public function save_meta_box_data( $post_id ) {

		if ( ! empty( $_POST['wpf_settings_memberoni'] ) ) {
			update_post_meta( $post_id, 'wpf_settings_memberoni', $_POST['wpf_settings_memberoni'] );
		} else {
			delete_post_meta( $post_id, 'wpf_settings_memberoni' );
		}

	}


}

new WPF_Memberoni();
