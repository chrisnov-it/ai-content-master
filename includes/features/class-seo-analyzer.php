<?php
/**
 * SEO Analyzer Feature
 *
 * Handles SEO analysis functionality.
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AI_Content_Master_SEO_Analyzer
 */
class AI_Content_Master_SEO_Analyzer {

    /**
     * API handler instance
     *
     * @var AI_Content_Master_OpenRouter_API
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = AI_Content_Master::get_instance()->get_component('api');
    }

    /**
     * Initialize the feature
     */
    public function init() {
        add_action('wp_ajax_ai_content_master_analyze_seo', array($this, 'handle_ajax_request'));
    }

    /**
     * Handle AJAX request for SEO analysis
     */
    public function handle_ajax_request() {
        // Debug log for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master: SEO analysis AJAX request received');
            error_log('POST data: ' . print_r($_POST, true));
        }

        // Security check - PENTING: pastikan nonce action sama dengan yang di JavaScript
        if (!check_ajax_referer('ai_content_master_nonce', 'security', false)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master: Nonce verification failed for SEO analysis');
            }
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-content-master')), 400);
            return;
        }

        // Capability check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-content-master')), 403);
            return;
        }

        // Get and validate post data
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid Post ID.', 'ai-content-master')), 400);
            return;
        }

        // Get post content
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'ai-content-master')), 404);
            return;
        }

        // Prepare content for analysis
        $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $title = $post->post_title;

        // Validate content
        if (empty(trim($content))) {
            wp_send_json_error(array('message' => __('Post content is empty. Please write something before analyzing.', 'ai-content-master')), 400);
            return;
        }

        // Generate SEO analysis
        $analysis_result = $this->generate_seo_analysis($title, $content);

        if (is_wp_error($analysis_result)) {
            wp_send_json_error(array('message' => $analysis_result->get_error_message()), 500);
        } else {
            wp_send_json_success(array('analysis_result' => $analysis_result));
        }
    }

    /**
     * Generate SEO analysis for given content
     *
     * @param string $title Post title.
     * @param string $content Post content.
     * @return string|WP_Error Analysis result or error.
     */
    public function generate_seo_analysis($title, $content) {
        // Increase script execution time for complex analysis
        @set_time_limit(90);

        // Prepare SEO analysis prompt
        $prompt = $this->prepare_seo_prompt($title, $content);

        // Send to API
        return $this->api->send_prompt($prompt);
    }

	/**
	 * Prepare SEO analysis prompt
	 *
	 * @param string $title Post title.
	 * @param string $content Post content.
	 * @return string SEO analysis prompt.
	 */
	private function prepare_seo_prompt( $title, $content ) {
		return sprintf(
			"You are an elite SEO Strategist specialized in 'Search Generative Experience' (SGE) and AI-driven search. Analyze the following blog post to ensure it ranks in 'AI Overviews' and meets modern quality standards. Provide output in clean HTML format using headings (<h4>), lists (<ul><li>), and bold text (<strong>).\n\n" .
			"<h4>1. AI Search & SGE Optimization</h4>" .
			"<ul><li>**Direct Answer Potential:** Identify if there is a clear, concise answer (40-60 words) that could be used as a Featured Snippet or AI response. Suggest one if missing.</li>" .
			"<li>**Semantic Connectivity:** Does the content cover related entities and sub-topics naturally? Suggest 2-3 'People Also Ask' questions to answer.</li></ul>\n\n" .
			"<h4>2. E-E-A-T Assessment</h4>" .
			"<ul><li>**Experience & Expertise:** Does the tone show first-hand experience? Suggest ways to add 'Unique Value' that an AI can't generate (e.g., personal anecdotes, specific data).</li>" .
			"<li>**Trustworthiness:** Are there citations or authoritative claims? Suggest where to add external links or data points.</li></ul>\n\n" .
			"<h4>3. Structure & Readability</h4>" .
			"<ul><li>Evaluate the H2-H4 hierarchy. Is it optimized for 'skimming'?</li><li>Suggest where to add 'Comparison Tables' or 'Summary Bullets' to increase user dwell time.</li></ul>\n\n" .
			"<h4>4. Metadata Enhancement</h4>" .
			"<ul><li>**Title Tag:** Provide a High-CTR alternative title.</li>" .
			"<li>**Meta Description:** Provide a compelling 155-character description that encourages clicks from AI summaries.</li></ul>\n\n" .
			"---\n\n" .
			"**Original Title:** %s\n\n" .
			"**Content:**\n%s",
			esc_html( $title ),
			$content
		);
	}
}