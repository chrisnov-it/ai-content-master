<?php
/**
 * Plugin Name:       AI Content Master
 * Plugin URI:        https://chrisnov.com/
 * Description:       Transform your content creation with AI-powered SEO analysis, rewriting, and optimization tools.
 * Version:           1.0.0
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
