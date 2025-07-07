<?php
/**
 * Plugin Name:       GenAI Tools
 * Description:       A toolkit for integrating various Generative AI services (like Gemini, DALL-E, etc.) into the WordPress content workflow.
 * Version:           2.7.2
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
 * The current version of the plugin.
 *
 * @since 2.1.0
 */
define( 'DIFFYWEB_GENAI_TOOLS_VERSION', '2.7.2' );

/**
 * The full path to the main plugin file, used by the updater.
 *
 * @since 2.1.0
 */
define( 'DIFFYWEB_GENAI_TOOLS_FILE', __FILE__ );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-diffyweb-genai-tools.php';

/**
 * The class responsible for handling self-hosted updates.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-diffyweb-genai-tools-updater.php';

/**
 * The image helper class.
 * @since 2.7.0
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-diffyweb-genai-image-helper.php';

/**
 * The provider interface and classes.
 * @since 2.7.0
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/providers/interface-diffyweb-genai-provider.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/providers/class-diffyweb-genai-gemini-provider.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/providers/class-diffyweb-genai-openai-provider.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 2.1.0
 */
function diffyweb_run_wp_genai_tools() {
    return Diffyweb_GenAI_Tools::instance();
}
add_action( 'init', 'diffyweb_run_wp_genai_tools' );
