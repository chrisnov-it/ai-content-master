<?php
/**
 * Plugin Name:       AI Content Master
 * Plugin URI:        https://chrisnov.com/
 * Description:       AI-powered content creation with SGE optimization, smart model selector, and OpenRouter integration. Free models supported.
 * Version:           1.2.0
 * Author:            Reynov Christian
 * Author URI:        https://chrisnov.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-content-master
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_CONTENT_MASTER_DIR', plugin_dir_path(__FILE__));
define('AI_CONTENT_MASTER_URL', plugin_dir_url(__FILE__));

// Load autoloader
require_once AI_CONTENT_MASTER_DIR . 'includes/class-autoloader.php';

// Initialize the autoloader
$autoloader = new AI_Content_Master_Autoloader(AI_CONTENT_MASTER_DIR);
$autoloader->register();

// Include the main plugin class
require_once AI_CONTENT_MASTER_DIR . 'includes/class-ai-content-master.php';

/**
 * Force WordPress to use cURL as HTTP transport and provide CA bundle path.
 * Needed for LocalWP on Windows where WP_Http_Curl::test() may return false
 * even though cURL is available, due to PHP-FPM not loading php.ini correctly.
 */
add_filter( 'use_curl_transport', '__return_true' );
add_filter( 'http_request_args', function( $args ) {
    // Point cURL to WordPress bundled CA bundle if curl.cainfo is not set.
    if ( empty( ini_get( 'curl.cainfo' ) ) ) {
        $ca_bundle = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
        if ( file_exists( $ca_bundle ) ) {
            $args['sslcertificates'] = $ca_bundle;
        }
    }
    return $args;
} );

// Initialize the plugin
function ai_content_master_init() {
    // Log memory usage for debugging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('AI Content Master: Initializing plugin. Memory usage: ' . memory_get_usage(true) . ' bytes');
    }

    AI_Content_Master::get_instance();

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('AI Content Master: Plugin initialized. Memory usage: ' . memory_get_usage(true) . ' bytes');
    }
}
add_action('plugins_loaded', 'ai_content_master_init');
