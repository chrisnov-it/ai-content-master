<?php
/**
 * Article Generator Feature
 *
 * Handles full article generation from a topic.
 *
 * @package AIContentMaster
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AI_Content_Master_Article_Generator
 */
class AI_Content_Master_Article_Generator {

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
        add_action('wp_ajax_ai_content_master_generate_article', array($this, 'handle_ajax_request'));
    }

    /**
     * Handle AJAX request for article generation
     */
    public function handle_ajax_request() {
        // Debug log for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master: Article generation AJAX request received');
            error_log('POST data: ' . print_r($_POST, true));
        }

        // Security check - PENTING: pastikan nonce action sama dengan yang di JavaScript
        if (!check_ajax_referer('ai_content_master_nonce', 'security', false)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master: Nonce verification failed for article generation');
            }
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-content-master')), 400);
            return;
        }

        // Capability check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-content-master')), 403);
            return;
        }

        // Get and validate topic - PENTING: gunakan wp_unslash()
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        if (empty($topic)) {
            wp_send_json_error(array('message' => __('Please provide a topic for the article.', 'ai-content-master')), 400);
            return;
        }

        // Generate the article
        $generated_article = $this->generate_article($topic);

        if (is_wp_error($generated_article)) {
            wp_send_json_error(array('message' => $generated_article->get_error_message()), 500);
        } else {
            wp_send_json_success(array('generated_article' => $generated_article));
        }
    }

    /**
     * Generate an article from a topic
     *
     * @param string $topic The topic of the article.
     * @return string|WP_Error Generated article content or error.
     */
    public function generate_article($topic) {
        // Increase script execution time for article generation
        @set_time_limit(180);

        // Prepare the prompt
        $prompt = $this->prepare_generation_prompt($topic);

        // Send to API
        return $this->api->send_prompt($prompt);
    }

	/**
	 * Prepare article generation prompt
	 *
	 * @param string $topic The article topic.
	 * @return string The generation prompt.
	 */
	private function prepare_generation_prompt( $topic ) {
		return sprintf(
			"You are an expert content strategist and professional writer specialized in 'Search Generative Experience' (SGE). Write a comprehensive, high-quality blog post about: '%s'.\n\n" .
			"GUIDELINES FOR AI/SGE OPTIMIZATION:\n" .
			"1. **Direct Answer Block**: Start with a concise 'In short' or 'Key Takeaway' section that answers the main intent of the topic in 40-60 words. This is to increase chances of being featured in AI summaries.\n" .
			"2. **EEAT & Proof**: Use phrases like 'Based on our experience,' 'Experts suggest,' or reference industry data to show authority. (Note: Use professional placeholders for specific stats if unknown).\n" .
			"3. **Structured for Skimming**: Use clear H2 and H3 headings. Include at least one informative bulleted or numbered list.\n" .
			"4. **Semantic Coverage**: Naturally include related sub-topics and answer at least two common 'People Also Ask' questions within the text.\n" .
			"5. **Format**: Provide the output in clean HTML. Start with the title in an <h1> tag.\n\n" .
			"Tone: Professional yet conversational, highly authoritative, and extremely helpful.",
			esc_html( $topic )
		);
	}
}