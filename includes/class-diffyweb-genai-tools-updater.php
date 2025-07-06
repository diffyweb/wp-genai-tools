<?php
/**
 * The file that defines the self-hosted updater class
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
 * Self-hosted plugin updater class.
 */
class DiffyWeb_GenAI_Tools_Updater {
    private $file;
    private $plugin_data;
    private $update_url;
    private $slug;

    public function __construct( $file, $update_url ) {
        $this->file = $file;
        $this->update_url = $update_url;
        $this->slug = plugin_basename( $this->file );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
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
                'slug'        => basename($this->slug, '.php'),
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
}