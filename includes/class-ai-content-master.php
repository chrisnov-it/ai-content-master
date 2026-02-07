<?php
/**
 * Main AI Content Master Plugin Class
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Content_Master
 */
class AI_Content_Master {

    /**
     * Plugin version
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Plugin instance
     *
     * @var AI_Content_Master
     */
    private static $instance = null;

    /**
     * Plugin components
     *
     * @var array
     */
    private $components = array();

    /**
     * Get plugin instance
     *
     * @return AI_Content_Master
     */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init() {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Include required class files
        require_once AI_CONTENT_MASTER_DIR . 'includes/api/class-openrouter-api.php';
        require_once AI_CONTENT_MASTER_DIR . 'includes/admin/class-admin-settings.php';
        require_once AI_CONTENT_MASTER_DIR . 'includes/admin/class-admin-meta-box.php';
        require_once AI_CONTENT_MASTER_DIR . 'includes/admin/class-admin-scripts.php';
        require_once AI_CONTENT_MASTER_DIR . 'includes/features/class-article-generator.php';
        require_once AI_CONTENT_MASTER_DIR . 'includes/features/class-seo-analyzer.php';
        require_once AI_CONTENT_MASTER_DIR . 'includes/features/class-meta-generator.php';
        require_once AI_CONTENT_MASTER_DIR . 'includes/features/class-text-rephraser.php';
        require_once AI_CONTENT_MASTER_DIR . 'includes/features/class-article-rewriter.php';
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize API handler (always needed)
        $this->components['api'] = new AI_Content_Master_OpenRouter_API();

		// Initialize admin components only if in admin area.
		if ( is_admin() ) {
			$this->components['admin_settings'] = new AI_Content_Master_Admin_Settings();
			$this->components['admin_meta_box'] = new AI_Content_Master_Admin_Meta_Box();
			$this->components['admin_scripts']  = new AI_Content_Master_Admin_Scripts();
		}

        // Initialize features
        $this->components['article_generator'] = new AI_Content_Master_Article_Generator();
        $this->components['seo_analyzer'] = new AI_Content_Master_SEO_Analyzer();
        $this->components['meta_generator'] = new AI_Content_Master_Meta_Generator();
        $this->components['text_rephraser'] = new AI_Content_Master_Text_Rephraser();
        $this->components['article_rewriter'] = new AI_Content_Master_Article_Rewriter();
        
        // Initialize all components immediately
        foreach ($this->components as $component) {
            if (method_exists($component, 'init')) {
                $component->init();
            }
        }
    }

    /**
     * Initialize plugin hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init_plugin'));
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ai-content-master',
            false,
            dirname(plugin_basename(AI_CONTENT_MASTER_DIR . 'ai-content-master.php')) . '/languages/'
        );
    }

    /**
     * Initialize plugin functionality
     */
    public function init_plugin() {
        // Components are already initialized in init_components
    }

    /**
     * Initialize a specific component if it has an init method
     *
     * @param mixed $component Component instance.
     */
	private function init_component( $component ) {
		if ( method_exists( $component, 'init' ) ) {
			$component->init();
		}
	}

    /**
     * Get component instance with lazy loading
     *
     * @param string $component_name Component name.
     * @return mixed Component instance or null.
     */
    public function get_component($component_name) {
        // Return existing component if already loaded
        if (isset($this->components[$component_name])) {
            return $this->components[$component_name];
        }

        // Lazy load components as needed
        switch ($component_name) {
            case 'admin_settings':
                if (is_admin()) {
                    $this->components['admin_settings'] = new AI_Content_Master_Admin_Settings();
                    $this->init_component($this->components['admin_settings']);
                }
                break;
            case 'admin_meta_box':
                if (is_admin()) {
                    $this->components['admin_meta_box'] = new AI_Content_Master_Admin_Meta_Box();
                    $this->init_component($this->components['admin_meta_box']);
                }
                break;
            case 'admin_scripts':
                if (is_admin()) {
                    $this->components['admin_scripts'] = new AI_Content_Master_Admin_Scripts();
                    $this->init_component($this->components['admin_scripts']);
                }
                break;
            case 'article_generator':
                $this->components['article_generator'] = new AI_Content_Master_Article_Generator();
                $this->init_component($this->components['article_generator']);
                break;
            case 'seo_analyzer':
                $this->components['seo_analyzer'] = new AI_Content_Master_SEO_Analyzer();
                $this->init_component($this->components['seo_analyzer']);
                break;
            case 'meta_generator':
                $this->components['meta_generator'] = new AI_Content_Master_Meta_Generator();
                $this->init_component($this->components['meta_generator']);
                break;
            case 'text_rephraser':
                $this->components['text_rephraser'] = new AI_Content_Master_Text_Rephraser();
                $this->init_component($this->components['text_rephraser']);
                break;
            case 'article_rewriter':
                $this->components['article_rewriter'] = new AI_Content_Master_Article_Rewriter();
                $this->init_component($this->components['article_rewriter']);
                break;
        }

        return isset($this->components[$component_name]) ? $this->components[$component_name] : null;
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return self::VERSION;
    }

    /**
     * Get plugin directory
     *
     * @return string
     */
    public function get_plugin_dir() {
        return AI_CONTENT_MASTER_DIR;
    }

    /**
     * Get plugin URL
     *
     * @return string
     */
    public function get_plugin_url() {
        return AI_CONTENT_MASTER_URL;
    }
}