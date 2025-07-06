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
    public $version = '2.7.1';

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        if ( is_multisite() ) {
            add_action( 'network_admin_menu', [ $this, 'add_admin_menu' ] );
        } else {
            add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        }

        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_generation_meta_box' ] );
        add_action( 'wp_ajax_diffyweb_genai_generate_image', [ $this, 'handle_ajax_image_generation' ] );

        // The updater should only run when the plugin is active in the appropriate context.
        // For multisite, this means it must be network-activated.
        if ( ! is_multisite() || $this->is_network_activated() ) {
            $this->initialize_updater();
        } else {
            add_action( 'admin_notices', [ $this, 'render_network_activation_notice' ] );
        }
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
        if ( is_multisite() ) {
            add_submenu_page(
                'settings.php', // Parent slug for network settings.
                'GenAI Tools Settings',
                'GenAI Tools',
                'manage_network_options',
                'diffyweb-genai-tools',
                [ $this, 'render_settings_page' ]
            );
        } else {
            add_options_page( 'GenAI Tools Settings', 'GenAI Tools', 'manage_options', 'diffyweb-genai-tools', [ $this, 'render_settings_page' ] );
        }
    }

    /**
     * Render the settings page with a tabbed interface and instructions.
     */
    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'gemini_settings'; // @phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
    }

    public function render_api_key_field( $args ) {
        $api_key = get_option( $args['id'] );
        echo '<input type="password" name="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $api_key ) . '" size="50">';
    }

    public function render_network_activation_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php esc_html_e( 'GenAI Tools is installed on a multisite network but is not network-activated. The updater and other features may not work correctly. Please network-activate the plugin.', 'diffyweb-genai-tools' ); ?>
            </p>
        </div>
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
            <option value="openai">OpenAI DALL-E 3</option>
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

        $post_id       = intval( $_POST['post_id'] );
        $provider_slug = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : 'gemini';
        
        $provider = null;
        if ( 'gemini' === $provider_slug ) {
            $api_key  = is_multisite() ? get_site_option( 'diffyweb_genai_tools_gemini_api_key' ) : get_option( 'diffyweb_genai_tools_gemini_api_key' );
            $provider = new DiffyWeb_GenAI_Gemini_Provider( $api_key );
        } elseif ( 'openai' === $provider_slug ) {
            $api_key  = is_multisite() ? get_site_option( 'diffyweb_genai_tools_openai_api_key' ) : get_option( 'diffyweb_genai_tools_openai_api_key' );
            $provider = new DiffyWeb_GenAI_OpenAI_Provider( $api_key );
        }

        if ( ! $provider instanceof DiffyWeb_GenAI_Provider_Interface ) {
            wp_send_json_error( array( 'message' => 'Invalid provider selected.' ) );
            return;
        }

        $result = $provider->generate( $post_id );

        if ( 'success' === ( $result['status'] ?? 'error' ) ) {
            wp_send_json_success( array(
                'message'       => 'Featured image generated and set!',
                'attachment_id' => $result['attachment_id'],
            ) );
        } else {
            $error_message = $result['message'] ?? 'An unknown error occurred.';
            wp_send_json_error( array( 'message' => $error_message ) );
        }
    }

    /**
     * Check if the plugin is network-activated on a multisite install.
     *
     * @return bool
     */
    private function is_network_activated() {
        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
        }
        return is_plugin_active_for_network( plugin_basename( DIFFYWEB_GENAI_TOOLS_FILE ) );
    }
}