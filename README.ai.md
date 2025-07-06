# Project: GenAI Tools for WordPress - Conversational Changelog &amp; Context
# Version: 2.0.4
# Last Updated: 2025-07-06
# Purpose: This document serves as a comprehensive development history for the GenAI Tools WordPress plugin. It is intended for use by AI development assistants (e.g., Gemini for VS Code, GitHub Copilot) to provide full context on the project&#39;s evolution, technical decisions, and debugging history.
# INSTRUCTIONS FOR AI: Periodically update this file to reflect new development iterations, user feedback, and bug fixes to maintain a continuous context log.

## Phase 1: Initial Concept & n8n Workflow

  - **Objective:** Generate an image using a Google Gemini model from within an n8n workflow.
  - **Initial Model:** `gemini-2.0-flash-preview-image-generation`.
  - **Initial Approach:** User attempted to use the built-in n8n AI node.
  - **Problem:** Received `400 Bad Request` error. Investigation revealed the prompt was structured as key-value pairs instead of a descriptive paragraph required by the image model.
  - **Decision Point:** The conversation pivoted from a simple n8n workflow to creating a more robust, integrated solution directly in WordPress. This decision was made to gain more control over the API request and user experience.

-----

## Phase 2: WordPress Plugin - Initial Development & Debugging

  - **Objective:** Create a WordPress plugin that automatically generates a featured image on post save.
  - **Core Functionality:**
      - A settings page was created to store the Google Gemini API key.
      - A function was hooked into `save_post` to trigger the generation.
  - **Debugging Iterations:**
      - **Problem:** "Silent Fail" on "Save Draft".
      - **Diagnosis:** The `save_post` hook was too unreliable for this task, as it was firing before content was ready.
      - **Solution:** A manual trigger was implemented. A meta box with a "Generate Image" button was added to the post editor. This button uses AJAX to call the image generation function, providing immediate UI feedback and bypassing the `save_post` complexities.
      - **Problem:** `Error: Failed to connect to the Gemini API.`
      - **Diagnosis:** The generic error message hid the true cause.
      - **Solution:** The plugin's error handling was improved to pass the specific `WP_Error` or API error message back to the UI.
      - **Problem:** `Error: Gemini API Error (Code 400): * GenerateContentRequest.generation_config.response_mime_type: allowed mimetypes are text/plain...`
      - **Diagnosis:** This was the key breakthrough. It proved the model being called (`gemini-1.5-flash-latest` at that point) did not support image `responseMimeType`.
      - **Solution:** The plugin was updated to use the exact model and request body from a known-working `curl` command provided by the user:
          - **Model:** `gemini-2.0-flash-preview-image-generation`
          - **Config:** `"generationConfig": { "responseModalities": ["TEXT", "IMAGE"] }`
      - **Problem:** `Error: API returned success, but no image data was found.`
      - **Diagnosis:** The model was still sometimes returning only text.
      - **Solution:** The prompt was made more explicit, prepending "Task: Generate a single photorealistic image. Do not return text." to ensure the model's intent was clear.

-----

## Phase 3: Refactoring into an Extensible "AI Kit"

  - **Objective:** Future-proof the plugin to easily accommodate other AI services (OpenAI/DALL-E, etc.).
  - **User Request:** The user created a new GitHub repository at `diffyweb/wp-genai-tools`.
  - **Refactoring decisions:**
      - **Plugin Name:** Renamed to "GenAI Tools".
      - **Architecture:** Moved from a single-purpose plugin to a modular "kit".
      - **Settings Page:** Rebuilt with a tabbed interface to hold settings for different providers (Gemini, OpenAI). Added detailed, collapsible instructions for obtaining API keys for each service.
      - **Editor UI:** The meta box was updated with a dropdown to select the AI provider.
      - **Code Structure:** The core API call logic was separated into provider-specific functions (e.g., `generate_with_gemini()`) to make adding new providers cleaner.
      - **Self-Hosted Updater:** A PHP class was added to the plugin to handle automatic updates by checking an `info.json` file hosted in the `diffyweb/wp-genai-tools` GitHub repository. This avoids the need for the official WordPress.org plugin repository.

-----

## Phase 4: Final UI/UX Polish

  - **Objective:** Improve the user experience after a successful image generation.
  - **Problem:** After generating an image, the user had to manually save or refresh the post to see the new featured image thumbnail in the editor.
  - **Solution:** The plugin's AJAX JavaScript was modified.
      - The PHP AJAX handler was updated to return the new WordPress attachment ID upon successful image creation.
      - The JavaScript success callback now uses this ID with the WordPress core media function `wp.media.featuredImage.set(attachmentId)` to instantly refresh the featured image display without a page reload.

-----

## Final Code Artifact (Version 2.0.4)

```php
<?php
/**
 * Plugin Name:       GenAI Tools
 * Description:       A toolkit for integrating various Generative AI services (like Gemini, DALL-E, etc.) into the WordPress content workflow.
 * Version:           2.0.4
 * Author:            Diffy Web/Gemini
 * License:           GPL-2.0-or-later
 * License URI:       [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
 * Text Domain:       wp-genai-tools
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Main plugin class, now structured as a toolkit.
 */
final class WP_GenAI_Tools {

    private static $_instance = null;
    public $version = '2.0.4';

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_generation_meta_box' ] );
        add_action( 'wp_ajax_genai_generate_image', [ $this, 'handle_ajax_image_generation' ] );
        
        $this->initialize_updater();
    }

    /**
     * Initialize the self-hosted updater.
     */
    private function initialize_updater() {
        $update_url = '[https://raw.githubusercontent.com/diffyweb/wp-genai-tools/main/info.json](https://raw.githubusercontent.com/diffyweb/wp-genai-tools/main/info.json)';
        
        if ( ! class_exists( 'WP_GenAI_Tools_Updater' ) ) {
            // The updater class is included at the bottom of this file.
        }
        new WP_GenAI_Tools_Updater( __FILE__, $update_url );
    }

    /**
     * Add the main settings page.
     */
    public function add_admin_menu() {
        add_options_page( 'GenAI Tools Settings', 'GenAI Tools', 'manage_options', 'wp-genai-tools', [ $this, 'render_settings_page' ] );
    }

    /**
     * Render the settings page with a tabbed interface and instructions.
     */
    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'gemini_settings';
        ?>
        <div class="wrap">
            <h1>GenAI Tools Settings</h1>
            <p>Configure API keys for various Generative AI services.</p>

            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-genai-tools&tab=gemini_settings" class="nav-tab <?php echo $active_tab == 'gemini_settings' ? 'nav-tab-active' : ''; ?>">Gemini</a>
                <a href="?page=wp-genai-tools&tab=openai_settings" class="nav-tab <?php echo $active_tab == 'openai_settings' ? 'nav-tab-active' : ''; ?>">OpenAI (DALL-E)</a>
            </h2>

            <form action="options.php" method="post">
                <?php
                if ( $active_tab == 'gemini_settings' ) {
                    settings_fields( 'genai_tools_gemini_options' );
                    do_settings_sections( 'wp-genai-tools-gemini' );
                } elseif ( $active_tab == 'openai_settings' ) {
                    settings_fields( 'genai_tools_openai_options' );
                    do_settings_sections( 'wp-genai-tools-openai' );
                }
                submit_button( 'Save Settings' );
                ?>
            </form>
            
            <hr>
            
            <h2><span class="dashicons dashicons-info-outline"></span> API Key Instructions</h2>
            
            <?php if ( $active_tab == 'gemini_settings' ) : ?>
            <details open>
                <summary><strong>Google Gemini API Instructions</strong></summary>
                <div style="padding-left: 20px; border-left: 2px solid #ccc; margin-top: 10px; max-width: 800px;">
                    <p>For this plugin to work, your API key must be associated with a Google Cloud project that has the <strong>Generative Language API</strong> enabled and billing configured.</p>
                    <ol>
                        <li><strong>Create or Select a Project:</strong> Go to the <a href="[https://console.cloud.google.com/projectselector2/home/dashboard](https://console.cloud.google.com/projectselector2/home/dashboard)" target="_blank">Google Cloud Console</a> and create a new project or select an existing one.</li>
                        <li><strong>Enable Billing:</strong> You must <a href="[https://console.cloud.google.com/billing](https://console.cloud.google.com/billing)" target="_blank">enable billing</a> for your project. This is required even to use the free tier.</li>
                        <li><strong>Enable the API:</strong> Go to the API library and enable the <a href="[https://console.cloud.google.com/apis/library/generativelanguage.googleapis.com](https://console.cloud.google.com/apis/library/generativelanguage.googleapis.com)" target="_blank">Generative Language API</a> for your project.</li>
                        <li><strong>Create an API Key:</strong> Go to the <a href="[https://console.cloud.google.com/apis/credentials](https://console.cloud.google.com/apis/credentials)" target="_blank">Credentials page</a> and click "Create Credentials" &rarr; "API key".</li>
                        <li><strong>Restrict the Key (Important for Security):</strong>
                            <ul>
                                <li>After the key is created, click on its name to edit it.</li>
                                <li>Under "API restrictions", select "Restrict key".</li>
                                <li>In the dropdown, find and select "Generative Language API". This ensures the key can *only* be used for this specific service.</li>
                                <li>Click "Save".</li>
                            </ul>
                        </li>
                        <li><strong>Copy and Paste:</strong> Copy your new, restricted API key and paste it into the field above.</li>
                    </ol>
                     <p><em>Alternatively, the simplest way to get a key for personal use is from <a href="[https://aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey)" target="_blank">Google AI Studio</a>, which handles the project creation and API enablement for you.</em></p>
                </div>
            </details>
            <?php endif; ?>

            <?php if ( $active_tab == 'openai_settings' ) : ?>
            <details open>
                <summary><strong>OpenAI (DALL-E) API Instructions</strong></summary>
                <div style="padding-left: 20px; border-left: 2px solid #ccc; margin-top: 10px; max-width: 800px;">
                    <p>To use DALL-E for image generation, you need an API key from an OpenAI account with a payment method on file.</p>
                    <ol>
                        <li><strong>Create or Log In to an Account:</strong> Go to the <a href="[https://platform.openai.com/](https://platform.openai.com/)" target="_blank">OpenAI Platform</a> and sign up or log in.</li>
                        <li><strong>Set Up Billing:</strong> You must add a payment method to your account to use the API. Go to the <a href="[https://platform.openai.com/account/billing/overview](https://platform.openai.com/account/billing/overview)" target="_blank">Billing page</a> and set up payment details.</li>
                        <li><strong>Create an API Key:</strong> Go to the <a href="[https://platform.openai.com/api-keys](https://platform.openai.com/api-keys)" target="_blank">API Keys page</a>.</li>
                        <li>Click "Create new secret key".</li>
                        <li>Give the key a name (e.g., "WordPress GenAI Tools") and click "Create secret key".</li>
                        <li><strong>Copy and Paste:</strong> Copy your new key immediately (you will not be able to see it again) and paste it into the field above.</li>
                    </ol>
                     <p><em>You can view API usage and pricing information on the <a href="[https://platform.openai.com/usage](https://platform.openai.com/usage)" target="_blank">Usage</a> and <a href="[https://openai.com/pricing](https://openai.com/pricing)" target="_blank">Pricing</a> pages.</em></p>
                </div>
            </details>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Register all settings for the different services.
     */
    public function register_settings() {
        register_setting( 'genai_tools_gemini_options', 'genai_tools_gemini_api_key', 'sanitize_text_field' );
        add_settings_section( 'genai_gemini_api_section', 'Google Gemini API Settings', null, 'wp-genai-tools-gemini' );
        add_settings_field( 'genai_gemini_api_key_field', 'Gemini API Key', [ $this, 'render_api_key_field' ], 'wp-genai-tools-gemini', 'genai_gemini_api_section', [ 'id' => 'genai_tools_gemini_api_key' ] );

        register_setting( 'genai_tools_openai_options', 'genai_tools_openai_api_key', 'sanitize_text_field' );
        add_settings_section( 'genai_openai_api_section', 'OpenAI API Settings', null, 'wp-genai-tools-openai' );
        add_settings_field( 'genai_openai_api_key_field', 'OpenAI API Key', [ $this, 'render_api_key_field' ], 'wp-genai-tools-openai', 'genai_openai_api_section', [ 'id' => 'genai_tools_openai_api_key' ] );
    }

    public function render_api_key_field( $args ) {
        $api_key = get_option( $args['id'] );
        echo '<input type="password" name="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $api_key ) . '" size="50">';
    }

    public function add_generation_meta_box() {
        add_meta_box( 'genai_tools_meta_box', 'GenAI Tools', [ $this, 'render_meta_box_content' ], 'post', 'side', 'high' );
    }

    public function render_meta_box_content( $post ) {
        wp_nonce_field( 'genai_generate_image_nonce', 'genai_nonce' );
        ?>
        <p><strong>Featured Image Generator</strong></p>
        <label for="genai-provider-select">Provider:</label>
        <select id="genai-provider-select" style="width: 100%;">
            <option value="gemini">Google Gemini</option>
            <option value="openai" disabled>OpenAI DALL-E (Coming Soon)</option>
        </select>
        <button type="button" class="button button-primary" id="genai-generate-button" style="margin-top:10px; width: 100%;">Generate Image</button>
        <div id="genai-status-message" style="margin-top:10px; font-weight:bold;"></div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#genai-generate-button').on('click', function() {
                var button = $(this);
                var statusDiv = $('#genai-status-message');
                button.prop('disabled', true);
                statusDiv.css('color', '#555').text('Generating, please wait...');

                $.post(ajaxurl, {
                    'action': 'genai_generate_image',
                    'post_id': <?php echo intval( $post->ID ); ?>,
                    'provider': $('#genai-provider-select').val(),
                    'nonce': $('#genai_nonce').val()
                }, function(response) {
                    if(response.success) {
                        statusDiv.css('color', 'green').text(response.data.message);
                        
                        var newAttachmentId = response.data.attachment_id;
                        if (newAttachmentId && wp.media && wp.media.featuredImage) {
                            wp.media.featuredImage.set(newAttachmentId);
                        }
                    } else {
                        statusDiv.css('color', 'red').text('Error: ' + response.data.message);
                    }
                    button.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Handle the AJAX request from the meta box button.
     */
    public function handle_ajax_image_generation() {
        if ( ! check_ajax_referer( 'genai_generate_image_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Nonce verification failed.' ] );
            return;
        }
        if ( ! isset( $_POST['post_id'] ) || ! current_user_can( 'edit_post', $_POST['post_id'] ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
            return;
        }

        $post_id = intval( $_POST['post_id'] );
        $provider = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : 'gemini';
        
        $result = false;
        if ( $provider === 'gemini' ) {
            $result = $this->generate_with_gemini( $post_id );
        }
        
        if ( is_int($result) ) {
            wp_send_json_success( [ 
                'message' => 'Featured image generated and set!',
                'attachment_id' => $result
            ] );
        } else {
            wp_send_json_error( [ 'message' => $result ] );
        }
    }

    /**
     * Provider-specific logic for Gemini.
     */
    private function generate_with_gemini( $post_id ) {
        $api_key = get_option( 'genai_tools_gemini_api_key' );
        if ( empty( $api_key ) ) return 'Gemini API key is not set.';

        $post = get_post( $post_id );
        $post_title = $post->post_title;
        $post_content = wp_strip_all_tags( $post->post_content );
        $post_tags = get_the_tags( $post_id );
        
        $keywords = [];
        if ( $post_tags ) {
            foreach ( $post_tags as $tag ) $keywords[] = $tag->name;
        }
        $keywords_string = implode( ', ', $keywords );

        $prompt = "Task: Generate a single photorealistic image. Do not return text. The image should be a high-quality featured image for a blog post, visually compelling and relevant to the content. Do not include any text, logos, or watermarks in the image.\n\nPOST TITLE: {$post_title}\n\nKEYWORDS: {$keywords_string}\n\nCONTENT SUMMARY: " . substr( $post_content, 0, 1000 ) . "...";

        $api_url = '[https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent?key=)' . $api_key;
        $request_body = [
            'contents' => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
            'generationConfig' => [ 'responseModalities' => [ 'TEXT', 'IMAGE' ] ]
        ];

        $response = wp_remote_post( $api_url, [
            'method'    => 'POST',
            'headers'   => [ 'Content-Type' => 'application/json' ],
            'body'      => json_encode( $request_body ),
            'timeout'   => 60,
        ]);

        if ( is_wp_error( $response ) ) {
            return 'WordPress HTTP Error: ' . $response->get_error_message();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            $response_body = wp_remote_retrieve_body( $response );
            $api_error_details = json_decode( $response_body, true );
            $specific_message = $api_error_details['error']['message'] ?? 'Could not parse error from API.';
            return 'Gemini API Error (Code ' . $response_code . '): ' . $specific_message;
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        $base64_image_data = null;
        if (isset($response_body['candidates'][0]['content']['parts'])) {
            foreach ($response_body['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['inlineData']['data'])) {
                    $base64_image_data = $part['inlineData']['data'];
                    break;
                }
            }
        }

        if ( ! $base64_image_data ) {
            error_log( 'GenAI Tools Plugin Error: No image data found in response. Full response: ' . print_r($response_body, true) );
            return 'API returned success, but no image data was found. The model may have returned text instead. Check server logs for the full API response.';
        }

        return $this->upload_and_set_featured_image( $base64_image_data, $post_id, $post_title );
    }

    /**
     * Shared logic to upload the image to the media library.
     */
    private function upload_and_set_featured_image( $base64_image_data, $post_id, $post_title ) {
        $image_data = base64_decode( $base64_image_data );
        $filename = sanitize_title( $post_title ) . '-' . time() . '.png';
        $upload = wp_upload_bits( $filename, null, $image_data );

        if ( ! empty( $upload['error'] ) ) {
            return 'Could not save image to media library.';
        }

        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_text_field( $post_title ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

        if ( ! is_wp_error( $attachment_id ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
            set_post_thumbnail( $post_id, $attachment_id );
            return $attachment_id;
        } else {
            return 'Could not create attachment in WordPress.';
        }
    }
}

/**
 * Self-hosted plugin updater class.
 */
if ( ! class_exists( 'WP_GenAI_Tools_Updater' ) ) {
    class WP_GenAI_Tools_Updater {
        private $file;
        private $plugin_data;
        private $update_url;
        private $slug;

        public function __construct( $file, $update_url ) {
            $this->file = $file;
            $this->update_url = $update_url;
            $this->plugin_data = get_plugin_data( $this->file );
            $this->slug = plugin_basename( $this->file );

            add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        }

        public function check_for_update( $transient ) {
            if ( empty( $transient->checked ) ) {
                return $transient;
            }

            $remote_info = $this->get_remote_info();

            if ( $remote_info && version_compare( $this->plugin_data['Version'], $remote_info->version, '<' ) ) {
                $transient->response[ $this->slug ] = (object) [
                    'slug'        => basename($this->slug, '.php'),
                    'plugin'      => $this->slug,
                    'new_version' => $remote_info->version,
                    'url'         => $remote_info->sections['description'] ?? '',
                    'package'     => $remote_info->download_url,
                ];
            }

            return $transient;
        }

        private function get_remote_info() {
            $response = wp_remote_get( $this->update_url );
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                return false;
            }
            return json_decode( wp_remote_retrieve_body( $response ) );
        }
    }
}


/**
 * Begins execution of the plugin.
 */
function run_wp_genai_tools() {
    return WP_GenAI_Tools::instance();
}
run_wp_genai_tools();
```