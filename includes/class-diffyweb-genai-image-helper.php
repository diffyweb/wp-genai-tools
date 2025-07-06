<?php
/**
 * Helper class for image-related tasks.
 *
 * @link       https://diffyweb.com/
 * @since      2.7.0
 *
 * @package    DiffyWeb_GenAI_Tools
 * @subpackage DiffyWeb_GenAI_Tools/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class DiffyWeb_GenAI_Image_Helper {

	/**
	 * Uploads a base64 encoded image to the media library and sets it as the featured image.
	 *
	 * @since 2.7.0
	 * @param string $base64_image_data The base64 encoded image data.
	 * @param int    $post_id           The ID of the post.
	 * @param string $post_title        The title of the post.
	 * @return array An array containing the status and attachment ID or error message.
	 */
	public static function upload_and_set_featured_image( $base64_image_data, $post_id, $post_title ) {
		$image_data = base64_decode( $base64_image_data );
		$filename   = sanitize_title( $post_title ) . '-' . time() . '.png';
		$upload     = wp_upload_bits( $filename, null, $image_data );

		if ( ! empty( $upload['error'] ) ) {
			return array(
				'status'  => 'error',
				'message' => 'Could not save image to media library.',
			);
		}

		$attachment    = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_text_field( $post_title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

		if ( ! is_wp_error( $attachment_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
			set_post_thumbnail( $post_id, $attachment_id );

			// Set the alt text for the attachment.
			$alt_text = $post_title . ' featured image.';
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

			return array(
				'status'        => 'success',
				'attachment_id' => $attachment_id,
			);
		} else {
			return array(
				'status'  => 'error',
				'message' => 'Could not create attachment in WordPress.',
			);
		}
	}
}