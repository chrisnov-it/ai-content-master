<?php
/**
 * Text Rephraser Feature
 *
 * Handles text rephrasing functionality.
 *
 * @package AIContentMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AI_Content_Master_Text_Rephraser
 */
class AI_Content_Master_Text_Rephraser {

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
        add_action('wp_ajax_ai_content_master_rephrase_text', array($this, 'handle_ajax_request'));
    }

    /**
     * Handle AJAX request for text rephrasing
     */
    public function handle_ajax_request() {
        // Debug log for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master: Text rephrasing AJAX request received');
            error_log('POST data: ' . print_r($_POST, true));
        }

        // Security check - PENTING: pastikan nonce action sama dengan yang di JavaScript
        if (!check_ajax_referer('ai_content_master_ajax_nonce', 'security', false)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master: Nonce verification failed for text rephrasing');
            }
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-content-master')), 403);
            return;
        }

        // Capability check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-content-master')), 403);
            return;
        }

        // Get selected text
        $selected_text = isset($_POST['selected_text']) ? wp_kses_post(wp_unslash($_POST['selected_text'])) : '';

        // Validate selected text
        if (empty(trim($selected_text))) {
            wp_send_json_error(array('message' => __('No text was selected.', 'ai-content-master')), 400);
            return;
        }

        // Rephrase text
        $rephrased_text = $this->rephrase_text($selected_text);

        if (is_wp_error($rephrased_text)) {
            wp_send_json_error(array('message' => $rephrased_text->get_error_message()), 500);
        } else {
            wp_send_json_success(array('rephrased_text' => $rephrased_text));
        }
    }

    /**
     * Rephrase given text
     *
     * @param string $text Text to rephrase.
     * @return string|WP_Error Rephrased text or error.
     */
    public function rephrase_text($text) {
        // Prepare rephrasing prompt
        $prompt = $this->prepare_rephrase_prompt($text);

        // Send to API
        return $this->get_api()->send_prompt( $prompt );
    }

    /**
     * Prepare rephrasing prompt
     *
     * @param string $text Text to rephrase.
     * @return string Rephrasing prompt.
     */
    private function prepare_rephrase_prompt($text) {
        return sprintf(
            "Please rephrase the following text to improve its clarity and flow, keeping the core meaning intact:\n\n\"%s\"",
            $text
        );
    }
}