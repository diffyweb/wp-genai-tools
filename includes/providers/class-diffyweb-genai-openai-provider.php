<?php
/**
 * OpenAI Provider class.
 *
 * @link       https://diffyweb.com/
 * @since      2.7.0
 *
 * @package    DiffyWeb_GenAI_Tools
 * @subpackage DiffyWeb_GenAI_Tools/includes/providers
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class DiffyWeb_GenAI_OpenAI_Provider implements DiffyWeb_GenAI_Provider_Interface {

	/**
	 * The API key for the provider.
	 *
	 * @since 2.7.0
	 * @var string
	 */
	private $api_key;

	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Generates an image using the OpenAI API.
	 *
	 * @since 2.7.0
	 * @param int $post_id The ID of the post.
	 * @return array An array containing the status and message/data.
	 */
	public function generate( $post_id ) {
		if ( empty( $this->api_key ) ) {
			return array(
				'status'  => 'error',
				'message' => 'OpenAI API key is not set.',
			);
		}

		$post         = get_post( $post_id );
		$post_title   = $post->post_title;
		$post_content = wp_strip_all_tags( $post->post_content );
		$post_tags    = get_the_tags( $post_id );

		$keywords = array();
		if ( $post_tags ) {
			foreach ( $post_tags as $tag ) {
				$keywords[] = $tag->name;
			}
		}
		$keywords_string = implode( ', ', $keywords );

		$prompt = "Generate a single, photorealistic, high-quality featured image for a blog post. The image must be visually compelling, relevant to the content, and contain no text, logos, or watermarks. The style should be suitable for a professional blog.\n\nPOST TITLE: {$post_title}\n\nKEYWORDS: {$keywords_string}\n\nCONTENT SUMMARY: " . substr( $post_content, 0, 1000 ) . '...';

		$api_url      = 'https://api.openai.com/v1/images/generations';
		$request_body = array(
			'model'           => 'dall-e-3',
			'prompt'          => $prompt,
			'n'               => 1,
			'size'            => '1024x1024',
			'response_format' => 'b64_json',
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => json_encode( $request_body ),
				'timeout' => 90, // DALL-E 3 can be slower, so a longer timeout is safer.
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 'error',
				'message' => 'WordPress HTTP Error: ' . $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $response_code ) {
			$specific_message = $response_body['error']['message'] ?? 'Could not parse error from API.';
			return array(
				'status'  => 'error',
				'message' => 'OpenAI API Error (Code ' . $response_code . '): ' . $specific_message,
			);
		}

		$base64_image_data = $response_body['data'][0]['b64_json'] ?? null;

		if ( ! $base64_image_data ) {
			error_log( 'Diffyweb GenAI Tools Plugin Error: No image data found in OpenAI response. Full response: ' . print_r( $response_body, true ) );
			return array(
				'status'  => 'error',
				'message' => 'API returned success, but no image data was found. Check server logs for the full API response.',
			);
		}

		return DiffyWeb_GenAI_Image_Helper::upload_and_set_featured_image( $base64_image_data, $post_id, $post_title );
	}
}