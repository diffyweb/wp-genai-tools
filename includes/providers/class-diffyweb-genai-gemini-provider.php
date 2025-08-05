<?php
/**
 * Gemini Provider class.
 *
 * @link       https://diffyweb.com/
 * @since      2.7.0
 *
 * @package    Diffyweb_GenAI_Tools
 * @subpackage Diffyweb_GenAI_Tools/includes/providers
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Diffyweb_GenAI_Gemini_Provider implements Diffyweb_GenAI_Provider_Interface {

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
	 * Generates an image using the Gemini API.
	 *
	 * @since 2.7.0
	 * @param int $post_id The ID of the post.
	 * @return array An array containing the status and message/data.
	 */
	public function generate( $post_id ) {
		if ( empty( $this->api_key ) ) {
			return array(
				'status'  => 'error',
				'message' => 'Gemini API key is not set.',
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
		$content_summary = substr( $post_content, 0, 1000 ) . '...';

		$default_prompt  = "Task: Generate a single photorealistic image. Do not return text. The image should be a high-quality featured image for a blog post, visually compelling and relevant to the content. Do not include any text, logos, or watermarks in the image.\n\nPOST TITLE: {{post_title}}\n\nKEYWORDS: {{keywords_string}}\n\nCONTENT SUMMARY: {{post_content_summary}}";
		$prompt_template = is_multisite() ? get_site_option( 'diffyweb_genai_tools_gemini_prompt', $default_prompt ) : get_option( 'diffyweb_genai_tools_gemini_prompt', $default_prompt );

		$placeholders = [
			'{{post_title}}'           => $post_title,
			'{{keywords_string}}'      => $keywords_string,
			'{{post_content_summary}}' => $content_summary,
		];

		$prompt = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $prompt_template );

		$api_url      = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent?key=' . $this->api_key;
		$request_body = array(
			'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
			'generationConfig' => array( 'responseModalities' => array( 'TEXT', 'IMAGE' ) ),
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => json_encode( $request_body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 'error',
				'message' => 'WordPress HTTP Error: ' . $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$response_body     = wp_remote_retrieve_body( $response );
			$api_error_details = json_decode( $response_body, true );
			$specific_message  = $api_error_details['error']['message'] ?? 'Could not parse error from API.';
			return array(
				'status'  => 'error',
				'message' => 'Gemini API Error (Code ' . $response_code . '): ' . $specific_message,
			);
		}

		$response_body     = json_decode( wp_remote_retrieve_body( $response ), true );
		$base64_image_data = null;
		if ( isset( $response_body['candidates'][0]['content']['parts'] ) ) {
			foreach ( $response_body['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['inlineData']['data'] ) ) {
					$base64_image_data = $part['inlineData']['data'];
					break;
				}
			}
		}

		if ( ! $base64_image_data ) {
			error_log( 'Diffyweb GenAI Tools Plugin Error: No image data found in response. Full response: ' . print_r( $response_body, true ) );
			return array(
				'status'  => 'error',
				'message' => 'API returned success, but no image data was found. The model may have returned text instead. Check server logs for the full API response.',
			);
		}

		return Diffyweb_GenAI_Image_Helper::upload_and_set_featured_image( $base64_image_data, $post_id, $post_title );
	}
}