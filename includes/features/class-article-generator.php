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
     * Lazy API getter — resolve hanya saat pertama kali dibutuhkan,
     * bukan di constructor (mencegah circular dependency & memory spike saat activation).
     *
     * @return AI_Content_Master_OpenRouter_API
     */
    /**
     * Lazy getter — return Provider_Manager yang sudah resolved ke provider aktif.
     *
     * @return AI_Content_Master_Provider_Manager
     */
    private function get_api() {
        return AI_Content_Master::get_instance()->get_component( 'api' );
    }

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
        if (!check_ajax_referer('ai_content_master_ajax_nonce', 'security', false)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master: Nonce verification failed for article generation');
            }
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-content-master')), 403);
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
        return $this->get_api()->send_prompt( $prompt );
    }

	/**
	 * Prepare article generation prompt — optimized for international tech blog,
	 * SGE/AI Overview targeting, FAQ schema, and code snippet support.
	 *
	 * @param string $topic The article topic.
	 * @return string The generation prompt.
	 */
	private function prepare_generation_prompt( $topic ) {
		return sprintf(
			<<<'PROMPT'
			You are a senior technical writer and SEO strategist for an international English-language tech blog. Your goal is to write an article that ranks in Google AI Overviews, wins Featured Snippets, and is genuinely useful to a global developer/tech audience.

			TOPIC: "%s"

			--- STRUCTURE REQUIREMENTS ---

			1. TITLE (H1)
			   - Write one strong, click-worthy H1 title.
			   - Format: <h1>Your Title Here</h1>
			   - Include the main keyword naturally. Keep it under 65 characters.

			2. QUICK ANSWER BLOCK (for AI Overview / Featured Snippet)
			   - Immediately after the H1, write a <div class="quick-answer"> block.
			   - Contains a <strong>TL;DR:</strong> or <strong>Quick Answer:</strong> followed by a concise 40-60 word direct answer to the topic's main intent.
			   - This is the most important section — write it as if Google will quote it directly.

			3. INTRODUCTION (1 paragraph, ~80 words)
			   - Hook the reader. State the problem this article solves.
			   - Mention who this article is for (developers, sysadmins, tech enthusiasts, etc.).

			4. MAIN BODY (H2 and H3 sections)
			   - Minimum 5 H2 sections. Use H3 for sub-points.
			   - Each H2 should address a distinct sub-topic or question a reader would search for.
			   - Where relevant, include:
			     * A <ul> or <ol> list for steps, comparisons, or key points.
			     * A <pre><code class="language-bash"> or <pre><code class="language-python"> block for any commands or code examples (use realistic, useful examples).
			     * A <table> for any feature comparisons, pros/cons, or benchmarks.
			   - Back claims with authority: use phrases like "According to [Source/Study]", "In our testing", "Industry benchmarks show". Use realistic placeholders where exact data is unknown.

			5. FAQ SECTION (Schema-ready)
			   - Add an H2: <h2>Frequently Asked Questions</h2>
			   - Include 3-4 Q&A pairs. Format each as:
			     <div class="faq-item">
			       <h3>Question text here?</h3>
			       <p>Answer text here (2-4 sentences).</p>
			     </div>
			   - Questions must reflect real "People Also Ask" queries for the topic.

			6. CONCLUSION
			   - H2: <h2>Conclusion</h2>
			   - Summarize key takeaways in 2-3 sentences.
			   - End with a clear call-to-action (e.g., "Try it yourself", "Share your experience in the comments", "Explore the official docs").

			--- CONTENT RULES ---

			- Language: English only. Aim for US/global readability (Flesch-Kincaid Grade 8-10).
			- Target word count: 1,200 - 1,800 words.
			- No filler phrases like "In conclusion, it is important to note that..." — be direct.
			- Do NOT include any CSS, <style>, <script>, or <!DOCTYPE> tags.
			- Output clean, semantic HTML only. No markdown.
			- Do NOT add any commentary, preamble, or explanation outside the article HTML.
			PROMPT,
			esc_html( $topic )
		);
	}
}