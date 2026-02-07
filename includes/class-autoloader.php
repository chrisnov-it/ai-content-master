<?php
/**
 * Autoloader for AI Content Master Plugin
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AI_Content_Master_Autoloader
 */
class AI_Content_Master_Autoloader {

    /**
     * Plugin directory path
     *
     * @var string
     */
    private $plugin_dir;

    /**
     * Constructor
     *
     * @param string $plugin_dir Plugin directory path.
     */
    public function __construct($plugin_dir) {
        $this->plugin_dir = rtrim($plugin_dir, '/');
    }

    /**
     * Register the autoloader
     */
    public function register() {
        spl_autoload_register(array($this, 'autoload'));
    }

    /**
     * Autoload class files
     *
     * @param string $class_name Class name to autoload.
     */
    public function autoload($class_name) {
        // Check if the class belongs to our plugin
        if (strpos($class_name, 'AI_Content_Master') !== 0) {
            return;
        }

        // Convert class name to file path
        $file_path = $this->get_file_path_from_class_name($class_name);

        // Include the file if it exists
        if ($file_path && file_exists($file_path)) {
            require_once $file_path;
        }
    }

    /**
     * Convert class name to file path
     *
     * @param string $class_name Class name.
     * @return string File path.
     */
    private function get_file_path_from_class_name($class_name) {
        // Must be one of our classes
        if (strpos($class_name, 'AI_Content_Master_') !== 0) {
            return null;
        }

        // Remove the prefix
        $relative_class = str_replace('AI_Content_Master_', '', $class_name);

        // Generate the filename, e.g., AI_Content_Master_Admin_Settings -> class-admin-settings.php
        $file_slug = strtolower(str_replace('_', '-', $relative_class));
        $file_name = 'class-' . $file_slug . '.php';

        // Determine the subdirectory based on the first part of the class name
        $class_parts = explode('_', $relative_class);
        $first_part = strtolower($class_parts[0]);

        $path = $this->plugin_dir . '/includes/';

        // Directory mapping
        $directory_map = [
            'admin'     => 'admin/',
            'seo'       => 'features/',
            'meta'      => 'features/',
            'text'      => 'features/',
            'article'   => 'features/',
            'openrouter'=> 'api/'
        ];

        if (isset($directory_map[$first_part])) {
            $path .= $directory_map[$first_part];
        }

        return $path . $file_name;
    }
}