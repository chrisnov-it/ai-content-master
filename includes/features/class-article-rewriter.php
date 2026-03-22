<?php
/**
 * Article Rewriter Feature
 *
 * Handles full article rewriting functionality.
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AI_Content_Master_Article_Rewriter
 */
class AI_Content_Master_Article_Rewriter {

    /**
     * Lazy API getter — resolve hanya saat pertama kali dibutuhkan.
     *
     * @return AI_Content_Master_OpenRouter_API
     */
    private function get_api() {
        return AI_Content_Master::get_instance()->get_component( 'api' );
    }

    /**
     * Initialize the feature
     */
    public function init() {
        add_action('wp_ajax_ai_content_master_rewrite_article', array($this, 'handle_ajax_request'));
    }

    /**
     * Handle AJAX request for article rewriting
     */
    public function handle_ajax_request() {
        // Debug log for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master: Article rewriting AJAX request received');
            error_log('POST data: ' . print_r($_POST, true));
        }

        // Security check - PENTING: pastikan nonce action sama dengan yang di JavaScript
        if (!check_ajax_referer('ai_content_master_ajax_nonce', 'security', false)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master: Nonce verification failed for article rewriting');
            }
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-content-master')), 403);
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

        // Prepare content for rewriting.
        // Truncate ke 4000 karakter untuk batasi token input.
        $content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
        $content = mb_substr( $content, 0, 4000 );
        $title   = $post->post_title;

        // Validate content
        if (empty(trim($content))) {
            wp_send_json_error(array('message' => __('Post content is empty. Please write something before rewriting.', 'ai-content-master')), 400);
            return;
        }

        // Generate rewritten content
        $rewritten_content = $this->rewrite_article($title, $content);

        if (is_wp_error($rewritten_content)) {
            wp_send_json_error(array('message' => $rewritten_content->get_error_message()), 500);
        } else {
            wp_send_json_success(array('rewritten_content' => $rewritten_content));
        }
    }

    /**
     * Rewrite entire article
     *
     * @param string $title Article title.
     * @param string $content Article content.
     * @return string|WP_Error Rewritten content or error.
     */
    public function rewrite_article($title, $content) {
        // Increase script execution time for complex rewriting
        @set_time_limit(120);

        // Prepare rewriting prompt
        $prompt = $this->prepare_rewrite_prompt($title, $content);

        // Send to API
        return $this->get_api()->send_prompt( $prompt );
    }

    /**
     * Prepare article rewriting prompt
     *
     * @param string $title Article title.
     * @param string $content Article content.
     * @return string Rewriting prompt.
     */
    private function prepare_rewrite_prompt($title, $content) {
        return sprintf(
            "You are a professional content writer and editor. Rewrite the following blog post completely while maintaining the core message, facts, and key points. Improve the clarity, flow, readability, and engagement. Make it more compelling and professional.\n\n" .
            "Guidelines for rewriting:\n" .
            "- Keep the same main topic and key information\n" .
            "- Improve sentence structure and flow\n" .
            "- Use more engaging and natural language\n" .
            "- Break up long paragraphs into shorter ones\n" .
            "- Add smooth transitions between ideas\n" .
            "- Maintain a professional yet accessible tone\n" .
            "- Ensure the rewritten content is original and not just rephrased word-for-word\n" .
            "- Keep the length approximately the same as the original\n\n" .
            "Original Title: %s\n\n" .
            "Original Content:\n%s\n\n" .
            "Please provide only the rewritten article content without any additional commentary or explanations.",
            esc_html($title),
            $content
        );
    }
}