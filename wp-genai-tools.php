<?php
/**
 * Plugin Name:       GenAI Tools
 * Description:       A toolkit for integrating various Generative AI services (like Gemini, DALL-E, etc.) into the WordPress content workflow.
 * Version:           2.0.5
 * Author:            Diffyweb
 * Author URI:        https://diffyweb.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       diffyweb-genai-tools
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Main plugin class, now structured as a toolkit.
 */
final class DiffyWeb_GenAI_Tools {

    private static $_instance = null;
    public $version = '2.0.5';

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
        add_action( 'wp_ajax_diffyweb_genai_generate_image', [ $this, 'handle_ajax_image_generation' ] );
        
        $this->initialize_updater();
    }

    /**
     * Initialize the self-hosted updater.
     */
    private function initialize_updater() {
        $update_url = 'https://raw.githubusercontent.com/diffyweb/wp-genai-tools/main/info.json';
        
        if ( ! class_exists( 'DiffyWeb_GenAI_Tools_Updater' ) ) {
            // The updater class is included at the bottom of this file.
        }
        new DiffyWeb_GenAI_Tools_Updater( __FILE__, $update_url );
    }

    /**
     * Add the main settings page.
     */
    public function add_admin_menu() {
        add_options_page( 'GenAI Tools Settings', 'GenAI Tools', 'manage_options', 'diffyweb-genai-tools', [ $this, 'render_settings_page' ] );
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
                <a href="?page=diffyweb-genai-tools&tab=gemini_settings" class="nav-tab <?php echo $active_tab == 'gemini_settings' ? 'nav-tab-active' : ''; ?>">Gemini</a>
                <a href="?page=diffyweb-genai-tools&tab=openai_settings" class="nav-tab <?php echo $active_tab == 'openai_settings' ? 'nav-tab-active' : ''; ?>">OpenAI (DALL-E)</a>
            </h2>

            <form action="options.php" method="post">
                <?php
                if ( $active_tab == 'gemini_settings' ) {
                    settings_fields( 'diffyweb_genai_tools_gemini_options' );
                    do_settings_sections( 'diffyweb-genai-tools-gemini' );
                } elseif ( $active_tab == 'openai_settings' ) {
                    settings_fields( 'diffyweb_genai_tools_openai_options' );
                    do_settings_sections( 'diffyweb-genai-tools-openai' );
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
                        <li><strong>Create or Select a Project:</strong> Go to the <a href="https://console.cloud.google.com/projectselector2/home/dashboard" target="_blank">Google Cloud Console</a> and create a new project or select an existing one.</li>
                        <li><strong>Enable Billing:</strong> You must <a href="https://console.cloud.google.com/billing" target="_blank">enable billing</a> for your project. This is required even to use the free tier.</li>
                        <li><strong>Enable the API:</strong> Go to the API library and enable the <a href="https://console.cloud.google.com/apis/library/generativelanguage.googleapis.com" target="_blank">Generative Language API</a> for your project.</li>
                        <li><strong>Create an API Key:</strong> Go to the <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Credentials page</a> and click "Create Credentials" &rarr; "API key".</li>
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
                     <p><em>Alternatively, the simplest way to get a key for personal use is from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>, which handles the project creation and API enablement for you.</em></p>
                </div>
            </details>
            <?php endif; ?>

            <?php if ( $active_tab == 'openai_settings' ) : ?>
            <details open>
                <summary><strong>OpenAI (DALL-E) API Instructions</strong></summary>
                <div style="padding-left: 20px; border-left: 2px solid #ccc; margin-top: 10px; max-width: 800px;">
                    <p>To use DALL-E for image generation, you need an API key from an OpenAI account with a payment method on file.</p>
                    <ol>
                        <li><strong>Create or Log In to an Account:</strong> Go to the <a href="https://platform.openai.com/" target="_blank">OpenAI Platform</a> and sign up or log in.</li>
                        <li><strong>Set Up Billing:</strong> You must add a payment method to your account to use the API. Go to the <a href="https://platform.openai.com/account/billing/overview" target="_blank">Billing page</a> and set up payment details.</li>
                        <li><strong>Create an API Key:</strong> Go to the <a href="https://platform.openai.com/api-keys" target="_blank">API Keys page</a>.</li>
                        <li>Click "Create new secret key".</li>
                        <li>Give the key a name (e.g., "WordPress GenAI Tools") and click "Create secret key".</li>
                        <li><strong>Copy and Paste:</strong> Copy your new key immediately (you will not be able to see it again) and paste it into the field above.</li>
                    </ol>
                     <p><em>You can view API usage and pricing information on the <a href="https://platform.openai.com/usage" target="_blank">Usage</a> and <a href="https://openai.com/pricing" target="_blank">Pricing</a> pages.</em></p>
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
        register_setting( 'diffyweb_genai_tools_gemini_options', 'diffyweb_genai_tools_gemini_api_key', 'sanitize_text_field' );
        add_settings_section( 'diffyweb_genai_gemini_api_section', 'Google Gemini API Settings', null, 'diffyweb-genai-tools-gemini' );
        add_settings_field( 'diffyweb_genai_gemini_api_key_field', 'Gemini API Key', [ $this, 'render_api_key_field' ], 'diffyweb-genai-tools-gemini', 'diffyweb_genai_gemini_api_section', [ 'id' => 'diffyweb_genai_tools_gemini_api_key' ] );

        register_setting( 'diffyweb_genai_tools_openai_options', 'diffyweb_genai_tools_openai_api_key', 'sanitize_text_field' );
        add_settings_section( 'diffyweb_genai_openai_api_section', 'OpenAI API Settings', null, 'diffyweb-genai-tools-openai' );
        add_settings_field( 'diffyweb_genai_openai_api_key_field', 'OpenAI API Key', [ $this, 'render_api_key_field' ], 'diffyweb-genai-tools-openai', 'diffyweb_genai_openai_api_section', [ 'id' => 'diffyweb_genai_tools_openai_api_key' ] );
    }

    public function render_api_key_field( $args ) {
        $api_key = get_option( $args['id'] );
        echo '<input type="password" name="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $api_key ) . '" size="50">';
    }

    public function add_generation_meta_box() {
        add_meta_box( 'diffyweb_genai_tools_meta_box', 'GenAI Tools', [ $this, 'render_meta_box_content' ], 'post', 'side', 'high' );
    }

    public function render_meta_box_content( $post ) {
        wp_nonce_field( 'diffyweb_genai_generate_image_nonce', 'diffyweb_genai_nonce' );
        ?>
        <p><strong>Featured Image Generator</strong></p>
        <label for="diffyweb-genai-provider-select">Provider:</label>
        <select id="diffyweb-genai-provider-select" style="width: 100%;">
            <option value="gemini">Google Gemini</option>
            <option value="openai" disabled>OpenAI DALL-E (Coming Soon)</option>
        </select>
        <button type="button" class="button button-primary" id="diffyweb-genai-generate-button" style="margin-top:10px; width: 100%;">Generate Image</button>
        <div id="diffyweb-genai-status-message" style="margin-top:10px; font-weight:bold;"></div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#diffyweb-genai-generate-button').on('click', function() {
                var button = $(this);
                var statusDiv = $('#diffyweb-genai-status-message');
                button.prop('disabled', true);
                statusDiv.css('color', '#555').text('Generating, please wait...');

                $.post(ajaxurl, {
                    'action': 'diffyweb_genai_generate_image',
                    'post_id': <?php echo intval( $post->ID ); ?>,
                    'provider': $('#diffyweb-genai-provider-select').val(),
                    'diffyweb_genai_nonce': $('#diffyweb_genai_nonce').val()
                }, function(response) {
                    if(response.success) {
                        statusDiv.css('color', 'green').text(response.data.message);
                        
                        // --- NEW: Robust featured image refresh ---
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
        if ( ! check_ajax_referer( 'diffyweb_genai_generate_image_nonce', 'diffyweb_genai_nonce', false ) ) {
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

    private function generate_with_gemini( $post_id ) {
        $api_key = get_option( 'diffyweb_genai_tools_gemini_api_key' );
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

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent?key=' . $api_key;
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
            error_log( 'Diffyweb GenAI Tools Plugin Error: No image data found in response. Full response: ' . print_r($response_body, true) );
            return 'API returned success, but no image data was found. The model may have returned text instead. Check server logs for the full API response.';
        }

        return $this->upload_and_set_featured_image( $base64_image_data, $post_id, $post_title );
    }

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
if ( ! class_exists( 'DiffyWeb_GenAI_Tools_Updater' ) ) {
    class DiffyWeb_GenAI_Tools_Updater {
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
function diffyweb_run_wp_genai_tools() {
    return DiffyWeb_GenAI_Tools::instance();
}
diffyweb_run_wp_genai_tools();
