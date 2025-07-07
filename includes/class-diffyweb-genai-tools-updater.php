<?php
/**
 * The file that defines the self-hosted updater class
 *
 * @link       https://diffyweb.com/
 * @since      2.1.0
 *
 * @package    Diffyweb_GenAI_Tools
 * @subpackage Diffyweb_GenAI_Tools/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Self-hosted plugin updater class.
 */
class Diffyweb_GenAI_Tools_Updater {
    private $file;
    private $plugin_data;
    private $update_url;
    private $slug;

    public function __construct( $file, $update_url ) {
        $this->file = $file;
        $this->update_url = $update_url;
        $this->slug = plugin_basename( $this->file );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugins_api_filter' ], 10, 3 );
    }

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Defer getting plugin data until it's needed to prevent running too early.
        if ( ! isset( $this->plugin_data ) ) {
            $this->plugin_data = get_plugin_data( $this->file );
        }

        $remote_info = $this->get_remote_info();

        if ( $remote_info && version_compare( $this->plugin_data['Version'], $remote_info['version'], '<' ) ) {
            $transient->response[ $this->slug ] = (object) [
                'slug'        => dirname( $this->slug ),
                'plugin'      => $this->slug,
                'new_version' => $remote_info['version'],
                'url'         => $remote_info['sections']['description'] ?? '',
                'package'     => $remote_info['download_url'],
            ];
        }

        return $transient;
    }

    private function get_remote_info() {
        $response = wp_remote_get( $this->update_url );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Intercepts the plugin details screen request to inject our plugin data.
     *
     * @param false|object|array $result The result object or array. Default false.
     * @param string             $action The type of information being requested from the Plugin Installation API.
     * @param object             $args   Plugin API arguments.
     * @return false|object
     */
    public function plugins_api_filter( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || ( $args->slug !== dirname( $this->slug ) ) ) {
            return $result;
        }

        // Defer getting plugin data until it's needed to prevent running too early.
        if ( ! isset( $this->plugin_data ) ) {
            $this->plugin_data = get_plugin_data( $this->file );
        }

        $remote_info = $this->get_remote_info();
        if ( ! $remote_info ) {
            return $result;
        }

        $result = new stdClass();

        $result->name         = $remote_info['name'] ?? $this->plugin_data['Name'];
        $result->slug         = dirname( $this->slug );
        $result->version      = $remote_info['version'] ?? '';
        $result->author       = $this->plugin_data['Author'];
        $result->requires_php = $remote_info['requires_php'] ?? '';
        $result->download_link = $remote_info['download_url'] ?? '';
        $result->trunk        = $remote_info['download_url'] ?? '';
        $result->last_updated = $remote_info['last_updated'] ?? '';
        $result->sections     = (array) ( $remote_info['sections'] ?? array() );

        return $result;
    }
}