<?php
/**
 * Interface for AI providers.
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

interface DiffyWeb_GenAI_Provider_Interface {
	/**
	 * Generates an image based on the post context.
	 *
	 * @param int $post_id The ID of the post.
	 * @return array An array containing the status and message/data.
	 */
	public function generate( $post_id );
}