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
        @set_time_limit(180);

        $prompt = $this->prepare_generation_prompt($topic);
        $result = $this->get_api()->send_prompt( $prompt );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->sanitize_ai_output( $result );
    }

    /**
     * Strip Markdown artefacts from AI output and ensure clean HTML.
     *
     * Some free models wrap their response in ```html ... ``` fences
     * or add stray backticks/asterisks even when instructed not to.
     * This runs entirely in PHP — zero extra API calls, zero token cost.
     *
     * @param string $raw Raw text returned by the AI model.
     * @return string Clean HTML string.
     */
    public function sanitize_ai_output( $raw ) {
        $output = trim( $raw );

        // 1. Strip ```html ... ``` or ``` ... ``` fences (most common artefact).
        //    Handles both ```html\n...\n``` and ```\n...\n``` variants.
        $output = preg_replace( '/^```(?:html)?\s*/i', '', $output );
        $output = preg_replace( '/\s*```\s*$/', '', $output );

        // 2. Remove any remaining isolated triple-backtick lines.
        $output = preg_replace( '/^```.*$/m', '', $output );

        // 3. Strip bold/italic Markdown outside of HTML tags
        //    e.g. **text** -> text, *text* -> text, __text__ -> text
        //    Use a negative lookahead to avoid touching HTML attributes.
        $output = preg_replace( '/(?<![\w\/"\'=])\*\*([^*]+)\*\*(?![\w])/', '$1', $output );
        $output = preg_replace( '/(?<![\w\/"\'=])__([^_]+)__(?![\w])/',     '$1', $output );
        $output = preg_replace( '/(?<![\w\/"\'=])\*([^*\n]+)\*(?![\w])/',   '$1', $output );
        $output = preg_replace( '/(?<![\w\/"\'=])_([^_\n]+)_(?![\w])/',     '$1', $output );

        // 4. Convert Markdown-style inline code `code` that appears
        //    outside an existing <code> tag to <code>code</code>.
        $output = preg_replace( '/(?<!`)(`[^`\n]+`)(?!`)/', '<code>$1</code>', $output );
        $output = str_replace( array( '<code>`', '`</code>' ), array( '<code>', '</code>' ), $output );

        // 5. Remove any <!DOCTYPE>, <html>, <head>, <body> wrapper tags
        //    that some models add despite instructions.
        $output = preg_replace( '/<!DOCTYPE[^>]*>/i',  '', $output );
        $output = preg_replace( '/<\/?html[^>]*>/i',   '', $output );
        $output = preg_replace( '/<head[^>]*>.*?<\/head>/is', '', $output );
        $output = preg_replace( '/<\/?body[^>]*>/i',   '', $output );

        // 6. Collapse excess blank lines left behind by removals.
        $output = preg_replace( '/\n{3,}/', "\n\n", $output );

        return trim( $output );
    }

	/**
	 * Prepare article generation prompt — optimized for international tech blog,
	 * SGE/AI Overview targeting, FAQ schema, and code snippet support.
	 *
	 * @param string $topic The article topic.
	 * @return string The generation prompt.
	 */
	private function prepare_generation_prompt( $topic ) {
		// PENTING: Jangan gunakan heredoc dengan indentasi — whitespace ikut dikirim ke API
		// dan memboroskan ribuan tokens. Gunakan string biasa yang dimulai dari kolom 0.
		$topic = esc_html( $topic );

		return "You are a senior technical writer and SEO strategist for an international English-language tech blog. Write an article that ranks in Google AI Overviews, wins Featured Snippets, and is useful to a global developer/tech audience.\n"
			. "\nTOPIC: \"{$topic}\"\n"
			. "\nOUTPUT: Clean semantic HTML only. No markdown, no CSS/JS tags, no preamble or commentary outside the article HTML.\n"
			. "\nSTRUCTURE:\n"
			. "1. <h1> title (under 65 chars, keyword-rich)\n"
			. "2. <div class=\"quick-answer\"><strong>TL;DR:</strong> [40-60 word direct answer]</div>\n"
			. "3. Introduction paragraph (~80 words): hook, problem, target audience\n"
			. "4. Min 5 H2 sections with H3 sub-points. Include where relevant:\n"
			. "   - <ul>/<ol> for steps or comparisons\n"
			. "   - <pre><code class=\"language-bash\"> or language-python for code examples\n"
			. "   - <table> for feature comparisons or benchmarks\n"
			. "   - Authority phrases: 'According to [Source]', 'In our testing', 'Industry benchmarks show'\n"
			. "5. FAQ: <h2>Frequently Asked Questions</h2> with 3-4 items formatted as:\n"
			. "   <div class=\"faq-item\"><h3>Question?</h3><p>Answer (2-4 sentences).</p></div>\n"
			. "6. <h2>Conclusion</h2>: 2-3 sentence summary + call-to-action\n"
			. "\nCONTENT RULES:\n"
			. "- English only, Flesch-Kincaid Grade 8-10\n"
			. "- Target 1,200-1,800 words\n"
			. "- No filler phrases, be direct and authoritative";
	}
}