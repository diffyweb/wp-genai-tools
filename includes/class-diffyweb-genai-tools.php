<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://diffyweb.com/
 * @since      2.1.0
 *
 * @package    DiffyWeb_GenAI_Tools
 * @subpackage DiffyWeb_GenAI_Tools/includes
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
    public $version = '2.3.0';

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
        
        // The updater class is now included from the main plugin file.
        new DiffyWeb_GenAI_Tools_Updater( DIFFYWEB_GENAI_TOOLS_FILE, $update_url );
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
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'gemini_settings'; // @phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="wrap">
            <h1>GenAI Tools Settings</h1>

            <?php
            $optimization_tool = get_option( 'diffyweb_genai_tools_optimization_tool', 'none' );
            if ( 'none' !== $optimization_tool ) {
                $status = true;
                if ( 'pngquant' === $optimization_tool ) {
                    $status = $this->check_pngquant_availability();
                } elseif ( 'imagick' === $optimization_tool ) {
                    $status = $this->check_imagick_availability();
                }

                if ( true !== $status ) {
                    ?>
                    <div class="notice notice-warning is-dismissible">
                        <p>
                            <strong><?php esc_html_e( 'Optimization Prerequisite Missing:', 'diffyweb-genai-tools' ); ?></strong>
                            <?php echo esc_html( $status ); ?>
                        </p>
                    </div>
                    <?php
                }
            }
            ?>

            <p>Configure API keys for various Generative AI services.</p>

            <h2 class="nav-tab-wrapper">
                <a href="?page=diffyweb-genai-tools&tab=gemini_settings" class="nav-tab <?php echo $active_tab == 'gemini_settings' ? 'nav-tab-active' : ''; ?>">Gemini</a>
                <a href="?page=diffyweb-genai-tools&tab=openai_settings" class="nav-tab <?php echo $active_tab == 'openai_settings' ? 'nav-tab-active' : ''; ?>">OpenAI (DALL-E)</a>
                <a href="?page=diffyweb-genai-tools&tab=optimization_settings" class="nav-tab <?php echo $active_tab == 'optimization_settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Optimization', 'diffyweb-genai-tools' ); ?></a>
            </h2>

            <form action="options.php" method="post">
                <?php
                if ( $active_tab == 'gemini_settings' ) {
                    settings_fields( 'diffyweb_genai_tools_gemini_options' );
                    do_settings_sections( 'diffyweb-genai-tools-gemini' );
                } elseif ( $active_tab == 'openai_settings' ) {
                    settings_fields( 'diffyweb_genai_tools_openai_options' );
                    do_settings_sections( 'diffyweb-genai-tools-openai' );
                } elseif ( 'optimization_settings' === $active_tab ) {
                    settings_fields( 'diffyweb_genai_tools_optimization_options' );
                    do_settings_sections( 'diffyweb-genai-tools-optimization' );
                }
                submit_button( 'Save Settings' );
                ?>
            </form>
            
            <hr>
            
            <h2><span class="dashicons dashicons-info-outline"></span> Instructions</h2>
            
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

            <?php if ( 'optimization_settings' === $active_tab ) : ?>
            <details open>
                <summary><strong>Optimization Tool Instructions</strong></summary>
                <div style="padding-left: 20px; border-left: 2px solid #ccc; margin-top: 10px; max-width: 800px;">
                    <h4>PNGQuant</h4>
                    <p>PNGQuant is a command-line utility for lossy compression of PNG images. It can significantly reduce file sizes while maintaining high visual quality.</p>
                    <p>To use this feature, <code>pngquant</code> must be installed on your server and accessible in the system's PATH. The plugin also requires the <code>exec()</code> PHP function to be enabled.</p>
                    <p><strong>Installation on Debian/Ubuntu:</strong></p>
                    <pre><code>sudo apt-get update && sudo apt-get install pngquant</code></pre>
                    <p><strong>Installation on CentOS/RHEL:</strong></p>
                    <pre><code>sudo yum install pngquant</code></pre>
                    <hr>
                    <h4>Imagick</h4>
                    <p>Imagick is a powerful PHP extension that uses the ImageMagick library to create and modify images. It can perform high-quality compression on various image formats, including PNG and JPEG.</p>
                    <p>To use this feature, the <code>imagick</code> PHP extension must be installed and enabled on your server.</p>
                    <p><strong>Installation on Debian/Ubuntu:</strong></p>
                    <pre><code>sudo apt-get update && sudo apt-get install php-imagick
sudo phpenmod imagick
sudo systemctl restart apache2 # or php-fpm</code></pre>
                    <p><strong>Installation on CentOS/RHEL:</strong></p>
                    <pre><code>sudo yum install php-imagick
sudo systemctl restart httpd # or php-fpm</code></pre>
                    <p>After installation, you can select the desired optimization feature above. The plugin will automatically attempt to use it for generated images.</p>
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
        // Gemini Settings.
        register_setting( 'diffyweb_genai_tools_gemini_options', 'diffyweb_genai_tools_gemini_api_key', 'sanitize_text_field' );
        add_settings_section( 'diffyweb_genai_gemini_api_section', 'Google Gemini API Settings', null, 'diffyweb-genai-tools-gemini' );
        add_settings_field( 'diffyweb_genai_gemini_api_key_field', 'Gemini API Key', [ $this, 'render_api_key_field' ], 'diffyweb-genai-tools-gemini', 'diffyweb_genai_gemini_api_section', [ 'id' => 'diffyweb_genai_tools_gemini_api_key' ] );

        // OpenAI Settings.
        register_setting( 'diffyweb_genai_tools_openai_options', 'diffyweb_genai_tools_openai_api_key', 'sanitize_text_field' );
        add_settings_section( 'diffyweb_genai_openai_api_section', 'OpenAI API Settings', null, 'diffyweb-genai-tools-openai' );
        add_settings_field( 'diffyweb_genai_openai_api_key_field', 'OpenAI API Key', [ $this, 'render_api_key_field' ], 'diffyweb-genai-tools-openai', 'diffyweb_genai_openai_api_section', [ 'id' => 'diffyweb_genai_tools_openai_api_key' ] );

        // Optimization Settings.
        register_setting( 'diffyweb_genai_tools_optimization_options', 'diffyweb_genai_tools_optimization_tool', 'sanitize_text_field' );
        add_settings_section( 'diffyweb_genai_optimization_section', 'Image Optimization Settings', null, 'diffyweb-genai-tools-optimization' );
        add_settings_field( 'diffyweb_genai_optimization_tool_field', 'Optimization Tool', [ $this, 'render_optimization_tool_field' ], 'diffyweb-genai-tools-optimization', 'diffyweb_genai_optimization_section' );
    }

    public function render_api_key_field( $args ) {
        $api_key = get_option( $args['id'] );
        echo '<input type="password" name="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $api_key ) . '" size="50">';
    }

    public function render_optimization_tool_field() {
        $selected_tool = get_option( 'diffyweb_genai_tools_optimization_tool', 'none' );
        $tools = [
            'none'     => [
                'label' => __( 'None', 'diffyweb-genai-tools' ),
                'check' => function() { return true; },
            ],
            'pngquant' => [
                'label' => __( 'PNGQuant (for PNG)', 'diffyweb-genai-tools' ),
                'check' => [ $this, 'check_pngquant_availability' ],
            ],
            'imagick'  => [
                'label' => __( 'Imagick (for PNG & JPEG)', 'diffyweb-genai-tools' ),
                'check' => [ $this, 'check_imagick_availability' ],
            ],
        ];

        echo '<fieldset>';
        foreach ( $tools as $value => $tool ) {
            $availability = call_user_func( $tool['check'] );
            $disabled = '';
            $description = '';
            if ( true !== $availability ) {
                $disabled = 'disabled';
                $description = '<span style="color: #d63638; margin-left: 10px;">' . esc_html( $availability ) . '</span>';
            }

            echo '<label style="display: block; margin-bottom: 10px;">';
            echo '<input type="radio" name="diffyweb_genai_tools_optimization_tool" value="' . esc_attr( $value ) . '" ' . checked( $selected_tool, $value, false ) . ' ' . $disabled . '>';
            echo ' ' . esc_html( $tool['label'] );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $description;
            echo '</label>';
        }
        echo '</fieldset>';
        ?>
        <p class="description"><?php esc_html_e( 'Select a tool to automatically optimize generated images before they are saved to the Media Library.', 'diffyweb-genai-tools' ); ?></p>
        <?php
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
                    'provider': $('#diffyweb-genai-provider-select').val(), // @phpcs:ignore
                    'diffyweb_genai_nonce': $('#diffyweb_genai_nonce').val()
                }, function(response) {
                    if(response.success) {
                        var message = response.data.message;
                        var optimization = response.data.optimization;

                        if (optimization && optimization.success && optimization.final_size < optimization.initial_size) {
                            var initialKb = (optimization.initial_size / 1024).toFixed(1);
                            var finalKb = (optimization.final_size / 1024).toFixed(1);
                            var reduction = 100 - (optimization.final_size / optimization.initial_size * 100);
                            message += '<br><small style="font-weight: normal;">Optimized: ' + initialKb + ' KB &rarr; ' + finalKb + ' KB (' + reduction.toFixed(0) + '% reduction)</small>';
                        } else if (optimization && optimization.success) {
                            message += '<br><small style="font-weight: normal;">Optimization ran, but did not reduce file size.</small>';
                        }

                        statusDiv.css('color', 'green').html(message); // Use .html() to render the <br> and <small> tags
                        
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
        
        $result = [
            'status'  => 'error',
            'message' => 'Invalid provider selected.',
        ];

        if ( $provider === 'gemini' ) {
            $result = $this->generate_with_gemini( $post_id );
        }

        if ( 'success' === ( $result['status'] ?? 'error' ) ) {
            wp_send_json_success( [
                'message'       => __( 'Featured image generated and set!', 'diffyweb-genai-tools' ),
                'attachment_id' => $result['attachment_id'],
                'optimization'  => $result['optimization'] ?? null,
            ] );
        } else {
            $error_message = $result['message'] ?? 'An unknown error occurred.';
            wp_send_json_error( [ 'message' => $error_message ] );
        }
    }

    private function generate_with_gemini( $post_id ) {
        $api_key = get_option( 'diffyweb_genai_tools_gemini_api_key' );
        if ( empty( $api_key ) ) {
            return [ 'status' => 'error', 'message' => 'Gemini API key is not set.' ];
        }

        $post = get_post( $post_id );
        $post_title = $post->post_title;
        $post_content = wp_strip_all_tags( $post->post_content );
        $post_tags = get_the_tags( $post_id );
        
        $keywords = array();
        if ( $post_tags ) {
            foreach ( $post_tags as $tag ) {
                $keywords[] = $tag->name;
            }
        }
        $keywords_string = implode( ', ', $keywords );

        $prompt = "Task: Generate a single photorealistic image. Do not return text. The image should be a high-quality featured image for a blog post, visually compelling and relevant to the content. Do not include any text, logos, or watermarks in the image.\n\nPOST TITLE: {$post_title}\n\nKEYWORDS: {$keywords_string}\n\nCONTENT SUMMARY: " . substr( $post_content, 0, 1000 ) . "...";

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent?key=' . $api_key;
        $request_body = array(
            'contents' => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
            'generationConfig' => [ 'responseModalities' => [ 'TEXT', 'IMAGE' ] ],
        );

        $response = wp_remote_post( $api_url, array(
            'method'    => 'POST',
            'headers'   => [ 'Content-Type' => 'application/json' ],
            'body'      => json_encode( $request_body ),
            'timeout'   => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            return [ 'status' => 'error', 'message' => 'WordPress HTTP Error: ' . $response->get_error_message() ];
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            $response_body = wp_remote_retrieve_body( $response );
            $api_error_details = json_decode( $response_body, true );
            $specific_message = $api_error_details['error']['message'] ?? 'Could not parse error from API.';
            return [ 'status' => 'error', 'message' => 'Gemini API Error (Code ' . $response_code . '): ' . $specific_message ];
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
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
            return [ 'status' => 'error', 'message' => 'API returned success, but no image data was found. The model may have returned text instead. Check server logs for the full API response.' ];
        }

        return $this->upload_and_set_featured_image( $base64_image_data, $post_id, $post_title );
    }

    /**
     * Checks for pngquant availability and returns status.
     *
     * @return true|string True if available, error string otherwise.
     */
    private function check_pngquant_availability() {
        // Check if exec is available on the server.
        if ( ! function_exists( 'exec' ) || false !== strpos( ini_get( 'disable_functions' ), 'exec' ) ) {
            return __( 'The `exec()` PHP function is disabled on this server. PNG optimization is not available.', 'diffyweb-genai-tools' );
        }

        // Check if pngquant is installed and in the system's PATH.
        // The @ suppresses errors if the command doesn't exist.
        // `2>&1` redirects stderr to stdout to capture potential errors.
        @exec( 'command -v pngquant 2>&1', $output, $exit_code );
        if ( $exit_code !== 0 ) {
            return __( 'The pngquant utility could not be found on your server. PNG optimization is not available.', 'diffyweb-genai-tools' );
        }

        return true;
    }

    /**
     * Checks for Imagick availability and returns status.
     *
     * @return true|string True if available, error string otherwise.
     */
    private function check_imagick_availability() {
        if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
            return __( 'The Imagick PHP extension is not installed or enabled on this server. Imagick optimization is not available.', 'diffyweb-genai-tools' );
        }
        return true;
    }

    /**
     * Optimizes a PNG image using the pngquant command-line utility.
     *
     * @param string $file_path Absolute path to the PNG file.
     * @return array An array with the optimization result.
     */
    private function optimize_image_with_pngquant( $file_path ) {
        $availability = $this->check_pngquant_availability();
        if ( true !== $availability ) {
            error_log( 'Diffyweb GenAI Tools: ' . $availability );
            return [ 'success' => false, 'message' => $availability ];
        }

        $initial_size = filesize( $file_path );
        if ( false === $initial_size ) {
            error_log( 'Diffyweb GenAI Tools: Could not get initial file size for ' . $file_path );
            return [ 'success' => false, 'message' => 'Could not read file size.' ];
        }

        $file_path_escaped = escapeshellarg( $file_path );
        $command = "pngquant --force --quality=65-80 --skip-if-larger --output {$file_path_escaped} -- {$file_path_escaped}";

        $output_lines = array();
        $exit_code = 0;
        exec( $command . ' 2>&1', $output_lines, $exit_code );

        if ( ! in_array( $exit_code, [ 0, 98, 99 ], true ) ) {
            error_log( 'Diffyweb GenAI Tools: pngquant optimization failed. Exit code: ' . $exit_code . '. Output: ' . implode( "\n", $output_lines ) );
            return [ 'success' => false, 'message' => 'pngquant execution failed.' ];
        }

        // Clear the stat cache to get the new file size.
        clearstatcache( true, $file_path );
        $final_size = filesize( $file_path );

        return [
            'success'      => true,
            'initial_size' => $initial_size,
            'final_size'   => $final_size,
        ];
    }

    /**
     * Optimizes an image using the Imagick PHP extension.
     *
     * @param string $file_path Absolute path to the image file.
     * @return array An array with the optimization result.
     */
    private function optimize_image_with_imagick( $file_path ) {
        if ( true !== $this->check_imagick_availability() ) {
            $message = __( 'Imagick extension not available for optimization.', 'diffyweb-genai-tools' );
            error_log( 'Diffyweb GenAI Tools: ' . $message );
            return [ 'success' => false, 'message' => $message ];
        }

        $initial_size = filesize( $file_path );
        if ( false === $initial_size ) {
            error_log( 'Diffyweb GenAI Tools: Could not get initial file size for ' . $file_path );
            return [ 'success' => false, 'message' => 'Could not read file size.' ];
        }

        try {
            $imagick = new \Imagick( realpath( $file_path ) );
            $mime_type = $imagick->getImageMimeType();

            // Set compression quality.
            if ( 'image/jpeg' === $mime_type ) {
                $imagick->setImageCompressionQuality( 82 );
            } elseif ( 'image/png' === $mime_type ) {
                $imagick->setImageCompressionQuality( 90 );
                $imagick->setOption( 'png:compression-filter', '5' );
                $imagick->setOption( 'png:compression-level', '9' );
                $imagick->setOption( 'png:compression-strategy', '1' );
                $imagick->quantizeImage( 256, \Imagick::COLORSPACE_RGB, 0, false, false );
            }

            // Strip unnecessary metadata.
            $imagick->stripImage();

            // Write the optimized image back to the original file.
            if ( ! $imagick->writeImage( $file_path ) ) {
                error_log( 'Diffyweb GenAI Tools: Imagick failed to write optimized image to ' . $file_path );
                $imagick->destroy();
                return [ 'success' => false, 'message' => 'Imagick failed to write image.' ];
            }

            $imagick->destroy();

            // Clear the stat cache to get the new file size.
            clearstatcache( true, $file_path );
            $final_size = filesize( $file_path );

            return [
                'success'      => true,
                'initial_size' => $initial_size,
                'final_size'   => $final_size,
            ];

        } catch ( \Exception $e ) {
            error_log( 'Diffyweb GenAI Tools: Imagick optimization failed with exception: ' . $e->getMessage() );
            return [ 'success' => false, 'message' => 'Imagick exception: ' . $e->getMessage() ];
        }
    }

    private function upload_and_set_featured_image( $base64_image_data, $post_id, $post_title ) {
        $image_data = base64_decode( $base64_image_data );
        $filename = sanitize_title( $post_title ) . '-' . time() . '.png';
        $upload = wp_upload_bits( $filename, null, $image_data );

        if ( ! empty( $upload['error'] ) ) {
            return [ 'status' => 'error', 'message' => 'Could not save image to media library.' ];
        }

        $optimization_result = null;
        $optimization_tool = get_option( 'diffyweb_genai_tools_optimization_tool', 'none' );
        switch ( $optimization_tool ) {
            case 'pngquant':
                $optimization_result = $this->optimize_image_with_pngquant( $upload['file'] );
                break;
            case 'imagick':
                $optimization_result = $this->optimize_image_with_imagick( $upload['file'] );
                break;
        }

        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_text_field( $post_title ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        $attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

        if ( ! is_wp_error( $attachment_id ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
            set_post_thumbnail( $post_id, $attachment_id );
            return [
                'status'        => 'success',
                'attachment_id' => $attachment_id,
                'optimization'  => $optimization_result,
            ];
        } else {
            return [ 'status' => 'error', 'message' => 'Could not create attachment in WordPress.' ];
        }
    }
}